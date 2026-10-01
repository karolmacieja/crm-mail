<?php

namespace App\Enums;

enum ActivityType: string
{
    case Email = 'email';
    case Note = 'note';
    case System = 'system';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
