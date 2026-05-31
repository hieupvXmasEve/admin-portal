<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use Illuminate\Support\Str;

final class StudentCodeParser
{
    public const MAX_CODES = 100;

    /**
     * @return list<string>
     */
    public static function tokens(string $input): array
    {
        $parts = preg_split('/[\s,;]+/u', $input) ?: [];

        return collect($parts)
            ->map(fn (string $code): string => Str::upper(trim($code)))
            ->filter(fn (string $code): bool => $code !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function unique(string $input): array
    {
        return collect(self::tokens($input))
            ->unique()
            ->values()
            ->all();
    }
}
