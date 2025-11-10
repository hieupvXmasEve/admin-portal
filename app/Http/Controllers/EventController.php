<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Http\Resources\EventParticipantResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventParticipationService;
use App\Services\EventService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(
        private EventService $eventService,
        private EventParticipationService $eventParticipationService
    ) {}

    /**
     * Display a listing of events
     */
    public function index(Request $request): Response
    {

        $currentCampusId = session('current_campus_id');
        $filters = $request->only(['search', 'status', 'date_from', 'date_to', 'per_page']);
        $perPage = (int) ($filters['per_page'] ?? 10);

        $events = $this->eventService->getEventsForCampus($currentCampusId, $filters, $perPage);

        // Transform events and add can_delete flag
        $eventsCollection = EventResource::collection($events);
        $eventsResponse = $eventsCollection->response($request)->getData(true);

        // Add can_delete flag to each event
        if (isset($eventsResponse['data']) && is_array($eventsResponse['data'])) {
            foreach ($eventsResponse['data'] as &$eventData) {
                // Find the original event model to check if it has participants
                $originalEvent = $events->firstWhere('id', $eventData['id']);
                $eventData['can_delete'] = $originalEvent ? $originalEvent->getRegisteredCount() === 0 : false;
            }
        }

        // Restructure to match DataPagination component expectations
        $paginationData = [
            'data' => $eventsResponse['data'] ?? [],
            'links' => $eventsResponse['links'] ?? [],
            'current_page' => $eventsResponse['meta']['current_page'] ?? 1,
            'last_page' => $eventsResponse['meta']['last_page'] ?? 1,
            'per_page' => $eventsResponse['meta']['per_page'] ?? $perPage,
            'total' => $eventsResponse['meta']['total'] ?? 0,
            'from' => $eventsResponse['meta']['from'] ?? null,
            'to' => $eventsResponse['meta']['to'] ?? null,
            'prev_page_url' => $eventsResponse['links']['prev'] ?? null,
            'next_page_url' => $eventsResponse['links']['next'] ?? null,
        ];

        return Inertia::render('Events/EventList', [
            'events' => $paginationData,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new event
     */
    public function create(): Response
    {

        return Inertia::render('Events/EventForm', [
            'event' => null,
            'isEditing' => false,
            'isManual' => false,
        ]);
    }

    /**
     * Show the form for creating a manual/historical event
     */
    public function createManual(): Response
    {

        return Inertia::render('Events/EventForm', [
            'event' => null,
            'isEditing' => false,
            'isManual' => true,
        ]);
    }

    /**
     * Store a newly created event
     */
    public function store(EventRequest $request)
    {

        $campus = session('current_campus_id');
        if (! $campus) {
            abort(403, 'User must be associated with a campus');
        }

        $eventData = array_merge($request->validated(), [
            'campus_id' => $campus,
            'organizer_type' => 'school',
            'organizer_id' => $campus,
        ]);

        $event = $this->eventService->createEvent($eventData, $request->user());

        return redirect()->route('events.show', $event)
            ->with('success', 'Event created successfully.');
    }

    /**
     * Store a newly created manual event
     */
    public function storeManual(EventRequest $request)
    {

        $campus = session('current_campus_id');
        if (! $campus) {
            abort(403, 'User must be associated with a campus');
        }

        $eventData = array_merge($request->validated(), [
            'campus_id' => $campus,
            'organizer_type' => 'school',
            'organizer_id' => $campus,
        ]);

        $event = $this->eventService->createManualEvent($eventData, $request->user());

        return redirect()->route('events.show', $event)
            ->with('success', 'Manual event created successfully.');
    }

    /**
     * Display the specified event
     */
    public function show(Request $request, Event $event): Response
    {

        $statistics = $this->eventService->getEventStatistics($event);

        // Fetch all participants
        $participants = $event->participants()
            ->with(['student.program', 'student.specialization', 'checkinStaff'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Events/EventDetails', [
            'event' => new EventResource($event->load(['creator', 'campus'])),
            'statistics' => $statistics,
            'participants' => EventParticipantResource::collection($participants)->resolve(),
            'can' => [
                'delete' => $event->getRegisteredCount() === 0,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified event
     */
    public function edit(Event $event): Response
    {

        return Inertia::render('Events/EventForm', [
            'event' => new EventResource($event),
            'isEditing' => true,
        ]);
    }

    /**
     * Update the specified event
     */
    public function update(EventRequest $request, Event $event)
    {

        $this->eventService->updateEvent($event, $request->validated());

        return redirect()->route('events.show', $event)
            ->with('success', 'Event updated successfully.');
    }

    /**
     * Remove the specified event
     */
    public function destroy(Event $event)
    {
        // Check if event has any participants
        if ($event->getRegisteredCount() > 0) {
            return back()->with('error', 'Cannot delete event with registered participants.');
        }

        $event->delete();

        return redirect()->route('events.index')
            ->with('success', 'Event deleted successfully.');
    }

    /**
     * Publish the specified event
     */
    public function publish(Event $event)
    {

        $this->eventService->publishEvent($event);

        return back()->with('success', 'Event published successfully.');
    }

    /**
     * Cancel the specified event
     */
    public function cancel(Request $request, Event $event)
    {

        $reason = $request->input('reason');
        $this->eventService->cancelEvent($event, $reason);

        return back()->with('success', 'Event cancelled successfully.');
    }

    /**
     * Complete the specified event
     */
    public function complete(Event $event)
    {

        $this->eventService->completeEvent($event);

        return back()->with('success', 'Event completed successfully.');
    }

    /**
     * Show the QR scanner interface
     */
    public function scanner(Request $request, Event $event): Response
    {
        $campus = session('current_campus_id');
        if (! $campus) {
            abort(403, 'User must be associated with a campus');
        }

        if ((int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        return Inertia::render('Events/EventQRScanner', [
            'event' => (new EventResource($event->loadMissing(['campus', 'creator'])))->resolve(),
        ]);
    }

    /**
     * Show manual participant management interface
     */
    public function manageParticipants(Request $request, Event $event): Response
    {
        $campus = session('current_campus_id');
        if (! $campus) {
            abort(403, 'User must be associated with a campus');
        }

        if ((int) $event->campus_id !== (int) $campus) {
            abort(403, 'You are not authorized to manage this event');
        }

        // if (! $event->isManual()) {
        //     abort(403, 'Participant management is only available for manual events');
        // }

        $filters = $request->only(['status', 'search', 'gold_awarded']);

        // Normalize filters (treat "all" or empty as null, convert boolean flags)
        $normalizedFilters = [];
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $normalizedFilters['status'] = $filters['status'];
        }

        if (! empty($filters['search'])) {
            $normalizedFilters['search'] = $filters['search'];
        }

        if (isset($filters['gold_awarded']) && $filters['gold_awarded'] !== '' && $filters['gold_awarded'] !== 'all') {
            if ($filters['gold_awarded'] === 'true') {
                $normalizedFilters['gold_awarded'] = true;
            } elseif ($filters['gold_awarded'] === 'false') {
                $normalizedFilters['gold_awarded'] = false;
            }
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $participants = $this->eventParticipationService->getEventParticipants(
            $event,
            $normalizedFilters,
            $perPage
        );
        $participantResource = EventParticipantResource::collection($participants);
        $participantArray = $participantResource->response($request)->getData(true);

        return Inertia::render('Events/ManualParticipants', [
            'event' => new EventResource($event->load(['creator', 'campus'])),
            'participants' => $participantArray['data'] ?? [],
            'participantsPagination' => [
                'current_page' => $participants->currentPage(),
                'last_page' => $participants->lastPage(),
                'per_page' => $participants->perPage(),
                'total' => $participants->total(),
                'from' => $participants->firstItem(),
                'to' => $participants->lastItem(),
                'prev_page_url' => $participants->previousPageUrl(),
                'next_page_url' => $participants->nextPageUrl(),
                'links' => $participantArray['links'] ?? [],
            ],
            'participantFilters' => [
                'status' => $filters['status'] ?? '',
                'search' => $filters['search'] ?? '',
                'gold_awarded' => $filters['gold_awarded'] ?? '',
            ],
        ]);
    }
}
