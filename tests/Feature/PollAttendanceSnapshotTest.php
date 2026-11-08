<?php

use App\Actions\CastVote;
use App\Models\Assembly;
use App\Models\Poll;
use App\Models\User;
use App\Models\VotingMember;
use App\Services\AssemblyLifecycle;
use App\Services\PollLifecycle;
use App\Services\PollResult;

beforeEach(function () {
    $this->assemblies = app(AssemblyLifecycle::class);
    $this->polls = app(PollLifecycle::class);
    $this->assembly = Assembly::factory()->create();
    $this->assemblies->open($this->assembly);

    $this->nord = VotingMember::factory()->members(120)->create(); // 3 Stimmen
    $this->ost = VotingMember::factory()->members(10)->create();   // 1 Stimme
    $this->assemblies->checkIn($this->assembly, $this->nord);
    $this->assemblies->checkIn($this->assembly, $this->ost);

    $this->poll = Poll::factory()->create(['assembly_id' => $this->assembly->id]);
    $this->polls->open($this->poll);
});

function voteFor(VotingMember $votingMember, Poll $poll): void
{
    $position = $poll->positions()->first();
    app(CastVote::class)->handle(User::factory()->forVotingMember($votingMember)->create(), $votingMember, $poll, [
        $position->id => $position->options()->where('label', 'Dafür')->value('id'),
    ]);
}

it('hält die Anwesenheit beim Beenden fest, spätere Checkouts ändern sie nicht', function () {
    voteFor($this->nord, $this->poll);
    $this->polls->close($this->poll);

    $this->assemblies->checkOut($this->assembly, $this->nord);
    $this->assemblies->checkOut($this->assembly, $this->ost);

    expect(PollResult::for($this->poll->refresh())->progress())->toBe([
        'voting_members_present' => 2,
        'voting_members_voted' => 1,
        'votes_present' => 4,
        'votes_cast' => 3,
    ]);
});

it('zählt Mitglieder, die abgestimmt und dann ausgecheckt haben, weiter als anwesend', function () {
    voteFor($this->nord, $this->poll);
    $this->assemblies->checkOut($this->assembly, $this->nord);

    expect(PollResult::for($this->poll)->progress())
        ->toMatchArray(['voting_members_present' => 2, 'votes_present' => 4]);

    $this->polls->close($this->poll);

    expect($this->poll->refresh())
        ->voting_members_present->toBe(2)
        ->votes_present->toBe(4);
});

it('zählt Mitglieder, die ohne Abstimmung gegangen sind, nicht mehr mit', function () {
    $this->assemblies->checkOut($this->assembly, $this->ost);
    $this->polls->close($this->poll);

    expect($this->poll->refresh())
        ->voting_members_present->toBe(1)
        ->votes_present->toBe(3);
});

it('verwirft die festgehaltene Anwesenheit beim Zurücksetzen', function () {
    $this->polls->close($this->poll);
    $this->polls->reset($this->poll);

    expect($this->poll->refresh())
        ->voting_members_present->toBeNull()
        ->votes_present->toBeNull();
});
