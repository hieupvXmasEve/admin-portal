<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Building;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\RoomBookingAction;
use App\Modules\Facilities\Actions\CreateRoomBookingSeriesAction;
use App\Modules\Facilities\Exceptions\RoomBookingSeriesConflictException;
use App\Modules\Facilities\Http\Requests\RoomBooking\ApproveRoomBookingRequest;
use App\Modules\Facilities\Http\Requests\RoomBooking\ListAvailabilityRequest;
use App\Modules\Facilities\Http\Requests\RoomBooking\PreviewRoomBookingSeriesRequest;
use App\Modules\Facilities\Http\Requests\RoomBooking\RejectRoomBookingRequest;
use App\Modules\Facilities\Http\Requests\RoomBooking\StoreRoomBookingRequest;
use App\Modules\Facilities\Http\Requests\RoomBooking\UpdateRoomBookingRequest;
use App\Modules\Facilities\Queries\GetRoomAvailabilityBoardQuery;
use App\Modules\Facilities\Queries\GetRoomBookingCloneDraftQuery;
use App\Modules\Facilities\Queries\PreviewRoomBookingSeriesAvailabilityQuery;
use App\Modules\Facilities\Support\RoomBookingOccurrenceNormalizer;
use App\Modules\Facilities\Support\RoomBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class RoomBookingController extends Controller
{
    public function __construct(
        private readonly RoomBookingService $bookingService,
        private readonly CreateRoomBookingSeriesAction $createRoomBookingSeries,
        private readonly PreviewRoomBookingSeriesAvailabilityQuery $previewSeriesAvailability,
        private readonly GetRoomAvailabilityBoardQuery $availabilityBoardQuery,
        private readonly GetRoomBookingCloneDraftQuery $cloneDraftQuery,
        private readonly RoomBookingOccurrenceNormalizer $occurrenceNormalizer
    ) {
        $this->middleware('can:view_room_booking')->only(['index', 'show', 'calendar', 'logs', 'availability', 'apiGetBookings']);
        $this->middleware('can:create_room_booking')->only(['create', 'store', 'apiCheckConflicts', 'apiPreviewSeries']);
        $this->middleware('can:edit_room_booking')->only(['edit', 'update']);
        $this->middleware('can:delete_room_booking')->only(['destroy']);
        $this->middleware('can:approve_room_booking')->only(['pending', 'approve', 'reject']);
    }

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'status' => 'nullable|string|in:'.implode(',', RoomBooking::getStatuses()),
            'booking_type' => 'nullable|string|in:'.implode(',', RoomBooking::getBookingTypes()),
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $filters = array_merge($validated, ['campus_id' => app('campus')->id]);

        return Inertia::render('room-bookings/Index', [
            'bookings' => $this->bookingService->getPaginatedBookings($filters, (int) ($validated['per_page'] ?? 15)),
            'filters' => $validated,
            'statistics' => $this->bookingService->getBookingStatistics(),
            'booking_types' => $this->getBookingTypeOptions(),
            'booking_statuses' => $this->getBookingStatusOptions(),
            'buildings' => $this->getBuildingOptions(),
            'rooms' => $this->getRoomOptions(),
        ]);
    }

    public function myBookings(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:'.implode(',', RoomBooking::getStatuses()),
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $user = $request->user();

        return Inertia::render('room-bookings/MyBookings', [
            'bookings' => $this->bookingService->getMyBookings(
                RoomBooking::BOOKED_BY_USER,
                $user->id,
                $validated,
                (int) ($validated['per_page'] ?? 15)
            ),
            'filters' => $validated,
            'booking_statuses' => $this->getBookingStatusOptions(),
            'permissions' => [
                'can_create' => $user->can('create_room_booking'),
                'can_edit' => $user->can('edit_room_booking'),
            ],
        ]);
    }

    public function pending(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'booking_type' => 'nullable|string|in:'.implode(',', RoomBooking::getBookingTypes()),
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $filters = array_merge($validated, ['campus_id' => app('campus')->id]);

        return Inertia::render('room-bookings/Pending', [
            'bookings' => $this->bookingService->getPendingBookings($filters, (int) ($validated['per_page'] ?? 15)),
            'filters' => $validated,
            'statistics' => $this->bookingService->getBookingStatistics(),
            'booking_types' => $this->getBookingTypeOptions(),
            'buildings' => $this->getBuildingOptions(),
            'rooms' => $this->getRoomOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $roomId = $request->query('room_id');
        $sourceBookingId = $request->query('source_booking_id');
        $selectedRoom = $roomId
            ? Room::with(['building', 'campus'])
                ->forCampus(app('campus')->id)
                ->find($roomId)
            : null;
        $cloneDraft = null;

        if ($sourceBookingId) {
            $sourceBooking = RoomBooking::query()
                ->whereHas('room', fn ($query) => $query->where('campus_id', app('campus')->id))
                ->findOrFail($sourceBookingId);
            $cloneDraft = $this->cloneDraftQuery->handle($sourceBooking);
            $selectedRoom = $sourceBooking->room;
        }

        return Inertia::render('room-bookings/Create', [
            'selected_room' => $selectedRoom,
            'clone_source' => $cloneDraft,
            'initial_schedule' => [
                'booking_date' => $request->query('date', now()->toDateString()),
                'date_from' => $request->query('date', now()->toDateString()),
                'date_to' => $request->query('date', now()->toDateString()),
                'start_time' => $request->query('start_time', '09:00'),
                'end_time' => $request->query('end_time', '10:00'),
            ],
            'booking_types' => $this->getBookingTypeOptions(),
            'priorities' => $this->getPriorityOptions(),
            'buildings' => $this->getBuildingOptions(),
            'rooms' => $this->getRoomOptions(),
        ]);
    }

    public function store(StoreRoomBookingRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $result = $this->createRoomBookingSeries->run(
                $request->validated(),
                RoomBooking::BOOKED_BY_USER,
                $user->id,
                $user,
                app('campus')->id
            );

            $count = $result['bookings']->count();
            $parent = $result['parent'];
            $message = $count > 1
                ? "Created {$count} room booking occurrences for '{$parent->title}'."
                : ($parent->isApproved()
                    ? "Room booking '{$parent->title}' has been created and approved."
                    : "Room booking '{$parent->title}' has been submitted for approval.");

            Inertia::flash('success', $message);

            return redirect()->route('room-bookings.show', $parent);
        } catch (RoomBookingSeriesConflictException $exception) {
            return redirect()
                ->back()
                ->withErrors([
                    'occurrences' => $exception->getMessage(),
                    'conflicts' => 'Resolve all conflicting dates before creating this booking set.',
                ])
                ->withInput();
        } catch (\InvalidArgumentException $exception) {
            return redirect()
                ->back()
                ->withErrors($this->mapBusinessError($exception->getMessage()))
                ->withInput();
        } catch (\Throwable $exception) {
            Log::error('Room booking creation failed', [
                'user_id' => $user?->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withErrors(['general' => 'Failed to create booking. Please try again.'])
                ->withInput();
        }
    }

    public function show(RoomBooking $roomBooking): Response
    {
        $this->assertBookingCampus($roomBooking);
        $roomBooking->load(['room.building', 'room.campus', 'bookedBy', 'approvedBy', 'actions.actionBy']);

        return Inertia::render('room-bookings/Show', [
            'booking' => $roomBooking,
        ]);
    }

    public function edit(RoomBooking $roomBooking): Response
    {
        $this->assertBookingCampus($roomBooking);

        if (! $roomBooking->canBeEdited()) {
            abort(403, 'This booking cannot be edited.');
        }

        $roomBooking->load(['room.building', 'room.campus']);
        $bookingData = $roomBooking->toArray();
        $bookingData['booking_date'] = $roomBooking->booking_date->format('Y-m-d');

        return Inertia::render('room-bookings/Edit', [
            'booking' => $bookingData,
            'booking_types' => $this->getBookingTypeOptions(),
            'priorities' => $this->getPriorityOptions(),
            'buildings' => $this->getBuildingOptions(),
            'rooms' => $this->getRoomOptions(),
        ]);
    }

    public function update(UpdateRoomBookingRequest $request, RoomBooking $roomBooking): RedirectResponse
    {
        $this->assertBookingCampus($roomBooking);

        if (! $roomBooking->canBeEdited()) {
            return redirect()->back()->withErrors(['general' => 'This booking cannot be edited.'])->withInput();
        }

        try {
            $booking = $this->bookingService->updateBooking(
                $roomBooking,
                $request->validated(),
                RoomBooking::BOOKED_BY_USER,
                $request->user()->id
            );

            Inertia::flash('success', "Booking '{$booking->title}' has been updated successfully.");

            return redirect()->route('room-bookings.show', $booking);
        } catch (\InvalidArgumentException $exception) {
            return redirect()->back()->withErrors($this->mapBusinessError($exception->getMessage()))->withInput();
        }
    }

    public function approve(ApproveRoomBookingRequest $request, RoomBooking $roomBooking): RedirectResponse
    {
        $this->assertBookingCampus($roomBooking);

        if (! $roomBooking->isPending()) {
            Inertia::flash('error', 'This booking is not pending approval.');

            return redirect()->back();
        }

        try {
            $booking = $this->bookingService->approveBooking($roomBooking, 'user', $request->user()->id, $request->note);
        } catch (\InvalidArgumentException $exception) {
            Inertia::flash('error', $exception->getMessage());

            return redirect()->back();
        }

        Inertia::flash('success', "Booking '{$booking->title}' has been approved.");

        return redirect()->route('room-bookings.show', $booking);
    }

    public function reject(RejectRoomBookingRequest $request, RoomBooking $roomBooking): RedirectResponse
    {
        $this->assertBookingCampus($roomBooking);

        if (! $roomBooking->isPending()) {
            Inertia::flash('error', 'This booking is not pending approval.');

            return redirect()->back();
        }

        $booking = $this->bookingService->rejectBooking($roomBooking, 'user', $request->user()->id, $request->reason);
        Inertia::flash('success', "Booking '{$booking->title}' has been rejected.");

        return redirect()->route('room-bookings.show', $booking);
    }

    public function cancel(Request $request, RoomBooking $roomBooking): RedirectResponse
    {
        $this->assertBookingCampus($roomBooking);

        if (! $roomBooking->canBeCancelled()) {
            Inertia::flash('error', 'This booking cannot be cancelled.');

            return redirect()->back();
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $booking = $this->bookingService->cancelBooking($roomBooking, 'user', $request->user()->id, $validated['reason'] ?? null);
        Inertia::flash('success', "Booking '{$booking->title}' has been cancelled.");

        return redirect()->route('room-bookings.my-bookings');
    }

    public function destroy(RoomBooking $roomBooking): RedirectResponse
    {
        $this->assertBookingCampus($roomBooking);
        $title = $roomBooking->title;
        $this->bookingService->deleteBooking($roomBooking);
        Inertia::flash('success', "Booking '{$title}' has been deleted.");

        return redirect()->route('room-bookings.index');
    }

    public function calendar(Request $request): Response
    {
        $validated = $request->validate([
            'room_id' => 'nullable|integer|exists:rooms,id',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $startDate = $validated['start_date'] ?? now()->startOfWeek()->toDateString();
        $endDate = $validated['end_date'] ?? now()->endOfWeek()->toDateString();
        $filters = ['campus_id' => app('campus')->id];

        if (! empty($validated['room_id'])) {
            $filters['room_id'] = (int) $validated['room_id'];
        }

        if (! empty($validated['building_id'])) {
            $filters['building_id'] = (int) $validated['building_id'];
        }

        return Inertia::render('room-bookings/Calendar', [
            'calendar_data' => $this->bookingService->getCalendarData($filters, $startDate, $endDate),
            'filters' => array_merge($validated, ['start_date' => $startDate, 'end_date' => $endDate]),
            'buildings' => $this->getBuildingOptions(),
            'rooms' => $this->getRoomOptions(),
        ]);
    }

    public function availability(ListAvailabilityRequest $request): Response
    {
        $validated = $request->validated();
        $filters = array_merge([
            'campus_id' => app('campus')->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->copy()->addDays(6)->toDateString(),
            'start_time' => '07:00',
            'end_time' => '20:00',
        ], array_filter($validated, fn ($value) => $value !== null && $value !== ''));

        return Inertia::render('room-bookings/Availability', [
            'availability' => $this->availabilityBoardQuery->handle($filters),
            'filters' => $filters,
            'buildings' => $this->getBuildingOptions(),
            'rooms' => $this->getRoomOptions(),
        ]);
    }

    public function logs(Request $request): Response
    {
        $validated = $request->validate([
            'booking_id' => 'nullable|integer|exists:room_bookings,id',
            'action_type' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = RoomBookingAction::query()
            ->with(['roomBooking.room', 'actionBy'])
            ->latest();

        if (! empty($validated['booking_id'])) {
            $query->where('room_booking_id', $validated['booking_id']);
        }

        if (! empty($validated['action_type'])) {
            $query->where('action_type', $validated['action_type']);
        }

        if (! empty($validated['start_date'])) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }

        if (! empty($validated['end_date'])) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }

        return Inertia::render('room-bookings/Logs', [
            'logs' => $query->paginate((int) ($validated['per_page'] ?? 25)),
            'filters' => $validated,
            'action_types' => $this->getActionTypeOptions(),
        ]);
    }

    public function apiGetBookings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => 'nullable|integer|exists:rooms,id',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $filters = ['campus_id' => app('campus')->id];

        if (! empty($validated['room_id'])) {
            $filters['room_id'] = (int) $validated['room_id'];
        }

        if (! empty($validated['building_id'])) {
            $filters['building_id'] = (int) $validated['building_id'];
        }

        return ApiResponse::success($this->bookingService->getCalendarData($filters, $validated['start_date'], $validated['end_date']));
    }

    public function apiCheckConflicts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id',
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'exclude_booking_id' => 'nullable|integer|exists:room_bookings,id',
        ]);

        Room::query()
            ->forCampus(app('campus')->id)
            ->findOrFail($validated['room_id']);

        $conflicts = $this->bookingService->checkAllConflicts(
            (int) $validated['room_id'],
            $validated['booking_date'],
            $validated['start_time'],
            $validated['end_time'],
            $validated['exclude_booking_id'] ?? null
        );

        return ApiResponse::success([
            'has_conflicts' => count($conflicts) > 0,
            'conflicts' => $conflicts,
        ]);
    }

    public function apiPreviewSeries(PreviewRoomBookingSeriesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $occurrences = $this->occurrenceNormalizer->normalize($validated);

        return ApiResponse::success($this->previewSeriesAvailability->handle(
            (int) $validated['room_id'],
            $occurrences,
            app('campus')->id,
            $validated['exclude_booking_id'] ?? null
        ));
    }

    private function assertBookingCampus(RoomBooking $booking): void
    {
        $booking->loadMissing('room');

        abort_unless((int) $booking->room?->campus_id === (int) app('campus')->id, 404);
    }

    private function mapBusinessError(string $message): array
    {
        if (stripos($message, 'time slot') !== false || stripos($message, 'conflict') !== false) {
            return ['start_time' => $message, 'end_time' => $message];
        }

        if (stripos($message, 'room') !== false) {
            return ['room_id' => $message];
        }

        if (stripos($message, 'date') !== false) {
            return ['booking_date' => $message];
        }

        return ['general' => $message];
    }

    private function getBookingTypeOptions(): array
    {
        return collect(RoomBooking::getBookingTypes())->map(fn (string $type) => [
            'value' => $type,
            'label' => match ($type) {
                'class' => 'Class',
                'exam' => 'Exam',
                'meeting' => 'Meeting',
                'event' => 'Event',
                'maintenance' => 'Maintenance',
                'personal_study' => 'Personal Study',
                'workshop' => 'Workshop',
                'other' => 'Other',
                default => ucfirst(str_replace('_', ' ', $type)),
            },
        ])->toArray();
    }

    private function getBookingStatusOptions(): array
    {
        return collect(RoomBooking::getStatuses())->map(fn (string $status) => [
            'value' => $status,
            'label' => ucfirst($status),
        ])->toArray();
    }

    private function getPriorityOptions(): array
    {
        return collect(RoomBooking::getPriorities())->map(fn (string $priority) => [
            'value' => $priority,
            'label' => ucfirst($priority),
        ])->toArray();
    }

    private function getActionTypeOptions(): array
    {
        return collect(RoomBookingAction::getActionTypes())->map(fn (string $type) => [
            'value' => $type,
            'label' => ucfirst($type),
        ])->toArray();
    }

    private function getBuildingOptions(): array
    {
        return Building::forCampus(app('campus')->id)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->map(fn (Building $building) => [
                'id' => $building->id,
                'value' => (string) $building->id,
                'label' => $building->name.' ('.$building->code.')',
            ])
            ->toArray();
    }

    private function getRoomOptions(): array
    {
        return Room::query()
            ->with('building')
            ->forCampus(app('campus')->id)
            ->select('id', 'building_id', 'name', 'code', 'capacity', 'is_bookable', 'status')
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'value' => (string) $room->id,
                'label' => $room->name.' ('.$room->code.')',
                'building_id' => $room->building_id,
                'building_name' => $room->building?->name,
                'capacity' => $room->capacity,
                'is_bookable' => $room->is_bookable,
                'status' => $room->status,
            ])
            ->toArray();
    }
}
