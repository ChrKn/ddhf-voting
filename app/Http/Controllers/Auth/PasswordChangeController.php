<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Eigenes Passwort festlegen, nachdem ein vorläufiges vergeben wurde. */
class PasswordChangeController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Ohne vorläufiges Passwort gibt es hier nichts zu tun
        return $user->must_change_password ? view('auth.change-password') : $this->home($user);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::min(10)],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (Hash::check($request->string('password')->value(), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Bitte ein neues Passwort wählen, nicht das vorläufige.',
            ]);
        }

        $user->forceFill([
            'password' => $request->string('password')->value(),
            'must_change_password' => false,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $request->session()->regenerate();

        return $this->home($user)->with('status', 'Das Passwort wurde geändert.');
    }

    private function home(User $user): RedirectResponse
    {
        return redirect()->intended($user->canManage() ? '/admin' : route('home'));
    }
}
