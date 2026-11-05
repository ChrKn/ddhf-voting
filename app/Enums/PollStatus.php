<?php

namespace App\Enums;

enum PollStatus: string
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
}
