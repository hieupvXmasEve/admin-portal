<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Lecture;
use App\Models\Student;
use App\Models\Syllabus;
use App\Services\AssessmentManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private AssessmentManagementService $service;
    private Lecture $lecturer;
    private CourseOffering $courseOffering;
    private Student $student;
    private AssessmentComponentDetailScore $score;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AssessmentManagementService::class);

        // Create test data
        $this->lecturer = Lecture::factory()->create();
        $this->courseOffering = CourseOffering::factory()->create([
            'lecture_id' => $this->lecturer->id
        ]);

        $syllabus = Syllabus::factory()->create();
        $this->courseOffering->update(['syllabus_id' => $syllabus->id]);

        $this->student = Student::factory()->create();

        // Enroll student in course
        CourseRegistration::factory()->create([
            'course_offering_id' => $this->courseOffering->id,
            'student_id' => $this->student->id
        ]);

        $assessmentComponent = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 30.0
        ]);

        $assessmentDetail = AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $assessmentComponent->id,
            'weight' => 100.0,
            'max_points' => 100
        ]);

        $this->score = AssessmentComponentDetailScore::factory()->create([
            'assessment_component_detail_id' => $assessmentDetail->id,
            'course_offering_id' => $this->courseOffering->id,
            'student_id' => $this->student->id,
            'points_earned' => 85,
            'percentage_score' => 85.0,
            'score_status' => 'final'
        ]);
    }

    public function test_can_flag_submission_for_plagiarism(): void
    {
        $this->actingAs($this->lecturer);

        $flagData = [
            'plagiarism_suspected' => true,
            'plagiarism_score' => 75.5,
            'plagiarism_notes' => 'Detected similarities with another submission',
            'integrity_status' => 'under_review'
        ];

        $result = $this->service->processAcademicIntegrityFlag($this->score, $flagData);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['plagiarism_suspected']);
        $this->assertEquals(75.5, $result['plagiarism_score']);
        $this->assertEquals('under_review', $result['integrity_status']);

        $this->assertDatabaseHas('assessment_component_detail_scores', [
            'id' => $this->score->id,
            'plagiarism_suspected' => true,
            'plagiarism_score' => 75.5,
            'plagiarism_notes' => 'Detected similarities with another submission',
            'integrity_status' => 'under_review'
        ]);
    }

    public function test_can_clear_plagiarism_flag(): void
    {
        $this->actingAs($this->lecturer);

        // First flag the submission
        $this->score->flagForPlagiarism(80.0, 'Initial concern');

        // Then clear the flag
        $flagData = [
            'plagiarism_suspected' => false,
            'integrity_status' => 'clean'
        ];

        $result = $this->service->processAcademicIntegrityFlag($this->score, $flagData);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['plagiarism_suspected']);
        $this->assertEquals('clean', $result['integrity_status']);

        $this->assertDatabaseHas('assessment_component_detail_scores', [
            'id' => $this->score->id,
            'plagiarism_suspected' => false,
            'integrity_status' => 'clean'
        ]);
    }

    public function test_can_request_appeal(): void
    {
        $this->actingAs($this->lecturer);

        // First confirm a violation
        $this->score->update([
            'plagiarism_suspected' => true,
            'integrity_status' => 'violation_confirmed'
        ]);

        $appealReason = 'I believe this is a false positive due to common reference material';

        $result = $this->service->processAppealRequest($this->score, $appealReason);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['appeal_requested']);
        $this->assertEquals('pending', $result['appeal_status']);
        $this->assertEquals($appealReason, $result['appeal_reason']);

        $this->assertDatabaseHas('assessment_component_detail_scores', [
            'id' => $this->score->id,
            'appeal_requested' => true,
            'appeal_status' => 'pending',
            'appeal_reason' => $appealReason
        ]);
    }

    public function test_can_approve_appeal(): void
    {
        $this->actingAs($this->lecturer);

        // Set up a pending appeal
        $this->score->update([
            'plagiarism_suspected' => true,
            'integrity_status' => 'violation_confirmed',
            'appeal_requested' => true,
            'appeal_status' => 'pending',
            'appeal_reason' => 'False positive'
        ]);

        $reviewerNotes = 'After review, this appears to be legitimate common knowledge';
        $instructorFeedback = 'Appeal approved - no violation found';

        $result = $this->service->processAppealDecision(
            $this->score,
            'approved',
            $reviewerNotes,
            $instructorFeedback
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('approved', $result['appeal_decision']);
        $this->assertEquals('violation_dismissed', $result['new_integrity_status']);

        $this->assertDatabaseHas('assessment_component_detail_scores', [
            'id' => $this->score->id,
            'appeal_status' => 'approved',
            'integrity_status' => 'violation_dismissed',
            'plagiarism_suspected' => false,
            'instructor_feedback' => $instructorFeedback,
            'private_notes' => $reviewerNotes
        ]);
    }

    public function test_can_deny_appeal(): void
    {
        $this->actingAs($this->lecturer);

        // Set up a pending appeal
        $this->score->update([
            'plagiarism_suspected' => true,
            'integrity_status' => 'violation_confirmed',
            'appeal_requested' => true,
            'appeal_status' => 'pending',
            'appeal_reason' => 'Claim of false positive'
        ]);

        $reviewerNotes = 'Evidence clearly shows plagiarism';
        $instructorFeedback = 'Appeal denied - violation confirmed';

        $result = $this->service->processAppealDecision(
            $this->score,
            'denied',
            $reviewerNotes,
            $instructorFeedback
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('denied', $result['appeal_decision']);
        $this->assertEquals('violation_confirmed', $result['new_integrity_status']);

        $this->assertDatabaseHas('assessment_component_detail_scores', [
            'id' => $this->score->id,
            'appeal_status' => 'denied',
            'integrity_status' => 'violation_confirmed',
            'plagiarism_suspected' => true,
            'instructor_feedback' => $instructorFeedback,
            'private_notes' => $reviewerNotes
        ]);
    }

    public function test_validates_plagiarism_score_range(): void
    {
        $this->actingAs($this->lecturer);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Plagiarism score must be between 0 and 100');

        $flagData = [
            'plagiarism_suspected' => true,
            'plagiarism_score' => 150.0 // Invalid score
        ];

        $this->service->processAcademicIntegrityFlag($this->score, $flagData);
    }

    public function test_validates_integrity_status(): void
    {
        $this->actingAs($this->lecturer);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid integrity status');

        $flagData = [
            'plagiarism_suspected' => true,
            'integrity_status' => 'invalid_status'
        ];

        $this->service->processAcademicIntegrityFlag($this->score, $flagData);
    }

    public function test_cannot_request_appeal_without_violation(): void
    {
        $this->actingAs($this->lecturer);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Appeals can only be requested for confirmed violations');

        $this->service->processAppealRequest($this->score, 'Appeal reason');
    }

    public function test_cannot_request_duplicate_appeal(): void
    {
        $this->actingAs($this->lecturer);

        // Set up confirmed violation with existing appeal
        $this->score->update([
            'integrity_status' => 'violation_confirmed',
            'appeal_requested' => true
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Appeal has already been requested');

        $this->service->processAppealRequest($this->score, 'Another appeal reason');
    }

    public function test_can_get_academic_integrity_statistics(): void
    {
        $this->actingAs($this->lecturer);

        // Create additional scores with various integrity statuses
        $score2 = AssessmentComponentDetailScore::factory()->create([
            'course_offering_id' => $this->courseOffering->id,
            'plagiarism_suspected' => true,
            'integrity_status' => 'violation_confirmed'
        ]);

        $score3 = AssessmentComponentDetailScore::factory()->create([
            'course_offering_id' => $this->courseOffering->id,
            'plagiarism_suspected' => true,
            'integrity_status' => 'under_review'
        ]);

        $stats = $this->service->getAcademicIntegrityStatistics($this->courseOffering);

        $this->assertEquals(3, $stats['total_submissions']);
        $this->assertEquals(2, $stats['flagged_submissions']);
        $this->assertEquals(1, $stats['confirmed_violations']);
        $this->assertEquals(1, $stats['under_review']);
        $this->assertGreaterThan(0, $stats['flagged_rate']);
    }

    public function test_can_get_flagged_submissions(): void
    {
        $this->actingAs($this->lecturer);

        // Flag the submission
        $this->score->flagForPlagiarism(85.0, 'Suspicious content');

        $flaggedSubmissions = $this->service->getFlaggedSubmissions($this->courseOffering);

        $this->assertCount(1, $flaggedSubmissions);
        $this->assertEquals($this->score->id, $flaggedSubmissions->first()->id);
    }

    public function test_can_update_instructor_feedback(): void
    {
        $this->actingAs($this->lecturer);

        $feedback = 'Please review academic integrity policies';
        $notes = 'Student seems unaware of proper citation';

        $result = $this->service->updateInstructorFeedback($this->score, $feedback, $notes);

        $this->assertTrue($result['success']);
        $this->assertEquals($feedback, $result['instructor_feedback']);
        $this->assertEquals($notes, $result['private_notes']);

        $this->assertDatabaseHas('assessment_component_detail_scores', [
            'id' => $this->score->id,
            'instructor_feedback' => $feedback,
            'private_notes' => $notes
        ]);
    }
}
