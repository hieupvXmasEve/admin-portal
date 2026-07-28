<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Shared\Contracts\Academic\StudentDirectoryFormOptionsReader;

/**
 * Catalog reference lists rendered by the student directory pages (filters,
 * create form, edit form), so those pages reach Program, Specialization,
 * CurriculumVersion, and Semester through a Catalog-owned seam instead of
 * importing the shared models directly.
 *
 * Each method returns the array form of the model collection the matching
 * Inertia prop already carried, keeping the rendered payloads byte-compatible.
 */
class GetStudentDirectoryFormOptionsQuery implements StudentDirectoryFormOptionsReader
{
    /**
     * Dropdown sources for the directory listing's filter bar.
     *
     * @return array{
     *     programs: list<array<string, mixed>>,
     *     specializations: list<array<string, mixed>>,
     *     intake_semesters: list<array<string, mixed>>
     * }
     */
    public function filterOptions(): array
    {
        return [
            // Programs are not campus-specific, so the full list is offered.
            'programs' => Program::orderBy('name')->get(['id', 'name'])->toArray(),
            'specializations' => Specialization::active()
                ->orderBy('name')
                ->get(['id', 'program_id', 'name', 'code'])
                ->toArray(),
            'intake_semesters' => Semester::orderByDesc('start_date')
                ->get(['id', 'name', 'code', 'start_date', 'end_date'])
                ->toArray(),
        ];
    }

    /**
     * Program, specialization, and curriculum-version sources for the create form.
     *
     * @return array{
     *     programs: list<array<string, mixed>>,
     *     specializations: list<array<string, mixed>>,
     *     curriculumVersions: list<array<string, mixed>>
     * }
     */
    public function createOptions(): array
    {
        $programs = Program::with('specializations')->orderBy('name')->get();
        $programIds = $programs->pluck('id')->toArray();

        return [
            'programs' => $programs->toArray(),
            'specializations' => Specialization::whereIn('program_id', $programIds)
                ->orderBy('name')
                ->get()
                ->toArray(),
            'curriculumVersions' => CurriculumVersion::whereIn('program_id', $programIds)
                ->orderBy('version_code', 'desc')
                ->get()
                ->toArray(),
        ];
    }

    /**
     * Program list plus the curriculum versions available to one student's
     * program, narrowed by specialization when the student has one.
     *
     * @return array{
     *     programs: list<array<string, mixed>>,
     *     curriculumVersions: list<array<string, mixed>>
     * }
     */
    public function editOptions(?int $programId, ?int $specializationId): array
    {
        return [
            'programs' => Program::with('specializations')->orderBy('name')->get()->toArray(),
            'curriculumVersions' => CurriculumVersion::where('program_id', $programId)
                ->when($specializationId, function ($query) use ($specializationId) {
                    $query->where('specialization_id', $specializationId);
                })
                ->orderBy('created_at', 'desc')
                ->get(['id', 'version_code'])
                ->toArray(),
        ];
    }
}
