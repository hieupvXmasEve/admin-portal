<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveRoomBookingRequest;
use App\Http\Requests\RejectRoomBookingRequest;
use App\Http\Requests\StoreRoomBookingRequest;
use App\Http\Requests\UpdateRoomBookingRequest;
use App\Models\Building;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Services\RoomBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class RoomBookingController extends Controller
{
    public function __construct(
        private readonly RoomBookingService $bookingService
    ) {
        $this->middleware('auth');
        $this->middleware('campus.selected');

        // Apply permission middleware
        $this->middleware('can:view_room_booking')->only(['index', 'show']);
        $this->middleware('can:create_room_booking')->only(['create', 'store']);
        $this->middleware('can:edit_room_booking')->only(['edit', 'update']);
        $this->middleware('can:delete_room_booking')->only(['destroy']);
        $this->middleware('can:approve_room_booking')->only(['pending', 'approve', 'reject']);
    }

    /**
     * Display a listing of all bookings (admin/room manager view).
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'status' => 'nullable|string|in:' . implode(',', RoomBooking::getStatuses()),
            'booking_type' => 'nullable|string|in:' . implode(',', RoomBooking::getBookingTypes()),
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        // Add campus filter
        $filters = array_merge($validated, ['campus_id' => app('campus')->id]);

        $bookings = $this->bookingService->getPaginatedBookings(
            $filters,
            (int) ($validated['per_page'] ?? 15)
        );

        $statistics = $this->bookingService->getBookingStatistics();

        return Inertia::render('room-bookings/Index', [
            'bookings' => $bookings,
            'filters' => $validated,
            'statistics' => $statistics,
            'booking_types' => $this->getBookingTypeOptions(),
            'booking_statuses' => $this->getBookingStatusOptions(),
            'buildings' => $this->getBuildingOptions(),
            'rooms' => $this->getRoomOptions(),
        ]);
    }

    /**
     * Display my bookings (for current user).
     */
    public function myBookings(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:' . implode(',', RoomBooking::getStatuses()),
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $user = auth()->user();
        $bookings = $this->bookingService->getMyBookings(
            RoomBooking::BOOKED_BY_USER,
            $user->id,
            $validated,
            $validated['per_page'] ?? 15
        );

        return Inertia::render('room-bookings/MyBookings', [
            'bookings' => $bookings,
            'filters' => $validated,
            'booking_statuses' => $this->getBookingStatusOptions(),
        ]);
    }

    /**
     * Display pending bookings for approval (room manager view).
     */
    public function pending(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'room_id' => 'nullable|integer|exists:rooms,id',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'booking_type' => 'nullable|string|in:' . implode(',', RoomBooking::getBookingTypes()),
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $filters = array_merge($validated, ['campus_id' => app('campus')->id]);

        $bookings = $this->bookingService->getPendingBookings(
            $filters,
            $validated['per_page'] ?? 15
        );

        $statistics = $this->bookingService->getBookingStatistics();

        return Inertia::render('room-bookings/Pending', [
            'bookings' => $bookings,
            'filters' => $validated,
            'statistics' => $statistics,
            'booking_types' => $this->getBookingTypeOptions(),
            'buildings' => $this->getBuildingOptions(),
            'rooms' => $this->getRoomOptions(),
        ]);
    }

    /**
     * Show the form for creating a new booking.
     */
    public function create(Request $request): Response
    {
        $roomId = $request->query('room_id');
        $selectedRoom = $roomId ? Room::with(['building', 'campus'])->find($roomId) : null;

        return Inertia::render('room-bookings/Create', [
            'selected_room' => $selectedRoom,
            'booking_types' => $this->getBookingTypeOptions(),
            'priorities' => $this->getPriorityOptions(),
            'buildings' => $this->getBuildingOptions(),
            'rooms' => $this->getRoomOptions(),
        ]);
    }

    /**
     * Store a newly created booking.
     */
    public function store(StoreRoomBookingRequest $request): RedirectResponse
    {
        // FormRequest validation errors are automatically returned via Inertia
        // We only need to handle business logic errors here

        $user = auth()->user();

        if (!$user) {
            return redirect()
                ->back()
                ->withErrors(['general' => 'You must be logged in to create a booking.'])
                ->withInput();
        }

        try {
            $validated = $request->validated();

            \Log::info('Creating room booking', [
                'user_id' => $user->id,
                'room_id' => $validated['room_id'] ?? null,
                'booking_date' => $validated['booking_date'] ?? null,
            ]);

            $booking = $this->bookingService->createBooking(
                $validated,
                RoomBooking::BOOKED_BY_USER,
                $user->id,
                $user
            );

            \Log::info('Room booking created successfully', [
                'booking_id' => $booking->id,
                'status' => $booking->status,
            ]);

            $message = $booking->isApproved()
                ? "Room booking '{$booking->title}' has been created and approved."
                : "Room booking '{$booking->title}' has been submitted for approval.";

            return redirect()
                ->route('room-bookings.show', $booking)
                ->with('success', $message);
        } catch (\InvalidArgumentException $e) {
            \Log::warning('Room booking creation failed (validation): ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->except(['password', '_token']),
            ]);

            // Map business logic errors to form fields
            // Check if error message contains field names
            $errors = [];
            $message = $e->getMessage();

            // Try to map common business logic errors to form fields
            if (stripos($message, 'time slot') !== false || stripos($message, 'conflict') !== false) {
                $errors['start_time'] = $message;
                $errors['end_time'] = $message;
            } elseif (stripos($message, 'room') !== false) {
                $errors['room_id'] = $message;
            } elseif (stripos($message, 'date') !== false) {
                $errors['booking_date'] = $message;
            } else {
                // General error
                $errors['general'] = $message;
            }

            return redirect()
                ->back()
                ->withErrors($errors)
                ->withInput();
        } catch (\Exception $e) {
            \Log::error('Room booking creation failed: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->except(['password', '_token']),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withErrors(['general' => 'Failed to create booking: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display the specified booking.
     */
    public function show(RoomBooking $roomBooking): Response
    {
        // Ensure bookedBy relationship is loaded for booker_name accessor
        // The accessor now handles both object and array cases, but we should still load it
        $roomBooking->load(['room.building', 'room.campus', 'bookedBy', 'approvedBy', 'actions.actionBy']);

        return Inertia::render('room-bookings/Show', [
            'booking' => $roomBooking,
            // Permissions are now handled by usePermissions composable in frontend
        ]);
    }

    /**
     * Show the form for editing the specified booking.
     */
    public function edit(RoomBooking $roomBooking): Response
    {
        if (!$roomBooking->canBeEdited()) {
            abort(403, 'This booking cannot be edited.');
        }

        $roomBooking->load(['room.building', 'room.campus']);

        // Format booking_date to YYYY-MM-DD to avoid timezone issues
        // Get the date value before serialization to ensure correct format
        $bookingDate = $roomBooking->booking_date;
        if ($bookingDate instanceof \Carbon\Carbon) {
            // Format as YYYY-MM-DD (date only, no timezone conversion)
            $formattedDate = $bookingDate->format('Y-m-d');
        } else {
            // Fallback: parse and format
            $formattedDate = \Carbon\Carbon::parse($bookingDate)->format('Y-m-d');
        }

        $bookingData = $roomBooking->toArray();
        $bookingData['booking_date'] = $formattedDate;

        return Inertia::render('room-bookings/Edit', [
            'booking' => $bookingData,
            'booking_types' => $this->getBookingTypeOptions(),
            'priorities' => $this->getPriorityOptions(),
            'buildings' => $this->getBuildingOptions(),
            'rooms' => $this->getRoomOptions(),
        ]);
    }

    /**
     * Update the specified booking.
     */
    public function update(UpdateRoomBookingRequest $request, RoomBooking $roomBooking): RedirectResponse
    {
        if (!$roomBooking->canBeEdited()) {
            return redirect()
                ->back()
                ->withErrors(['general' => 'This booking cannot be edited.'])
                ->withInput();
        }

        try {
            $user = auth()->user();
            $booking = $this->bookingService->updateBooking(
                $roomBooking,
                $request->validated(),
                RoomBooking::BOOKED_BY_USER,
                $user->id
            );

            return redirect()
                ->route('room-bookings.show', $booking)
                ->with('success', "Booking '{$booking->title}' has been updated successfully.");
        } catch (\InvalidArgumentException $e) {
            // Map business logic errors to form fields
            $errors = [];
            $message = $e->getMessage();

            if (stripos($message, 'time slot') !== false || stripos($message, 'conflict') !== false) {
                $errors['start_time'] = $message;
                $errors['end_time'] = $message;
            } elseif (stripos($message, 'room') !== false) {
                $errors['room_id'] = $message;
            } elseif (stripos($message, 'date') !== false) {
                $errors['booking_date'] = $message;
            } else {
                $errors['general'] = $message;
            }

            return redirect()
                ->back()
                ->withErrors($errors)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['general' => 'Failed to update booking. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Approve a booking.
     */
    public function approve(ApproveRoomBookingRequest $request, RoomBooking $roomBooking): RedirectResponse
    {
        if (!$roomBooking->isPending()) {
            return redirect()
                ->back()
                ->with('error', 'This booking is not pending approval.');
        }

        try {
            $user = auth()->user();
            $booking = $this->bookingService->approveBooking(
                $roomBooking,
                'user',
                $user->id,
                $request->note
            );

            return redirect()
                ->route('room-bookings.show', $booking)
                ->with('success', "Booking '{$booking->title}' has been approved.");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to approve booking. Please try again.');
        }
    }

    /**
     * Reject a booking.
     */
    public function reject(RejectRoomBookingRequest $request, RoomBooking $roomBooking): RedirectResponse
    {
        if (!$roomBooking->isPending()) {
            return redirect()
                ->back()
                ->with('error', 'This booking is not pending approval.');
        }

        try {
            $user = auth()->user();
            $booking = $this->bookingService->rejectBooking(
                $roomBooking,
                'user',
                $user->id,
                $request->reason
            );

            return redirect()
                ->route('room-bookings.show', $booking)
                ->with('success', "Booking '{$booking->title}' has been rejected.");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to reject booking. Please try again.');
        }
    }

    /**
     * Cancel a booking.
     */
    public function cancel(Request $request, RoomBooking $roomBooking): RedirectResponse
    {
        if (!$roomBooking->canBeCancelled()) {
            return redirect()
                ->back()
                ->with('error', 'This booking cannot be cancelled.');
        }

        $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $user = auth()->user();
            $booking = $this->bookingService->cancelBooking(
                $roomBooking,
                'user',
                $user->id,
                $request->reason
            );

            return redirect()
                ->route('room-bookings.my-bookings')
                ->with('success', "Booking '{$booking->title}' has been cancelled.");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to cancel booking. Please try again.');
        }
    }

    /**
     * Remove the specified booking.
     */
    public function destroy(RoomBooking $roomBooking): RedirectResponse
    {
        try {
            $title = $roomBooking->title;
            $this->bookingService->deleteBooking($roomBooking);

            return redirect()
                ->route('room-bookings.index')
                ->with('success', "Booking '{$title}' has been deleted.");
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete booking. Please try again.');
        }
    }

    /**
     * Display the booking calendar.
     */
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

        $filters = [
            'campus_id' => app('campus')->id,
        ];

        if (!empty($validated['room_id'])) {
            $filters['room_id'] = (int) $validated['room_id'];
        }

        if (!empty($validated['building_id'])) {
            $filters['building_id'] = (int) $validated['building_id'];
        }

        // Get combined calendar data (bookings + class sessions)
        $calendarData = $this->bookingService->getCalendarData($filters, $startDate, $endDate);

        return Inertia::render('room-bookings/Calendar', [
            'calendar_data' => $calendarData,
            'filters' => array_merge($validated, [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]),
            'buildings' => $this->getBuildingOptions(),
            'rooms' => $this->getRoomOptions(),
        ]);
    }

    /**
     * Display booking history/logs.
     */
    public function logs(Request $request): Response
    {
        $validated = $request->validate([
            'booking_id' => 'nullable|integer|exists:room_bookings,id',
            'action_type' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = \App\Models\RoomBookingAction::query()
            ->with(['roomBooking.room', 'actionBy'])
            ->latest();

        if (!empty($validated['booking_id'])) {
            $query->where('room_booking_id', $validated['booking_id']);
        }

        if (!empty($validated['action_type'])) {
            $query->where('action_type', $validated['action_type']);
        }

        if (!empty($validated['start_date'])) {
            $query->whereDate('created_at', '>=', $validated['start_date']);
        }

        if (!empty($validated['end_date'])) {
            $query->whereDate('created_at', '<=', $validated['end_date']);
        }

        $logs = $query->paginate($validated['per_page'] ?? 25);

        return Inertia::render('room-bookings/Logs', [
            'logs' => $logs,
            'filters' => $validated,
            'action_types' => $this->getActionTypeOptions(),
        ]);
    }

    /**
     * API: Get room bookings and class sessions for a date range (for calendar).
     */
    public function apiGetBookings(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'nullable|integer|exists:rooms,id',
            'building_id' => 'nullable|integer|exists:buildings,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $filters = [
            'campus_id' => app('campus')->id,
        ];

        if (!empty($validated['room_id'])) {
            $filters['room_id'] = (int) $validated['room_id'];
        }

        if (!empty($validated['building_id'])) {
            $filters['building_id'] = (int) $validated['building_id'];
        }

        // Get combined calendar data (bookings + class sessions)
        $calendarData = $this->bookingService->getCalendarData(
            $filters,
            $validated['start_date'],
            $validated['end_date']
        );

        return response()->json([
            'success' => true,
            'data' => $calendarData,
        ]);
    }

    /**
     * API: Check for time slot conflicts (including class sessions).
     */
    public function apiCheckConflicts(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|integer|exists:rooms,id',
            'booking_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'exclude_booking_id' => 'nullable|integer|exists:room_bookings,id',
        ]);

        // Check all conflicts (bookings + class sessions)
        $conflicts = $this->bookingService->checkAllConflicts(
            (int) $validated['room_id'],
            $validated['booking_date'],
            $validated['start_time'],
            $validated['end_time'],
            $validated['exclude_booking_id'] ?? null
        );

        return response()->json([
            'success' => true,
            'has_conflicts' => !empty($conflicts),
            'conflicts' => $conflicts,
        ]);
    }

    // ========== HELPER METHODS ==========

    private function getBookingTypeOptions(): array
    {
        return collect(RoomBooking::getBookingTypes())->map(function ($type) {
            return [
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
            ];
        })->toArray();
    }

    private function getBookingStatusOptions(): array
    {
        return collect(RoomBooking::getStatuses())->map(function ($status) {
            return [
                'value' => $status,
                'label' => match ($status) {
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'cancelled' => 'Cancelled',
                    'completed' => 'Completed',
                    default => ucfirst($status),
                },
            ];
        })->toArray();
    }

    private function getPriorityOptions(): array
    {
        return collect(RoomBooking::getPriorities())->map(function ($priority) {
            return [
                'value' => $priority,
                'label' => ucfirst($priority),
            ];
        })->toArray();
    }

    private function getActionTypeOptions(): array
    {
        return collect(\App\Models\RoomBookingAction::getActionTypes())->map(function ($type) {
            return [
                'value' => $type,
                'label' => ucfirst($type),
            ];
        })->toArray();
    }

    private function getBuildingOptions(): array
    {
        return Building::forCampus(app('campus')->id)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get()
            ->map(function ($building) {
                return [
                    'id' => $building->id,
                    'value' => (string) $building->id,
                    'label' => $building->name . ' (' . $building->code . ')',
                ];
            })
            ->toArray();
    }

    private function getRoomOptions(): array
    {
        return Room::forCampus(app('campus')->id)
            ->with('building')
            ->where('is_bookable', true)
            ->where('status', Room::STATUS_AVAILABLE)
            ->orderBy('name')
            ->get()
            ->map(function ($room) {
                return [
                    'id' => $room->id,
                    'value' => (string) $room->id,
                    'label' => $room->name . ' (' . $room->code . ')',
                    'building_id' => $room->building_id,
                    'capacity' => $room->capacity,
                ];
            })
            ->toArray();
    }
}
