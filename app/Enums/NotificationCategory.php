<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case ACADEMIC = 'academic';
    case SYSTEM = 'system';
    case FINANCE = 'finance';
    case PERSONAL = 'personal';
    case EVENT = 'event';
    case CLUB = 'club';
    case ADMINISTRATIVE = 'administrative';

    public function label(): string
    {
        return match ($this) {
            self::ACADEMIC => 'Academic',
            self::SYSTEM => 'System',
            self::FINANCE => 'Finance',
            self::PERSONAL => 'Personal',
            self::EVENT => 'Event',
            self::CLUB => 'Club',
            self::ADMINISTRATIVE => 'Administrative',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ACADEMIC => 'Academic-related notifications such as grades, enrollment, and course updates',
            self::SYSTEM => 'System notifications including maintenance, updates, and technical announcements',
            self::FINANCE => 'Financial notifications for payments, scholarships, and billing',
            self::PERSONAL => 'Personal notifications and account-related updates',
            self::EVENT => 'Event notifications',
            self::CLUB => 'Club notifications',
            self::ADMINISTRATIVE => 'Administrative notifications',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn(self $category) => [
                'value' => $category->value,
                'label' => $category->label(),
                'description' => $category->description(),
            ],
            self::cases()
        );
    }
}
