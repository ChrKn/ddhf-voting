<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->user = User::factory()->unverified()->create([
        'password' => 'vorlaeufig-1234',
        'must_change_password' => true,
    ]);
});

it('leitet nach dem Login mit vorläufigem Passwort zur Passwortänderung', function () {
    $this->post('/login', ['email' => $this->user->email, 'password' => 'vorlaeufig-1234'])
        ->assertRedirect(route('home'));

    $this->get('/')->assertRedirect(route('password.change'));
    $this->get(route('password.change'))->assertOk()->assertSeeText('vorläufigen Passwort');
});

it('sperrt auch die Verwaltung, bis das Passwort geändert ist', function () {
    $this->user->update(['role' => 'manager']);

    $this->actingAs($this->user)->get('/admin')->assertRedirect(route('password.change'));
});

it('setzt das eigene Passwort und hebt die Sperre auf', function () {
    $this->actingAs($this->user)
        ->post(route('password.change.update'), [
            'password' => 'mein-neues-passwort',
            'password_confirmation' => 'mein-neues-passwort',
        ])
        ->assertRedirect(route('home'));

    $this->user->refresh();
    expect(Hash::check('mein-neues-passwort', $this->user->password))->toBeTrue()
        ->and($this->user->must_change_password)->toBeFalse()
        ->and($this->user->email_verified_at)->not->toBeNull();

    $this->get('/')->assertOk();
});

it('lehnt das vorläufige Passwort als neues ab', function () {
    $this->actingAs($this->user)
        ->post(route('password.change.update'), [
            'password' => 'vorlaeufig-1234',
            'password_confirmation' => 'vorlaeufig-1234',
        ])
        ->assertSessionHasErrors('password');

    expect($this->user->refresh()->must_change_password)->toBeTrue();
});

it('verlangt mindestens 10 Zeichen und eine Bestätigung', function () {
    $this->actingAs($this->user)
        ->post(route('password.change.update'), ['password' => 'kurz', 'password_confirmation' => 'anders'])
        ->assertSessionHasErrors('password');
});

it('erlaubt die Abmeldung trotz Sperre', function () {
    $this->actingAs($this->user)->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('schickt Personen ohne vorläufiges Passwort weiter', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('password.change'))
        ->assertRedirect(route('home'));
});

it('hebt die Sperre auch beim Zurücksetzen per Mail-Link auf', function () {
    $token = Password::createToken($this->user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $this->user->email,
        'password' => 'per-mail-gesetzt',
        'password_confirmation' => 'per-mail-gesetzt',
    ])->assertRedirect(route('login'));

    expect($this->user->refresh()->must_change_password)->toBeFalse();
});
