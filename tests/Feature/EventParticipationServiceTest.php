<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Student;
use App\Models\User;
use App\Services\EventParticipationService;
use App\Services\NotificationService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EventParticipationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventParticipationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Use real services for integration testing
        $this->service = app(EventParticipationService::class);
    }

    public function test_can_register_student_for_event()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->published()->create([
            'campus_id' => $campus->id,
            'max_participants' => 10,
            'gold_reward_amount' => 50.00
        ]);
        $student = Student::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'active'
        ]);

        $participant = $this->service->registerStudent($event, $student);

        $this->assertInstanceOf(EventParticipant::class, $participant);
        $this->assertEquals('registered', $participant->status);
        $this->assertEquals($event->id, $participant->event_id);
        $this->assertEquals($student->id, $participant->student_id);
        $this->assertNotNull($participant->registered_at);
    }

    public function test_prevents_duplicate_registration()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->published()->create(['campus_id' => $campus->id]);
        $student = Student::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'active'
        ]);

        // Create existing registration
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'registered'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student is already registered for this event');

        $this->service->registerStudent($event, $student);
    }

    public function test_prevents_registration_when_capacity_reached()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->published()->create([
            'campus_id' => $campus->id,
            'max_participants' => 1
        ]);
        $student1 = Student::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'active'
        ]);
        $student2 = Student::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'active'
        ]);

        // Fill capacity
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student1->id,
            'status' => 'registered'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event has reached maximum capacity');

        $this->service->registerStudent($event, $student2);
    }

    public function test_prevents_registration_for_draft_events()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->draft()->create(['campus_id' => $campus->id]);
        $student = Student::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'active'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot register for draft events');

        $this->service->registerStudent($event, $student);
    }

    public function test_prevents_registration_for_inactive_students()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->published()->create(['campus_id' => $campus->id]);
        $student = Student::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'inactive'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student account is not active');

        $this->service->registerStudent($event, $student);
    }

    public function test_prevents_cross_campus_registration()
    {
        $campus1 = Campus::factory()->create();
        $campus2 = Campus::factory()->create();
        $event = Event::factory()->published()->create(['campus_id' => $campus1->id]);
        $student = Student::factory()->create([
            'campus_id' => $campus2->id,
            'status' => 'active'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student cannot register for events from other campuses');

        $this->service->registerStudent($event, $student);
    }

    public function test_can_unregister_student_from_event()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->published()->create(['campus_id' => $campus->id]);
        $student = Student::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'active'
        ]);
        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'registered'
        ]);

        $result = $this->service->unregisterStudent($event, $student);

        $this->assertTrue($result);
        $this->assertEquals('cancelled', $participant->fresh()->status);
    }

    public function test_can_check_in_registered_student()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->ongoing()->create(['campus_id' => $campus->id]);
        $student = Student::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'active'
        ]);
        $staff = User::factory()->create();
        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'registered'
        ]);

        $deviceInfo = [
            'user_agent' => 'Mozilla/5.0...',
            'ip_address' => '192.168.1.1',
            'device_type' => 'mobile'
        ];

        $result = $this->service->checkInStudent($event, $student, $staff, $deviceInfo);

        $this->assertInstanceOf(EventParticipant::class, $result);
        $this->assertEquals('checked_in', $result->status);
        $this->assertNotNull($result->checkin_time);
        $this->assertEquals($staff->id, $result->checkin_staff_id);
        $this->assertEquals($deviceInfo, $result->checkin_device_info);
    }

    public function test_can_register_and_check_in_student_simultaneously()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->ongoing()->create(['campus_id' => $campus->id]);
        $student = Student::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'active'
        ]);
        $staff = User::factory()->create();

        $result = $this->service->checkInStudent($event, $student, $staff);

        $this->assertInstanceOf(EventParticipant::class, $result);
        $this->assertEquals('checked_in', $result->status);
        $this->assertNotNull($result->checkin_time);
        $this->assertEquals($staff->id, $result->checkin_staff_id);
    }

    public function test_prevents_duplicate_check_ins()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->ongoing()->create(['campus_id' => $campus->id]);
        $student = Student::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'active'
        ]);
        $staff = User::factory()->create();
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'checked_in'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student is already checked in to this event');

        $this->service->checkInStudent($event, $student, $staff);
    }

    public function test_prevents_check_in_for_events_not_started()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->published()->future()->create(['campus_id' => $campus->id]);
        $student = Student::factory()->create([
            'campus_id' => $campus->id,
            'status' => 'active'
        ]);
        $staff = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event is not available for check-in');

        $this->service->checkInStudent($event, $student, $staff);
    }

    public function test_can_get_event_statistics()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->create([
            'campus_id' => $campus->id,
            'gold_reward_amount' => 25.00
        ]);

        // Create participants with different statuses
        EventParticipant::factory()->count(5)->create([
            'event_id' => $event->id,
            'status' => 'registered'
        ]);
        EventParticipant::factory()->count(3)->create([
            'event_id' => $event->id,
            'status' => 'checked_in'
        ]);
        EventParticipant::factory()->count(2)->create([
            'event_id' => $event->id,
            'status' => 'completed',
            'gold_awarded' => true
        ]);
        EventParticipant::factory()->count(1)->create([
            'event_id' => $event->id,
            'status' => 'cancelled'
        ]);

        $stats = $this->service->getEventStatistics($event);

        $this->assertEquals(10, $stats['total_registered']); // registered + checked_in + completed
        $this->assertEquals(5, $stats['checked_in']); // checked_in + completed
        $this->assertEquals(2, $stats['completed']);
        $this->assertEquals(1, $stats['cancelled']);
        $this->assertEquals(2, $stats['gold_awarded_count']);
        $this->assertEquals(50.00, $stats['total_gold_awarded']); // 2 * 25.00
    }

    public function test_can_get_student_participations_with_filters()
    {
        $campus = Campus::factory()->create();
        $student = Student::factory()->create(['campus_id' => $campus->id]);
        $event1 = Event::factory()->create(['campus_id' => $campus->id]);
        $event2 = Event::factory()->create(['campus_id' => $campus->id]);

        EventParticipant::factory()->create([
            'student_id' => $student->id,
            'event_id' => $event1->id,
            'status' => 'registered'
        ]);
        EventParticipant::factory()->create([
            'student_id' => $student->id,
            'event_id' => $event2->id,
            'status' => 'completed'
        ]);

        // Test without filters
        $participations = $this->service->getStudentParticipations($student);
        $this->assertCount(2, $participations);

        // Test with status filter
        $participations = $this->service->getStudentParticipations($student, ['status' => 'registered']);
        $this->assertCount(1, $participations);
        $this->assertEquals('registered', $participations->first()->status);
    }

    public function test_can_get_event_participants_with_pagination()
    {
        $campus = Campus::factory()->create();
        $event = Event::factory()->create(['campus_id' => $campus->id]);
        $students = Student::factory()->count(25)->create(['campus_id' => $campus->id]);

        foreach ($students as $student) {
            EventParticipant::factory()->create([
                'event_id' => $event->id,
                'student_id' => $student->id
            ]);
        }

        $participants = $this->service->getEventParticipants($event, [], 10);

        $this->assertEquals(10, $participants->perPage());
        $this->assertEquals(25, $participants->total());
        $this->assertEquals(3, $participants->lastPage());
    }
}
