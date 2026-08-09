<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Facilities\Http\Requests\Room\ListAvailableRoomsRequest;
use App\Modules\Facilities\Http\Requests\Room\ListRoomsRequest;
use App\Modules\Facilities\Http\Requests\Room\StoreRoomRequest;
use App\Modules\Facilities\Http\Requests\Room\UpdateRoomRequest;
use App\Modules\Facilities\Models\Room;
use App\Modules\Facilities\Queries\GetRoomFilterOptionsQuery;
use App\Modules\Facilities\Queries\ListAvailableRoomsQuery;
use App\Modules\Facilities\Queries\ListRoomsQuery;
use App\Modules\Facilities\Support\RoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function __construct(
        private readonly RoomService $roomService,
        private readonly ListRoomsQuery $listRooms,
        private readonly ListAvailableRoomsQuery $listAvailableRooms,
        private readonly GetRoomFilterOptionsQuery $roomFilterOptions,
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
    public function index(ListRoomsRequest $request): Response
    {
        $validated = $request->validated();

        return Inertia::render('Rooms/Index', [
            'rooms' => $this->listRooms->handle($validated),
            'filters' => [
                'search' => $validated['search'] ?? null,
                'type' => $validated['type'] ?? null,
                'status' => $validated['status'] ?? null,
                'building_id' => $validated['building_id'] ?? null,
                'floor' => $validated['floor'] ?? null,
                'is_bookable' => $validated['is_bookable'] ?? null,
                'requires_approval' => $validated['requires_approval'] ?? null,
                'min_capacity' => $validated['min_capacity'] ?? null,
                'max_capacity' => $validated['max_capacity'] ?? null,
            ],
            'statistics' => $this->roomService->getRoomStatistics(),
            'room_types' => $this->getRoomTypeOptions(),
            'room_statuses' => $this->getRoomStatusOptions(),
            'buildings' => $this->roomFilterOptions->buildings(),
            'floors' => $this->roomFilterOptions->floors(),
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
        return Inertia::render('Rooms/CreateAutoForm', [
            'room_types' => $this->getRoomTypeOptions(),
            'room_statuses' => $this->getRoomStatusOptions(),
            'buildings' => $this->roomFilterOptions->buildings(),
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
            Inertia::flash('success', "Room '{$room->name}' has been created successfully.");

            return redirect()
                ->route('rooms.index');
        } catch (\Exception $e) {
            Inertia::flash('error', 'Failed to create room. Please try again.');

            return redirect()
                ->back()
                ->withInput();
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

        return Inertia::render('Rooms/Show', [
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

        $room->load(['campus', 'building']);

        return Inertia::render('Rooms/Edit', [
            'room' => $room,
            'room_types' => $this->getRoomTypeOptions(),
            'room_statuses' => $this->getRoomStatusOptions(),
            'buildings' => $this->roomFilterOptions->buildings(),
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
            Inertia::flash('success', "Room '{$room->name}' has been updated successfully.");

            return redirect()
                ->route('rooms.index');
        } catch (\Exception $e) {
            Inertia::flash('error', 'Failed to update room. Please try again.');

            return redirect()
                ->back()
                ->withInput();
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
            Inertia::flash('success', "Room '{$roomName}' has been deleted successfully.");

            return redirect()
                ->route('rooms.index');
        } catch (\Exception $e) {
            Inertia::flash('error', 'Failed to delete room. Please try again.');

            return redirect()
                ->back();
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
     * API endpoint for getting rooms (for dropdowns, quick edits, etc.)
     */
    public function apiIndex(ListAvailableRoomsRequest $request): JsonResponse
    {
        return ApiResponse::success($this->listAvailableRooms->handle($request->validated()), message: 'Rooms retrieved successfully');
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
