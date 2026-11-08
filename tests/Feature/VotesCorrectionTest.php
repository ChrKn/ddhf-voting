<?php

use App\Actions\CastVote;
use App\Enums\AssemblyEventType;
use App\Exceptions\VotingException;
use App\Filament\Resources\Assemblies\Pages\ConductAssembly;
use App\Models\Assembly;
use App\Models\Poll;
use App\Models\PollParticipation;
use App\Models\User;
use App\Models\VotingMember;
use App\Services\AssemblyLifecycle;
use App\Services\PollLifecycle;
use Livewire\Livewire;

/*
 * Korrektur einer falschen Mitgliederzahl während der Versammlung: Stammdaten und
 * Snapshot ändern sich, beendete Abstimmungen nicht, und das Protokoll zeigt es.
 */

beforeEach(function () {
    $this->assemblies = app(AssemblyLifecycle::class);
    $this->polls = app(PollLifecycle::class);
    $this->manager = User::factory()->manager()->create(['name' => 'Lea Leitung']);
    $this->assembly = Assembly::factory()->create(['title' => 'MV 2027']);
    $this->nord = VotingMember::factory()->members(48)->create(['name' => 'Fechtschule Nord']);

    $this->actingAs($this->manager);
    $this->assemblies->open($this->assembly);
    $this->assemblies->checkIn($this->assembly, $this->nord);
});

it('korrigiert Snapshot und Stammdaten und protokolliert alt, neu und Begründung', function () {
    $this->assemblies->correctVotes($this->assembly, $this->nord, 75, 'Meldung übersehen');

    $attendance = $this->assemblies->attendanceOf($this->assembly, $this->nord);
    expect($attendance)->votes->toBe(2)->member_count->toBe(75)
        ->and($this->nord->fresh()->member_count)->toBe(75)
        ->and($this->assembly->totalVotes())->toBe(2);

    $event = $this->assembly->events()->get()->last();
    expect($event->type)->toBe(AssemblyEventType::VotesCorrected)
        ->and($event)->votes->toBe(2)->member_count->toBe(75)->user_name->toBe('Lea Leitung')
        ->and($event->details)->toBe(['old_votes' => 1, 'old_member_count' => 48, 'reason' => 'Meldung übersehen']);
});

it('lässt beendete Abstimmungen, wie sie gezählt wurden', function () {
    $representative = User::factory()->forVotingMember($this->nord)->create();
    $poll = Poll::factory()->create(['assembly_id' => $this->assembly->id]);
    $this->polls->open($poll);
    $poll->refresh()->load('positions.options');
    $yes = $poll->positions->first()->options->firstWhere('label', 'Dafür');
    app(CastVote::class)->handle($representative, $this->nord, $poll, [$poll->positions->first()->id => $yes->id]);
    $this->polls->close($poll);

    $this->assemblies->correctVotes($this->assembly, $this->nord, 75, 'Meldung übersehen');

    expect(PollParticipation::firstOrFail()->votes)->toBe(1)
        ->and($poll->fresh()->votes_present)->toBe(1)
        ->and($poll->tallies()->where('poll_option_id', $yes->id)->value('votes'))->toBe(1);
});

it('sperrt die Korrektur während einer offenen Abstimmung', function () {
    $poll = Poll::factory()->create(['assembly_id' => $this->assembly->id]);
    $this->polls->open($poll);

    expect(fn () => $this->assemblies->correctVotes($this->assembly, $this->nord, 75, 'Meldung übersehen'))
        ->toThrow(VotingException::class, 'offenen Abstimmung');

    expect($this->nord->fresh()->member_count)->toBe(48);
});

it('verlangt eine Begründung und einen gültigen Wert', function () {
    expect(fn () => $this->assemblies->correctVotes($this->assembly, $this->nord, 75, '   '))
        ->toThrow(VotingException::class, 'Begründung');
    expect(fn () => $this->assemblies->correctVotes($this->assembly, $this->nord, -1, 'Tippfehler'))
        ->toThrow(VotingException::class, 'negativ');

    $post = VotingMember::firstWhere('name', 'Präsident:in');
    expect(fn () => $this->assemblies->correctVotes($this->assembly, $post, 0, 'Tippfehler'))
        ->toThrow(VotingException::class, 'mindestens eine Stimme');
});

it('ist nach dem Ende der Versammlung nicht mehr möglich', function () {
    $this->assemblies->close($this->assembly);

    expect(fn () => $this->assemblies->correctVotes($this->assembly, $this->nord, 75, 'zu spät'))
        ->toThrow(VotingException::class, 'beendet');
});

it('behält die Korrektur nach Checkout und erneutem Check-in', function () {
    $this->assemblies->correctVotes($this->assembly, $this->nord, 75, 'Meldung übersehen');
    $this->assemblies->checkOut($this->assembly, $this->nord);
    $this->assemblies->checkIn($this->assembly, $this->nord);

    expect($this->assemblies->attendanceOf($this->assembly, $this->nord))->votes->toBe(2)->member_count->toBe(75);
});

it('korrigiert auch noch nicht eingecheckte Mitglieder, über die Stammdaten', function () {
    $sued = VotingMember::factory()->members(10)->create(['name' => 'Süd']);

    $this->assemblies->correctVotes($this->assembly, $sued, 60, 'Meldung übersehen');
    $this->assemblies->checkIn($this->assembly, $sued);

    expect($this->assemblies->attendanceOf($this->assembly, $sued))->votes->toBe(2)->member_count->toBe(60);
});

it('korrigiert die festen Stimmen eines Präsidiumspostens', function () {
    $post = VotingMember::firstWhere('name', 'Präsident:in');
    $this->assemblies->checkIn($this->assembly, $post);

    $this->assemblies->correctVotes($this->assembly, $post, 2, 'Satzungsänderung');

    expect($this->assemblies->attendanceOf($this->assembly, $post))->votes->toBe(2)->member_count->toBeNull()
        ->and($post->fresh())->votes->toBe(2)->member_count->toBeNull();
});

it('lässt die Versammlungsleitung über die Seite korrigieren', function () {
    Livewire::test(ConductAssembly::class, ['record' => $this->assembly->getRouteKey()])
        ->assertSeeInOrder(['Fechtschule Nord', '1 Stimme', '(48 Mitglieder)'])
        ->callAction('correctVotes', data: ['value' => 75, 'reason' => 'Meldung übersehen'], arguments: ['votingMember' => $this->nord->id])
        ->assertHasNoActionErrors()
        ->assertNotified('Fechtschule Nord korrigiert')
        ->assertSeeInOrder(['Fechtschule Nord', '2 Stimmen', '(75 Mitglieder)']);

    expect($this->nord->fresh()->member_count)->toBe(75);
});

it('verlangt auf der Seite eine Begründung', function () {
    Livewire::test(ConductAssembly::class, ['record' => $this->assembly->getRouteKey()])
        ->callAction('correctVotes', data: ['value' => 75, 'reason' => ''], arguments: ['votingMember' => $this->nord->id])
        ->assertHasActionErrors(['reason' => 'required']);

    expect($this->nord->fresh()->member_count)->toBe(48);
});

it('zeigt die Korrektur im Protokoll', function () {
    $this->assemblies->correctVotes($this->assembly, $this->nord, 75, 'Meldung übersehen');

    $csv = $this->get(route('assemblies.export', $this->assembly))->assertOk()->streamedContent();

    expect($csv)->toContain(';"Stimmen korrigiert";"Fechtschule Nord";2;"Lea Leitung";"Mitglieder 48 → 75, Stimmen 1 → 2. Begründung: Meldung übersehen"');
});
