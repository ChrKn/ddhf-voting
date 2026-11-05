<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('leitet Gäste zum Login um', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('zeigt die Login-Seite', function () {
    $this->get(route('login'))->assertOk()->assertSee('E-Mail-Adresse');
});

it('meldet Vereinsvertreter:innen an und leitet zur Wahlmaske', function () {
    $user = User::factory()->create(['password' => 'geheim-passwort']);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'geheim-passwort'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('leitet Verwalter:innen nach dem Login in die Verwaltung', function () {
    $user = User::factory()->manager()->create(['password' => 'geheim-passwort']);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'geheim-passwort'])
        ->assertRedirect('/admin');
});

it('lehnt falsche Passwörter ab', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'falsch'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('bremst nach fünf Fehlversuchen', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post(route('login'), ['email' => $user->email, 'password' => 'falsch']);
    }

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.throttle', ['seconds' => 60])]);
    $this->assertGuest();
});

it('meldet ab', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('verschickt einen Reset-Link, ohne zu verraten, ob die Adresse existiert', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
    $this->post(route('password.email'), ['email' => 'unbekannt@example.com'])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('setzt ein neues Passwort über den Link', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'ein-neues-passwort',
        'password_confirmation' => 'ein-neues-passwort',
    ])->assertRedirect(route('login'));

    $this->post(route('login'), ['email' => $user->email, 'password' => 'ein-neues-passwort'])
        ->assertRedirect(route('home'));
});
