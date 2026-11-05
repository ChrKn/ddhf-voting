<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PollStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'In Vorbereitung',
            self::Open => 'Offen',
            self::Closed => 'Geschlossen',
            self::Published => 'Veröffentlicht',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Open => 'success',
            self::Closed => 'warning',
            self::Published => 'info',
        };
    }
}
