<?php

namespace App\Shared\Support\Enums;

enum UserType: string
{
    case STAFF = 'staff';
    case STUDENT = 'student';
    case LECTURER = 'lecturer';
    case PARENT = 'parent';

    public function label(): string
    {
        return match ($this) {
            self::STAFF => 'Staff',
            self::STUDENT => 'Student',
            self::LECTURER => 'Lecturer',
            self::PARENT => 'Parent',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
