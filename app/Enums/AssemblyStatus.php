<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AssemblyStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'In Vorbereitung',
            self::Open => 'Laufend',
            self::Closed => 'Beendet',
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
            self::Closed => 'info',
        };
    }
}
