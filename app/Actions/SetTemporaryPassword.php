<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Vergibt ein vorläufiges Passwort, z.B. wenn die Einladungsmail nicht
 * ankommt. Die Person muss es beim ersten Login durch ein eigenes ersetzen.
 */
class SetTemporaryPassword
{
    /** Ohne leicht verwechselbare Zeichen (0/o, 1/l/i), damit es sich diktieren lässt */
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    /**
     * @return string Das Passwort im Klartext, nur zur einmaligen Anzeige
     */
    public function __invoke(User $user, User $by): string
    {
        $password = $this->generate();

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
            'remember_token' => Str::random(60),
        ])->save();

        // Ein noch offener Einladungs-/Reset-Link soll danach nicht mehr funktionieren.
        Password::deleteToken($user);

        Log::info('Vorläufiges Passwort vergeben', ['user' => $user->email, 'by' => $by->email]);

        return $password;
    }

    /** z.B. „kmtp-4z9w-rxa3“: 12 Zufallszeichen in Vierergruppen */
    private function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $groups = [];

        for ($g = 0; $g < 3; $g++) {
            $group = '';
            for ($i = 0; $i < 4; $i++) {
                $group .= self::ALPHABET[random_int(0, $max)];
            }
            $groups[] = $group;
        }

        return implode('-', $groups);
    }
}
