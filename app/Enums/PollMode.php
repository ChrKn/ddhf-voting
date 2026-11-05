<?php

namespace App\Enums;

enum PollMode: string
{
    case Secret = 'secret';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Secret => 'Geheim',
            self::Public => 'Öffentlich',
        };
    }
}
