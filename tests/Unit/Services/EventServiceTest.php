<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\EventService;
use App\Services\QRCodeService;
use App\Services\NotificationService;
use App\Models\Event;
use App\Models\User;
use App\Models\Campus;
use App\Models\EventParticipant;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Mockery;
use Carbon\Carbon;

class EventServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EventService $eventService;
    protected $qrCodeService;
    protected $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->qrCodeService = Mockery::mock(QRCodeService::class);
        $this->notificationService = Mockery::mock(NotificationService::class);

        $this->eventService = new EventService(
            $this->qrCodeService,
            $this->notificationService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_create_an_event()
    {
        $campus = Campus::factory()->create();
        $user = User::factory()->create();

        $this->qrCodeService
            ->shouldReceive('generateEventQRCode')
            ->once()
            ->andReturn('EVTABC123XYZ789');

        $eventData = [
            'campus_id' => $campus->id,
            'title' => 'Test Event',
            'description' => 'Test Description',
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDay()->addHours(2)->toDateTimeString(),
            'location' => 'Test Location',
            'gold_reward_amount' => 10.50,
            'max_participants' => 100,
        ];

        $event = $this->eventService->createEvent($eventData, $user);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals('Test Event', $event->title);
        $this->assertEquals('draft', $event->status);
        $this->assertEquals('EVTABC123XYZ789', $event->qr_code);
        $this->assertEquals($user->id, $event->created_by_user_id);
        $this->assertDatabaseHas('events', [
            'title' => 'Test Event',
            'status' => 'draft',
            'qr_code' => 'EVTABC123XYZ789'
        ]);
    }

    /** @test */
    public function it_validates_event_data_on_creation()
    {
        $user = User::factory()->create();

        $eventData = [
            'campus_id' => 999, // Non-existent campus
            'title' => 'Test Event',
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDay()->addHours(2)->toDateTimeString(),
            'location' => 'Test Location',
        ];

        $this->expectException(ValidationException::class);
        $this->eventService->createEvent($eventData, $user);
    }

    /** @test */
    public function it_validates_start_time_is_before_end_time()
    {
        $campus = Campus::factory()->create();
        $user = User::factory()->create();

        $eventData = [
            'campus_id' => $campus->id,
            'title' => 'Test Event',
            'start_time' => now()->addDay()->addHours(2)->toDateTimeString(),
            'end_time' => now()->addDay()->toDateTimeString(), // End before start
            'location' => 'Test Location',
        ];

        $this->expectException(ValidationException::class);
        $this->eventService->createEvent($eventData, $user);
    }

    /** @test */
    public function it_validates_start_time_is_in_future()
    {
        $campus = Campus::factory()->create();
        $user = User::factory()->create();

        $eventData = [
            'campus_id' => $campus->id,
            'title' => 'Test Event',
            'start_time' => now()->subHour()->toDateTimeString(), // Past time
            'end_time' => now()->addHour()->toDateTimeString(),
            'location' => 'Test Location',
        ];

        $this->expectException(ValidationException::class);
        $this->eventService->createEvent($eventData, $user);
    }

    /** @test */
    public function it_can_update_an_event()
    {
        $event = Event::factory()->create([
            'title' => 'Original Title',
            'status' => 'draft'
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'description' => 'Updated Description',
        ];

        $updatedEvent = $this->eventService->updateEvent($event, $updateData);

        $this->assertEquals('Updated Title', $updatedEvent->title);
        $this->assertEquals('Updated Description', $updatedEvent->description);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Updated Title'
        ]);
    }

    /** @test */
    public function it_notifies_participants_on_significant_updates()
    {
        $event = Event::factory()->create(['status' => 'published']);

        $this->notificationService
            ->shouldReceive('notifyEventUpdate')
            ->once();

        $updateData = [
            'title' => 'Significantly Updated Title', // Significant change
        ];

        $this->eventService->updateEvent($event, $updateData);
    }

    /** @test */
    public function it_can_publish_an_event()
    {
        $event = Event::factory()->create(['status' => 'draft']);

        $this->notificationService
            ->shouldReceive('notifyEventPublication')
            ->once()
            ->with($event);

        $publishedEvent = $this->eventService->publishEvent($event);

        $this->assertEquals('published', $publishedEvent->status);
        $this->assertNotNull($publishedEvent->published_at);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'status' => 'published'
        ]);
    }

    /** @test */
    public function it_cannot_publish_non_draft_event()
    {
        $event = Event::factory()->create(['status' => 'published']);

        $this->expectException(ValidationException::class);
        $this->eventService->publishEvent($event);
    }

    /** @test */
    public function it_can_cancel_an_event()
    {
        $event = Event::factory()->create(['status' => 'published']);
        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'status' => 'registered'
        ]);

        $this->notificationService
            ->shouldReceive('notifyEventCancellation')
            ->once()
            ->with($event, 'Test reason');

        $cancelledEvent = $this->eventService->cancelEvent($event, 'Test reason');

        $this->assertEquals('cancelled', $cancelledEvent->status);
        $this->assertNotNull($cancelledEvent->cancelled_at);

        // Check participant was cancelled
        $participant->refresh();
        $this->assertEquals('cancelled', $participant->status);
    }

    /** @test */
    public function it_cannot_cancel_already_cancelled_event()
    {
        $event = Event::factory()->create(['status' => 'cancelled']);

        $this->expectException(ValidationException::class);
        $this->eventService->cancelEvent($event);
    }

    /** @test */
    public function it_can_complete_an_event()
    {
        $event = Event::factory()->create([
            'status' => 'published',
            'end_time' => now()->subHour() // Event has ended
        ]);

        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'status' => 'checked_in'
        ]);

        $completedEvent = $this->eventService->completeEvent($event);

        $this->assertEquals('completed', $completedEvent->status);
        $this->assertNotNull($completedEvent->completed_at);

        // Check participant was completed
        $participant->refresh();
        $this->assertEquals('completed', $participant->status);
    }

    /** @test */
    public function it_cannot_complete_event_before_end_time()
    {
        $event = Event::factory()->create([
            'status' => 'published',
            'end_time' => now()->addHour() // Event hasn't ended
        ]);

        $this->expectException(ValidationException::class);
        $this->eventService->completeEvent($event);
    }

    /** @test */
    public function it_can_get_events_for_campus_with_filters()
    {
        $campus = Campus::factory()->create();
        $otherCampus = Campus::factory()->create();

        // Create events for the campus
        Event::factory()->create([
            'campus_id' => $campus->id,
            'title' => 'Campus Event 1',
            'status' => 'published'
        ]);

        Event::factory()->create([
            'campus_id' => $campus->id,
            'title' => 'Campus Event 2',
            'status' => 'draft'
        ]);

        // Create event for other campus
        Event::factory()->create([
            'campus_id' => $otherCampus->id,
            'title' => 'Other Campus Event'
        ]);

        // Test basic campus filter
        $events = $this->eventService->getEventsForCampus($campus->id);
        $this->assertCount(2, $events);

        // Test status filter
        $publishedEvents = $this->eventService->getEventsForCampus($campus->id, [
            'status' => 'published'
        ]);
        $this->assertCount(1, $publishedEvents);

        // Test search filter
        $searchEvents = $this->eventService->getEventsForCampus($campus->id, [
            'search' => 'Event 1'
        ]);
        $this->assertCount(1, $searchEvents);
        $this->assertEquals('Campus Event 1', $searchEvents->first()->title);
    }

    /** @test */
    public function it_can_get_event_statistics()
    {
        $event = Event::factory()->create([
            'max_participants' => 10,
            'gold_reward_amount' => 5.0
        ]);

        // Create participants with different statuses
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'status' => 'registered'
        ]);

        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'status' => 'checked_in'
        ]);

        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'status' => 'completed',
            'gold_awarded' => true
        ]);

        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'status' => 'cancelled'
        ]);

        $stats = $this->eventService->getEventStatistics($event);

        $this->assertEquals(3, $stats['registered_count']); // registered + checked_in + completed
        $this->assertEquals(2, $stats['checked_in_count']); // checked_in + completed
        $this->assertEquals(1, $stats['completed_count']);
        $this->assertEquals(1, $stats['cancelled_count']);
        $this->assertEquals(5.0, $stats['total_gold_distributed']);
    }

    /** @test */
    public function it_validates_max_participants_against_current_registrations()
    {
        $event = Event::factory()->create(['max_participants' => 5]);

        // Create 3 registered participants
        EventParticipant::factory()->count(3)->create([
            'event_id' => $event->id,
            'status' => 'registered'
        ]);

        // Try to update max_participants to less than current registrations
        $updateData = ['max_participants' => 2];

        $this->expectException(ValidationException::class);
        $this->eventService->updateEvent($event, $updateData);
    }

    /** @test */
    public function it_can_generate_qr_code_for_event()
    {
        $event = Event::factory()->create(['qr_code' => 'EVTTEST123']);

        $this->qrCodeService
            ->shouldReceive('generateQRCodeImage')
            ->once()
            ->with('EVTTEST123')
            ->andReturn('data:image/svg+xml;base64,PHN2Zz4uLi48L3N2Zz4=');

        $qrCodeImage = $this->eventService->generateQRCode($event);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qrCodeImage);
    }

    /** @test */
    public function it_handles_database_transactions_properly()
    {
        $campus = Campus::factory()->create();
        $user = User::factory()->create();

        $this->qrCodeService
            ->shouldReceive('generateEventQRCode')
            ->once()
            ->andThrow(new \Exception('QR Code generation failed'));

        $eventData = [
            'campus_id' => $campus->id,
            'title' => 'Test Event',
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDay()->addHours(2)->toDateTimeString(),
            'location' => 'Test Location',
        ];

        $this->expectException(\Exception::class);

        try {
            $this->eventService->createEvent($eventData, $user);
        } catch (\Exception $e) {
            // Verify no event was created due to transaction rollback
            $this->assertDatabaseMissing('events', [
                'title' => 'Test Event'
            ]);
            throw $e;
        }
    }
}
