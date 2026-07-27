<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web\Canvas;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Delivery\Http\Requests\Canvas\CanvasCourseMappingRequest;
use App\Modules\Academic\Delivery\Http\Resources\Canvas\CanvasCourseResource;
use App\Models\CanvasCourseMapping;
use App\Models\CanvasIntegration;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Support\Canvas\CanvasSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CanvasCourseController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'canvas_course_code',
        'canvas_course_name',
        'canvas_course_id',
        'sync_status',
        'last_synced_at',
        'created_at',
        'updated_at',
    ];

    public function __construct(
        private CanvasSyncService $syncService
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sync_status' => ['nullable', 'string', 'in:pending,mapped,ignored'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $requestedSort = $request->input('sort');
        $sort = is_string($requestedSort) && in_array($requestedSort, self::SORTABLE_COLUMNS, true)
            ? $requestedSort
            : 'created_at';

        $requestedDirection = $validated['direction'] ?? 'desc';
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'desc';

        $filters = [
            'search' => $validated['search'] ?? null,
            'sync_status' => $validated['sync_status'] ?? null,
            'semester_id' => isset($validated['semester_id']) ? (int) $validated['semester_id'] : null,
            'unit_id' => isset($validated['unit_id']) ? (int) $validated['unit_id'] : null,
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : 15,
        ];

        // All campuses share one Canvas instance
        $integration = CanvasIntegration::where('is_active', true)->first();

        if (! $integration) {
            // Return empty pagination structure
            $emptyPagination = new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                $filters['per_page'],
                1
            );

            return Inertia::render('Admin/Canvas/Courses/Index', [
                'mappings' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $filters['per_page'],
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                    'prev_page_url' => null,
                    'next_page_url' => null,
                    'links' => [],
                ],
                'filters' => $filters,
                'integration' => null,
                'semesters' => $this->getSemesterOptions(),
                'units' => $this->getUnitOptions(),
            ]);
        }

        $query = CanvasCourseMapping::with(['courseOffering.semester', 'courseOffering.unit', 'canvasIntegration'])
            ->where('canvas_integration_id', $integration->id);

        // Apply filters
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('canvas_course_code', 'like', "%{$search}%")
                    ->orWhere('canvas_course_name', 'like', "%{$search}%")
                    ->orWhere('canvas_course_id', 'like', "%{$search}%")
                    ->orWhereHas('courseOffering', function ($offeringQuery) use ($search) {
                        $offeringQuery
                            ->where('section_code', 'like', "%{$search}%")
                            ->orWhereHas('unit', function ($unitQuery) use ($search) {
                                $unitQuery
                                    ->where('code', 'like', "%{$search}%")
                                    ->orWhere('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        if (! empty($filters['sync_status'])) {
            $query->where('sync_status', $filters['sync_status']);
        }

        if (! empty($filters['semester_id']) || ! empty($filters['unit_id'])) {
            $query->where('sync_status', 'mapped')
                ->whereNotNull('course_offering_id');

            if (! empty($filters['semester_id'])) {
                $semesterId = $filters['semester_id'];
                $query->whereHas('courseOffering', fn ($offeringQuery) => $offeringQuery->where('semester_id', $semesterId));
            }

            if (! empty($filters['unit_id'])) {
                $unitId = $filters['unit_id'];
                $query->whereHas('courseOffering', fn ($offeringQuery) => $offeringQuery->where('unit_id', $unitId));
            }
        }

        // Sorting
        $query->orderBy($filters['sort'], $filters['direction'])->orderBy('id', 'desc');

        // Pagination
        $mappings = $query->paginate($filters['per_page'])->withQueryString();

        return Inertia::render('Admin/Canvas/Courses/Index', [
            'mappings' => [
                'data' => CanvasCourseResource::collection($mappings->items())->toArray(request()),
                'current_page' => $mappings->currentPage(),
                'last_page' => $mappings->lastPage(),
                'per_page' => $mappings->perPage(),
                'total' => $mappings->total(),
                'from' => $mappings->firstItem(),
                'to' => $mappings->lastItem(),
                'prev_page_url' => $mappings->previousPageUrl(),
                'next_page_url' => $mappings->nextPageUrl(),
                'links' => $mappings->linkCollection()->toArray(),
            ],
            'filters' => $filters,
            'integration' => [
                'id' => $integration->id,
                'canvas_url' => $integration->canvas_url,
                'has_token' => $integration->hasValidToken(),
                'has_refresh_token' => $integration->canRefreshToken(),
                'sync_status' => $integration->sync_status,
                'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
            ],
            'semesters' => $this->getSemesterOptions(),
            'units' => $this->getUnitOptions(),
        ]);
    }

    public function mapCourse(CanvasCourseMappingRequest $request): RedirectResponse
    {
        try {
            $mapping = CanvasCourseMapping::findOrFail($request->mapping_id);

            $this->syncService->mapCanvasCourse($mapping, $request->course_offering_id);

            return back()->with('success', 'Canvas course mapped successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to map course: '.$e->getMessage()]);
        }
    }

    public function unmapCourse(CanvasCourseMapping $mapping): RedirectResponse
    {
        try {
            $this->syncService->unmapCanvasCourse($mapping);

            return back()->with('success', 'Canvas course unmapped successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to unmap course: '.$e->getMessage()]);
        }
    }

    public function ignoreCourse(CanvasCourseMapping $mapping): RedirectResponse
    {
        try {
            $this->syncService->ignoreCanvasCourse($mapping);

            return back()->with('success', 'Canvas course marked as ignored');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to ignore course: '.$e->getMessage()]);
        }
    }

    public function getAvailableCourseOfferings(Request $request): JsonResponse
    {
        $campusId = session('current_campus_id');
        $search = $request->input('search', '');
        $semesterId = $request->integer('semester_id');

        $offerings = CourseOffering::with(['unit', 'semester'])
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->when($semesterId, function ($q) use ($semesterId) {
                $q->where('semester_id', $semesterId);
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where('section_code', 'like', "%{$search}%")
                        ->orWhereHas('unit', function ($unitQuery) use ($search) {
                            $unitQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('semester_id')
            ->limit(50)
            ->get()
            ->map(function ($offering) {
                return [
                    'id' => $offering->id,
                    'label' => sprintf(
                        '%s - %s (Section %s, %s)',
                        $offering->course_code,
                        $offering->course_title,
                        $offering->section_code ?? 'N/A',
                        $offering->semester->code
                    ),
                    'course_code' => $offering->course_code,
                    'course_title' => $offering->course_title,
                    'section_code' => $offering->section_code,
                    'semester' => $offering->semester->name,
                    'semester_id' => $offering->semester_id,
                ];
            });

        return response()->json($offerings);
    }

    private function getSemesterOptions()
    {
        return Semester::where('is_archived', false)
            ->orderBy('start_date', 'desc')
            ->get(['id', 'name', 'code']);
    }

    private function getUnitOptions()
    {
        return Unit::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }
}
