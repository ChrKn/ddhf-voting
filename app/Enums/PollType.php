<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PollType: string implements HasLabel
{
    /** Antrag: Dafür / Dagegen / Enthaltung */
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

    public function getLabel(): string
    {
        return $this->label();
    }
}
