<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Constants\CurriculumRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\DuplicateCurriculumVersionRequest;
use App\Http\Requests\StoreCurriculumVersionRequest;
use App\Http\Requests\UpdateCurriculumVersionRequest;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CurriculumVersionController extends Controller
{
    /**
     * Display a listing of curriculum versions with global management features.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'program_id' => 'nullable|exists:programs,id',
            'specialization_id' => 'nullable|exists:specializations,id',
            'sort' => 'nullable|string|in:version_code,program_name,specialization_name,created_at,units_count',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        // Base query with relationships
        $query = CurriculumVersion::query()
            ->with(['program', 'specialization', 'effectiveFromSemester'])
            ->withCount('curriculumUnits');

        // Apply search filters
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('version_code', 'like', "%{$request->search}%")
                    ->orWhere('notes', 'like', "%{$request->search}%")
                    ->orWhereHas('program', fn($subQ) => $subQ->where('name', 'like', "%{$request->search}%"))
                    ->orWhereHas('specialization', fn($subQ) => $subQ->where('name', 'like', "%{$request->search}%"));
            });
        }

        // Apply filters
        if ($request->program_id) {
            $query->where('program_id', $request->program_id);
        }

        if ($request->specialization_id) {
            $query->where('specialization_id', $request->specialization_id);
        }

        // Apply sorting
        $sortField = $request->sort ?? 'created_at';
        $sortDirection = $request->direction ?? 'desc';

        switch ($sortField) {
            case 'program_name':
                $query->join('programs', 'curriculum_versions.program_id', '=', 'programs.id')
                    ->orderBy('programs.name', $sortDirection)
                    ->select('curriculum_versions.*');
                break;
            case 'specialization_name':
                $query->leftJoin('specializations', 'curriculum_versions.specialization_id', '=', 'specializations.id')
                    ->orderBy('specializations.name', $sortDirection)
                    ->select('curriculum_versions.*');
                break;
            case 'units_count':
                $query->orderBy('curriculum_units_count', $sortDirection);
                break;
            default:
                $query->orderBy($sortField, $sortDirection);
        }

        // Pagination
        $perPage = $request->per_page ?? 15;
        $curriculumVersions = $query->paginate($perPage)->withQueryString();

        // Calculate statistics
        $statistics = $this->calculateGlobalStatistics($request);

        return Inertia::render('curriculum-versions/Index', [
            'curriculumVersions' => $curriculumVersions,
            'statistics' => $statistics,
            'filters' => $request->only(['search', 'program_id', 'specialization_id', 'sort', 'direction', 'per_page']),
            'programs' => Program::select('id', 'name', 'code')->orderBy('name')->get(),
            'specializations' => Specialization::select('id', 'name', 'code', 'program_id')->orderBy('name')->get(),
            'semesters' => Semester::select('id', 'name', 'code')->orderBy('name')->get(),
        ]);
    }

    /**
     * Calculate overview statistics for a single curriculum version.
     */
    private function calculateOverviewStatistics(CurriculumVersion $curriculumVersion): array
    {
        $unitsCount = $curriculumVersion->curriculumUnits()->count();
        $totalCreditPoints = $curriculumVersion->curriculumUnits()
            ->join('units', 'curriculum_units.unit_id', '=', 'units.id')
            ->sum('units.credit_points');

        $byYearLevel = $curriculumVersion->curriculumUnits()
            ->selectRaw('year_level, COUNT(*) as count')
            ->whereNotNull('year_level')
            ->groupBy('year_level')
            ->pluck('count', 'year_level')
            ->toArray();

        $byUnitScope = $curriculumVersion->curriculumUnits()
            ->selectRaw('unit_scope, COUNT(*) as count')
            ->whereNotNull('unit_scope')
            ->groupBy('unit_scope')
            ->pluck('count', 'unit_scope')
            ->toArray();

        return [
            'totalUnits' => $unitsCount,
            'totalCreditPoints' => $totalCreditPoints,
            'byYearLevel' => $byYearLevel,
            'byUnitScope' => $byUnitScope,
        ];
    }

    /**
     * Calculate units statistics for the Units tab.
     */
    private function calculateUnitsStatistics(CurriculumVersion $curriculumVersion): array
    {
        $totalUnits = $curriculumVersion->curriculumUnits()->count();
        $totalCreditPoints = $curriculumVersion->curriculumUnits()
            ->join('units', 'curriculum_units.unit_id', '=', 'units.id')
            ->sum('units.credit_points');

        $byYearLevel = $curriculumVersion->curriculumUnits()
            ->selectRaw('year_level, COUNT(*) as count, SUM(units.credit_points) as total_credits')
            ->join('units', 'curriculum_units.unit_id', '=', 'units.id')
            ->whereNotNull('year_level')
            ->groupBy('year_level')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->year_level => [
                    'count' => $item->count,
                    'total_credits' => $item->total_credits,
                ]];
            })
            ->toArray();

        $byUnitScope = $curriculumVersion->curriculumUnits()
            ->selectRaw('unit_scope, COUNT(*) as count')
            ->whereNotNull('unit_scope')
            ->groupBy('unit_scope')
            ->pluck('count', 'unit_scope')
            ->toArray();

        return [
            'totalUnits' => $totalUnits,
            'totalCreditPoints' => $totalCreditPoints,
            'byYearLevel' => $byYearLevel,
            'byUnitScope' => $byUnitScope,
        ];
    }

    /**
     * Calculate student statistics for the Students tab.
     */
    private function calculateStudentStatistics(CurriculumVersion $curriculumVersion): array
    {
        // Get student counts by academic status
        $statusCounts = DB::table('students')
            ->where('curriculum_version_id', $curriculumVersion->id)
            ->whereNull('deleted_at') // Exclude soft deleted students
            ->groupBy('academic_status')
            ->selectRaw('academic_status, COUNT(*) as count')
            ->pluck('count', 'academic_status')
            ->toArray();

        // Ensure all expected statuses are present
        $counts = [
            'active' => $statusCounts['active'] ?? 0,
            'inactive' => $statusCounts['inactive'] ?? 0,
            'graduated' => $statusCounts['graduated'] ?? 0,
            'suspended' => $statusCounts['suspended'] ?? 0,
            'withdrawn' => $statusCounts['withdrawn'] ?? 0,
        ];

        // Calculate additional statistics
        $total = array_sum($counts);

        // Get enrollment trends (last 6 months)
        $enrollmentTrends = DB::table('students')
            ->where('curriculum_version_id', $curriculumVersion->id)
            ->whereNull('deleted_at')
            ->where('admission_date', '>=', now()->subMonths(6))
            ->selectRaw('YEAR(admission_date) as year, MONTH(admission_date) as month, COUNT(*) as count')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->year . '-' . str_pad((string) $item->month, 2, '0', STR_PAD_LEFT),
                    'count' => $item->count,
                ];
            })
            ->toArray();

        // Get expected graduation years for active students
        $graduationProjections = DB::table('students')
            ->where('curriculum_version_id', $curriculumVersion->id)
            ->where('academic_status', 'active')
            ->whereNull('deleted_at')
            ->whereNotNull('expected_graduation_date')
            ->selectRaw('YEAR(expected_graduation_date) as year, COUNT(*) as count')
            ->groupBy('year')
            ->orderBy('year')
            ->pluck('count', 'year')
            ->toArray();

        return [
            'counts' => $counts,
            'total' => $total,
            'enrollment_trends' => $enrollmentTrends,
            'graduation_projections' => $graduationProjections,
            'statistics' => [
                'active_percentage' => $total > 0 ? round(($counts['active'] / $total) * 100, 2) : 0,
                'graduation_rate' => $total > 0 ? round(($counts['graduated'] / $total) * 100, 2) : 0,
                'retention_rate' => $total > 0 ? round((($counts['active'] + $counts['inactive']) / $total) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Calculate deployment statistics for the Deployments tab.
     */
    private function calculateDeploymentStatistics(CurriculumVersion $curriculumVersion, Request $request): array
    {
        // Get all semesters with deployments
        $semesters = Semester::select('id', 'name', 'code')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($semester) use ($curriculumVersion) {
                // TODO: Calculate actual deployment stats when CourseOffering model is available
                return [
                    'id' => $semester->id,
                    'name' => $semester->name,
                    'opened_units_count' => 0,
                    'classes_count' => 0,
                    'students_count' => 0,
                ];
            });

        // Get curriculum units with deployment status
        $curriculumUnits = $curriculumVersion->curriculumUnits()
            ->with(['unit:id,code,name,credit_points'])
            ->get();

        $openedUnits = collect([]); // Placeholder for opened units
        $neverOpenedUnits = $curriculumUnits->map(function ($curriculumUnit) {
            return [
                'unit_code' => $curriculumUnit->unit->code,
                'unit_name' => $curriculumUnit->unit->name,
                'year_level' => $curriculumUnit->year_level,
                'semester_number' => $curriculumUnit->semester_number,
                'credit_points' => $curriculumUnit->unit->credit_points,
            ];
        });

        return [
            'semesters' => $semesters,
            'opened_units' => [
                'items' => $openedUnits,
                'meta' => ['total' => $openedUnits->count()],
            ],
            'never_opened_units' => [
                'items' => $neverOpenedUnits,
                'meta' => ['total' => $neverOpenedUnits->count()],
            ],
        ];
    }

    /**
     * Calculate global statistics for curriculum versions.
     */
    private function calculateGlobalStatistics(Request $request): array
    {
        // Base query for statistics
        $baseQuery = CurriculumVersion::query();

        // Apply same filters as main query
        if ($request->search) {
            $baseQuery->where(function ($q) use ($request) {
                $q->where('version_code', 'like', "%{$request->search}%")
                    ->orWhere('notes', 'like', "%{$request->search}%")
                    ->orWhereHas('program', fn($subQ) => $subQ->where('name', 'like', "%{$request->search}%"))
                    ->orWhereHas('specialization', fn($subQ) => $subQ->where('name', 'like', "%{$request->search}%"));
            });
        }

        if ($request->program_id) {
            $baseQuery->where('program_id', $request->program_id);
        }

        if ($request->specialization_id) {
            $baseQuery->where('specialization_id', $request->specialization_id);
        }

        // Total curriculum versions
        $totalVersions = $baseQuery->count();

        // Active vs Inactive (versions with curriculum units vs without)
        $activeVersions = $baseQuery->whereHas('curriculumUnits')->count();
        $inactiveVersions = $totalVersions - $activeVersions;

        // By year distribution
        $byYear = $baseQuery->selectRaw('YEAR(created_at) as year, COUNT(*) as count')
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->pluck('count', 'year')
            ->toArray();

        // By program distribution
        $byProgram = $baseQuery->with('program')
            ->get()
            ->groupBy('program.name')
            ->map(fn($versions) => $versions->count())
            ->toArray();

        return [
            'total_curriculum_versions' => $totalVersions,
            'active_versions' => $activeVersions,
            'inactive_versions' => $inactiveVersions,
            'by_year' => $byYear,
            'by_program' => $byProgram,
        ];
    }

    /**
     * Export curriculum versions to Excel with applied filters.
     */
    public function exportFiltered(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'program_id' => 'nullable|exists:programs,id',
            'specialization_id' => 'nullable|exists:specializations,id',
        ]);

        try {
            // Build query with same filters as index
            $query = CurriculumVersion::query()
                ->with(['program', 'specialization', 'effectiveFromSemester', 'curriculumUnits.unit'])
                ->withCount('curriculumUnits');

            if ($request->search) {
                $query->where(function ($q) use ($request) {
                    $q->where('version_code', 'like', "%{$request->search}%")
                        ->orWhere('notes', 'like', "%{$request->search}%")
                        ->orWhereHas('program', fn($subQ) => $subQ->where('name', 'like', "%{$request->search}%"))
                        ->orWhereHas('specialization', fn($subQ) => $subQ->where('name', 'like', "%{$request->search}%"));
                });
            }

            if ($request->program_id) {
                $query->where('program_id', $request->program_id);
            }

            if ($request->specialization_id) {
                $query->where('specialization_id', $request->specialization_id);
            }

            $curriculumVersions = $query->orderBy('created_at', 'desc')->get();

            // Generate Excel file
            $filename = 'curriculum_versions_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

            // You would implement Excel export logic here
            // For now, return a JSON response
            return response()->json([
                'success' => true,
                'message' => 'Export would be generated',
                'filename' => $filename,
                'total_records' => $curriculumVersions->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Curriculum versions export failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk operations for curriculum versions.
     */
    public function bulkOperations(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:delete,export',
            'curriculum_version_ids' => 'required|array|min:1|max:100',
            'curriculum_version_ids.*' => 'integer|exists:curriculum_versions,id',
        ]);

        try {
            DB::beginTransaction();

            $curriculumVersions = CurriculumVersion::whereIn('id', $validated['curriculum_version_ids'])->get();

            switch ($validated['action']) {
                case 'delete':
                    return $this->bulkDelete($request);

                case 'export':
                    return $this->bulkExport($curriculumVersions);

                default:
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid bulk action specified.',
                    ], 400);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk operation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Bulk operation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk export selected curriculum versions.
     */
    private function bulkExport($curriculumVersions)
    {
        try {
            $filename = 'selected_curriculum_versions_' . now()->format('Y_m_d_H_i_s') . '.xlsx';

            // Export logic would go here

            return response()->json([
                'success' => true,
                'message' => 'Selected curriculum versions exported successfully.',
                'filename' => $filename,
                'total_records' => $curriculumVersions->count(),
            ]);
        } catch (\Exception $e) {
            throw new \Exception('Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new curriculum version.
     */
    public function create(): Response
    {
        return Inertia::render('curriculum-versions/Create', [
            'programs' => Program::with('specializations')->get(),
            'specializations' => Specialization::select('id', 'name', 'code', 'program_id')->orderBy('name')->get(),
            'semesters' => Semester::select('id', 'name', 'code')->get(),
        ]);
    }

    /**
     * Store a newly created curriculum version.
     */
    public function store(StoreCurriculumVersionRequest $request): RedirectResponse
    {
        $curriculumVersion = CurriculumVersion::create($request->validated());

        return redirect()->route(CurriculumRoutes::VERSION_SHOW, $curriculumVersion)
            ->with('success', 'Curriculum version created successfully');
    }

    /**
     * Display the specified curriculum version - redirects to overview tab.
     */
    public function show(CurriculumVersion $curriculumVersion): RedirectResponse
    {
        // Redirect to the overview tab for the new tab-based layout
        return redirect()->route(CurriculumRoutes::VERSION_SUMMARY_OVERVIEW, $curriculumVersion);
    }

    /**
     * Show the form for editing the specified curriculum version.
     */
    public function edit(CurriculumVersion $curriculumVersion): Response
    {
        $curriculumVersion->load(['program', 'specialization', 'effectiveFromSemester']);

        return Inertia::render('curriculum-versions/Edit', [
            'curriculumVersion' => $curriculumVersion,
            'programs' => Program::select('id', 'name', 'code')->orderBy('name')->get(),
            'specializations' => Specialization::select('id', 'name', 'code', 'program_id')->orderBy('name')->get(),
            'semesters' => Semester::select('id', 'name', 'code')->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified curriculum version.
     */
    public function update(UpdateCurriculumVersionRequest $request, CurriculumVersion $curriculumVersion): RedirectResponse
    {
        $curriculumVersion->update($request->validated());

        return redirect()->route(CurriculumRoutes::VERSION_INDEX, $curriculumVersion)
            ->with('success', 'Curriculum version updated successfully');
    }

    /**
     * Remove the specified curriculum version.
     */
    public function destroy(CurriculumVersion $curriculumVersion): RedirectResponse
    {
        $curriculumVersion->delete();

        return redirect()->route(CurriculumRoutes::VERSION_INDEX)
            ->with('success', 'Curriculum version deleted successfully');
    }

    /**
     * Duplicate the specified curriculum version with a new version code.
     */
    public function duplicate(DuplicateCurriculumVersionRequest $request, CurriculumVersion $curriculumVersion): RedirectResponse
    {
        try {
            DB::beginTransaction();

            // Create the duplicate curriculum version
            $duplicateData = [
                'program_id' => $curriculumVersion->program_id,
                'specialization_id' => $curriculumVersion->specialization_id,
                'version_code' => $request->validated()['version_code'],
                'semester_id' => $curriculumVersion->semester_id,
                'notes' => $request->validated()['notes'] ?? $curriculumVersion->notes,
            ];

            $duplicatedVersion = CurriculumVersion::create($duplicateData);

            // If requested, duplicate curriculum units
            if ($request->validated()['include_curriculum_units']) {
                $curriculumUnits = $curriculumVersion->curriculumUnits()->get();
                
                foreach ($curriculumUnits as $unit) {
                    $duplicatedVersion->curriculumUnits()->create([
                        'unit_id' => $unit->unit_id,
                        'year_level' => $unit->year_level,
                        'semester_number' => $unit->semester_number,
                        'unit_scope' => $unit->unit_scope,
                        'is_compulsory' => $unit->is_compulsory,
                        'is_prerequisite_flexible' => $unit->is_prerequisite_flexible,
                        'note' => $unit->note,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route(CurriculumRoutes::VERSION_SUMMARY_OVERVIEW, $duplicatedVersion)
                ->with('success', "Curriculum version duplicated successfully as '{$duplicatedVersion->version_code}'");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Curriculum version duplication failed: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'Failed to duplicate curriculum version. Please try again.');
        }
    }

    /**
     * Summary Overview tab - Basic curriculum version information and statistics.
     */
    public function summaryOverview(CurriculumVersion $curriculumVersion): Response
    {
        // Load minimal relationships for overview
        $curriculumVersion->load([
            'program:id,name,code',
            'specialization:id,name,code',
            'effectiveFromSemester:id,name,code',
        ]);

        // Calculate overview statistics
        $stats = $this->calculateOverviewStatistics($curriculumVersion);

        return Inertia::render('curriculum-versions/summary/Overview', [
            'curriculumVersion' => [
                'id' => $curriculumVersion->id,
                'version_code' => $curriculumVersion->version_code,
                'notes' => $curriculumVersion->notes,
                'created_at' => $curriculumVersion->created_at,
                'program' => $curriculumVersion->program,
                'specialization' => $curriculumVersion->specialization,
                'effective_from_semester' => $curriculumVersion->effectiveFromSemester,
            ],
            'data' => $stats,
            'meta' => [
                'lastUpdatedAt' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Summary Units tab - Paginated curriculum units with search and filters.
     */
    public function summaryUnits(Request $request, CurriculumVersion $curriculumVersion): Response
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'unit_scope' => 'nullable|string|in:program,common,specialization_specific,cross_program',
            'year_level' => 'nullable|integer|min:1|max:5',
            'semester_number' => 'nullable|integer|min:1|max:9',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:50',
        ]);

        // Load minimal curriculum version data
        $curriculumVersion->load([
            'program:id,name,code',
            'specialization:id,name,code',
            'effectiveFromSemester:id,name,code',
        ]);

        // Query curriculum units with filters
        $query = $curriculumVersion->curriculumUnits()
            ->with(['unit:id,code,name,credit_points']);

        // Apply search
        if ($request->search) {
            $query->whereHas('unit', function ($q) use ($request) {
                $q->where('code', 'like', "%{$request->search}%")
                    ->orWhere('name', 'like', "%{$request->search}%");
            });
        }

        // Apply filters
        if ($request->unit_scope) {
            $query->where('unit_scope', $request->unit_scope);
        }

        if ($request->year_level) {
            $query->where('year_level', $request->year_level);
        }

        if ($request->semester_number) {
            $query->where('semester_number', $request->semester_number);
        }

        $units = $query->orderBy('year_level')
            ->orderBy('semester_number')
            ->orderBy('created_at')
            ->get();

        // Calculate units statistics
        $unitsStats = $this->calculateUnitsStatistics($curriculumVersion);

        return Inertia::render('curriculum-versions/summary/Units', [
            'curriculumVersion' => [
                'id' => $curriculumVersion->id,
                'version_code' => $curriculumVersion->version_code,
                'notes' => $curriculumVersion->notes,
                'created_at' => $curriculumVersion->created_at,
                'program' => $curriculumVersion->program,
                'specialization' => $curriculumVersion->specialization,
                'effective_from_semester' => $curriculumVersion->effectiveFromSemester,
                'semester_id' => $curriculumVersion->semester_id
            ],
            'data' => [
                'units' => $units,
                'stats' => $unitsStats,
            ],
            'meta' => [
                'filters' => $request->only(['search', 'unit_scope', 'year_level', 'semester_number']),
            ],
            'units' => Unit::select('id', 'code', 'name', 'credit_points')->orderBy('code')->get(),
        ]);
    }

    /**
     * Summary Students tab - Student enrollment statistics and breakdowns.
     */
    public function summaryStudents(CurriculumVersion $curriculumVersion): Response
    {
        // Load minimal curriculum version data
        $curriculumVersion->load([
            'program:id,name,code',
            'specialization:id,name,code',
            'effectiveFromSemester:id,name,code',
        ]);

        // Calculate student statistics
        $studentStats = $this->calculateStudentStatistics($curriculumVersion);

        return Inertia::render('curriculum-versions/summary/Students', [
            'curriculumVersion' => [
                'id' => $curriculumVersion->id,
                'version_code' => $curriculumVersion->version_code,
                'program' => $curriculumVersion->program,
                'specialization' => $curriculumVersion->specialization,
                'effective_from_semester' => $curriculumVersion->effectiveFromSemester,
            ],
            'data' => $studentStats,
            'meta' => [
                'lastUpdatedAt' => now()->toISOString(),
            ],
            'links' => [
                'drillDown' => [
                    'active' => route('students.index', ['curriculum_version_id' => $curriculumVersion->id, 'academic_status' => 'active']),
                    'inactive' => route('students.index', ['curriculum_version_id' => $curriculumVersion->id, 'academic_status' => 'inactive']),
                    'graduated' => route('students.index', ['curriculum_version_id' => $curriculumVersion->id, 'academic_status' => 'graduated']),
                    'suspended' => route('students.index', ['curriculum_version_id' => $curriculumVersion->id, 'academic_status' => 'suspended']),
                    'withdrawn' => route('students.index', ['curriculum_version_id' => $curriculumVersion->id, 'academic_status' => 'withdrawn']),
                ],
            ],
        ]);
    }

    /**
     * Summary Deployments tab - Track course offerings and deployment status.
     */
    public function summaryDeployments(Request $request, CurriculumVersion $curriculumVersion): Response
    {
        $request->validate([
            'semester_id' => 'nullable|exists:semesters,id',
            'unit_scope' => 'nullable|string|in:program,common,specialization_specific,cross_program',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:50',
        ]);

        // Load minimal curriculum version data
        $curriculumVersion->load([
            'program:id,name,code',
            'specialization:id,name,code',
            'effectiveFromSemester:id,name,code',
        ]);

        // Calculate deployment statistics
        $deploymentStats = $this->calculateDeploymentStatistics($curriculumVersion, $request);

        return Inertia::render('curriculum-versions/summary/Deployments', [
            'curriculumVersion' => [
                'id' => $curriculumVersion->id,
                'version_code' => $curriculumVersion->version_code,
                'program' => $curriculumVersion->program,
                'specialization' => $curriculumVersion->specialization,
                'effective_from_semester' => $curriculumVersion->effectiveFromSemester,
            ],
            'data' => $deploymentStats,
            'meta' => [
                'filters' => $request->only(['semester_id', 'unit_scope']),
                'currentSemester' => Semester::where('is_active', true)->first(),
            ],
        ]);
    }

    /**
     * Get elective management data for a curriculum version.
     */
    public function electiveManagement(CurriculumVersion $curriculumVersion): Response
    {
        $curriculumVersion->load(['program', 'specialization']);

        $electiveSlots = $curriculumVersion->getElectiveSlots();
        $availableElectives = $curriculumVersion->getElectiveUnitsByCategory();

        return Inertia::render('curriculum-versions/ElectiveManagement', [
            'curriculumVersion' => $curriculumVersion,
            'electiveSlots' => $electiveSlots->map(function ($slot) {
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
            }),
            'availableElectives' => [
                'same_program_other_specializations' => [
                    'label' => 'Units from other specializations in the same program',
                    'units' => $availableElectives['same_program_other_specializations']->take(20),
                    'total_count' => $availableElectives['same_program_other_specializations']->count(),
                ],
                'cross_program_electives' => [
                    'label' => 'Units from other programs',
                    'units' => $availableElectives['cross_program_electives']->take(20),
                    'total_count' => $availableElectives['cross_program_electives']->count(),
                ],
                'general_electives' => [
                    'label' => 'General elective units',
                    'units' => $availableElectives['general_electives']->take(20),
                    'total_count' => $availableElectives['general_electives']->count(),
                ],
            ],
        ]);
    }

    public function getSpecializationsByProgram(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
        ]);

        $specializations = Specialization::where('program_id', $validated['program_id'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($specializations);
    }

    /**
     * Get curriculum versions by program and specialization (AJAX)
     */
    public function getCurriculumVersionsByProgramSpecialization(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'specialization_id' => 'nullable|exists:specializations,id',
        ]);

        $query = CurriculumVersion::where('program_id', $validated['program_id']);

        if ($validated['specialization_id']) {
            $query->where('specialization_id', $validated['specialization_id']);
        } else {
            $query->whereNull('specialization_id');
        }

        $versions = $query->orderBy('created_at', 'desc')
            ->get(['id', 'version_code'])
            ->map(function ($version) {
                return [
                    'id' => $version->id,
                    'version_code' => $version->version_code,
                ];
            })
            ->toArray();

        return response()->json($versions);
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'curriculum_version_ids' => 'required|array|min:1|max:100',
            'curriculum_version_ids.*' => 'integer|exists:curriculum_versions,id',
        ]);

        try {
            DB::beginTransaction();

            $curriculumVersions = CurriculumVersion::whereIn('id', $validated['curriculum_version_ids'])->get();
            $deleted = [];
            $failed = [];

            foreach ($curriculumVersions as $curriculumVersion) {
                if ($curriculumVersion->curriculumUnits()->count() > 0) {
                    $failed[] = [
                        'version_code' => $curriculumVersion->version_code,
                        'reason' => 'Has existing curriculum units',
                    ];
                } else {
                    $curriculumVersion->delete();
                    $deleted[] = $curriculumVersion->version_code;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
                'failed' => $failed,
                'message' => count($deleted) . ' curriculum versions deleted successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function apiStore(StoreCurriculumVersionRequest $request)
    {
        try {
            DB::transaction(function () use ($request, &$curriculumVersion) {
                $curriculumVersion = CurriculumVersion::create($request->validated());
            });

            // Load relationships for the response
            $curriculumVersion->load([
                'program:id,name',
                'specialization:id,name',
                'effectiveFromSemester:id,name,code',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Curriculum version created successfully.',
                'data' => $curriculumVersion,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Curriculum version creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create curriculum version. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function apiDestroy(CurriculumVersion $curriculumVersion)
    {
        try {
            // Check if curriculum version has any curriculum units
            if ($curriculumVersion->curriculumUnits()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete curriculum version with existing curriculum units.',
                    'error' => 'HAS_CURRICULUM_UNITS',
                ], 400);
            }

            DB::transaction(function () use ($curriculumVersion) {
                $curriculumVersion->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Curriculum version deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Curriculum version deletion failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete curriculum version. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
