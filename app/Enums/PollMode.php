<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PollMode: string implements HasLabel
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

    public function getLabel(): string
    {
        return $this->label();
    }
}
