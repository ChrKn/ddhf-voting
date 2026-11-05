<?php

use App\Enums\Role;
use App\Models\User;

it('legt einen Systemadmin an', function () {
    $this->artisan('voting:create-admin', [
        '--name' => 'Admin',
        '--email' => 'admin@example.com',
        '--password' => 'sehr-geheim-123',
    ])->assertSuccessful();

    $user = User::firstWhere('email', 'admin@example.com');
    expect($user->role)->toBe(Role::SystemAdmin)
        ->and($user->email_verified_at)->not->toBeNull();
});

it('lehnt zu kurze Passwörter und doppelte Adressen ab', function () {
    User::factory()->create(['email' => 'admin@example.com']);

    $this->artisan('voting:create-admin', [
        '--name' => 'Admin',
        '--email' => 'admin@example.com',
        '--password' => 'kurz',
    ])->assertFailed();

    expect(User::count())->toBe(1);
});
