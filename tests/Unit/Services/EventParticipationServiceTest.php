<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Student;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\EventParticipationService;
use App\Services\NotificationService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class EventParticipationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventParticipationService $service;
    private WalletService $walletService;
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->walletService = $this->createMock(WalletService::class);
        $this->notificationService = $this->createMock(NotificationService::class);

        $this->service = new EventParticipationService(
            $this->walletService,
            $this->notificationService
        );
    }

    /** @test */
    public function it_can_register_student_for_event()
    {
        $event = Event::factory()->published()->create([
            'max_participants' => 10,
            'gold_reward_amount' => 50.00
        ]);
        $student = Student::factory()->create([
            'campus_id' => $event->campus_id,
            'status' => 'active'
        ]);

        $this->notificationService
            ->expects($this->once())
            ->method('sendEventRegistrationConfirmation')
            ->with($student, $event);

        $participant = $this->service->registerStudent($event, $student);

        $this->assertInstanceOf(EventParticipant::class, $participant);
        $this->assertEquals('registered', $participant->status);
        $this->assertEquals($event->id, $participant->event_id);
        $this->assertEquals($student->id, $participant->student_id);
        $this->assertNotNull($participant->registered_at);
    }

    /** @test */
    public function it_prevents_duplicate_registration()
    {
        $event = Event::factory()->published()->create();
        $student = Student::factory()->create([
            'campus_id' => $event->campus_id,
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

    /** @test */
    public function it_prevents_registration_when_capacity_reached()
    {
        $event = Event::factory()->published()->create(['max_participants' => 1]);
        $student1 = Student::factory()->create([
            'campus_id' => $event->campus_id,
            'status' => 'active'
        ]);
        $student2 = Student::factory()->create([
            'campus_id' => $event->campus_id,
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

    /** @test */
    public function it_prevents_registration_for_draft_events()
    {
        $event = Event::factory()->create(['status' => 'draft']);
        $student = Student::factory()->create([
            'campus_id' => $event->campus_id,
            'status' => 'active'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot register for draft events');

        $this->service->registerStudent($event, $student);
    }

    /** @test */
    public function it_prevents_registration_for_inactive_students()
    {
        $event = Event::factory()->published()->create();
        $student = Student::factory()->create([
            'campus_id' => $event->campus_id,
            'status' => 'inactive'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student account is not active');

        $this->service->registerStudent($event, $student);
    }

    /** @test */
    public function it_prevents_cross_campus_registration()
    {
        $event = Event::factory()->published()->create(['campus_id' => 1]);
        $student = Student::factory()->create([
            'campus_id' => 2,
            'status' => 'active'
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student cannot register for events from other campuses');

        $this->service->registerStudent($event, $student);
    }

    /** @test */
    public function it_can_unregister_student_from_event()
    {
        $event = Event::factory()->published()->create();
        $student = Student::factory()->create([
            'campus_id' => $event->campus_id,
            'status' => 'active'
        ]);
        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'registered'
        ]);

        $this->notificationService
            ->expects($this->once())
            ->method('sendEventCancellationConfirmation')
            ->with($student, $event);

        $result = $this->service->unregisterStudent($event, $student);

        $this->assertTrue($result);
        $this->assertEquals('cancelled', $participant->fresh()->status);
    }

    /** @test */
    public function it_reclaims_gold_when_unregistering_awarded_participant()
    {
        $event = Event::factory()->published()->create(['gold_reward_amount' => 50.00]);
        $student = Student::factory()->create([
            'campus_id' => $event->campus_id,
            'status' => 'active'
        ]);
        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'completed',
            'gold_awarded' => true,
            'awarded_at' => now()
        ]);

        $mockTransaction = new WalletTransaction([
            'student_id' => $student->id,
            'amount' => -50.00,
            'type' => WalletTransaction::TYPE_SPEND
        ]);

        $this->walletService
            ->expects($this->once())
            ->method('deductGold')
            ->with(
                $student,
                50.00,
                WalletTransaction::SOURCE_EVENT,
                $event->id,
                $this->stringContains('Gold reclaimed due to event participation cancellation')
            )
            ->willReturn($mockTransaction);

        $this->notificationService
            ->expects($this->once())
            ->method('sendGoldReclaimNotification')
            ->with($student, 50.00, $event);

        $result = $this->service->unregisterStudent($event, $student);

        $this->assertTrue($result);
        $participant = $participant->fresh();
        $this->assertEquals('cancelled', $participant->status);
        $this->assertFalse($participant->gold_awarded);
        $this->assertNull($participant->awarded_at);
    }

    /** @test */
    public function it_can_check_in_registered_student()
    {
        $event = Event::factory()->ongoing()->create();
        $student = Student::factory()->create([
            'campus_id' => $event->campus_id,
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

        $this->notificationService
            ->expects($this->once())
            ->method('sendEventCheckinConfirmation')
            ->with($student, $event);

        $result = $this->service->checkInStudent($event, $student, $staff, $deviceInfo);

        $this->assertInstanceOf(EventParticipant::class, $result);
        $this->assertEquals('checked_in', $result->status);
        $this->assertNotNull($result->checkin_time);
        $this->assertEquals($staff->id, $result->checkin_staff_id);
        $this->assertEquals($deviceInfo, $result->checkin_device_info);
    }

    /** @test */
    public function it_can_register_and_check_in_student_simultaneously()
    {
        $event = Event::factory()->ongoing()->create();
        $student = Student::factory()->create([
            'campus_id' => $event->campus_id,
            'status' => 'active'
        ]);
        $staff = User::factory()->create();

        $this->notificationService
            ->expects($this->once())
            ->method('sendEventCheckinConfirmation')
            ->with($student, $event);

        $result = $this->service->checkInStudent($event, $student, $staff);

        $this->assertInstanceOf(EventParticipant::class, $result);
        $this->assertEquals('checked_in', $result->status);
        $this->assertNotNull($result->checkin_time);
        $this->assertEquals($staff->id, $result->checkin_staff_id);
    }

    /** @test */
    public function it_prevents_duplicate_check_ins()
    {
        $event = Event::factory()->ongoing()->create();
        $student = Student::factory()->create([
            'campus_id' => $event->campus_id,
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

    /** @test */
    public function it_prevents_check_in_for_events_not_started()
    {
        $event = Event::factory()->published()->future()->create();
        $student = Student::factory()->create([
            'campus_id' => $event->campus_id,
            'status' => 'active'
        ]);
        $staff = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event is not available for check-in');

        $this->service->checkInStudent($event, $student, $staff);
    }

    /** @test */
    public function it_can_complete_participation_and_award_gold()
    {
        $event = Event::factory()->past()->create(['gold_reward_amount' => 75.00]);
        $student = Student::factory()->create();
        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'checked_in',
            'checkin_time' => $event->start_time->addMinutes(30)
        ]);

        $mockTransaction = new WalletTransaction([
            'student_id' => $student->id,
            'amount' => 75.00,
            'type' => WalletTransaction::TYPE_EARN
        ]);

        $this->walletService
            ->expects($this->once())
            ->method('addGold')
            ->with(
                $student,
                75.00,
                WalletTransaction::SOURCE_EVENT,
                $event->id,
                $this->stringContains('Gold reward for attending event')
            )
            ->willReturn($mockTransaction);

        $this->notificationService
            ->expects($this->once())
            ->method('sendGoldRewardNotification')
            ->with($student, 75.00, $event);

        $result = $this->service->completeParticipation($participant);

        $this->assertEquals('completed', $result->status);
        $this->assertTrue($result->gold_awarded);
        $this->assertNotNull($result->awarded_at);
    }

    /** @test */
    public function it_prevents_double_gold_award()
    {
        $event = Event::factory()->past()->create(['gold_reward_amount' => 50.00]);
        $student = Student::factory()->create();
        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'completed',
            'gold_awarded' => true,
            'awarded_at' => now()
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gold reward has already been awarded');

        $this->service->awardGoldReward($participant);
    }

    /** @test */
    public function it_handles_gold_award_failure_gracefully()
    {
        $event = Event::factory()->past()->create(['gold_reward_amount' => 50.00]);
        $student = Student::factory()->create();
        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $student->id,
            'status' => 'completed'
        ]);

        $this->walletService
            ->expects($this->once())
            ->method('addGold')
            ->willThrowException(new \Exception('Wallet service error'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to award gold reward: Wallet service error');

        $this->service->awardGoldReward($participant);
    }

    /** @test */
    public function it_can_get_student_participations_with_filters()
    {
        $student = Student::factory()->create();
        $event1 = Event::factory()->create(['campus_id' => 1]);
        $event2 = Event::factory()->create(['campus_id' => 2]);

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

        // Test with campus filter
        $participations = $this->service->getStudentParticipations($student, ['campus_id' => 1]);
        $this->assertCount(1, $participations);
        $this->assertEquals($event1->id, $participations->first()->event_id);
    }

    /** @test */
    public function it_can_get_event_participants_with_pagination()
    {
        $event = Event::factory()->create();
        $students = Student::factory()->count(25)->create();

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

    /** @test */
    public function it_can_get_event_statistics()
    {
        $event = Event::factory()->create(['gold_reward_amount' => 25.00]);

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

    /** @test */
    public function it_sanitizes_device_info()
    {
        $event = Event::factory()->ongoing()->create();
        $student = Student::factory()->create([
            'campus_id' => $event->campus_id,
            'status' => 'active'
        ]);
        $staff = User::factory()->create();

        $deviceInfo = [
            'user_agent' => str_repeat('a', 300), // Too long
            'ip_address' => '192.168.1.1',
            'malicious_field' => 'should be removed',
            'device_type' => 'mobile'
        ];

        $this->notificationService
            ->expects($this->once())
            ->method('sendEventCheckinConfirmation');

        $result = $this->service->checkInStudent($event, $student, $staff, $deviceInfo);

        $sanitized = $result->checkin_device_info;
        $this->assertEquals(255, strlen($sanitized['user_agent'])); // Truncated
        $this->assertEquals('192.168.1.1', $sanitized['ip_address']);
        $this->assertEquals('mobile', $sanitized['device_type']);
        $this->assertArrayNotHasKey('malicious_field', $sanitized);
    }

    /** @test */
    public function it_processes_automatic_completions()
    {
        // Create ended event with checked-in participants
        $event = Event::factory()->past()->create(['gold_reward_amount' => 30.00]);
        $participants = EventParticipant::factory()->count(3)->create([
            'event_id' => $event->id,
            'status' => 'checked_in'
        ]);

        $this->walletService
            ->expects($this->exactly(3))
            ->method('addGold')
            ->willReturn(new WalletTransaction());

        $this->notificationService
            ->expects($this->exactly(3))
            ->method('sendGoldRewardNotification');

        $completedCount = $this->service->processAutomaticCompletions();

        $this->assertEquals(3, $completedCount);

        foreach ($participants as $participant) {
            $this->assertEquals('completed', $participant->fresh()->status);
        }
    }
}
