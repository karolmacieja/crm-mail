<?php

namespace App\Enums;

enum ContactStatus: string
{
    case Lead = 'lead';
    case Prospect = 'prospect';
    case Customer = 'customer';
    case Inactive = 'inactive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
