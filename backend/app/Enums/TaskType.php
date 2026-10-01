<?php

namespace App\Enums;

enum TaskType: string
{
    case FollowUp = 'follow_up';
    case Offer = 'offer';
    case Internal = 'internal';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
