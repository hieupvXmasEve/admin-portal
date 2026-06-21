<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Semester;

/**
 * Resolves the operator's selected semester from session (set by SemesterSwitcher)
 * with active-semester fallback — same source as the shared Inertia `semester` prop.
 */
final class FinanceSemesterContextResolver
{
    public static function selectedId(): ?int
    {
        $selectedId = session('current_semester_id');

        if ($selectedId !== null) {
            return (int) $selectedId;
        }

        $activeId = Semester::query()->where('is_active', true)->value('id');

        return $activeId !== null ? (int) $activeId : null;
    }
}
