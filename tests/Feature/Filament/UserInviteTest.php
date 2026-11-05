<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('verschickt Einladungen an mehrere Personen auf einmal', function () {
    Notification::fake();
    $this->actingAs(User::factory()->systemAdmin()->create());
    $people = User::factory()->count(3)->unverified()->create();

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('invite', $people)
        ->assertNotified('3 Einladung(en) verschickt');

    Notification::assertSentTo($people, ResetPassword::class);
});

it('filtert Personen, die noch kein Passwort gesetzt haben', function () {
    $this->actingAs($admin = User::factory()->systemAdmin()->create());
    $pending = User::factory()->unverified()->create();
    $done = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->filterTable('email_verified_at', false)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$done, $admin]);
});
