<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Campus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuildingController extends Controller
{
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
