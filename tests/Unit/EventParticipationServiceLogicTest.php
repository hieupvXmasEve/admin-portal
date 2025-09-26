<?php

namespace Tests\Unit;

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
use Tests\TestCase;

class EventParticipationServiceLogicTest extends TestCase
{
    private EventParticipationService $service;
    private WalletService $walletService;
    private NotificationService $notificationService;

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

    public function test_sanitizes_device_info_correctly()
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('sanitizeDeviceInfo');
        $method->setAccessible(true);

        $deviceInfo = [
            'user_agent' => str_repeat('a', 300), // Too long
            'ip_address' => '192.168.1.1',
            'malicious_field' => 'should be removed',
            'device_type' => 'mobile',
            'browser' => 'Chrome',
            'platform' => 'Windows',
        ];

        $result = $method->invoke($this->service, $deviceInfo);

        // Should remove malicious fields
        $this->assertArrayNotHasKey('malicious_field', $result);

        // Should add timestamp
        $this->assertArrayHasKey('timestamp', $result);

        // Should truncate long strings
        $this->assertEquals(255, strlen($result['user_agent']));

        // Should keep allowed fields
        $this->assertEquals('192.168.1.1', $result['ip_address']);
        $this->assertEquals('mobile', $result['device_type']);
        $this->assertEquals('Chrome', $result['browser']);
        $this->assertEquals('Windows', $result['platform']);
    }

    public function test_validates_event_registration_correctly()
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateEventRegistration');
        $method->setAccessible(true);

        // Test with draft event
        $draftEvent = Mockery::mock(Event::class);
        $draftEvent->shouldReceive('canRegister')->andReturn(false);
        $draftEvent->shouldReceive('isDraft')->andReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot register for draft events');

        $method->invoke($this->service, $draftEvent);
    }

    public function test_validates_event_registration_for_cancelled_event()
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateEventRegistration');
        $method->setAccessible(true);

        // Test with cancelled event
        $cancelledEvent = Mockery::mock(Event::class);
        $cancelledEvent->shouldReceive('canRegister')->andReturn(false);
        $cancelledEvent->shouldReceive('isDraft')->andReturn(false);
        $cancelledEvent->shouldReceive('isCancelled')->andReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot register for cancelled events');

        $method->invoke($this->service, $cancelledEvent);
    }

    public function test_validates_event_registration_for_completed_event()
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateEventRegistration');
        $method->setAccessible(true);

        // Test with completed event
        $completedEvent = Mockery::mock(Event::class);
        $completedEvent->shouldReceive('canRegister')->andReturn(false);
        $completedEvent->shouldReceive('isDraft')->andReturn(false);
        $completedEvent->shouldReceive('isCancelled')->andReturn(false);
        $completedEvent->shouldReceive('isCompleted')->andReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot register for completed events');

        $method->invoke($this->service, $completedEvent);
    }

    public function test_validates_event_registration_for_started_event()
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateEventRegistration');
        $method->setAccessible(true);

        // Test with started event
        $startedEvent = Mockery::mock(Event::class);
        $startedEvent->shouldReceive('canRegister')->andReturn(false);
        $startedEvent->shouldReceive('isDraft')->andReturn(false);
        $startedEvent->shouldReceive('isCancelled')->andReturn(false);
        $startedEvent->shouldReceive('isCompleted')->andReturn(false);
        $startedEvent->shouldReceive('hasStarted')->andReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot register for events that have already started');

        $method->invoke($this->service, $startedEvent);
    }

    public function test_validates_event_registration_for_capacity_reached()
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateEventRegistration');
        $method->setAccessible(true);

        // Test with event at capacity
        $fullEvent = Mockery::mock(Event::class);
        $fullEvent->shouldReceive('canRegister')->andReturn(false);
        $fullEvent->shouldReceive('isDraft')->andReturn(false);
        $fullEvent->shouldReceive('isCancelled')->andReturn(false);
        $fullEvent->shouldReceive('isCompleted')->andReturn(false);
        $fullEvent->shouldReceive('hasStarted')->andReturn(false);
        $fullEvent->shouldReceive('hasReachedCapacity')->andReturn(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event has reached maximum capacity');

        $method->invoke($this->service, $fullEvent);
    }

    public function test_validates_event_registration_passes_for_valid_event()
    {
        // Use reflection to test the private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateEventRegistration');
        $method->setAccessible(true);

        // Test with valid event
        $validEvent = Mockery::mock(Event::class);
        $validEvent->shouldReceive('canRegister')->andReturn(true);

        // Should not throw any exception
        $method->invoke($this->service, $validEvent);

        // If we reach here, the test passes
        $this->assertTrue(true);
    }

    public function test_service_has_all_required_methods()
    {
        // Test that the service has all the required public methods
        $this->assertTrue(method_exists($this->service, 'registerStudent'));
        $this->assertTrue(method_exists($this->service, 'unregisterStudent'));
        $this->assertTrue(method_exists($this->service, 'checkInStudent'));
        $this->assertTrue(method_exists($this->service, 'completeParticipation'));
        $this->assertTrue(method_exists($this->service, 'awardGoldReward'));
        $this->assertTrue(method_exists($this->service, 'reclaimGoldReward'));
        $this->assertTrue(method_exists($this->service, 'getStudentParticipations'));
        $this->assertTrue(method_exists($this->service, 'getEventParticipants'));
        $this->assertTrue(method_exists($this->service, 'bulkCompleteParticipations'));
        $this->assertTrue(method_exists($this->service, 'getParticipationStatistics'));
    }

    public function test_service_constructor_accepts_dependencies()
    {
        // Test that the service can be constructed with its dependencies
        $walletService = Mockery::mock(WalletService::class);
        $notificationService = Mockery::mock(NotificationService::class);

        $service = new EventParticipationService($walletService, $notificationService);

        $this->assertInstanceOf(EventParticipationService::class, $service);
    }
}
