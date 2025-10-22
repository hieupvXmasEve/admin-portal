<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Canvas;

use App\Http\Controllers\Controller;
use App\Http\Requests\CanvasCourseMappingRequest;
use App\Http\Resources\CanvasCourseResource;
use App\Models\CanvasCourseMapping;
use App\Models\CanvasIntegration;
use App\Models\CourseOffering;
use App\Services\Canvas\CanvasSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CanvasCourseController extends Controller
{
    public function __construct(
        private CanvasSyncService $syncService
    ) {}

    public function index(Request $request): Response
    {
        // All campuses share one Canvas instance
        $integration = CanvasIntegration::where('is_active', true)->first();

        if (! $integration) {
            // Return empty pagination structure
            $emptyPagination = new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                15,
                1
            );

            return Inertia::render('Admin/Canvas/Courses/Index', [
                'mappings' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                    'prev_page_url' => null,
                    'next_page_url' => null,
                    'links' => [],
                ],
                'filters' => $request->only(['search', 'sync_status', 'sort', 'direction', 'per_page']),
                'integration' => null,
            ]);
        }

        $query = CanvasCourseMapping::with(['courseOffering.semester', 'canvasIntegration'])
            ->where('canvas_integration_id', $integration->id);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('canvas_course_code', 'like', "%{$search}%")
                    ->orWhere('canvas_course_name', 'like', "%{$search}%")
                    ->orWhere('canvas_course_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sync_status')) {
            $query->where('sync_status', $request->sync_status);
        }

        // Sorting
        $sortField = $request->input('sort', 'created_at');
        $sortDirection = $request->input('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->input('per_page', 15);
        $mappings = $query->paginate($perPage);

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
            'filters' => $request->only(['search', 'sync_status', 'sort', 'direction', 'per_page']),
            'integration' => [
                'id' => $integration->id,
                'canvas_url' => $integration->canvas_url,
                'has_token' => $integration->hasValidToken(),
                'has_refresh_token' => $integration->canRefreshToken(),
                'sync_status' => $integration->sync_status,
                'last_sync_at' => $integration->last_sync_at?->toIso8601String(),
            ],
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

    public function getAvailableCourseOfferings(Request $request)
    {
        $campusId = session('current_campus_id');
        $search = $request->input('search', '');

        $offerings = CourseOffering::with(['unit', 'semester'])
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->when($search, function ($q) use ($search) {
                $q->whereHas('unit', function ($unitQuery) use ($search) {
                    $unitQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
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
                ];
            });

        return response()->json($offerings);
    }
}
