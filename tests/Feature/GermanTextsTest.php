<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('verschickt die Einladung bzw. den Reset-Link auf Deutsch', function () {
    Notification::fake();
    $user = User::factory()->create();

    Password::sendResetLink(['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $mail = $notification->toMail($user);

        return $mail->subject === 'Zugang zum DDHF Wahl-O-Mat'
            && $mail->actionText === 'Passwort setzen'
            && str_contains($mail->actionUrl, '/passwort-setzen/');
    });
});

it('meldet Validierungsfehler auf Deutsch', function () {
    $this->post(route('login'), ['email' => 'keine-adresse', 'password' => ''])
        ->assertSessionHasErrors([
            'email' => 'E-Mail-Adresse muss eine gültige E-Mail-Adresse sein.',
            'password' => 'Passwort muss ausgefüllt werden.',
        ]);
});
