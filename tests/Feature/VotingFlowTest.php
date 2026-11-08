<?php

use App\Actions\CastVote;
use App\Enums\AssemblyStatus;
use App\Enums\PollStatus;
use App\Exceptions\VotingException;
use App\Models\Assembly;
use App\Models\Poll;
use App\Models\PollParticipation;
use App\Models\User;
use App\Models\VotingMember;
use App\Services\AssemblyLifecycle;
use App\Services\PollLifecycle;
use App\Services\PollResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Wahl-Hilfe: Option einer Position per Label finden */
function optionId(Poll $poll, string $label, int $position = 0): int
{
    return $poll->positions()->get()[$position]->options()->where('label', $label)->value('id');
}

function choose(Poll $poll, string $label): array
{
    $position = $poll->positions()->first();

    return [$position->id => optionId($poll, $label)];
}

beforeEach(function () {
    $this->lifecycle = app(PollLifecycle::class);
    $this->assemblies = app(AssemblyLifecycle::class);
    $this->cast = app(CastVote::class);

    // 120 Mitglieder → 3 Stimmen, 30 → 1 Stimme
    $this->nord = VotingMember::factory()->members(120)->create(['name' => 'Nord']);
    $this->sued = VotingMember::factory()->members(30)->create(['name' => 'Süd']);
    $this->ost = VotingMember::factory()->members(60)->create(['name' => 'Ost']);

    $this->alice = User::factory()->forVotingMember($this->nord)->create();
    $this->bob = User::factory()->forVotingMember($this->nord)->create();
    $this->carla = User::factory()->forVotingMember($this->sued)->create();
    $this->dora = User::factory()->forVotingMember($this->ost)->create();

    $this->assembly = Assembly::factory()->create();
    $this->assemblies->open($this->assembly);
    $this->assemblies->checkIn($this->assembly, $this->nord);
    $this->assemblies->checkIn($this->assembly, $this->sued);

    $this->poll = Poll::factory()->create(['assembly_id' => $this->assembly->id]);
    $this->lifecycle->open($this->poll);
});

