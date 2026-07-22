<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\Program;

class GetProgramDetailQuery
{
    /**
     * @return array{program: Program, stats: array{totalSpecializations: int, activeSpecializations: int, totalCurriculumVersions: int}}
     */
    public function handle(Program $program): array
    {
        /** @var Program $program */
        $program = $program->fresh()->load([
            'specializations' => fn ($query) => $query->withCount('curriculumVersions')->orderBy('name'),
            'curriculumVersions' => fn ($query) => $query
                ->with(['specialization', 'effectiveFromSemester'])
                ->withCount('curriculumUnits')
                ->orderByDesc('created_at'),
        ]);

        return [
            'program' => $program,
            'stats' => [
                'totalSpecializations' => $program->specializations()->count(),
                'activeSpecializations' => $program->activeSpecializations()->count(),
                'totalCurriculumVersions' => $program->curriculumVersions()->count(),
            ],
        ];
    }
}
