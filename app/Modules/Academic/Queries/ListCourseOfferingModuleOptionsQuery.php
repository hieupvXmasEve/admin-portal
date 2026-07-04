<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use Illuminate\Support\Facades\DB;

class ListCourseOfferingModuleOptionsQuery
{
    /**
     * @return array<int, array{value:string,label:string}>
     */
    public function handle(int $campusId, ?int $semesterId): array
    {
        return DB::table('modules')
            ->join('module_units', 'modules.id', '=', 'module_units.module_id')
            ->join('units', 'module_units.unit_id', '=', 'units.id')
            ->join('course_offerings', 'units.id', '=', 'course_offerings.unit_id')
            ->where('modules.campus_id', $campusId)
            ->where('course_offerings.campus_id', $campusId)
            ->whereNull('modules.deleted_at')
            ->whereNull('course_offerings.deleted_at')
            ->when($semesterId !== null, fn ($query) => $query->where('course_offerings.semester_id', $semesterId))
            ->select('modules.id', 'modules.name')
            ->distinct()
            ->orderBy('modules.name')
            ->get()
            ->map(fn (object $module): array => [
                'value' => (string) $module->id,
                'label' => (string) $module->name,
            ])
            ->all();
    }
}
