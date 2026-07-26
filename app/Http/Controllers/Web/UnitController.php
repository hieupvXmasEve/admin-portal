<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Constants\UnitRoutes;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Units\UnitExportController;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\PrerequisiteLogicService;
use App\Services\UnitRelationshipService;
use App\Services\UnitService;
use App\Services\UnitValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @deprecated Unit management routes are owned by Academic Catalog. Retained only
 * for the uncut import/export compatibility endpoints until their adapter cutover.
 */
class UnitController extends Controller
{
    public function __construct(
        private UnitValidationService $validationService,
        private UnitRelationshipService $relationshipService,
        private PrerequisiteLogicService $prerequisiteLogicService,
        private UnitService $unitService
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:code,name,credit_points,level,unit_type,base_fee,retake_fee,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
            'type' => 'nullable|string|in:general,egc,semi,ai,mkt,ba,cs,ee,me,fin',
            'level' => 'nullable|integer|min:0|max:9',
        ]);

        $units = Unit::query()
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($validated['type'] ?? null, function ($query, $type) {
                $query->where('unit_type', $type);
            })
            ->when(isset($validated['level']), function ($query) use ($validated) {
                $query->where('level', $validated['level']);
            })
            ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                $direction = $validated['direction'] ?? 'asc';
                $query->orderBy($sort, $direction);
            }, function ($query) {
                // Default sort when no sort specified
                $query->orderBy('created_at', 'desc');
            })
            ->withCount(['equivalentUnits', 'curriculumUnits', 'prerequisiteConditions', 'syllabusTemplates'])
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return Inertia::render('Units/Index', [
            'units' => $units,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'sort' => $validated['sort'] ?? '',
                'direction' => $validated['direction'] ?? 'asc',
                'per_page' => $validated['per_page'] ?? 15,
                'type' => $validated['type'] ?? 'all',
                'level' => isset($validated['level']) ? $validated['level'] : 'all',
            ],
            'statistics' => [
                'total_units' => Unit::count(),
                'units_with_prerequisites' => Unit::has('prerequisiteConditions')->count(),
                'units_with_equivalents' => Unit::has('equivalentUnits')->count(),
                'avg_credit_points' => Unit::avg('credit_points'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Units/Create', [
            'formDefaults' => [
                'code' => '',
                'name' => '',
                'credit_points' => 3,
            ],
        ]);
    }

    public function store(StoreUnitRequest $request)
    {
        try {
            $this->unitService->createUnit($request->validated());

            // Redirect to return URL if provided, otherwise to index
            $returnUrl = $request->input('return');
            if ($returnUrl && $this->isValidReturnUrl($returnUrl)) {
                return redirect($returnUrl)->with('success', 'Unit created successfully.');
            }

            return redirect()->route(UnitRoutes::INDEX)->with('success', 'Unit created successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to create unit: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function show(Unit $unit)
    {
        $unit->load([
            'curriculumUnits.curriculumVersion.program',
            'curriculumUnits.curriculumVersion.specialization',
            'curriculumUnits.semester',
            'prerequisiteGroups.conditions.requiredUnit',
            'syllabusTemplates.assessmentComponents',
        ]);

        // Transform the data to match frontend expectations
        $unit->prerequisite_groups = $unit->prerequisiteGroups->map(function ($group) {
            return $this->transformPrerequisiteGroup($group);
        });

        // Get all equivalent units (both direct and transitive relationships)
        $allEquivalentUnits = $this->relationshipService->getAllEquivalentUnits($unit);

        // Generate human-readable prerequisite descriptions
        $prerequisiteDescriptions = $unit->prerequisiteGroups->isNotEmpty()
            ? $this->prerequisiteLogicService->generatePrerequisiteDescription($unit->id)
            : null;

        return Inertia::render('Units/Show', [
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
                'syllabus_templates_count' => $unit->syllabusTemplates()->count(),
                'active_syllabus_templates_count' => $unit->syllabusTemplates()->where('is_active', true)->count(),
            ],
            //            'canEdit' => $this->validationService->canEditUnit($unit),
            'canEdit' => true,
            'canDelete' => $this->validationService->canDeleteUnit($unit)['allowed'],
        ]);
    }

    public function edit(Unit $unit)
    {
        // Check if unit can be edited
        // if (!$this->validationService->canEditUnit($unit)) {
        //     return redirect()->route(UnitRoutes::SHOW, $unit)
        //         ->with('error', 'Unit cannot be edited due to active relationships.');
        // }

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

        return Inertia::render('Units/Edit', [
            'unit' => $unitData,
            'prerequisiteDescriptions' => $prerequisiteDescriptions,
            // 'editRestrictions' => $this->validationService->getEditRestrictions($unit),
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
                        'credit_points' => (float) $conditionData['required_unit']['credit_points'],
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
            $this->unitService->updateUnit($unit, $request->validated());

            // Redirect to return URL if provided, otherwise to index
            $returnUrl = $request->input('return');
            if ($returnUrl && $this->isValidReturnUrl($returnUrl)) {
                return redirect($returnUrl)->with('success', 'Unit updated successfully.');
            }

            return redirect()->route(UnitRoutes::INDEX)->with('success', 'Unit updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Failed to update unit: '.$e->getMessage()])
                ->withInput();
        }
    }

    public function destroy(Request $request, Unit $unit)
    {
        $canDelete = $this->validationService->canDeleteUnit($unit);

        if (! $canDelete['allowed']) {
            return redirect()->route(UnitRoutes::INDEX)
                ->with('error', $canDelete['reason']);
        }

        try {
            $this->unitService->deleteUnit($unit);

            // Redirect to return URL if provided, otherwise to index
            $returnUrl = $request->input('return');
            if ($returnUrl && $this->isValidReturnUrl($returnUrl)) {
                return redirect($returnUrl)->with('success', 'Unit deleted successfully.');
            }

            return redirect()->route(UnitRoutes::INDEX)->with('success', 'Unit deleted successfully.');
        } catch (\Exception $e) {
            // Always redirect to return URL or index on error
            $returnUrl = $request->input('return');
            if ($returnUrl && $this->isValidReturnUrl($returnUrl)) {
                return redirect($returnUrl)->with('error', 'Failed to delete unit: '.$e->getMessage());
            }

            return redirect()->route(UnitRoutes::INDEX)
                ->with('error', 'Failed to delete unit: '.$e->getMessage());
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
        if (! empty($validated['exclude'])) {
            $excludeIds = array_filter(
                array_map('intval', explode(',', $validated['exclude'])),
                fn ($id) => $id > 0
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

        return response()->json([
            'success' => true,
            'data' => $units,
            'message' => 'Units retrieved successfully',
        ]);
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
                'message' => 'Error validating expression: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validate if return URL is safe for redirection
     */
    private function isValidReturnUrl(?string $url): bool
    {
        if (! $url) {
            return false;
        }

        // Parse URL to get components
        $parsed = parse_url($url);

        // Only allow relative URLs or URLs from the same domain
        if (isset($parsed['host'])) {
            $currentHost = parse_url(config('app.url'), PHP_URL_HOST);

            return $parsed['host'] === $currentHost;
        }

        // Allow relative paths that start with /
        return str_starts_with($url, '/');
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
            'success' => true,
            'data' => [
                'valid' => ! $exists,
                'message' => $exists ? 'Unit code already exists' : 'Unit code is available',
            ],
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
                        'reason' => $canDelete['reason'],
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'failed' => $failed,
                'message' => count($deleted).' units deleted successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function exportExcel(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:code,name,credit_points,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'credit_points_from' => 'nullable|numeric|min:0',
            'credit_points_to' => 'nullable|numeric|min:0|gte:credit_points_from',
            'has_prerequisites' => 'nullable|boolean',
            'has_equivalents' => 'nullable|boolean',
            'in_curricula' => 'nullable|boolean',
        ]);

        // Use the UnitExportController
        return app(UnitExportController::class)
            ->exportExcelWithCurrentFilters($request);
    }
}
