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
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\TestCase;

class EventParticipationServiceUnitTest extends TestCase
{
    private EventParticipationService $service;
    private $walletService;
    private $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->walletService = Mockery::mock(WalletService::class);
        $this->notificationService = Mockery::mock(NotificationService::class);

        $this->service = new EventParticipationService(
            $this->walletService,
            $this->notificationService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_validates_event_registration_requirements()
    {
        // Create mock event that cannot accept registrations
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('canRegister')->andReturn(false);
        $event->shouldReceive('isDraft')->andReturn(true);

        $student = Mockery::mock(Student::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot register for draft events');

        $this->service->registerStudent($event, $student);
    }

    public function test_validates_student_registration_requirements()
    {
        // Create mock event that can accept registrations
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('canRegister')->andReturn(true);
        $event->shouldReceive('getStudentParticipation')->with(1)->andReturn(null);
        $event->shouldReceive('hasReachedCapacity')->andReturn(false);
        $event->campus_id = 1;

        // Create mock student that is inactive
        $student = Mockery::mock(Student::class);
        $student->shouldReceive('isActive')->andReturn(false);
        $student->id = 1;
        $student->campus_id = 1;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student account is not active');

        $this->service->registerStudent($event, $student);
    }

    public function test_validates_cross_campus_registration()
    {
        // Create mock event
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('canRegister')->andReturn(true);
        $event->shouldReceive('getStudentParticipation')->with(1)->andReturn(null);
        $event->shouldReceive('hasReachedCapacity')->andReturn(false);
        $event->campus_id = 1;

        // Create mock student from different campus
        $student = Mockery::mock(Student::class);
        $student->shouldReceive('isActive')->andReturn(true);
        $student->id = 1;
        $student->campus_id = 2; // Different campus

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student cannot register for events from other campuses');

        $this->service->registerStudent($event, $student);
    }

    public function test_validates_capacity_limits()
    {
        // Create mock event at capacity
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('canRegister')->andReturn(true);
        $event->shouldReceive('getStudentParticipation')->with(1)->andReturn(null);
        $event->shouldReceive('hasReachedCapacity')->andReturn(true);

        $student = Mockery::mock(Student::class);
        $student->id = 1;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event has reached maximum capacity');

        $this->service->registerStudent($event, $student);
    }

    public function test_prevents_duplicate_registration()
    {
        // Create existing active participation
        $existingParticipant = Mockery::mock(EventParticipant::class);
        $existingParticipant->shouldReceive('isActive')->andReturn(true);

        // Create mock event
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('canRegister')->andReturn(true);
        $event->shouldReceive('getStudentParticipation')->with(1)->andReturn($existingParticipant);

        $student = Mockery::mock(Student::class);
        $student->id = 1;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student is already registered for this event');

        $this->service->registerStudent($event, $student);
    }

    public function test_validates_check_in_requirements()
    {
        // Create mock event that cannot accept check-ins
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('canCheckIn')->andReturn(false);

        $student = Mockery::mock(Student::class);
        $staff = Mockery::mock(User::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event is not available for check-in');

        $this->service->checkInStudent($event, $student, $staff);
    }

    public function test_prevents_duplicate_check_ins()
    {
        // Create existing checked-in participant
        $existingParticipant = Mockery::mock(EventParticipant::class);
        $existingParticipant->shouldReceive('isCheckedIn')->andReturn(true);

        // Create mock event
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('canCheckIn')->andReturn(true);
        $event->shouldReceive('getStudentParticipation')->with(1)->andReturn($existingParticipant);

        $student = Mockery::mock(Student::class);
        $student->id = 1;

        $staff = Mockery::mock(User::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student is already checked in to this event');

        $this->service->checkInStudent($event, $student, $staff);
    }

    public function test_validates_completion_requirements()
    {
        // Create participant that cannot be completed
        $participant = Mockery::mock(EventParticipant::class);
        $participant->shouldReceive('canComplete')->andReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Participation cannot be completed at this time');

        $this->service->completeParticipation($participant);
    }

    public function test_prevents_double_gold_award()
    {
        // Create participant that already has gold awarded
        $participant = Mockery::mock(EventParticipant::class);
        $participant->shouldReceive('hasBeenAwarded')->andReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Gold reward has already been awarded');

        $this->service->awardGoldReward($participant);
    }

    public function test_validates_gold_award_completion_status()
    {
        // Create participant that is not completed
        $participant = Mockery::mock(EventParticipant::class);
        $participant->shouldReceive('hasBeenAwarded')->andReturn(false);
        $participant->shouldReceive('isCompleted')->andReturn(false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Participation must be completed before awarding gold');

        $this->service->awardGoldReward($participant);
    }

    public function test_sanitizes_device_info()
    {
        $deviceInfo = [
            'user_agent' => str_repeat('a', 300), // Too long
            'ip_address' => '192.168.1.1',
            'malicious_field' => 'should be removed',
            'device_type' => 'mobile',
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'location' => 'Office'
        ];

        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeDeviceInfo');
        $method->setAccessible(true);

        $sanitized = $method->invoke($this->service, $deviceInfo);

        // Check that user_agent is truncated to 255 characters
        $this->assertEquals(255, strlen($sanitized['user_agent']));

        // Check that allowed fields are preserved
        $this->assertEquals('192.168.1.1', $sanitized['ip_address']);
        $this->assertEquals('mobile', $sanitized['device_type']);
        $this->assertEquals('Chrome', $sanitized['browser']);
        $this->assertEquals('Windows', $sanitized['platform']);
        $this->assertEquals('Office', $sanitized['location']);

        // Check that malicious field is removed
        $this->assertArrayNotHasKey('malicious_field', $sanitized);
    }

    public function test_validates_unregistration_requirements()
    {
        // Create participant that cannot be cancelled
        $participant = Mockery::mock(EventParticipant::class);
        $participant->shouldReceive('canCancel')->andReturn(false);

        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getStudentParticipation')->with(1)->andReturn($participant);

        $student = Mockery::mock(Student::class);
        $student->id = 1;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel registration for this event');

        $this->service->unregisterStudent($event, $student);
    }

    public function test_handles_missing_participation_for_unregistration()
    {
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getStudentParticipation')->with(1)->andReturn(null);

        $student = Mockery::mock(Student::class);
        $student->id = 1;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot cancel registration for this event');

        $this->service->unregisterStudent($event, $student);
    }

    public function test_validates_cross_campus_check_in()
    {
        // Create mock event
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('canCheckIn')->andReturn(true);
        $event->shouldReceive('getStudentParticipation')->with(1)->andReturn(null);
        $event->campus_id = 1;

        // Create mock student from different campus
        $student = Mockery::mock(Student::class);
        $student->shouldReceive('isActive')->andReturn(true);
        $student->id = 1;
        $student->campus_id = 2; // Different campus

        $staff = Mockery::mock(User::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student cannot check in to events from other campuses');

        $this->service->checkInStudent($event, $student, $staff);
    }

    public function test_validates_inactive_student_check_in()
    {
        // Create mock event
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('canCheckIn')->andReturn(true);
        $event->shouldReceive('getStudentParticipation')->with(1)->andReturn(null);
        $event->campus_id = 1;

        // Create mock inactive student
        $student = Mockery::mock(Student::class);
        $student->shouldReceive('isActive')->andReturn(false);
        $student->id = 1;
        $student->campus_id = 1;

        $staff = Mockery::mock(User::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Student account is not active');

        $this->service->checkInStudent($event, $student, $staff);
    }
}
