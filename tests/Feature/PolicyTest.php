<?php

use App\Enums\AssemblyStatus;
use App\Enums\PollStatus;
use App\Models\Assembly;
use App\Models\Club;
use App\Models\Poll;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->systemAdmin()->create();
    $this->manager = User::factory()->manager()->create();
    $this->club = Club::factory()->create();
    $this->member = User::factory()->forClub($this->club)->create();
});

it('erlaubt nur dem Systemadmin, die Mitgliederzahl zu ändern', function () {
    expect($this->admin->can('updateMemberCount', $this->club))->toBeTrue()
        ->and($this->manager->can('updateMemberCount', $this->club))->toBeFalse()
        ->and($this->member->can('updateMemberCount', $this->club))->toBeFalse()
        ->and($this->manager->can('update', $this->club))->toBeFalse()
        ->and($this->manager->can('viewAny', Club::class))->toBeTrue()
        ->and($this->member->can('viewAny', Club::class))->toBeFalse();
});

it('erlaubt nur dem Systemadmin, Personen zu verwalten', function () {
    expect($this->admin->can('create', User::class))->toBeTrue()
        ->and($this->manager->can('viewAny', User::class))->toBeTrue()
        ->and($this->manager->can('create', User::class))->toBeFalse()
        ->and($this->manager->can('update', $this->member))->toBeFalse()
        ->and($this->member->can('viewAny', User::class))->toBeFalse()
        ->and($this->admin->can('delete', $this->admin))->toBeFalse();
});

it('erlaubt vorläufige Passwörter nur für Vertreter:innen', function () {
    $otherManager = User::factory()->manager()->create();

    expect($this->admin->can('setPassword', $this->member))->toBeTrue()
        ->and($this->manager->can('setPassword', $this->member))->toBeTrue()
        ->and($this->manager->can('setPassword', $otherManager))->toBeFalse()
        ->and($this->manager->can('setPassword', $this->admin))->toBeFalse()
        ->and($this->admin->can('setPassword', $this->manager))->toBeFalse()
        ->and($this->member->can('setPassword', $this->member))->toBeFalse();
});

it('lässt Verwalter:innen nur Vertreter:innen einladen', function () {
    expect($this->admin->can('invite', $this->manager))->toBeTrue()
        ->and($this->manager->can('invite', $this->member))->toBeTrue()
        ->and($this->manager->can('invite', $this->admin))->toBeFalse();
});

it('lässt Verwalter:innen Versammlungen leiten', function () {
    $assembly = Assembly::factory()->create();

    expect($this->manager->can('conduct', $assembly))->toBeTrue()
        ->and($this->member->can('conduct', $assembly))->toBeFalse();

    $assembly->update(['status' => AssemblyStatus::Closed]);
    expect($this->manager->can('conduct', $assembly))->toBeFalse()
        ->and($this->manager->can('delete', $assembly))->toBeFalse();
});

it('sperrt Abstimmungen nach dem Öffnen gegen Änderungen', function () {
    $poll = Poll::factory()->create();
    expect($this->manager->can('update', $poll))->toBeTrue();

    $poll->forceFill(['status' => PollStatus::Open])->save();
    expect($this->manager->can('update', $poll))->toBeFalse();
});

it('zeigt Vereinen Ergebnisse erst nach der Freigabe', function () {
    $poll = Poll::factory()->status(PollStatus::Closed)->create();

    expect($this->member->can('viewResults', $poll))->toBeFalse()
        ->and($this->manager->can('viewResults', $poll))->toBeTrue();

    $poll->forceFill(['status' => PollStatus::Published])->save();
    expect($this->member->can('viewResults', $poll))->toBeTrue();
});
