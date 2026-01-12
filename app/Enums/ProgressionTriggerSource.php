<?php

declare(strict_types=1);

namespace App\Enums;

enum ProgressionTriggerSource: string
{
    case IELTS = 'ielts';
    case PLACEMENT_TEST = 'placement_test';
    case MANUAL_ADMIN = 'manual_admin';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::IELTS => 'IELTS Score',
            self::PLACEMENT_TEST => 'Placement Test',
            self::MANUAL_ADMIN => 'Manual (Admin)',
            self::SYSTEM => 'System',
        };
    }

    public function labelVi(): string
    {
        return match ($this) {
            self::IELTS => 'Điểm IELTS',
            self::PLACEMENT_TEST => 'Bài kiểm tra xếp lớp',
            self::MANUAL_ADMIN => 'Admin nhập thủ công',
            self::SYSTEM => 'Hệ thống',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'labelVi' => $type->labelVi(),
            ],
            self::cases()
        );
    }
}
