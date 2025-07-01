<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBuildingRequest;
use App\Http\Requests\UpdateBuildingRequest;
use App\Models\Building;
use App\Models\Campus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class BuildingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:view_building')->only(['index', 'show']);
        $this->middleware('can:create_building')->only(['create', 'store']);
        $this->middleware('can:edit_building')->only(['edit', 'update']);
        $this->middleware('can:delete_building')->only(['destroy']);
    }

    /**
     * Display a listing of buildings
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'sort' => 'nullable|string|in:name,code,campus_id,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $buildings = Building::query()
            ->with(['campus'])
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('campus', function ($campusQuery) use ($search) {
                            $campusQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($validated['campus_id'] ?? null, function ($query, $campusId) {
                $query->where('campus_id', $campusId);
            })
            ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                $direction = $validated['direction'] ?? 'asc';
                if ($sort === 'campus_id') {
                    $query->join('campuses', 'buildings.campus_id', '=', 'campuses.id')
                        ->orderBy('campuses.name', $direction)
                        ->select('buildings.*');
                } else {
                    $query->orderBy($sort, $direction);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        // Get filter options
        $campuses = Campus::orderBy('name')->get(['id', 'name']);

        return Inertia::render('buildings/Index', [
            'buildings' => $buildings,
            'filters' => $request->only(['search', 'campus_id', 'sort', 'direction', 'per_page']),
            'campuses' => $campuses,
        ]);
    }

    /**
     * Show the form for creating a new building for a specific campus
     */
    public function create(Campus $campus): Response
    {
        return Inertia::render('buildings/Create', [
            'campus' => $campus,
        ]);
    }

    /**
     * Store a newly created building for a specific campus
     */
    public function store(StoreBuildingRequest $request, Campus $campus): RedirectResponse
    {
        try {
            $validatedData = $request->validated();
            $validatedData['campus_id'] = $campus->id;

            $building = Building::create($validatedData);

            return Redirect::route('campuses.show', $campus)
                ->with('success', 'Building created successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to create building', [
                'campus_id' => $campus->id,
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create building. Please try again.']);
        }
    }

    /**
     * Display the specified building
     */
    public function show(Building $building): Response
    {
        $building->load(['campus']);

        // Get building statistics (placeholder for now)
        $statistics = [
            'total_rooms' => $building->getTotalRooms(),
            'available_rooms' => 0, // Placeholder
            'occupied_rooms' => 0, // Placeholder
            'maintenance_rooms' => 0, // Placeholder
        ];

        return Inertia::render('buildings/Show', [
            'building' => $building,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Show the form for editing the specified building within a campus
     */
    public function edit(Campus $campus, Building $building): Response
    {
        // Ensure the building belongs to the campus
        if ($building->campus_id !== $campus->id) {
            abort(404);
        }

        $building->load(['campus']);

        return Inertia::render('buildings/Edit', [
            'building' => $building,
            'campus' => $campus,
        ]);
    }

    /**
     * Update the specified building within a campus
     */
    public function update(UpdateBuildingRequest $request, Campus $campus, Building $building): RedirectResponse
    {
        try {
            // Ensure the building belongs to the campus
            if ($building->campus_id !== $campus->id) {
                abort(404);
            }

            $building->update($request->validated());

            return Redirect::route('campuses.show', $campus)
                ->with('success', 'Building updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update building', [
                'campus_id' => $campus->id,
                'building_id' => $building->id,
                'error' => $e->getMessage(),
                'data' => $request->validated()
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update building. Please try again.']);
        }
    }

    /**
     * Remove the specified building from a campus
     */
    public function destroy(Campus $campus, Building $building): RedirectResponse
    {
        try {
            // Ensure the building belongs to the campus
            if ($building->campus_id !== $campus->id) {
                abort(404);
            }

            // Check if building has associated rooms (placeholder)
            // if ($building->rooms()->exists()) {
            //     return back()->withErrors(['error' => 'Cannot delete building with associated rooms.']);
            // }

            $building->delete();

            return Redirect::route('campuses.show', $campus)
                ->with('success', 'Building deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete building', [
                'campus_id' => $campus->id,
                'building_id' => $building->id,
                'error' => $e->getMessage()
            ]);

            return back()->withErrors(['error' => 'Failed to delete building. Please try again.']);
        }
    }

    /**
     * Get buildings for API/dropdown usage
     */
    public function api(Request $request)
    {
        $request->validate([
            'campus_id' => 'nullable|integer|exists:campuses,id',
        ]);

        $buildings = Building::select('id', 'name', 'code', 'campus_id')
            ->with(['campus:id,name'])
            ->when($request->campus_id, function ($query, $campusId) {
                $query->where('campus_id', $campusId);
            })
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $buildings,
        ]);
    }
}
