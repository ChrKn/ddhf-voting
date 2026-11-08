<?php

use App\Livewire\Ballot;
use App\Models\Assembly;
use App\Models\Poll;
use App\Models\PollParticipation;
use App\Models\User;
use App\Models\VotingMember;
use App\Services\AssemblyLifecycle;
use App\Services\PollLifecycle;
use Livewire\Livewire;

beforeEach(function () {
    $this->votingMember = VotingMember::factory()->members(120)->create(['name' => 'Fechtschule Nord']);
    $this->user = User::factory()->forVotingMember($this->votingMember)->create();

    $this->assembly = Assembly::factory()->create(['title' => 'MV 2027']);
    app(AssemblyLifecycle::class)->open($this->assembly);

    $this->actingAs($this->user);
});

function openPoll(Assembly $assembly, array $attributes = [], ?callable $factory = null): Poll
{
    $f = Poll::factory();
    $f = $factory ? $factory($f) : $f;
    $poll = $f->create(['assembly_id' => $assembly->id, ...$attributes]);
    app(PollLifecycle::class)->open($poll);

    return $poll->fresh();
}

it('zeigt die Wahlmaske auf der Startseite', function () {
    $this->get('/')->assertOk()->assertSeeLivewire(Ballot::class);
});

it('weist auf fehlenden Check-in hin', function () {
    Livewire::test(Ballot::class)
        ->assertSee('Fechtschule Nord')
        ->assertSee('noch nicht eingecheckt');
});

it('zeigt neben den Stimmen die Mitgliederzahl vom Check-in', function () {
    app(AssemblyLifecycle::class)->checkIn($this->assembly, $this->votingMember);

    // Spätere Änderungen gelten erst für die nächste Versammlung
    $this->actingAs(User::factory()->systemAdmin()->create());
    $this->votingMember->update(['member_count' => 400]);
    $this->actingAs($this->user);

    Livewire::test(Ballot::class)
        ->assertSeeInOrder(['MV 2027', '3 Stimmen', '(120 Mitglieder)'])
        ->assertDontSee('400');
});

it('zeigt bei Präsidiumsposten keine Mitgliederzahl', function () {
    $post = VotingMember::firstWhere('name', 'Präsident:in');
    $this->user->votingMembers()->sync([$post->id]);
    app(AssemblyLifecycle::class)->checkIn($this->assembly, $post);

    Livewire::test(Ballot::class)
        ->assertSee('1 Stimme')
        ->assertDontSee('Mitglied');
});

it('zeigt ohne offene Abstimmung einen Hinweis', function () {
    app(AssemblyLifecycle::class)->checkIn($this->assembly, $this->votingMember);

    Livewire::test(Ballot::class)
        ->assertSee('3 Stimmen')
        ->assertSee('Aktuell keine Abstimmung aktiv');
});

it('führt durch Auswahl, Bestätigung und Abgabe', function () {
    app(AssemblyLifecycle::class)->checkIn($this->assembly, $this->votingMember);
    $poll = openPoll($this->assembly, ['title' => 'Antrag auf Beitragserhöhung']);
    $position = $poll->positions->first();
    $yes = $position->options->firstWhere('label', 'Dafür');
    $abstention = $position->options->firstWhere('is_abstention', true);

    $component = Livewire::test(Ballot::class)
        ->assertSee('Antrag auf Beitragserhöhung')
        ->assertSee('Geheimer Wahlmodus')
        // Enthaltung ist vorausgewählt
        ->assertSet("choices.{$position->id}", $abstention->id)
        ->set("choices.{$position->id}", $yes->id)
        ->call('review')
        ->assertSet('confirming', true)
        ->assertSee('Bitte prüfe deine Auswahl');

    expect(PollParticipation::count())->toBe(0);

    $component->call('submit')
        ->assertSee('Fechtschule Nord hat abgestimmt.')
        ->assertDontSee('Verbindlich abstimmen');

    expect(PollParticipation::first()->votes)->toBe(3)
        ->and($poll->tallies()->where('poll_option_id', $yes->id)->value('votes'))->toBe(3);
});

