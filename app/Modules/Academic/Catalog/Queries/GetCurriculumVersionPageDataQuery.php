<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\CurriculumVersion;
use App\Models\Module;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Unit;
use Illuminate\Support\Collection;

final class GetCurriculumVersionPageDataQuery
{
    /** @return array{programs: \Illuminate\Database\Eloquent\Collection<int, Program>, specializations: \Illuminate\Database\Eloquent\Collection<int, Specialization>, semesters: \Illuminate\Database\Eloquent\Collection<int, Semester>} */
    public function create(): array
    {
        return [
            'programs' => Program::query()->with('specializations')->get(),
            'specializations' => Specialization::query()->select('id', 'name', 'code', 'program_id')->orderBy('name')->get(),
            'semesters' => Semester::query()->select('id', 'name', 'code')->get(),
        ];
    }

    /**
     * @return array{curriculumVersion: CurriculumVersion, programs: \Illuminate\Database\Eloquent\Collection<int, Program>, specializations: \Illuminate\Database\Eloquent\Collection<int, Specialization>, semesters: \Illuminate\Database\Eloquent\Collection<int, Semester>, editable: bool}
     */
    public function edit(CurriculumVersion $curriculumVersion): array
    {
        $curriculumVersion->load(['program', 'specialization', 'effectiveFromSemester']);
        $startDate = $curriculumVersion->effectiveFromSemester?->start_date;

        return [
            'curriculumVersion' => $curriculumVersion,
            'programs' => Program::query()->select('id', 'name', 'code')->orderBy('name')->get(),
            'specializations' => Specialization::query()->select('id', 'name', 'code', 'program_id')->orderBy('name')->get(),
            'semesters' => Semester::query()->select('id', 'name', 'code')->orderBy('name')->get(),
            'editable' => $startDate === null || now()->lt($startDate),
        ];
    }

    /**
     * @return array{curriculumVersion: CurriculumVersion, availableModules: \Illuminate\Database\Eloquent\Collection<int, Module>, units: \Illuminate\Database\Eloquent\Collection<int, Unit>}
     */
    public function show(CurriculumVersion $curriculumVersion): array
    {
        $curriculumVersion->load([
            'program:id,name,code',
            'specialization:id,name,code',
            'effectiveFromSemester:id,name,code',
            'curriculumModules.module:id,campus_id,code,name,total_credits,grading_type',
            'curriculumUnits.unit:id,code,name,credit_points',
        ]);

        return [
            'curriculumVersion' => $curriculumVersion,
            'availableModules' => Module::query()
                ->select('id', 'campus_id', 'code', 'name', 'total_credits', 'grading_type')
                ->with('campus:id,name,code')
                ->orderBy('code')
                ->get(),
            'units' => Unit::query()->select('id', 'code', 'name', 'credit_points')->orderBy('code')->get(),
        ];
    }

    /**
     * @return array{curriculumVersion: CurriculumVersion, electiveSlots: Collection<int, array<string, mixed>>, availableElectives: array<string, Collection<int, Unit>>}
     */
    public function electives(CurriculumVersion $curriculumVersion): array
    {
        $curriculumVersion->load(['program', 'specialization']);

        $electiveSlots = $curriculumVersion->getElectiveSlots()->map(static function ($slot): array {
            return [
                'id' => $slot->id,
                'year_level' => $slot->year_level,
                'semester_number' => $slot->semester_number,
                'current_unit' => [
                    'id' => $slot->unit->id,
                    'code' => $slot->unit->code,
                    'name' => $slot->unit->name,
                    'credit_points' => $slot->unit->credit_points,
                ],
                'note' => $slot->note,
            ];
        });

        return [
            'curriculumVersion' => $curriculumVersion,
            'electiveSlots' => $electiveSlots,
            'availableElectives' => $curriculumVersion->getElectiveUnitsByCategory(),
        ];
    }
}
