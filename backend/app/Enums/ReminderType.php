<?php

namespace App\Enums;

enum ReminderType: string
{
    case Email = 'email';
    case Reservation = 'reservation';
    case General = 'general';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
