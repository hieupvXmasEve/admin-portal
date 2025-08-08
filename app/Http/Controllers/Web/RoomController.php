<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\RoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function __construct(
        private readonly RoomService $roomService
    ) {
        $this->middleware('auth');
        $this->middleware('campus.selected');

        // Apply permission middleware
        $this->middleware('can:view_room')->only(['index', 'show']);
        $this->middleware('can:create_room')->only(['create', 'store']);
        $this->middleware('can:edit_room')->only(['edit', 'update']);
        $this->middleware('can:delete_room')->only(['destroy']);
    }

    /**
     * Display a listing of rooms.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:'.implode(',', Room::getTypes()),
            'status' => 'nullable|string|in:'.implode(',', Room::getStatuses()),
            'building' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:255',
            'is_bookable' => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean',
            'min_capacity' => 'nullable|integer|min:1',
            'max_capacity' => 'nullable|integer|min:1',
            'sort' => 'nullable|string|in:name,building,type,capacity,status,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = Room::query()
            ->with('campus')
            ->forCampus(app('campus')->id);

        // Apply search filter
        if ($validated['search'] ?? null) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('building', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply type filter
        if ($validated['type'] ?? null) {
            $query->where('type', $validated['type']);
        }

        // Apply status filter
        if ($validated['status'] ?? null) {
            $query->where('status', $validated['status']);
        }

        // Apply building filter
        if ($validated['building'] ?? null) {
            $query->where('building', $validated['building']);
        }

        // Apply floor filter
        if ($validated['floor'] ?? null) {
            $query->where('floor', $validated['floor']);
        }

        // Apply bookable filter
        if (isset($validated['is_bookable'])) {
            $query->where('is_bookable', $validated['is_bookable']);
        }

        // Apply approval requirement filter
        if (isset($validated['requires_approval'])) {
            $query->where('requires_approval', $validated['requires_approval']);
        }

        // Apply capacity filters
        if ($validated['min_capacity'] ?? null) {
            $query->where('capacity', '>=', $validated['min_capacity']);
        }

        if ($validated['max_capacity'] ?? null) {
            $query->where('capacity', '<=', $validated['max_capacity']);
        }

        // Apply sorting
        if ($validated['sort'] ?? null) {
            $direction = $validated['direction'] ?? 'asc';
            $query->orderBy($validated['sort'], $direction);
        }

        // Default ordering
        $query->orderBy('created_at', 'desc');

        $rooms = $query->paginate($validated['per_page'] ?? 15)
            ->withQueryString();
        //   ->through(fn ($room) => new RoomResource($room));

        // Get statistics
        $statistics = $this->roomService->getRoomStatistics();

        // Get unique buildings and floors for filters
        $buildings = Room::forCampus(app('campus')->id)
            ->distinct()
            ->pluck('building')
            ->filter()
            ->sort()
            ->values();

        $floors = Room::forCampus(app('campus')->id)
            ->distinct()
            ->pluck('floor')
            ->filter()
            ->sort()
            ->values();

        return Inertia::render('rooms/Index', [
            'rooms' => $rooms,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'type' => $validated['type'] ?? null,
                'status' => $validated['status'] ?? null,
                'building' => $validated['building'] ?? null,
                'floor' => $validated['floor'] ?? null,
                'is_bookable' => $validated['is_bookable'] ?? null,
                'requires_approval' => $validated['requires_approval'] ?? null,
                'min_capacity' => $validated['min_capacity'] ?? null,
                'max_capacity' => $validated['max_capacity'] ?? null,
            ],
            'statistics' => $statistics,
            'room_types' => $this->getRoomTypeOptions(),
            'room_statuses' => $this->getRoomStatusOptions(),
            'buildings' => $buildings,
            'floors' => $floors,
            'permissions' => [
                'can_create' => auth()->user()->can('create_room'),
                'can_edit' => auth()->user()->can('edit_room'),
                'can_delete' => auth()->user()->can('delete_room'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new room.
     */
    public function create(): Response
    {
        // Get unique buildings for dropdown
        $buildings = Room::forCampus(app('campus')->id)
            ->distinct()
            ->pluck('building')
            ->filter()
            ->sort()
            ->values();

        return Inertia::render('rooms/Create', [
            'room_types' => $this->getRoomTypeOptions(),
            'room_statuses' => $this->getRoomStatusOptions(),
            'buildings' => $buildings,
            'days_of_week' => $this->getDaysOfWeekOptions(),
        ]);
    }

    /**
     * Store a newly created room.
     */
    public function store(StoreRoomRequest $request): RedirectResponse
    {
        try {
            $room = $this->roomService->createRoom($request->validated());

            return redirect()
                ->route('rooms.index')
                ->with('success', "Room '{$room->name}' has been created successfully.");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create room. Please try again.');
        }
    }

    /**
     * Display the specified room.
     */
    public function show(Room $room): Response
    {
        // Ensure room belongs to current campus
        if ($room->campus_id !== app('campus')->id) {
            abort(404);
        }

        $room->load('campus');

        return Inertia::render('rooms/Show', [
            'room' => new RoomResource($room),
            'permissions' => [
                'can_edit' => auth()->user()->can('edit_room'),
                'can_delete' => auth()->user()->can('delete_room'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified room.
     */
    public function edit(Room $room): Response
    {
        // Ensure room belongs to current campus
        if ($room->campus_id !== app('campus')->id) {
            abort(404);
        }

        $room->load('campus');

        // Get unique buildings for dropdown
        $buildings = Room::forCampus(app('campus')->id)
            ->distinct()
            ->pluck('building')
            ->filter()
            ->sort()
            ->values();

        return Inertia::render('rooms/Edit', [
            'room' => new RoomResource($room),
            'room_types' => $this->getRoomTypeOptions(),
            'room_statuses' => $this->getRoomStatusOptions(),
            'buildings' => $buildings,
            'days_of_week' => $this->getDaysOfWeekOptions(),
        ]);
    }

    /**
     * Update the specified room.
     */
    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        // Ensure room belongs to current campus
        if ($room->campus_id !== app('campus')->id) {
            abort(404);
        }

        try {
            $room = $this->roomService->updateRoom($room, $request->validated());

            return redirect()
                ->route('rooms.index')
                ->with('success', "Room '{$room->name}' has been updated successfully.");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update room. Please try again.');
        }
    }

    /**
     * Remove the specified room.
     */
    public function destroy(Room $room): RedirectResponse
    {
        // Ensure room belongs to current campus
        if ($room->campus_id !== app('campus')->id) {
            abort(404);
        }

        try {
            $roomName = $room->name;
            $this->roomService->deleteRoom($room);

            return redirect()
                ->route('rooms.index')
                ->with('success', "Room '{$roomName}' has been deleted successfully.");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete room. Please try again.');
        }
    }

    /**
     * Get room type options for dropdowns.
     */
    private function getRoomTypeOptions(): array
    {
        return collect(Room::getTypes())->map(function ($type) {
            return [
                'value' => $type,
                'label' => match ($type) {
                    'classroom' => 'Classroom',
                    'laboratory' => 'Laboratory',
                    'computer_lab' => 'Computer Lab',
                    'auditorium' => 'Auditorium',
                    'meeting_room' => 'Meeting Room',
                    'library' => 'Library',
                    'study_room' => 'Study Room',
                    'workshop' => 'Workshop',
                    'office' => 'Office',
                    'other' => 'Other',
                    default => ucfirst(str_replace('_', ' ', $type)),
                },
            ];
        })->toArray();
    }

    /**
     * Get room status options for dropdowns.
     */
    private function getRoomStatusOptions(): array
    {
        return collect(Room::getStatuses())->map(function ($status) {
            return [
                'value' => $status,
                'label' => match ($status) {
                    'available' => 'Available',
                    'occupied' => 'Occupied',
                    'maintenance' => 'Under Maintenance',
                    'out_of_service' => 'Out of Service',
                    'reserved' => 'Reserved',
                    default => ucfirst(str_replace('_', ' ', $status)),
                },
            ];
        })->toArray();
    }

    /**
     * Get days of week options for dropdowns.
     */
    private function getDaysOfWeekOptions(): array
    {
        return [
            ['value' => 'Monday', 'label' => 'Monday'],
            ['value' => 'Tuesday', 'label' => 'Tuesday'],
            ['value' => 'Wednesday', 'label' => 'Wednesday'],
            ['value' => 'Thursday', 'label' => 'Thursday'],
            ['value' => 'Friday', 'label' => 'Friday'],
            ['value' => 'Saturday', 'label' => 'Saturday'],
            ['value' => 'Sunday', 'label' => 'Sunday'],
        ];
    }
}
