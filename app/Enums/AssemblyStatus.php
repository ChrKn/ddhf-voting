<?php

namespace App\Enums;

enum AssemblyStatus: string
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
}
