<?php

use App\Actions\SetTemporaryPassword;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('erzeugt ein diktierbares Passwort und erzwingt die Änderung', function () {
    $member = User::factory()->unverified()->create();

    $password = app(SetTemporaryPassword::class)($member, User::factory()->manager()->create());

    $member->refresh();
    expect($password)->toMatch('/^[a-hj-km-np-z2-9]{4}-[a-hj-km-np-z2-9]{4}-[a-hj-km-np-z2-9]{4}$/')
        ->and(Hash::check($password, $member->password))->toBeTrue()
        ->and($member->must_change_password)->toBeTrue()
        // Gilt erst als gesetzt, wenn die Person ein eigenes Passwort vergibt
        ->and($member->email_verified_at)->toBeNull();
});

it('lässt Verwalter:innen Vertreter:innen ein vorläufiges Passwort geben', function () {
    $this->actingAs(User::factory()->manager()->create());
    $member = User::factory()->unverified()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('setPassword')->table($member))
        ->assertNotified();

    expect($member->refresh()->must_change_password)->toBeTrue();
});

it('bietet Verwalter:innen die Aktion nicht bei Admins und Verwalter:innen an', function () {
    $this->actingAs(User::factory()->manager()->create());
    $admin = User::factory()->systemAdmin()->create();
    $manager = User::factory()->manager()->create();
    $member = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertActionVisible(TestAction::make('setPassword')->table($member))
        ->assertActionHidden(TestAction::make('setPassword')->table($admin))
        ->assertActionHidden(TestAction::make('setPassword')->table($manager))
        ->assertActionHidden(TestAction::make('edit')->table($member));
});

it('lässt Verwalter:innen per Sammelaktion nur Vertreter:innen einladen', function () {
    Notification::fake();
    $this->actingAs(User::factory()->manager()->create());
    $member = User::factory()->unverified()->create();
    $admin = User::factory()->systemAdmin()->unverified()->create();

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('invite', [$member, $admin]);

    Notification::assertSentTo($member, ResetPassword::class);
    Notification::assertNotSentTo($admin, ResetPassword::class);
});
