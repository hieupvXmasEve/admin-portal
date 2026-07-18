<?php

declare(strict_types=1);

namespace App\Support;

use App\Shared\Contracts\Academic\AcademicPeriodReader;

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
        return app(AcademicPeriodReader::class)->current()?->id;
    }
}
