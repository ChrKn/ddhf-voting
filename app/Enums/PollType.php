<?php

namespace App\Enums;

enum PollType: string
{
    /** Antrag: Ja / Nein / Enthaltung */
    case Motion = 'motion';

    /** Wahl: ein oder mehrere Ämter mit Kandidat:innen */
    case Election = 'election';

    public function label(): string
    {
        return match ($this) {
            self::Motion => 'Antrag',
            self::Election => 'Wahl',
        };
    }
}