it('lässt Verwalter:innen, die einen Verein vertreten, mit demselben Konto abstimmen', function () {
    $manager = User::factory()->manager()->forVotingMember($this->votingMember)->create();
    app(AssemblyLifecycle::class)->checkIn($this->assembly, $this->votingMember);
    $poll = openPoll($this->assembly);
    $position = $poll->positions->first();
    $yes = $position->options->firstWhere('label', 'Dafür');

    $this->actingAs($manager);

    // Dieselbe Sitzung reicht für beides: Verwaltung und Wahlmaske
    $this->get('/admin')->assertOk();
    $this->get('/')->assertOk()->assertSeeLivewire(Ballot::class)->assertSee('Verwaltung');

    Livewire::test(Ballot::class)
        ->set("choices.{$position->id}", $yes->id)
        ->call('review')
        ->call('submit')
        ->assertSee('Fechtschule Nord hat abgestimmt.');

    expect(PollParticipation::firstOrFail())
        ->user_id->toBe($manager->id)
        ->voting_member_id->toBe($this->votingMember->id)
        ->votes->toBe(3);
});

it('zeigt bei öffentlichen Abstimmungen die eigene Wahl', function () {
    app(AssemblyLifecycle::class)->checkIn($this->assembly, $this->votingMember);
    $poll = openPoll($this->assembly, [], fn ($f) => $f->public());
    $position = $poll->positions->first();
    $no = $position->options->firstWhere('label', 'Dagegen');

    Livewire::test(Ballot::class)
        ->assertSee('Öffentlicher Wahlmodus')
        ->set("choices.{$position->id}", $no->id)
        ->call('review')
        ->call('submit')
        ->assertSee('Dagegen');
});

it('zeigt Fehlermeldungen statt doppelt abzustimmen', function () {
    app(AssemblyLifecycle::class)->checkIn($this->assembly, $this->votingMember);
    $poll = openPoll($this->assembly);

    $component = Livewire::test(Ballot::class)->call('review');

    // Abstimmung wird zwischen Bestätigung und Abgabe geschlossen
    app(PollLifecycle::class)->close($poll);

    $component->call('submit')
        ->assertSee('Aktuell keine Abstimmung aktiv');
    expect(PollParticipation::count())->toBe(0);
});

it('lässt bei mehreren Vereinen den Verein wählen', function () {
    $other = VotingMember::factory()->members(10)->create(['name' => 'Fechtgilde Süd']);
    $this->user->votingMembers()->attach($other);
    app(AssemblyLifecycle::class)->checkIn($this->assembly, $other);

    Livewire::test(Ballot::class)
        ->assertSet('votingMemberId', null)
        ->assertSee('Ich stimme ab für')
        ->call('selectVotingMember', $other->id)
        ->assertSet('votingMemberId', $other->id)
        ->assertSee('1 Stimme');
});

it('ignoriert fremde Vereine bei der Auswahl', function () {
    $foreign = VotingMember::factory()->create();

    Livewire::test(Ballot::class)
        ->call('selectVotingMember', $foreign->id)
        ->assertSet('votingMemberId', $this->votingMember->id);
});

it('zeigt das zuletzt veröffentlichte Ergebnis', function () {
    app(AssemblyLifecycle::class)->checkIn($this->assembly, $this->votingMember);
    $poll = openPoll($this->assembly, ['title' => 'Haushalt 2027']);
    app(PollLifecycle::class)->close($poll);

    Livewire::test(Ballot::class)->assertDontSee('Letztes Ergebnis');

    app(PollLifecycle::class)->publish($poll);
    Livewire::test(Ballot::class)->assertSee('Letztes Ergebnis: Haushalt 2027');
});
