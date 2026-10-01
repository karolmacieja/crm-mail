<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Normal = 'normal';
    case High = 'high';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
