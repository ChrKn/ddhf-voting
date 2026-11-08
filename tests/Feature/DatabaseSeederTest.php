<?php

use App\Models\Assembly;
use App\Models\User;

it('legt Demo-Daten an', function () {
    $this->seed();

    expect(User::where('email', 'admin@example.com')->first()->isSystemAdmin())->toBeTrue()
        ->and(Assembly::first()->totalVotes())->toBe(14)
        ->and(Assembly::first()->polls)->toHaveCount(3);
});
