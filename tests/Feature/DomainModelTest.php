<?php

use App\Enums\PollType;
use App\Enums\Role;
use App\Models\Assembly;
use App\Models\Club;
use App\Models\Poll;
use App\Models\User;

it('leitet die Stimmen eines Vereins aus der Mitgliederzahl ab', function () {
    $club = Club::factory()->members(120)->create();

    expect($club->votes)->toBe(3);

    config(['voting.votes_divisor' => 100]);
    expect($club->fresh()->votes)->toBe(2);
});

it('legt neue Nutzer:innen als Vereinsvertretung an', function () {
    $user = User::factory()->create();

    expect($user->role)->toBe(Role::Member)
        ->and($user->canManage())->toBeFalse()
        ->and(User::factory()->manager()->create()->canManage())->toBeTrue()
        ->and(User::factory()->systemAdmin()->create()->isSystemAdmin())->toBeTrue();
});

it('ordnet Personen mehreren Vereinen zu', function () {
    [$a, $b] = Club::factory()->count(2)->create();
    $user = User::factory()->forClub($a)->create();
    $user->clubs()->attach($b);

    expect($user->represents($a))->toBeTrue()
        ->and($user->clubs)->toHaveCount(2);
});

it('friert die Stimmen beim Check-in ein', function () {
    $club = Club::factory()->members(60)->create();
    $assembly = Assembly::factory()->withCheckedIn([$club])->create();

    $club->update(['member_count' => 300]);

    expect($assembly->clubs()->first()->attendance->votes)->toBe(2)
        ->and($assembly->totalVotes())->toBe(2)
        ->and($assembly->public_token)->toHaveLength(40);
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
