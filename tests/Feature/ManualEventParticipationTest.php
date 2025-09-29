<?php

namespace Tests\Feature;

use App\Models\Campus;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Services\EventParticipationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualEventParticipationTest extends TestCase
{
    use RefreshDatabase;

    private EventParticipationService $participationService;
    private Campus $campus;
    private Event $manualEvent;
    private User $admin;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->participationService = app(EventParticipationService::class);

        // Create test data
        $this->campus = Campus::factory()->create();
        $program = Program::factory()->create(['campus_id' => $this->campus->id]);

        $this->admin = User::factory()->create();
        $this->admin->campuses()->attach($this->campus->id);

        $this->student = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'program_id' => $program->id,
            'status' => 'active',
            'academic_status' => 'good_standing'
        ]);

        $this->manualEvent = Event::factory()->create([
            'campus_id' => $this->campus->id,
            'is_manual' => true,
            'is_historical' => true,
            'status' => 'completed',
            'gold_reward_amount' => 50.00,
            'created_by_user_id' => $this->admin->id,
            'created_by_admin_id' => $this->admin->id,
        ]);
    }

    public function test_can_add_manual_participants_with_completed_status()
    {
        $results = $this->participationService->addManualParticipants(
            $this->manualEvent,
            [$this->student->id],
            'completed',
            $this->admin
        );

        $this->assertCount(1, $results['added']);
        $this->assertCount(0, $results['errors']);
        $this->assertCount(0, $results['skipped']);

        $participant = EventParticipant::where('event_id', $this->manualEvent->id)
            ->where('student_id', $this->student->id)
            ->first();

        $this->assertNotNull($participant);
        $this->assertEquals('completed', $participant->status);
        $this->assertTrue($participant->gold_awarded);
    }

    public function test_can_get_available_students_for_manual_event()
    {
        $students = $this->participationService->getAvailableStudentsForManualEvent(
            $this->manualEvent,
            [],
            10
        );

        $this->assertGreaterThan(0, $students->total());
        $this->assertTrue($students->contains('id', $this->student->id));
    }

    public function test_can_bulk_update_participant_status()
    {
        // First add a participant
        $participant = EventParticipant::create([
            'event_id' => $this->manualEvent->id,
            'student_id' => $this->student->id,
            'status' => 'registered',
            'gold_awarded' => false,
        ]);

        $results = $this->participationService->bulkUpdateParticipantStatus(
            $this->manualEvent,
            [$participant->id],
            'completed',
            $this->admin
        );

        $this->assertCount(1, $results['updated']);
        $this->assertCount(0, $results['errors']);

        $participant->refresh();
        $this->assertEquals('completed', $participant->status);
        $this->assertTrue($participant->gold_awarded);
    }

    public function test_can_remove_manual_participants()
    {
        // First add a participant with gold awarded
        $participant = EventParticipant::create([
            'event_id' => $this->manualEvent->id,
            'student_id' => $this->student->id,
            'status' => 'completed',
            'gold_awarded' => true,
            'awarded_at' => now(),
        ]);

        $results = $this->participationService->removeManualParticipants(
            $this->manualEvent,
            [$participant->id],
            $this->admin
        );

        $this->assertCount(1, $results['removed']);
        $this->assertCount(0, $results['errors']);

        $this->assertDatabaseMissing('event_participants', [
            'id' => $participant->id
        ]);
    }

    public function test_cannot_add_participants_to_non_manual_event()
    {
        $regularEvent = Event::factory()->create([
            'campus_id' => $this->campus->id,
            'is_manual' => false,
            'created_by_user_id' => $this->admin->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Can only add manual participants to manual events');

        $this->participationService->addManualParticipants(
            $regularEvent,
            [$this->student->id],
            'completed',
            $this->admin
        );
    }

    public function test_skips_already_registered_students()
    {
        // First register the student
        EventParticipant::create([
            'event_id' => $this->manualEvent->id,
            'student_id' => $this->student->id,
            'status' => 'registered',
        ]);

        $results = $this->participationService->addManualParticipants(
            $this->manualEvent,
            [$this->student->id],
            'completed',
            $this->admin
        );

        $this->assertCount(0, $results['added']);
        $this->assertCount(1, $results['skipped']);
        $this->assertCount(0, $results['errors']);
    }

    public function test_gets_manual_event_statistics()
    {
        // Add some participants with different statuses
        EventParticipant::create([
            'event_id' => $this->manualEvent->id,
            'student_id' => $this->student->id,
            'status' => 'completed',
            'gold_awarded' => true,
        ]);

        $student2 = Student::factory()->create([
            'campus_id' => $this->campus->id,
            'status' => 'active',
        ]);

        EventParticipant::create([
            'event_id' => $this->manualEvent->id,
            'student_id' => $student2->id,
            'status' => 'registered',
            'gold_awarded' => false,
        ]);

        $statistics = $this->participationService->getManualEventStatistics($this->manualEvent);

        $this->assertEquals(2, $statistics['total_added']);
        $this->assertEquals(1, $statistics['completed_count']);
        $this->assertEquals(1, $statistics['registered_count']);
        $this->assertEquals(1, $statistics['gold_awarded_count']);
        $this->assertEquals(50.00, $statistics['total_gold_distributed']);
        $this->assertEquals(50.0, $statistics['completion_rate']);
        $this->assertEquals(100.0, $statistics['gold_award_rate']);
    }
}
