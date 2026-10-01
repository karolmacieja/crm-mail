<?php

namespace App\Enums;

enum UserRole: string
{
    case MasterAdmin = 'master_admin';
    case Manager = 'manager';
    case Staff = 'staff';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
