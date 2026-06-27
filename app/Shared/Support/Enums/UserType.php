<?php

declare(strict_types=1);

namespace App\Shared\Support\Enums;

enum UserType: string
{
    case STAFF = 'staff';
    case STUDENT = 'student';
    case LECTURER = 'lecturer';
    case PARENT = 'parent';
    // A non-human, server-to-server account (e.g. the admissions CRM). Cannot
    // authenticate to the web UI; acts only through scoped Sanctum tokens.
    case SERVICE = 'service';

    public function label(): string
    {
        return match ($this) {
            self::STAFF => 'Staff',
            self::STUDENT => 'Student',
            self::LECTURER => 'Lecturer',
            self::PARENT => 'Parent',
            self::SERVICE => 'Service Account',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
