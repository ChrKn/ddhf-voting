<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Wer stimmberechtigt ist: Mitglieder (in der Regel Vereine) mit Stimmen nach Mitgliederzahl,
 * Präsidiumsposten mit einer festen Stimmenzahl, die am Posten hängt, nicht an der Person.
 */
enum VotingMemberType: string implements HasColor, HasLabel
{
    case Member = 'member';
    case BoardMember = 'board_member';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Mitglied',
            self::BoardMember => 'Präsidium',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Member => 'gray',
            self::BoardMember => 'primary',
        };
    }
}