describe('Stimmabgabe', function () {
    it('zählt die Stimmen eines Vereins gewichtet', function () {
        $this->cast->handle($this->alice, $this->nord, $this->poll, choose($this->poll, 'Dafür'));
        $this->cast->handle($this->carla, $this->sued, $this->poll, choose($this->poll, 'Dagegen'));

        $result = PollResult::for($this->poll)->positions()[0];
        $votes = collect($result['options'])->pluck('votes', 'label');

        expect($votes['Dafür'])->toBe(3)
            ->and($votes['Dagegen'])->toBe(1)
            ->and($votes['Enthaltung'])->toBe(0)
            ->and($result['total'])->toBe(4)
            ->and($result['options'][0]['share'])->toBe(75.0);
    });

    it('lässt jeden Verein nur einmal abstimmen, auch mit mehreren Personen', function () {
        $this->cast->handle($this->alice, $this->nord, $this->poll, choose($this->poll, 'Dafür'));

        expect(fn () => $this->cast->handle($this->bob, $this->nord, $this->poll, choose($this->poll, 'Dagegen')))
            ->toThrow(VotingException::class, 'Nord hat bereits abgestimmt.');

        expect(PollParticipation::count())->toBe(1)
            ->and(PollResult::for($this->poll)->progress()['votes_cast'])->toBe(3);
    });

    it('fängt eine gleichzeitige Doppelabgabe über den Unique-Index ab', function () {
        // Wettlauf simulieren: Die Vorabprüfung ist bestanden, dann schreibt eine
        // zweite Person desselben Vereins unmittelbar vor diesem Insert.
        PollParticipation::creating(function (PollParticipation $p) {
            DB::table('poll_participations')->insertOrIgnore([
                'poll_id' => $p->poll_id, 'voting_member_id' => $p->voting_member_id, 'votes' => $p->votes,
            ]);
        });

        expect(fn () => $this->cast->handle($this->alice, $this->nord, $this->poll, choose($this->poll, 'Dafür')))
            ->toThrow(VotingException::class, 'Nord hat bereits abgestimmt.');

        // Die gescheiterte Abgabe hat nichts gezählt
        expect(collect(PollResult::for($this->poll)->positions()[0]['options'])->sum('votes'))->toBe(0);
    });

    it('speichert bei geheimen Abstimmungen nichts, was die Wahl einem Verein zuordnet', function () {
        $this->cast->handle($this->alice, $this->nord, $this->poll, choose($this->poll, 'Dafür'));

        expect(PollParticipation::first()->public_choices)->toBeNull()
            ->and(Schema::getColumnListing('poll_tallies'))->not->toContain('voting_member_id')
            ->and(Schema::getColumnListing('poll_tallies'))->not->toContain('created_at')
            ->and(PollResult::for($this->poll)->details())->toBe([]);
    });

    it('zeigt bei öffentlichen Abstimmungen, wer wie gestimmt hat', function () {
        $poll = Poll::factory()->public()->create(['assembly_id' => $this->assembly->id]);
        $this->lifecycle->open($poll);

        $this->cast->handle($this->carla, $this->sued, $poll, choose($poll, 'Enthaltung'));

        expect(PollResult::for($poll)->details())->toBe([
            ['votingMember' => 'Süd', 'votes' => 1, 'choices' => [$poll->title => 'Enthaltung']],
        ]);
    });

    it('verwendet die beim Check-in eingefrorene Stimmenzahl', function () {
        $this->actingAs(User::factory()->systemAdmin()->create());
        $this->nord->update(['member_count' => 1000]);

        $this->cast->handle($this->alice, $this->nord->fresh(), $this->poll, choose($this->poll, 'Dafür'));

        expect(PollResult::for($this->poll)->progress()['votes_cast'])->toBe(3);
    });

    it('lehnt nicht eingecheckte Vereine ab', function () {
        expect(fn () => $this->cast->handle($this->dora, $this->ost, $this->poll, choose($this->poll, 'Dafür')))
            ->toThrow(VotingException::class, 'nicht zur Versammlung eingecheckt');
    });

    it('lehnt Personen ab, die den Verein nicht vertreten', function () {
        expect(fn () => $this->cast->handle($this->carla, $this->nord, $this->poll, choose($this->poll, 'Dafür')))
            ->toThrow(VotingException::class, 'vertrittst');
    });

    it('lehnt Stimmen nach dem Schließen ab', function () {
        $this->lifecycle->close($this->poll);

        expect(fn () => $this->cast->handle($this->alice, $this->nord, $this->poll, choose($this->poll, 'Dafür')))
            ->toThrow(VotingException::class, 'nicht (mehr) offen');
    });

    it('lehnt unvollständige oder fremde Auswahl ab', function () {
        $other = Poll::factory()->create(['assembly_id' => $this->assembly->id]);

        expect(fn () => $this->cast->handle($this->alice, $this->nord, $this->poll, []))
            ->toThrow(VotingException::class, 'Auswahl treffen');

        $position = $this->poll->positions()->first();
        $foreignOption = $other->positions()->first()->options()->first();
        expect(fn () => $this->cast->handle($this->alice, $this->nord, $this->poll, [$position->id => $foreignOption->id]))
            ->toThrow(VotingException::class, 'Ungültige Auswahl');

        expect(PollParticipation::count())->toBe(0);
    });

    it('wertet Wahlen mit mehreren Ämtern je Amt aus', function () {
        $poll = Poll::factory()->election([
            'Präsident:in' => ['Anna', 'Bert'],
            'Kasse' => ['Cem'],
        ])->create(['assembly_id' => $this->assembly->id]);
        $this->lifecycle->open($poll);
        [$president, $treasury] = $poll->positions()->get();

        $this->cast->handle($this->alice, $this->nord, $poll, [
            $president->id => optionId($poll, 'Bert'),
            $treasury->id => optionId($poll, 'Cem', 1),
        ]);
        $this->cast->handle($this->carla, $this->sued, $poll, [
            $president->id => optionId($poll, 'Anna'),
            $treasury->id => optionId($poll, 'Enthaltung', 1),
        ]);

        [$p, $t] = PollResult::for($poll)->positions();
        expect(collect($p['options'])->pluck('votes', 'label')->all())->toBe(['Bert' => 3, 'Anna' => 1, 'Enthaltung' => 0])
            ->and(collect($t['options'])->pluck('votes', 'label')->all())->toBe(['Cem' => 3, 'Enthaltung' => 1]);
    });
});

