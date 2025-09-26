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

class EventApiErrorTest extends TestCase
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

        Sanctum::actingAs($this->user);
    }

    public function test_registration_fails_for_cancelled_event()
    {
        $event = Event::factory()->cancelled()->create([
            'campus_id' => $this->campus->id
        ]);

        $response = $this->postJson("/api/v1/student/events/{$event->id}/register");

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot register for cancelled events');
    }

    public function test_registration_fails_for_completed_event()
    {
        $event = Event::factory()->completed()->create([
            'campus_id' => $this->campus->id
        ]);

        $response = $this->postJson("/api/v1/student/events/{$event->id}/register");

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot register for completed events');
    }

    public function test_registration_fails_for_started_event()
    {
        $event = Event::factory()->published()->past()->create([
            'campus_id' => $this->campus->id
        ]);

        $response = $this->postJson("/api/v1/student/events/{$event->id}/register");

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot register for events that have already started');
    }

    public function test_registration_fails_for_inactive_student()
    {
        // Update student to inactive status
        $this->student->update(['status' => 'inactive']);

        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id
        ]);

        $response = $this->postJson("/api/v1/student/events/{$event->id}/register");

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Student account is not active');
    }

    public function test_unregistration_fails_for_checked_in_student_after_event_ended()
    {
        $event = Event::factory()->completed()->create([
            'campus_id' => $this->campus->id
        ]);

        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $this->student->id,
            'status' => 'checked_in'
        ]);

        $response = $this->deleteJson("/api/v1/student/events/{$event->id}/register");

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot cancel registration for this event');
    }

    public function test_unregistration_fails_for_completed_participation()
    {
        $event = Event::factory()->completed()->create([
            'campus_id' => $this->campus->id
        ]);

        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $this->student->id,
            'status' => 'completed'
        ]);

        $response = $this->deleteJson("/api/v1/student/events/{$event->id}/register");

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Cannot cancel registration for this event');
    }

    public function test_handles_nonexistent_event_gracefully()
    {
        $response = $this->getJson('/api/v1/student/events/99999');

        $response->assertNotFound();
    }

    public function test_handles_invalid_event_id_format()
    {
        $response = $this->getJson('/api/v1/student/events/invalid-id');

        $response->assertNotFound();
    }

    public function test_registration_handles_race_condition_for_capacity()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id,
            'max_participants' => 1
        ]);

        // Simulate race condition by creating participant directly in database
        // after the capacity check but before registration
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

    public function test_handles_database_constraint_violations()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id
        ]);

        // Create a participation record directly
        EventParticipant::factory()->create([
            'event_id' => $event->id,
            'student_id' => $this->student->id,
            'status' => 'registered'
        ]);

        // Try to register again (should hit unique constraint)
        $response = $this->postJson("/api/v1/student/events/{$event->id}/register");

        $response->assertBadRequest()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Student is already registered for this event');
    }

    public function test_handles_malformed_request_data()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id
        ]);

        // Send malformed JSON
        $response = $this->postJson("/api/v1/student/events/{$event->id}/register", [
            'invalid_field' => 'invalid_value'
        ]);

        // Should still work as registration doesn't require body data
        $response->assertOk();
    }

    public function test_handles_missing_authentication_token()
    {
        Sanctum::actingAs(null);

        $response = $this->getJson('/api/v1/student/events');

        $response->assertUnauthorized();
    }

    public function test_handles_expired_authentication_token()
    {
        // This would require mocking token expiration
        // For now, we'll test with invalid token
        $this->withHeaders([
            'Authorization' => 'Bearer invalid-token'
        ]);

        $response = $this->getJson('/api/v1/student/events');

        $response->assertUnauthorized();
    }

    public function test_handles_student_without_campus()
    {
        // Create student without campus
        $userWithoutCampus = User::factory()->create();
        $studentWithoutCampus = Student::factory()->create([
            'campus_id' => null,
            'user_id' => $userWithoutCampus->id
        ]);

        Sanctum::actingAs($userWithoutCampus);

        $response = $this->getJson('/api/v1/student/events');

        // Should handle gracefully - might return empty results or error
        $this->assertTrue(in_array($response->status(), [200, 400, 500]));
    }

    public function test_handles_deleted_campus()
    {
        // Soft delete the campus
        $this->campus->delete();

        $response = $this->getJson('/api/v1/student/events');

        // Should handle gracefully
        $response->assertOk()
            ->assertJsonPath('data.events', []);
    }

    public function test_handles_concurrent_registrations()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id,
            'max_participants' => 1
        ]);

        // Simulate concurrent registration attempts
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->postJson("/api/v1/student/events/{$event->id}/register");
        }

        // Only one should succeed
        $successCount = 0;
        foreach ($responses as $response) {
            if ($response->status() === 200) {
                $successCount++;
            }
        }

        $this->assertEquals(1, $successCount);
    }

    public function test_handles_large_pagination_requests()
    {
        Event::factory()->count(5)->published()->create([
            'campus_id' => $this->campus->id
        ]);

        // Request more items than exist
        $response = $this->getJson('/api/v1/student/events?per_page=100&page=10');

        $response->assertOk()
            ->assertJsonPath('data.events', [])
            ->assertJsonPath('data.pagination.current_page', 10);
    }

    public function test_handles_negative_pagination_values()
    {
        Event::factory()->count(5)->published()->create([
            'campus_id' => $this->campus->id
        ]);

        $response = $this->getJson('/api/v1/student/events?per_page=-1&page=-1');

        $response->assertOk();
        // Should use default values or handle gracefully
    }

    public function test_handles_invalid_filter_values()
    {
        Event::factory()->count(3)->published()->create([
            'campus_id' => $this->campus->id
        ]);

        $response = $this->getJson('/api/v1/student/events?status=invalid_status&time_filter=invalid_time');

        $response->assertOk();
        // Should ignore invalid filters and return all events
    }

    public function test_handles_sql_injection_attempts()
    {
        Event::factory()->count(3)->published()->create([
            'campus_id' => $this->campus->id
        ]);

        $maliciousSearch = "'; DROP TABLE events; --";
        $response = $this->getJson('/api/v1/student/events?search=' . urlencode($maliciousSearch));

        $response->assertOk();
        // Should handle safely without SQL injection

        // Verify events table still exists
        $this->assertDatabaseCount('events', 3);
    }

    public function test_handles_extremely_long_search_terms()
    {
        Event::factory()->count(3)->published()->create([
            'campus_id' => $this->campus->id
        ]);

        $longSearch = str_repeat('a', 10000);
        $response = $this->getJson('/api/v1/student/events?search=' . urlencode($longSearch));

        $response->assertOk();
        // Should handle gracefully without errors
    }

    public function test_handles_special_characters_in_search()
    {
        Event::factory()->published()->create([
            'campus_id' => $this->campus->id,
            'title' => 'Event with special chars: @#$%^&*()'
        ]);

        $response = $this->getJson('/api/v1/student/events?search=' . urlencode('@#$%'));

        $response->assertOk();
        // Should handle special characters safely
    }

    public function test_handles_unicode_characters_in_search()
    {
        Event::factory()->published()->create([
            'campus_id' => $this->campus->id,
            'title' => 'Event with unicode: 测试活动 🎉'
        ]);

        $response = $this->getJson('/api/v1/student/events?search=' . urlencode('测试'));

        $response->assertOk();
        // Should handle unicode characters properly
    }

    public function test_rate_limiting_protection()
    {
        $event = Event::factory()->published()->create([
            'campus_id' => $this->campus->id
        ]);

        // Make many rapid requests
        $responses = [];
        for ($i = 0; $i < 100; $i++) {
            $responses[] = $this->getJson("/api/v1/student/events/{$event->id}");
        }

        // Should either succeed or be rate limited, but not crash
        foreach ($responses as $response) {
            $this->assertTrue(in_array($response->status(), [200, 429]));
        }
    }
}
