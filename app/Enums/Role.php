<?php

namespace App\Enums;

enum Role: string
{
    case SystemAdmin = 'system_admin';
    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'Systemadmin',
            self::Manager => 'Verwalter:in',
            self::Member => 'Vereinsvertreter:in',
        };
    }
}
