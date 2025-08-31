<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Constants\CampusRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campus\StoreCampusRequest;
use App\Http\Requests\Campus\UpdateCampusRequest;
use App\Models\Campus;
use App\Services\CampusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class CampusController extends Controller
{
    public function __construct(protected CampusService $campusService)
    {
        $this->middleware('can:view_campus')->only(['index', 'show']);
        $this->middleware('can:create_campus')->only(['create', 'store']);
        $this->middleware('can:edit_campus')->only(['edit', 'update']);
        $this->middleware('can:delete_campus')->only(['destroy']);
    }

    /**
     * Display a listing of campuses
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:name,code,address,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $page = (int) $request->query('page', 1);
        $cacheKey = 'campuses:index:'.md5(json_encode([
            'page' => $page,
            'per_page' => $validated['per_page'] ?? 15,
            'search' => $validated['search'] ?? null,
            'sort' => $validated['sort'] ?? null,
            'direction' => $validated['direction'] ?? 'asc',
        ]));

        $campuses = Cache::tags(['campuses', 'campuses.index'])->rememberForever($cacheKey, function () use ($validated, $page) {
            return Campus::query()
                ->withCount(['buildings', 'users'])
                ->when($validated['search'] ?? null, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%");
                    });
                })
                ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                    $direction = $validated['direction'] ?? 'asc';
                    $query->orderBy($sort, $direction);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($validated['per_page'] ?? 15, ['*'], 'page', $page);
        })->withQueryString();

        return Inertia::render('campuses/Index', [
            'campuses' => $campuses,
        ]);
    }

    /**
     * Show the form for creating a new campus
     */
    public function create(): Response
    {
        return Inertia::render('campuses/Create');
    }

    /**
     * Store a newly created campus
     */
    public function store(StoreCampusRequest $request): RedirectResponse
    {
        $this->campusService->createCampus($request->validated());

        // Invalidate campus caches after creation
        Cache::tags(['campuses'])->flush();

        return redirect()->route(CampusRoutes::INDEX)->with('success', 'Campus created successfully.');
    }

    /**
     * Display the specified campus with building management
     */
    public function show(Request $request, Campus $campus): Response
    {
        // Validate building search and pagination parameters
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:name,code,description,address,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $page = (int) $request->query('page', 1);
        $cacheKey = 'campuses:show:'.md5(json_encode([
            'campus_id' => $campus->id,
            'page' => $page,
            'per_page' => $validated['per_page'] ?? 15,
            'search' => $validated['search'] ?? null,
            'sort' => $validated['sort'] ?? null,
            'direction' => $validated['direction'] ?? 'asc',
        ]));

        [$cachedCampus, $buildings] = Cache::tags(['campuses', 'campuses.show'])->rememberForever($cacheKey, function () use ($campus, $validated, $page) {
            // Load campus with counts
            $camp = $campus->fresh()->loadCount(['buildings', 'users']);

            // Get paginated buildings for this campus
            $builds = $camp->buildings()
                ->when($validated['search'] ?? null, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%");
                    });
                })
                ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                    $direction = $validated['direction'] ?? 'asc';
                    $query->orderBy($sort, $direction);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($validated['per_page'] ?? 15, ['*'], 'page', $page);

            return [$camp, $builds];
        });

        return Inertia::render('campuses/Show', [
            'campus' => $cachedCampus,
            'buildings' => $buildings->withQueryString(),
            'filters' => $request->only(['search', 'sort', 'direction', 'per_page']),
        ]);
    }

    /**
     * Show the form for editing the specified campus
     */
    public function edit(Campus $campus): Response
    {
        return Inertia::render('campuses/Edit', [
            'campus' => $campus,
        ]);
    }

    /**
     * Update the specified campus
     */
    public function update(UpdateCampusRequest $request, Campus $campus): RedirectResponse
    {
        $this->campusService->updateCampus($campus, $request->validated());

        // Invalidate campus caches after update
        Cache::tags(['campuses'])->flush();

        return redirect()->route(CampusRoutes::INDEX)->with('success', 'Campus updated successfully.');
    }

    /**
     * Remove the specified campus
     */
    public function destroy(Campus $campus): RedirectResponse
    {
        $this->campusService->deleteCampus($campus);

        // Invalidate campus caches after deletion
        Cache::tags(['campuses'])->flush();

        return redirect()->route(CampusRoutes::INDEX)->with('success', 'Campus deleted successfully.');
    }

    /**
     * Get campuses for API/dropdown usage
     */
    public function api(Request $request)
    {
        $search = (string) ($request->search ?? '');
        $cacheKey = 'campuses:api:'.md5(json_encode([
            'search' => $search,
            'limit' => 50,
        ]));

        $campuses = Cache::tags(['campuses', 'campuses.api'])->rememberForever($cacheKey, function () use ($search) {
            return Campus::select('id', 'name', 'code')
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->limit(50)
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $campuses,
        ]);
    }
}
