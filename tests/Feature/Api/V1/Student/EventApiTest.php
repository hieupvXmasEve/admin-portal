<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Student;

use App\Models\Campus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventApiTest extends TestCase
{
    use RefreshDatabase;

    private Campus $campus;
    private Student $student;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campus = Campus::factory()->create();
        $this->user = User::factory()->create();
        $this->student = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'status' => 'active',
            'user_id' => $this->user->id
        ]);

        // Authenticate as student
        Sanctum::actingAs($this->user);
    }

    public function test_can_list_published_events_for_campus()
    {
        // Create events for the student's campus
        $publishedEvent = Event::factory()->published()->create([
            'campus_id' => $this->campus->id,
            'title' => 'Published Event',
            'gold_reward_amount' => 50.00
        ]);

        // Create draft event (should not appear)
        Event::factory()->draft()->create([
            'campus_id' => $this->campus->id,
            'title' => 'Draft Event'
        ]);

        // Create event for different campus (should not appear)
        $otherCampus = Campus::factory()->create();
        Event::factory()->published()->create([
            'campus_id' => $otherCampus->id,
            'title' => 'Other Campus Event'
        ]);

        $response = $this->getJson('/api/v1/student/events');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'events' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'location',
                            'start_time',
                            'end_time',
                            'gold_reward_amount',
                            'status',
                            'flags' => [
                                'is_published',
                                'can_register',
                                'has_reached_capacity'
                            ],
                            'participation' => [
                                'registered_count',
                                'available_spots'
                            ]
                        ]
                    ],
                    'pagination'
                ]
            ])
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.title', 'Published Event')
            ->assertJsonPath('data.events.0.gold_reward_amount', 50.0);
    }

    public function test_can_filter_events_by_search()
    {
        Event::factory()->published()->create([
            'campus_id' => $this->campus->id,
            'title' => 'Tech Conference',
            'description' => 'A technology conference'
        ]);

        Event::factory()->published()->create([
            'campus_id' => $this->campus->id,
            'title' => 'Art Exhibition',
            'description' => 'An art exhibition'
        ]);

        $response = $this->getJson('/api/v1/student/events?search=tech');

        $response->assertOk()
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.title', 'Tech Conference');
    }

    public function test_can_filter_events_by_time()
    {
        // Create upcoming event
        Event::factory()->published()->future()->create([
            'campus_id' => $this->campus->id,
            'title' => 'Future Event'
        ]);

        // Create past event
        Event::factory()->published()->past()->create([
            'campus_id' => $this->campus->id,
            'title' => 'Past Event'
        ]);

        $response = $this->getJson('/api/v1/student/events?time_filter=upcoming');

        $response->assertOk()
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.title', 'Future Event');
    }

    public function test_can_get_event_details()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id,
            'title' => 'Test Event',
            'description' => 'Test Description',
            'location' => 'Test Location',
            'gold_reward_amount' => 25.00,
            'max_participants' => 100
        ]);

        $response = $this->getJson("/api/v1/student/events/{$event->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'event' => [
                        'id',
                        'title',
                        'description',
                        'location',
                        'start_time',
                        'end_time',
                        'gold_reward_amount',
                        'max_participants',
                        'status',
                        'flags',
                        'participation',
                        'statistics',
                        'timing'
                    ]
                ]
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.title', 'Test Event')
            ->assertJsonPath('data.event.gold_reward_amount', 25.0);
    }

    public function test_cannot_get_draft_event_details()
    {
        $event = Event::factory()->draft()->create([
            'campus_id' => $this->campus->id
        ]);

        $response = $this->getJson("/api/v1/student/events/{$event->id}");

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Event not found');
    }

    public function test_cannot_get_event_from_other_campus()
    {
        $otherCampus = Campus::factory()->create();
        $event = Event::factory()->published()->create([
            'campus_id' => $otherCampus->id
        ]);

        $response = $this->getJson("/api/v1/student/events/{$event->id}");

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Event not found');
    }

    public function test_can_register_for_event()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id,
            'max_participants' => 10
        ]);

        $response = $this->postJson("/api/v1/student/events/{$event->id}/register");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'message',
                    'participation' => [
                        'id',
                        'status',
                        'registered_at',
                        'event',
                        'flags'
                    ]
                ]
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Successfully registered for event')
            ->assertJsonPath('data.participation.status', 'registered');

        // Verify database record
        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'student_id' => $this->student->id,
            'status' => 'registered'
        ]);
    }

    public function test_cannot_register_for_draft_event()
    {
        $event = Event::factory()->draft()->create([
            'campus_id' => $this->campus->id
        ]);

        $response = $this->postJson("/api/v1/student/events/{$event->id}/register");

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot register for draft events');
    }

    public function test_cannot_register_for_event_at_capacity()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id,
            'max_participants' => 1
        ]);

        // Fill capacity with another student
        $otherStudent = Student::factory()->create(['campus_id' => $this->campus->id]);
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $otherStudent->id,
            'status' => 'registered'
        ]);

        $response = $this->postJson("/api/v1/student/events/{$event->id}/register");

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Event has reached maximum capacity');
    }

    public function test_cannot_register_twice_for_same_event()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id
        ]);

        // Create existing registration
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $this->student->id,
            'status' => 'registered'
        ]);

        $response = $this->postJson("/api/v1/student/events/{$event->id}/register");

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Student is already registered for this event');
    }

    public function test_cannot_register_for_event_from_other_campus()
    {
        $otherCampus = Campus::factory()->create();
        $event = Event::factory()->published()->create([
            'campus_id' => $otherCampus->id
        ]);

        $response = $this->postJson("/api/v1/student/events/{$event->id}/register");

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Event not found');
    }

    public function test_can_unregister_from_event()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id
        ]);

        // Create existing registration
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $this->student->id,
            'status' => 'registered'
        ]);

        $response = $this->deleteJson("/api/v1/student/events/{$event->id}/register");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.message', 'Successfully unregistered from event');

        // Verify database record is updated
        $this->assertDatabaseHas('event_participants', [
            'event_id' => $event->id,
            'student_id' => $this->student->id,
            'status' => 'cancelled'
        ]);
    }

    public function test_cannot_unregister_from_event_not_registered_for()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id
        ]);

        $response = $this->deleteJson("/api/v1/student/events/{$event->id}/register");

        $response->assertBadRequest()
            ->assertJsonPath('success', false);
    }

    public function test_can_get_my_event_participations()
    {
        $event1 = Event::factory()->published()->create(['campus_id' => $this->campus->id]);
        $event2 = Event::factory()->published()->create(['campus_id' => $this->campus->id]);

        // Create participations
        EventParticipant::factory()->create([
            'event_id' => $event1->id,
            'student_id' => $this->student->id,
            'status' => 'registered'
        ]);
        EventParticipant::factory()->create([
            'event_id' => $event2->id,
            'student_id' => $this->student->id,
            'status' => 'completed',
            'gold_awarded' => true
        ]);

        $response = $this->getJson('/api/v1/student/events/my/participations');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'participations' => [
                        '*' => [
                            'id',
                            'status',
                            'registered_at',
                            'gold_awarded',
                            'event' => [
                                'id',
                                'title',
                                'start_time',
                                'end_time'
                            ],
                            'flags'
                        ]
                    ],
                    'pagination'
                ]
            ])
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.participations');
    }

    public function test_can_filter_my_participations_by_status()
    {
        $event1 = Event::factory()->published()->create(['campus_id' => $this->campus->id]);
        $event2 = Event::factory()->published()->create(['campus_id' => $this->campus->id]);

        EventParticipant::factory()->create([
            'event_id' => $event1->id,
            'student_id' => $this->student->id,
            'status' => 'registered'
        ]);
        EventParticipant::factory()->create([
            'event_id' => $event2->id,
            'student_id' => $this->student->id,
            'status' => 'completed'
        ]);

        $response = $this->getJson('/api/v1/student/events/my/participations?status=registered');

        $response->assertOk()
            ->assertJsonCount(1, 'data.participations')
            ->assertJsonPath('data.participations.0.status', 'registered');
    }

    public function test_can_get_my_event_participation_details()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id,
            'title' => 'Test Event'
        ]);

        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $this->student->id,
            'status' => 'checked_in',
            'gold_awarded' => true
        ]);

        $response = $this->getJson("/api/v1/student/events/my/{$event->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'participation' => [
                        'id',
                        'status',
                        'registered_at',
                        'checkin_time',
                        'gold_awarded',
                        'event' => [
                            'id',
                            'title',
                            'campus'
                        ],
                        'flags',
                        'timing'
                    ]
                ]
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.participation.status', 'checked_in')
            ->assertJsonPath('data.participation.gold_awarded', true);
    }

    public function test_cannot_get_participation_details_for_unregistered_event()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id
        ]);

        $response = $this->getJson("/api/v1/student/events/my/{$event->id}");

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No participation found for this event');
    }

    public function test_pagination_works_for_events_list()
    {
        // Create 25 events
        Event::factory()->count(25)->published()->create([
            'campus_id' => $this->campus->id
        ]);

        $response = $this->getJson('/api/v1/student/events?per_page=10&page=1');

        $response->assertOk()
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 25)
            ->assertJsonPath('data.pagination.last_page', 3)
            ->assertJsonCount(10, 'data.events');
    }

    public function test_pagination_works_for_my_participations()
    {
        $events = Event::factory()->count(25)->published()->create([
            'campus_id' => $this->campus->id
        ]);

        foreach ($events as $event) {
            EventParticipant::factory()->create([
                'event_id' => $event->id,
                'student_id' => $this->student->id
            ]);
        }

        $response = $this->getJson('/api/v1/student/events/my/participations?per_page=10&page=2');

        $response->assertOk()
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 25)
            ->assertJsonCount(10, 'data.participations');
    }

    public function test_requires_authentication()
    {
        // Clear authentication
        Sanctum::actingAs(null);

        $response = $this->getJson('/api/v1/student/events');

        $response->assertUnauthorized();
    }

    public function test_handles_server_errors_gracefully()
    {
        // Create an event that will cause an error (invalid campus_id)
        $event = Event::factory()->published()->make([
            'campus_id' => 99999 // Non-existent campus
        ]);
        $event->save(['timestamps' => false]); // Skip validation

        $response = $this->getJson('/api/v1/student/events');

        // Should handle the error gracefully and return proper error response
        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Failed to fetch events');
    }

    public function test_event_includes_student_participation_status()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id
        ]);

        // Create participation for the student
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $this->student->id,
            'status' => 'registered'
        ]);

        $response = $this->getJson("/api/v1/student/events/{$event->id}");

        $response->assertOk()
            ->assertJsonPath('data.event.my_participation.status', 'registered')
            ->assertJsonPath('data.event.my_participation.can_cancel', true);
    }

    public function test_respects_per_page_limit()
    {
        Event::factory()->count(100)->published()->create([
            'campus_id' => $this->campus->id
        ]);

        // Test that per_page is capped at 50
        $response = $this->getJson('/api/v1/student/events?per_page=100');

        $response->assertOk()
            ->assertJsonPath('data.pagination.per_page', 50)
            ->assertJsonCount(50, 'data.events');
    }
}
