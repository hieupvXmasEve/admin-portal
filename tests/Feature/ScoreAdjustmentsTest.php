<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\CourseOffering;
use App\Models\Student;
use App\Models\Syllabus;
use App\Services\AssessmentManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    private AssessmentManagementService $service;
    private CourseOffering $courseOffering;
    private Student $student;
    private AssessmentComponentDetailScore $score;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AssessmentManagementService();

        // Create test data
        $this->courseOffering = CourseOffering::factory()->create();
        $this->student = Student::factory()->create();

        $syllabus = Syllabus::factory()->create();
        $this->courseOffering->syllabus_id = $syllabus->id;
        $this->courseOffering->save();

        $component = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 30.0
        ]);

        $detail = AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'weight' => 100.0
        ]);

        $this->score = AssessmentComponentDetailScore::factory()->create([
            'assessment_component_detail_id' => $detail->id,
            'student_id' => $this->student->id,
            'course_offering_id' => $this->courseOffering->id,
            'percentage_score' => 85.0,
            'score_status' => 'final'
        ]);
    }

    public function test_can_apply_bonus_points()
    {
        $result = $this->service->applyBonusPoints($this->score, 5.0, 'Excellent participation');

        $this->assertTrue($result['success']);
        $this->assertEquals(5.0, $result['new_bonus']);
        $this->assertEquals('Excellent participation', $result['reason']);

        $this->score->refresh();
        $this->assertEquals(5.0, $this->score->bonus_points);
        $this->assertEquals('Excellent participation', $this->score->bonus_reason);
    }

    public function test_cannot_apply_negative_bonus_points()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Bonus points cannot be negative');

        $this->service->applyBonusPoints($this->score, -2.0, 'Invalid bonus');
    }

    public function test_cannot_apply_bonus_without_reason()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Bonus reason is required');

        $this->service->applyBonusPoints($this->score, 5.0, '');
    }

    public function test_can_remove_bonus_points()
    {
        // First apply bonus points
        $this->score->applyBonusPoints(5.0, 'Initial bonus');

        $result = $this->service->removeBonusPoints($this->score, 'Bonus removed due to error');

        $this->assertTrue($result['success']);
        $this->assertEquals(5.0, $result['previous_bonus']);

        $this->score->refresh();
        $this->assertEquals(0, $this->score->bonus_points);
        $this->assertNull($this->score->bonus_reason);
    }

    public function test_can_exclude_score()
    {
        $result = $this->service->excludeScore($this->score, 'Student was absent');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['excluded']);
        $this->assertEquals('Student was absent', $result['reason']);

        $this->score->refresh();
        $this->assertTrue($this->score->score_excluded);
        $this->assertEquals('Student was absent', $this->score->exclusion_reason);
    }

    public function test_cannot_exclude_score_without_reason()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Exclusion reason is required');

        $this->service->excludeScore($this->score, '');
    }

    public function test_cannot_exclude_already_excluded_score()
    {
        $this->score->excludeScore('First exclusion');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Score is already excluded');

        $this->service->excludeScore($this->score, 'Second exclusion');
    }

    public function test_can_include_excluded_score()
    {
        // First exclude the score
        $this->score->excludeScore('Initial exclusion');

        $result = $this->service->includeScore($this->score, 'Error corrected');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['excluded']);
        $this->assertEquals('Initial exclusion', $result['previous_exclusion_reason']);

        $this->score->refresh();
        $this->assertFalse($this->score->score_excluded);
        $this->assertNull($this->score->exclusion_reason);
    }

    public function test_cannot_include_non_excluded_score()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Score is not currently excluded');

        $this->service->includeScore($this->score, 'Trying to include');
    }

    public function test_calculate_weighted_score_with_adjustments()
    {
        // Apply bonus points and late penalty
        $this->score->update([
            'bonus_points' => 5.0,
            'is_late' => true,
            'late_penalty_applied' => 10.0,
            'late_excuse_approved' => false
        ]);

        $weightedScore = $this->score->calculateWeightedScore(30.0);

        // Expected: (85 - 10 + 5) * 30 / 100 = 80 * 0.3 = 24.0
        $this->assertEquals(24.0, $weightedScore);
    }

    public function test_calculate_weighted_score_returns_null_for_excluded()
    {
        $this->score->excludeScore('Test exclusion');

        $weightedScore = $this->score->calculateWeightedScore(30.0);

        $this->assertNull($weightedScore);
    }

    public function test_calculate_final_score_with_adjustments()
    {
        $this->score->update([
            'bonus_points' => 5.0,
            'is_late' => true,
            'late_penalty_applied' => 10.0,
            'late_excuse_approved' => false
        ]);

        $finalScore = $this->score->calculateFinalScore();

        // Expected: 85 - 10 + 5 = 80
        $this->assertEquals(80.0, $finalScore);
    }

    public function test_calculate_final_score_returns_null_for_excluded()
    {
        $this->score->excludeScore('Test exclusion');

        $finalScore = $this->score->calculateFinalScore();

        $this->assertNull($finalScore);
    }

    public function test_score_adjustments_maintain_audit_trail()
    {
        // Apply bonus points
        $this->score->applyBonusPoints(5.0, 'Good work');

        // Exclude score
        $this->score->excludeScore('Student absent');

        $history = $this->score->score_history;

        $this->assertIsArray($history);
        $this->assertCount(2, $history);

        // Check bonus points entry
        $bonusEntry = collect($history)->firstWhere('action', 'bonus_points_applied');
        $this->assertNotNull($bonusEntry);
        $this->assertEquals(5.0, $bonusEntry['new_bonus']);
        $this->assertEquals('Good work', $bonusEntry['reason']);

        // Check exclusion entry
        $exclusionEntry = collect($history)->firstWhere('action', 'score_excluded');
        $this->assertNotNull($exclusionEntry);
        $this->assertEquals('Student absent', $exclusionEntry['reason']);
    }

    public function test_get_score_adjustment_statistics()
    {
        // Create additional scores with various adjustments
        $score2 = AssessmentComponentDetailScore::factory()->create([
            'assessment_component_detail_id' => $this->score->assessment_component_detail_id,
            'student_id' => Student::factory()->create()->id,
            'course_offering_id' => $this->courseOffering->id,
            'bonus_points' => 3.0,
            'score_status' => 'final'
        ]);

        $score3 = AssessmentComponentDetailScore::factory()->create([
            'assessment_component_detail_id' => $this->score->assessment_component_detail_id,
            'student_id' => Student::factory()->create()->id,
            'course_offering_id' => $this->courseOffering->id,
            'score_excluded' => true,
            'score_status' => 'final'
        ]);

        $stats = $this->service->getScoreAdjustmentStatistics($this->courseOffering);

        $this->assertEquals(3, $stats['total_scores']);
        $this->assertEquals(1, $stats['scores_with_bonus']);
        $this->assertEquals(1, $stats['excluded_scores']);
        $this->assertEquals(33.33, $stats['bonus_rate']);
        $this->assertEquals(33.33, $stats['exclusion_rate']);
    }

    public function test_calculate_final_grades_respects_exclusions()
    {
        // Exclude one score
        $this->score->excludeScore('Test exclusion');

        $grades = $this->service->calculateFinalGrades($this->courseOffering);

        $studentGrade = collect($grades['students'])->firstWhere('student.id', $this->student->id);

        $this->assertNotNull($studentGrade);
        $this->assertEquals(1, $studentGrade['adjustments']['excluded_count']);
        $this->assertEquals(0, $studentGrade['grade_breakdown']['components_completed']);
    }

    public function test_calculate_final_grades_applies_bonus_points()
    {
        $this->score->applyBonusPoints(10.0, 'Excellent work');

        $grades = $this->service->calculateFinalGrades($this->courseOffering);

        $studentGrade = collect($grades['students'])->firstWhere('student.id', $this->student->id);

        $this->assertNotNull($studentGrade);
        $this->assertEquals(1, $studentGrade['adjustments']['bonus_count']);
        $this->assertEquals(10.0, $studentGrade['adjustments']['total_bonus_points']);
    }
}