describe('Ablauf', function () {
    it('durchläuft draft → open → closed → published', function () {
        expect($this->poll->status)->toBe(PollStatus::Open)
            ->and($this->poll->opened_at)->not->toBeNull();

        $this->lifecycle->close($this->poll);
        expect($this->poll->status)->toBe(PollStatus::Closed);

        $this->lifecycle->publish($this->poll);
        expect($this->poll->status)->toBe(PollStatus::Published);

        expect(fn () => $this->lifecycle->reset($this->poll))->toThrow(VotingException::class);
    });

    it('öffnet Abstimmungen nur in laufenden Versammlungen', function () {
        $poll = Poll::factory()->create();

        expect(fn () => $this->lifecycle->open($poll))->toThrow(VotingException::class, 'Versammlung muss zuerst');
    });

    it('öffnet keine Wahl ohne Kandidat:innen', function () {
        $poll = Poll::factory()->election(['Amt' => []])->create(['assembly_id' => $this->assembly->id]);

        expect(fn () => $this->lifecycle->open($poll))->toThrow(VotingException::class, 'keine Kandidat:innen');
        expect($poll->fresh()->status)->toBe(PollStatus::Draft);
    });

    it('verwirft beim Zurücksetzen alle Stimmen', function () {
        $this->cast->handle($this->alice, $this->nord, $this->poll, choose($this->poll, 'Dafür'));

        $this->lifecycle->reset($this->poll);

        expect($this->poll->status)->toBe(PollStatus::Draft)
            ->and($this->poll->participations()->count())->toBe(0)
            ->and($this->poll->tallies()->count())->toBe(0);

        // danach erneut abstimmbar
        $this->lifecycle->open($this->poll);
        $this->cast->handle($this->bob, $this->nord, $this->poll, choose($this->poll, 'Dagegen'));
        expect(PollParticipation::count())->toBe(1);
    });

    it('zeigt Fortschritt und fehlende Vereine', function () {
        $this->cast->handle($this->alice, $this->nord, $this->poll, choose($this->poll, 'Dafür'));
        $result = PollResult::for($this->poll);

        expect($result->progress())->toBe([
            'voting_members_present' => 2,
            'voting_members_voted' => 1,
            'votes_present' => 4,
            'votes_cast' => 3,
        ])->and($result->pendingVotingMembers()->pluck('name')->all())->toBe(['Süd']);
    });

    it('schließt Versammlungen nur ohne offene Abstimmung', function () {
        expect(fn () => $this->assemblies->close($this->assembly))->toThrow(VotingException::class, 'noch eine Abstimmung offen');

        $this->lifecycle->close($this->poll);
        $this->assemblies->close($this->assembly);

        expect($this->assembly->fresh()->status)->toBe(AssemblyStatus::Closed);
    });

    it('checkt inaktive Vereine nicht ein und ändert den Snapshot beim erneuten Check-in nicht', function () {
        $inactive = VotingMember::factory()->create(['active' => false]);
        expect(fn () => $this->assemblies->checkIn($this->assembly, $inactive))->toThrow(VotingException::class, 'inaktiv');

        $this->actingAs(User::factory()->systemAdmin()->create());
        $this->nord->update(['member_count' => 1000]);
        $this->assemblies->checkIn($this->assembly, $this->nord->fresh());

        expect($this->assemblies->votesOf($this->assembly, $this->nord))->toBe(3);
    });
});
