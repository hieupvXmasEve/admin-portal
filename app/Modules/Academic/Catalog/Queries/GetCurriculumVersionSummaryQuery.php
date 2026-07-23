<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Queries;

use App\Models\CurriculumVersion;
use App\Models\Module;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;

final class GetCurriculumVersionSummaryQuery
{
    /** @return array{curriculumVersion: array<string, mixed>, data: array<string, mixed>, meta: array{lastUpdatedAt: string}} */
    public function overview(CurriculumVersion $curriculumVersion): array
    {
        $curriculumVersion->load([
            'program:id,name,code',
            'specialization:id,name,code',
            'effectiveFromSemester:id,name,code',
        ]);
        $stats = $this->overviewStatistics($curriculumVersion);

        return [
            'curriculumVersion' => $this->versionData($curriculumVersion, ['has_modular_structure' => $stats['hasModules']]),
            'data' => $stats,
            'meta' => ['lastUpdatedAt' => now()->toISOString()],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{curriculumVersion: array<string, mixed>, data: array<string, mixed>, meta: array{filters: array<string, mixed>}, units: Collection<int, Unit>}
     */
    public function units(CurriculumVersion $curriculumVersion, array $filters): array
    {
        $curriculumVersion->load([
            'program:id,name,code',
            'specialization:id,name,code',
            'effectiveFromSemester:id,name,code,start_date',
        ]);

        $units = $curriculumVersion->curriculumUnits()
            ->with('unit:id,code,name,credit_points')
            ->when($filters['search'] ?? null, static function ($query, string $search): void {
                $query->whereHas('unit', static function ($unitQuery) use ($search): void {
                    $unitQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($filters['unit_scope'] ?? null, static fn ($query, string $scope) => $query->where('unit_scope', $scope))
            ->when($filters['year_level'] ?? null, static fn ($query, int $year) => $query->where('year_level', $year))
            ->when($filters['semester_number'] ?? null, static fn ($query, int $semester) => $query->where('semester_number', $semester))
            ->orderBy('year_level')
            ->orderBy('semester_number')
            ->orderBy('created_at')
            ->get();
        $modulesCount = $curriculumVersion->curriculumModules()->count();

        return [
            'curriculumVersion' => $this->versionData($curriculumVersion, [
                'semester_id' => $curriculumVersion->semester_id,
                'has_modular_structure' => $modulesCount > 0,
            ]),
            'data' => [
                'units' => $units,
                'stats' => $this->unitStatistics($curriculumVersion),
                'modulesCount' => $modulesCount,
            ],
            'meta' => ['filters' => collect($filters)->only(['search', 'unit_scope', 'year_level', 'semester_number'])->all()],
            'units' => Unit::query()->select('id', 'code', 'name', 'credit_points')->orderBy('code')->get(),
        ];
    }

    /** @return array{curriculumVersion: array<string, mixed>, data: array{standaloneUnitsCount: int}, availableModules: Collection<int, Module>} */
    public function modules(CurriculumVersion $curriculumVersion): array
    {
        $curriculumVersion->load([
            'program:id,name,code',
            'specialization:id,name,code',
            'effectiveFromSemester:id,name,code,start_date',
            'curriculumModules.module.units',
        ]);
        $standaloneUnitsCount = $curriculumVersion->curriculumUnits()->count();

        return [
            'curriculumVersion' => $this->versionData($curriculumVersion, [
                'curriculum_modules' => $curriculumVersion->curriculumModules,
                'has_standalone_units' => $standaloneUnitsCount > 0,
            ]),
            'data' => ['standaloneUnitsCount' => $standaloneUnitsCount],
            'availableModules' => Module::query()
                ->select('id', 'campus_id', 'code', 'name', 'total_credits', 'grading_type')
                ->with('campus:id,name,code')
                ->orderBy('code')
                ->get(),
        ];
    }

    /** @return array{curriculumVersion: array<string, mixed>} */
    public function roadmap(CurriculumVersion $curriculumVersion): array
    {
        $curriculumVersion->load([
            'program:id,name,code',
            'specialization:id,name,code',
            'effectiveFromSemester:id,name,code',
            'curriculumUnits' => static function ($query): void {
                $query->orderBy('year_level')->orderBy('semester_number')->orderBy('id');
            },
            'curriculumUnits.unit:id,code,name,credit_points',
            'curriculumUnits.unit.prerequisiteGroups.conditions.requiredUnit:id,code',
        ]);

        return [
            'curriculumVersion' => $this->versionData($curriculumVersion, [
                'curriculum_units' => $curriculumVersion->curriculumUnits->map(static function ($curriculumUnit): array {
                    return [
                        'id' => $curriculumUnit->id,
                        'unit_id' => $curriculumUnit->unit_id,
                        'semester_number' => $curriculumUnit->semester_number,
                        'year_level' => $curriculumUnit->year_level,
                        'unit' => $curriculumUnit->unit === null ? null : [
                            'id' => $curriculumUnit->unit->id,
                            'code' => $curriculumUnit->unit->code,
                            'name' => $curriculumUnit->unit->name,
                            'credit_points' => $curriculumUnit->unit->credit_points,
                            'prerequisite_groups' => $curriculumUnit->unit->prerequisiteGroups->map(static function ($group): array {
                                return [
                                    'logic_operator' => $group->logic_operator,
                                    'conditions' => $group->conditions->map(static function ($condition): array {
                                        return [
                                            'type' => $condition->type,
                                            'required_unit_id' => $condition->required_unit_id,
                                            'required_unit_code' => $condition->requiredUnit?->code,
                                        ];
                                    }),
                                ];
                            }),
                        ],
                    ];
                }),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    private function overviewStatistics(CurriculumVersion $curriculumVersion): array
    {
        $unitsCount = $curriculumVersion->curriculumUnits()->count();
        $totalCreditPoints = $curriculumVersion->curriculumUnits()
            ->join('units', 'curriculum_units.unit_id', '=', 'units.id')
            ->sum('units.credit_points');
        $modulesCount = $curriculumVersion->curriculumModules()->count();
        $totalModuleCredits = $curriculumVersion->curriculumModules()
            ->join('modules', 'curriculum_modules.module_id', '=', 'modules.id')
            ->sum('modules.total_credits');

        return [
            'totalUnits' => $unitsCount,
            'totalCreditPoints' => $totalCreditPoints,
            'byYearLevel' => $curriculumVersion->curriculumUnits()->selectRaw('year_level, COUNT(*) as count')->whereNotNull('year_level')->groupBy('year_level')->pluck('count', 'year_level')->toArray(),
            'byUnitScope' => $curriculumVersion->curriculumUnits()->selectRaw('unit_scope, COUNT(*) as count')->whereNotNull('unit_scope')->groupBy('unit_scope')->pluck('count', 'unit_scope')->toArray(),
            'totalModules' => $modulesCount,
            'totalModuleCredits' => $totalModuleCredits,
            'hasModules' => $modulesCount > 0,
            'roadmap' => $this->roadmapData($curriculumVersion),
        ];
    }

    /** @return array<string, mixed> */
    private function unitStatistics(CurriculumVersion $curriculumVersion): array
    {
        return [
            'totalUnits' => $curriculumVersion->curriculumUnits()->count(),
            'totalCreditPoints' => $curriculumVersion->curriculumUnits()->join('units', 'curriculum_units.unit_id', '=', 'units.id')->sum('units.credit_points'),
            'byYearLevel' => $curriculumVersion->curriculumUnits()
                ->selectRaw('year_level, COUNT(*) as count, SUM(units.credit_points) as total_credits')
                ->join('units', 'curriculum_units.unit_id', '=', 'units.id')
                ->whereNotNull('year_level')
                ->groupBy('year_level')
                ->get()
                ->mapWithKeys(static fn ($item): array => [$item->year_level => ['count' => $item->count, 'total_credits' => $item->total_credits]])
                ->toArray(),
            'byUnitScope' => $curriculumVersion->curriculumUnits()->selectRaw('unit_scope, COUNT(*) as count')->whereNotNull('unit_scope')->groupBy('unit_scope')->pluck('count', 'unit_scope')->toArray(),
        ];
    }

    /** @return array<int, array<int, array{items: array<int, array<string, mixed>>, total_credits: float|int}>> */
    private function roadmapData(CurriculumVersion $curriculumVersion): array
    {
        $items = $curriculumVersion->curriculumUnits()->with('unit:id,code,name,credit_points')->get()->map(static fn ($curriculumUnit): array => [
            'type' => 'unit', 'id' => $curriculumUnit->id, 'code' => $curriculumUnit->unit->code,
            'name' => $curriculumUnit->unit->name, 'credits' => $curriculumUnit->unit->credit_points,
            'year_level' => $curriculumUnit->year_level ?? 0, 'semester_number' => $curriculumUnit->semester_number ?? 0,
            'unit_scope' => $curriculumUnit->unit_scope, 'note' => $curriculumUnit->note,
        ])->concat($curriculumVersion->curriculumModules()->with(['module:id,code,name,total_credits,grading_type', 'module.units:id,code,name,credit_points'])->get()->map(static function ($curriculumModule): array {
            return [
                'type' => 'module', 'id' => $curriculumModule->id, 'code' => $curriculumModule->module->code,
                'name' => $curriculumModule->module->name, 'credits' => $curriculumModule->module->total_credits,
                'year_level' => $curriculumModule->year_level ?? 0, 'semester_number' => $curriculumModule->semester_number ?? 0,
                'is_required' => $curriculumModule->is_required, 'group_name' => $curriculumModule->group_name,
                'grading_type' => $curriculumModule->module->grading_type, 'note' => $curriculumModule->note,
                'sub_units' => $curriculumModule->module->units->map(static fn ($unit): array => [
                    'id' => $unit->id, 'code' => $unit->code, 'name' => $unit->name, 'credits' => $unit->credit_points,
                    'weight' => $unit->pivot->weight ?? null, 'order' => $unit->pivot->order ?? 0,
                ])->sortBy('order')->values()->all(),
            ];
        }));
        $roadmap = [];
        foreach ($items as $item) {
            $year = $item['year_level'] ?: 0;
            $semester = $item['semester_number'] ?: 0;
            $roadmap[$year][$semester] ??= ['items' => [], 'total_credits' => 0];
            $roadmap[$year][$semester]['items'][] = $item;
            $roadmap[$year][$semester]['total_credits'] += $item['credits'];
        }
        ksort($roadmap);
        foreach ($roadmap as &$semesters) {
            ksort($semesters);
        }

        return $roadmap;
    }

    /**
     * @param  array<string, mixed>  $additional
     * @return array<string, mixed>
     */
    private function versionData(CurriculumVersion $curriculumVersion, array $additional = []): array
    {
        return [
            'id' => $curriculumVersion->id,
            'version_code' => $curriculumVersion->version_code,
            'notes' => $curriculumVersion->notes,
            'created_at' => $curriculumVersion->created_at,
            'program' => $curriculumVersion->program,
            'specialization' => $curriculumVersion->specialization,
            'effective_from_semester' => $curriculumVersion->effectiveFromSemester,
            ...$additional,
        ];
    }
}
