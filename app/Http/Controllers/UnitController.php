<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\UnitValidationService;
use App\Services\UnitRelationshipService;
use App\Services\PrerequisiteLogicService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use App\Models\UnitPrerequisite;

class UnitController extends Controller
{
    public function __construct(
        private UnitValidationService    $validationService,
        private UnitRelationshipService  $relationshipService,
        private PrerequisiteLogicService $prerequisiteLogicService
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:code,name,credit_points,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $units = Unit::query()
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($validated['sort'] ?? null, callback: function ($query, $sort) use ($validated) {
                $direction = $validated['direction'] ?? 'asc';
                $query->orderBy($sort, $direction);
            })
            ->orderBy('created_at', 'desc') // Sort by created_at in descending order by default
            ->withCount(['equivalentUnits', 'curriculumUnits', 'prerequisiteConditions', 'syllabus'])
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return Inertia::render('units/Index', [
            'units' => $units,
            'filters' => $request->only(['search', 'sort', 'direction', 'per_page']),
            'statistics' => [
                'total_units' => Unit::count(),
                'units_with_prerequisites' => Unit::has('prerequisiteGroups')->count(),
                'units_with_equivalents' => Unit::has('equivalentUnits')->count(),
                'avg_credit_points' => Unit::avg('credit_points'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('units/Create', [
            'formDefaults' => [
                'code' => '',
                'name' => '',
                'credit_points' => 12.5,
            ],
        ]);
    }

    public function store(StoreUnitRequest $request)
    {
        try {
            DB::beginTransaction();

            $unit = Unit::create($request->validated());

            // Handle prerequisite groups if provided
            if ($request->has('prerequisite_groups') && is_array($request->prerequisite_groups)) {
                // Create new prerequisite groups and conditions
                foreach ($request->prerequisite_groups as $groupData) {
                    $group = \App\Models\UnitPrerequisiteGroup::create([
                        'unit_id' => $unit->id,
                        'logic_operator' => $groupData['logic_operator'] ?? 'AND',
                        'description' => $groupData['description'] ?? null,
                    ]);

                    // Create conditions for this group
                    if (isset($groupData['conditions']) && is_array($groupData['conditions'])) {
                        foreach ($groupData['conditions'] as $conditionData) {
                            \App\Models\UnitPrerequisiteCondition::create([
                                'group_id' => $group->id,
                                'type' => $conditionData['type'] ?? 'prerequisite',
                                'required_unit_id' => $conditionData['required_unit_id'] ?? null,
                                'required_credits' => $conditionData['required_credits'] ?? null,
                                'free_text' => $conditionData['free_text'] ?? null,
                            ]);
                        }
                    }
                }
            } // Handle prerequisite expression if provided (for backwards compatibility)
            elseif ($request->has('prerequisite_expression') && !empty($request->prerequisite_expression)) {
                $result = $this->prerequisiteLogicService->parseAndStorePrerequisiteExpression(
                    $unit->id,
                    $request->prerequisite_expression,
                    $request->prerequisite_description ?? null
                );

                if (!$result['success']) {
                    DB::rollBack();
                    return redirect()->back()
                        ->withErrors(['prerequisite_expression' => $result['message']])
                        ->withInput();
                }
            } // For backwards compatibility - if prerequisites array is provided and no expression
            elseif ($request->has('prerequisites') && is_array($request->prerequisites) && count($request->prerequisites) > 0) {
                // Create a single prerequisite group
                $group = \App\Models\UnitPrerequisiteGroup::create([
                    'unit_id' => $unit->id,
                    'logic_operator' => 'AND',
                    'description' => 'Basic prerequisites',
                ]);

                foreach ($request->prerequisites as $prerequisite) {
                    // Validate prerequisite relationship
                    $validation = $this->validationService->validatePrerequisiteRelationship(
                        $unit->id,
                        $prerequisite['required_unit_id'],
                        $prerequisite['type']
                    );

                    if ($validation['valid']) {
                        \App\Models\UnitPrerequisiteCondition::create([
                            'group_id' => $group->id,
                            'type' => strtolower($prerequisite['type']),
                            'required_unit_id' => $prerequisite['required_unit_id'],
                        ]);
                    }
                }
            }

            // Handle equivalent units if provided
            if ($request->has('equivalent_units') && is_array($request->equivalent_units)) {
                foreach ($request->equivalent_units as $equivalent) {
                    // For equivalent units, we need a semester - use current or latest semester
                    $currentSemester = \App\Models\Semester::orderBy('year', 'desc')
                        ->orderBy('term', 'desc')
                        ->first();

                    if ($currentSemester) {
                        \App\Models\EquivalentUnit::create([
                            'unit_id' => $unit->id,
                            'equivalent_unit_id' => $equivalent['equivalent_unit_id'],
                            'reason' => $equivalent['reason'],
                            'valid_from_semester_id' => $currentSemester->id,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('unit.index');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withErrors(['error' => 'Failed to create unit: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function show(Unit $unit)
    {
        $unit->load([
            'curriculumUnits.curriculumVersion.program',
            'curriculumUnits.curriculumVersion.specialization',
            'prerequisiteGroups.conditions.requiredUnit',
            'syllabus.effectiveFromSemester',
            'syllabus.assessmentComponents.details'
        ]);

        // Transform the data to match frontend expectations
        $unit->prerequisite_groups = $unit->prerequisiteGroups->map(function ($group) {
            return $this->transformPrerequisiteGroup($group);
        });

        // Calculate assessment weight for each syllabus
        $unit->syllabus->each(function ($syllabus) {
            $syllabus->total_assessment_weight = $syllabus->assessmentComponents->sum('weight');
        });

        // Get all equivalent units (both direct and transitive relationships)
        $allEquivalentUnits = $this->relationshipService->getAllEquivalentUnits($unit);

        // Generate human-readable prerequisite descriptions
        $prerequisiteDescriptions = $unit->prerequisiteGroups->isNotEmpty()
            ? $this->prerequisiteLogicService->generatePrerequisiteDescription($unit->id)
            : null;

        return Inertia::render('units/Show', [
            'unit' => $unit,
            'equivalentUnits' => $allEquivalentUnits,
            'prerequisiteDescriptions' => $prerequisiteDescriptions,
            'relationshipStats' => [
                'prerequisite_count' => $unit->prerequisiteConditions()->where('type', 'prerequisite')->count(),
                'corequisite_count' => $unit->prerequisiteConditions()->where('type', 'co_requisite')->count(),
                'antirequisite_count' => $unit->prerequisiteConditions()->where('type', 'anti_requisite')->count(),
                'prerequisite_conditions_count' => $unit->prerequisiteConditions()->count(),
                'equivalent_count' => $allEquivalentUnits->count(),
                'curriculum_count' => $unit->curriculumUnits()->count(),
                'syllabus_count' => $unit->syllabus()->count(),
                'active_syllabus_count' => $unit->syllabus()->where('is_active', true)->count(),
            ],
            'canEdit' => $this->validationService->canEditUnit($unit),
            'canDelete' => $this->validationService->canDeleteUnit($unit)['allowed'],
        ]);
    }

    public function edit(Unit $unit)
    {
        // Check if unit can be edited
        if (!$this->validationService->canEditUnit($unit)) {
            return redirect()->route('unit.show', $unit)
                ->with('error', 'Unit cannot be edited due to active relationships.');
        }

        // Load prerequisite groups with conditions
        $unit->load(['prerequisiteGroups.conditions.requiredUnit']);

        // Transform the data to match frontend expectations
        $transformedGroups = $unit->prerequisiteGroups->map(function ($group) {
            return $this->transformPrerequisiteGroup($group);
        })->toArray();

        // Generate human-readable prerequisite descriptions
        $prerequisiteDescriptions = $unit->prerequisiteGroups->isNotEmpty()
            ? $this->prerequisiteLogicService->generatePrerequisiteDescription($unit->id)
            : null;

        // Prepare unit data with transformed prerequisite groups
        $unitData = $unit->toArray();
        $unitData['prerequisite_groups'] = $transformedGroups;

        return Inertia::render('units/Edit', [
            'unit' => $unitData,
            'prerequisiteDescriptions' => $prerequisiteDescriptions,
            'editRestrictions' => $this->validationService->getEditRestrictions($unit),
        ]);
    }

    /**
     * Transform prerequisite group for frontend consumption
     */
    private function transformPrerequisiteGroup($group)
    {
        // Handle both array and object forms of the data
        $groupData = is_array($group) ? $group : $group->toArray();

        return [
            'id' => $groupData['id'],
            'logic_operator' => $groupData['logic_operator'],
            'description' => $groupData['description'],
            'conditions' => collect($groupData['conditions'] ?? [])->map(function ($condition) {
                $conditionData = is_array($condition) ? $condition : $condition->toArray();

                return [
                    'id' => $conditionData['id'],
                    'type' => $conditionData['type'],
                    'required_unit_id' => $conditionData['required_unit_id'],
                    'unit' => $conditionData['required_unit'] ? [
                        'id' => $conditionData['required_unit']['id'],
                        'code' => $conditionData['required_unit']['code'],
                        'name' => $conditionData['required_unit']['name'],
                        'credit_points' => (float)$conditionData['required_unit']['credit_points'],
                    ] : null,
                    'required_credits' => $conditionData['required_credits'],
                    'free_text' => $conditionData['free_text'],
                ];
            })->toArray(),
        ];
    }

    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        try {
            DB::beginTransaction();

            $unit->update($request->validated());

            // Handle prerequisite groups if provided
            if ($request->has('prerequisite_groups') && is_array($request->prerequisite_groups)) {
                // Delete existing prerequisite groups and conditions
                $unit->prerequisiteGroups()->delete();

                // Create new prerequisite groups and conditions
                foreach ($request->prerequisite_groups as $groupData) {
                    $group = \App\Models\UnitPrerequisiteGroup::create([
                        'unit_id' => $unit->id,
                        'logic_operator' => $groupData['logic_operator'] ?? 'AND',
                        'description' => $groupData['description'] ?? null,
                    ]);

                    // Create conditions for this group
                    if (isset($groupData['conditions']) && is_array($groupData['conditions'])) {
                        foreach ($groupData['conditions'] as $conditionData) {
                            \App\Models\UnitPrerequisiteCondition::create([
                                'group_id' => $group->id,
                                'type' => $conditionData['type'] ?? 'prerequisite',
                                'required_unit_id' => $conditionData['required_unit_id'] ?? null,
                                'required_credits' => $conditionData['required_credits'] ?? null,
                                'free_text' => $conditionData['free_text'] ?? null,
                            ]);
                        }
                    }
                }
            } // Handle prerequisite expression if provided (for backwards compatibility)
            elseif ($request->has('prerequisite_expression') && !empty($request->prerequisite_expression)) {
                // Delete existing prerequisite groups and conditions
                $unit->prerequisiteGroups()->delete();

                // Create new prerequisite groups and conditions
                $result = $this->prerequisiteLogicService->parseAndStorePrerequisiteExpression(
                    $unit->id,
                    $request->prerequisite_expression,
                    $request->prerequisite_description ?? null
                );

                if (!$result['success']) {
                    DB::rollBack();
                    return redirect()->back()
                        ->withErrors(['prerequisite_expression' => $result['message']])
                        ->withInput();
                }
            }

            // Handle equivalent units if provided
            if ($request->has('equivalent_units') && is_array($request->equivalent_units)) {
                foreach ($request->equivalent_units as $equivalent) {
                    // For equivalent units, we need a semester - use current or latest semester
                    $currentSemester = \App\Models\Semester::orderBy('year', 'desc')
                        ->orderBy('term', 'desc')
                        ->first();

                    if ($currentSemester) {
                        \App\Models\EquivalentUnit::create([
                            'unit_id' => $unit->id,
                            'equivalent_unit_id' => $equivalent['equivalent_unit_id'],
                            'reason' => $equivalent['reason'],
                            'valid_from_semester_id' => $currentSemester->id,
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('unit.index');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withErrors(['error' => 'Failed to update unit: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Unit $unit)
    {
        // Comprehensive deletion validation
        $canDelete = $this->validationService->canDeleteUnit($unit);

        if (!$canDelete['allowed']) {
            return redirect()->route('unit.index')
                ->with('error', $canDelete['reason']);
        }

        try {
            DB::beginTransaction();

            // Delete prerequisite groups and conditions
            $unit->prerequisiteGroups()->delete();

            // Delete the unit (hard delete)
            $unit->delete();

            DB::commit();

            return redirect()->route('unit.index');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->route('unit.index')
                ->with('error', 'Failed to delete unit: ' . $e->getMessage());
        }
    }

    // API endpoints for AJAX calls
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:255',
            'exclude' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        // Convert comma-separated string to array
        $excludeIds = [];
        if (!empty($validated['exclude'])) {
            $excludeIds = array_filter(
                array_map('intval', explode(',', $validated['exclude'])),
                fn($id) => $id > 0
            );
        }

        $units = Unit::query()
            ->where(function ($query) use ($validated) {
                $query->where('code', 'like', "%{$validated['q']}%")
                    ->orWhere('name', 'like', "%{$validated['q']}%");
            })
            ->when($excludeIds, function ($query, $exclude) {
                $query->whereNotIn('id', $exclude);
            })
            ->limit($validated['limit'] ?? 10)
            ->get(['id', 'code', 'name', 'credit_points']);

        return response()->json($units);
    }

    public function validatePrerequisiteExpression(Request $request)
    {
        $request->validate([
            'expression' => 'required|string|max:1000',
        ]);

        try {
            // Validate the expression without storing it
            $result = $this->prerequisiteLogicService->parseAndStorePrerequisiteExpression(
                0, // Temporary unit ID
                $request->expression,
                null,
                true // Validation mode - don't store
            );

            return response()->json([
                'valid' => $result['success'],
                'message' => $result['message'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Error validating expression: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:20',
            'unit_id' => 'nullable|integer|exists:units,id',
        ]);

        $exists = Unit::where('code', $request->code)
            ->when($request->unit_id, function ($query, $unitId) {
                $query->where('id', '!=', $unitId);
            })
            ->exists();

        return response()->json([
            'valid' => !$exists,
            'message' => $exists ? 'Unit code already exists' : 'Unit code is available',
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'unit_ids' => 'required|array|min:1|max:100',
            'unit_ids.*' => 'integer|exists:units,id',
        ]);

        try {
            DB::beginTransaction();

            $units = Unit::whereIn('id', $validated['unit_ids'])->get();
            $deleted = [];
            $failed = [];

            foreach ($units as $unit) {
                $canDelete = $this->validationService->canDeleteUnit($unit);

                if ($canDelete['allowed']) {
                    $unit->delete();
                    $deleted[] = $unit->code;
                } else {
                    $failed[] = [
                        'code' => $unit->code,
                        'reason' => $canDelete['reason']
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'failed' => $failed,
                'message' => count($deleted) . ' units deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
