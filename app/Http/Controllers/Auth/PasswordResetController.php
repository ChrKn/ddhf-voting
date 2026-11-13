<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Passwort vergessen / Passwort setzen. Wird auch für die Einladung neuer
 * Nutzer:innen verwendet: Diese bekommen einen Reset-Link und setzen ihr
 * Passwort selbst.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        // Immer dieselbe Antwort, damit nicht erkennbar ist, ob die Adresse existiert.
        return back()->with('status', 'Falls die Adresse bekannt ist, wurde ein Link zum Zurücksetzen verschickt.');
    }

    public function edit(Request $request, string $token): View|RedirectResponse
    {
        // Abgelaufene oder bereits benutzte Links gar nicht erst als Formular
        // anzeigen, sondern direkt zur Anforderung eines neuen Links schicken.
        $email = (string) $request->query('email');
        $user = $email !== '' ? Password::getUser(['email' => $email]) : null;

        if (! $user || ! Password::tokenExists($user, $token)) {
            return redirect()->route('password.request')
                ->withInput(['email' => $email])
                ->withErrors(['email' => __(Password::INVALID_TOKEN)]);
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Das Passwort wurde gesetzt. Bitte jetzt anmelden.');
    }
}
