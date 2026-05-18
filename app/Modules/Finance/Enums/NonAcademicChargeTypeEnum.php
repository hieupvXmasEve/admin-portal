<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

/**
 * Enum of non-academic charge types that may be generated via the Operations page.
 *
 * This enum is the single source of truth for non-academic types in code.
 * The DB ENUM mirrors it; parity is verified by NonAcademicChargeTypeEnumParityTest.
 *
 * Only `bhyt` is present in phase 1 (Q1 decision). Additional types are added
 * in future migrations + enum cases without touching this feature's logic.
 */
enum NonAcademicChargeTypeEnum: string
{
    case BHYT = 'bhyt';

    /**
     * Human-readable Vietnamese label for UI dropdowns.
     */
    public function label(): string
    {
        return match ($this) {
            self::BHYT => 'BHYT (Bảo hiểm y tế)',
        };
    }

    /**
     * Return all enum values as a plain string array.
     * Used by parity test: NonAcademicChargeTypeEnum::values() ⊂ DB ENUM.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Return cases as an array of {value, label} maps for Inertia props.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function forSelect(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
