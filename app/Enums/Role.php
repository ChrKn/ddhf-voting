<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasColor, HasLabel
{
    case SystemAdmin = 'system_admin';
    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'Systemadmin',
            self::Manager => 'Verwalter:in',
            self::Member => 'Vertreter:in',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SystemAdmin => 'danger',
            self::Manager => 'warning',
            self::Member => 'gray',
        };
    }
}
