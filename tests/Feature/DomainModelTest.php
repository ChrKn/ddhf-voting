<?php

use App\Enums\PollType;
use App\Enums\Role;
use App\Enums\VotingMemberType;
use App\Models\Assembly;
use App\Models\Poll;
use App\Models\User;
use App\Models\VotingMember;

it('leitet die Stimmen eines Vereins aus der Mitgliederzahl ab', function () {
    $votingMember = VotingMember::factory()->members(120)->create();

    expect($votingMember->votes)->toBe(3);

    config(['voting.votes_divisor' => 100]);
    expect($votingMember->fresh()->votes)->toBe(2);
});

it('legt neue Nutzer:innen als Vereinsvertretung an', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(Role::Member)
        ->and($user->canManage())->toBeFalse()
        ->and(User::factory()->manager()->create()->canManage())->toBeTrue()
        ->and(User::factory()->systemAdmin()->create()->isSystemAdmin())->toBeTrue();
});

it('ordnet Personen mehreren Vereinen zu', function () {
    [$a, $b] = VotingMember::factory()->count(2)->create();
    $user = User::factory()->forVotingMember($a)->create();
    $user->votingMembers()->attach($b);

    expect($user->represents($a))->toBeTrue()
        ->and($user->votingMembers)->toHaveCount(2);
});

it('friert die Stimmen beim Check-in ein', function () {
    $votingMember = VotingMember::factory()->members(60)->create();
    $assembly = Assembly::factory()->withCheckedIn([$votingMember])->create();

    $votingMember->update(['member_count' => 300]);

    expect($assembly->votingMembers()->first()->attendance->votes)->toBe(2)
        ->and($assembly->totalVotes())->toBe(2)
        ->and($assembly->public_token)->toHaveLength(40);
});

it('gibt Präsidiumsposten ihre feste Stimmenzahl, unabhängig von der Formel', function () {
    $post = VotingMember::factory()->boardMember(2, 'Präsident:in')->create();

    expect($post->type)->toBe(VotingMemberType::BoardMember)
        ->and($post->isBoardMember())->toBeTrue()
        ->and($post->votes)->toBe(2)
        ->and($post->member_count)->toBeNull();

    config(['voting.votes_base' => 10]);
    expect($post->fresh()->votes)->toBe(2);
});

it('leert beim Typwechsel die Zahl, die nicht mehr gilt', function () {
    $votingMember = VotingMember::factory()->members(120)->create();

    $votingMember->update(['type' => VotingMemberType::BoardMember, 'votes' => 1]);
    expect($votingMember->fresh())->member_count->toBeNull()->votes->toBe(1);

    $votingMember->update(['type' => VotingMemberType::Member, 'member_count' => 60]);
    expect($votingMember->fresh()->getAttributes()['votes'])->toBeNull()
        ->and($votingMember->fresh()->votes)->toBe(2);
});

it('verlangt für Präsidiumsposten mindestens eine Stimme', function () {
    expect(fn () => VotingMember::create(['name' => 'Posten', 'type' => VotingMemberType::BoardMember]))
        ->toThrow(InvalidArgumentException::class);
});

it('trennt Mitglieder und Präsidiumsposten per Scope', function () {
    $member = VotingMember::factory()->create();
    $post = VotingMember::factory()->boardMember()->create();

    expect(VotingMember::members()->pluck('id')->all())->toBe([$member->id])
        ->and(VotingMember::boardMembers()->pluck('id')->all())->toBe([$post->id]);
});

it('friert auch die Stimmen eines Präsidiumspostens beim Check-in ein', function () {
    $post = VotingMember::factory()->boardMember(2)->create();
    $assembly = Assembly::factory()->withCheckedIn([$post])->create();

    $post->update(['votes' => 5]);

    expect($assembly->votingMembers()->first()->attendance->votes)->toBe(2)
        ->and($assembly->totalVotes())->toBe(2);
});

it('legt einen Antrag mit Ja, Nein und Enthaltung an', function () {
    $poll = Poll::factory()->create()->load('positions.options');

    expect($poll->type)->toBe(PollType::Motion)
        ->and($poll->positions)->toHaveCount(1)
        ->and($poll->positions->first()->options->pluck('label')->all())
        ->toBe(['Dafür', 'Dagegen', 'Enthaltung']);
});

it('legt eine Wahl mit mehreren Ämtern an, Enthaltung jeweils zuletzt', function () {
    $poll = Poll::factory()->election([
        'Präsident:in' => ['Anna', 'Bert'],
        'Jugendbeauftragte:r' => ['Cem'],
    ])->create()->load('positions.options');

    expect($poll->positions->pluck('title')->all())->toBe(['Präsident:in', 'Jugendbeauftragte:r'])
        ->and($poll->positions->first()->options->pluck('label')->all())->toBe(['Anna', 'Bert', 'Enthaltung']);
});
