<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Semester;

final class SemesterContextResolver
{
    public const SessionKey = 'current_semester_id';

    public const AllSemesters = 'all';

    public static function selectedId(): ?int
    {
        if (! session()->has(self::SessionKey)) {
            return self::activeSemesterId();
        }

        $selectedId = session(self::SessionKey);

        if ($selectedId === self::AllSemesters || $selectedId === null || $selectedId === '') {
            return null;
        }

        return (int) $selectedId;
    }

    private static function activeSemesterId(): ?int
    {
        $activeId = Semester::query()->where('is_active', true)->value('id');

        return $activeId !== null ? (int) $activeId : null;
    }
}
