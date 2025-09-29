<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Http\Resources\EventParticipantResource;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use App\Services\EventParticipationService;
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

        $campus = $request->user()->campuses()->first();
        if (!$campus) {
            abort(403, 'User must be associated with a campus');
        }

        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);
        $events = $this->eventService->getEventsForCampus($campus->id, $filters);

        return Inertia::render('Events/EventList', [
            'events' => EventResource::collection($events),
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

        $campus = $request->user()->campuses()->first();
        if (!$campus) {
            abort(403, 'User must be associated with a campus');
        }

        $eventData = array_merge($request->validated(), [
            'campus_id' => $campus->id,
            'organizer_type' => 'school',
            'organizer_id' => $campus->id,
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

        $campus = $request->user()->campuses()->first();
        if (!$campus) {
            abort(403, 'User must be associated with a campus');
        }

        $eventData = array_merge($request->validated(), [
            'campus_id' => $campus->id,
            'organizer_type' => 'school',
            'organizer_id' => $campus->id,
        ]);

        $event = $this->eventService->createManualEvent($eventData, $request->user());

        return redirect()->route('events.show', $event)
            ->with('success', 'Manual event created successfully.');
    }

    /**
     * Display the specified event
     */
    public function show(Event $event): Response
    {

        $statistics = $this->eventService->getEventStatistics($event);

        return Inertia::render('Events/EventDetails', [
            'event' => new EventResource($event->load(['creator', 'campus'])),
            'statistics' => $statistics,
            'can' => [
                'update' => request()->user()->can('update', $event),
                'delete' => request()->user()->can('delete', $event),
                'publish' => request()->user()->can('publish', $event),
                'cancel' => request()->user()->can('cancel', $event),
            ]
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
        $campus = $request->user()->campuses()->first();
        if (!$campus) {
            abort(403, 'User must be associated with a campus');
        }

        if ((int) $event->campus_id !== (int) $campus->id) {
            abort(403, 'You are not authorized to manage this event');
        }

        return Inertia::render('Events/EventQRScanner', [
            'event' => (new EventResource($event->loadMissing(['campus', 'creator'])))->resolve(),
            'can' => [
                'checkin' => $request->user()->can('events.checkin'),
            ],
        ]);
    }

    /**
     * Show manual participant management interface
     */
    public function manageParticipants(Request $request, Event $event): Response
    {
        $campus = $request->user()->campuses()->first();
        if (!$campus) {
            abort(403, 'User must be associated with a campus');
        }

        if ((int) $event->campus_id !== (int) $campus->id) {
            abort(403, 'You are not authorized to manage this event');
        }

        if (!$event->isManual()) {
            abort(403, 'Participant management is only available for manual events');
        }

        $filters = $request->only(['status', 'search', 'gold_awarded']);

        // Normalize filters (treat "all" or empty as null, convert boolean flags)
        $normalizedFilters = [];
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $normalizedFilters['status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
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
            'can' => [
                'manage_participants' => $request->user()->can('events.manage_participants'),
            ],
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
