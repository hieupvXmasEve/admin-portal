<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Lecturer;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Syllabus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssessmentWeightValidationTest extends TestCase
{
    use RefreshDatabase;

    protected Lecture $lecturer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lecturer = Lecture::factory()->create();
        Sanctum::actingAs($this->lecturer, [], 'sanctum');
    }

    /** @test */
    public function it_validates_assessment_weights_for_authorized_lecturer(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create([
            'syllabus_id' => $syllabus->id,
            'lecture_id' => $this->lecturer->id
        ]);

        // Create components that total 100%
        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 40,
            'name' => 'Assignment'
        ]);

        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 60,
            'name' => 'Final Exam'
        ]);

        $response = $this->getJson("/api/v1/lecturer/courses/{$courseOffering->id}/assessments/validate-weights");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_weight',
                    'is_valid',
                    'exceeds_limit',
                    'missing_weight',
                    'component_validations' => [
                        '*' => [
                            'assessment_id',
                            'assessment_name',
                            'current_weight',
                            'detail_weights_sum',
                            'is_valid',
                            'errors'
                        ]
                    ]
                ]
            ]);

        expect($response->json('data.total_weight'))->toBe(100);
        expect($response->json('data.is_valid'))->toBeTrue();
        expect($response->json('data.exceeds_limit'))->toBeFalse();
        expect($response->json('data.missing_weight'))->toBe(0);
        expect($response->json('data.component_validations'))->toHaveCount(2);
    }

    /** @test */
    public function it_validates_assessment_weights_with_invalid_totals(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create([
            'syllabus_id' => $syllabus->id,
            'lecture_id' => $this->lecturer->id
        ]);

        // Create components that exceed 100%
        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 70,
            'name' => 'Assignment'
        ]);

        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 50,
            'name' => 'Final Exam'
        ]);

        $response = $this->getJson("/api/v1/lecturer/courses/{$courseOffering->id}/assessments/validate-weights");

        $response->assertOk();

        expect($response->json('data.total_weight'))->toBe(120);
        expect($response->json('data.is_valid'))->toBeFalse();
        expect($response->json('data.exceeds_limit'))->toBeTrue();
        expect($response->json('data.missing_weight'))->toBe(0);
        expect($response->json('data.errors'))->toContain('Total assessment weight (120%) exceeds 100%');
    }

    /** @test */
    public function it_validates_assessment_weights_with_component_detail_issues(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create([
            'syllabus_id' => $syllabus->id,
            'lecture_id' => $this->lecturer->id
        ]);

        $component = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 50,
            'name' => 'Assignment'
        ]);

        // Create details that exceed component weight
        AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'weight' => 40,
            'name' => 'Part A'
        ]);

        AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'weight' => 30,
            'name' => 'Part B'
        ]);

        $response = $this->getJson("/api/v1/lecturer/courses/{$courseOffering->id}/assessments/validate-weights");

        $response->assertOk();

        $componentValidation = collect($response->json('data.component_validations'))
            ->firstWhere('assessment_name', 'Assignment');

        expect($componentValidation['detail_weights_sum'])->toBe(70);
        expect($componentValidation['is_valid'])->toBeFalse();
        expect($componentValidation['errors'])->toContain('Sum of detail weights (70%) exceeds component weight (50%)');
    }

    /** @test */
    public function it_returns_403_for_unauthorized_lecturer(): void
    {
        $anotherLecturer = Lecture::factory()->create();
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create([
            'syllabus_id' => $syllabus->id,
            'lecture_id' => $anotherLecturer->id // Different lecturer
        ]);

        $response = $this->getJson("/api/v1/lecturer/courses/{$courseOffering->id}/assessments/validate-weights");

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized access to course offering',
                'error_code' => 'UNAUTHORIZED'
            ]);
    }

    /** @test */
    public function it_handles_course_offering_without_syllabus(): void
    {
        $courseOffering = CourseOffering::factory()->create([
            'syllabus_id' => null,
            'lecture_id' => $this->lecturer->id
        ]);

        $response = $this->getJson("/api/v1/lecturer/courses/{$courseOffering->id}/assessments/validate-weights");

        $response->assertOk();

        expect($response->json('data.total_weight'))->toBe(0);
        expect($response->json('data.is_valid'))->toBeFalse();
        expect($response->json('data.missing_weight'))->toBe(100);
        expect($response->json('data.component_validations'))->toBeEmpty();
        expect($response->json('data.errors'))->toContain('No syllabus found for this course offering');
    }

    /** @test */
    public function it_handles_course_offering_with_no_assessments(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create([
            'syllabus_id' => $syllabus->id,
            'lecture_id' => $this->lecturer->id
        ]);

        $response = $this->getJson("/api/v1/lecturer/courses/{$courseOffering->id}/assessments/validate-weights");

        $response->assertOk();

        expect($response->json('data.total_weight'))->toBe(0);
        expect($response->json('data.is_valid'))->toBeFalse();
        expect($response->json('data.missing_weight'))->toBe(100);
        expect($response->json('data.component_validations'))->toBeEmpty();
        expect($response->json('data.errors'))->toContain('No assessment components found for this course');
    }

    /** @test */
    public function it_validates_complex_assessment_structure(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create([
            'syllabus_id' => $syllabus->id,
            'lecture_id' => $this->lecturer->id
        ]);

        // Create first component with valid details
        $assignment = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 40,
            'name' => 'Assignment'
        ]);

        AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $assignment->id,
            'weight' => 25,
            'name' => 'Code Quality'
        ]);

        AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $assignment->id,
            'weight' => 15,
            'name' => 'Documentation'
        ]);

        // Create second component with insufficient detail weights
        $midterm = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 30,
            'name' => 'Midterm'
        ]);

        AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $midterm->id,
            'weight' => 20,
            'name' => 'Multiple Choice'
        ]);

        // Create third component with no details
        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 30,
            'name' => 'Final Exam'
        ]);

        $response = $this->getJson("/api/v1/lecturer/courses/{$courseOffering->id}/assessments/validate-weights");

        $response->assertOk();

        expect($response->json('data.total_weight'))->toBe(100);
        expect($response->json('data.is_valid'))->toBeTrue(); // Overall valid since no hard errors
        expect($response->json('data.component_validations'))->toHaveCount(3);

        $assignmentValidation = collect($response->json('data.component_validations'))
            ->firstWhere('assessment_name', 'Assignment');
        expect($assignmentValidation['is_valid'])->toBeTrue();
        expect($assignmentValidation['detail_weights_sum'])->toBe(40);

        $midtermValidation = collect($response->json('data.component_validations'))
            ->firstWhere('assessment_name', 'Midterm');
        expect($midtermValidation['is_valid'])->toBeTrue(); // Warning only
        expect($midtermValidation['detail_weights_sum'])->toBe(20);
        expect($midtermValidation['errors'])->toContain('Sum of detail weights (20%) is less than component weight (30%) - 10% unaccounted');

        $finalValidation = collect($response->json('data.component_validations'))
            ->firstWhere('assessment_name', 'Final Exam');
        expect($finalValidation['is_valid'])->toBeTrue();
        expect($finalValidation['detail_weights_sum'])->toBe(0);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_course_offering(): void
    {
        $response = $this->getJson("/api/v1/lecturer/courses/999999/assessments/validate-weights");

        $response->assertNotFound();
    }

    /** @test */
    public function it_requires_authentication(): void
    {
        Sanctum::actingAs(null);

        $courseOffering = CourseOffering::factory()->create();

        $response = $this->getJson("/api/v1/lecturer/courses/{$courseOffering->id}/assessments/validate-weights");

        $response->assertUnauthorized();
    }

    /** @test */
    public function it_validates_assessment_weights_with_zero_weight_components(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create([
            'syllabus_id' => $syllabus->id,
            'lecture_id' => $this->lecturer->id
        ]);

        // Create component with zero weight (invalid)
        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 0,
            'name' => 'Invalid Assignment'
        ]);

        // Create valid component
        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 80,
            'name' => 'Valid Assignment'
        ]);

        $response = $this->getJson("/api/v1/lecturer/courses/{$courseOffering->id}/assessments/validate-weights");

        $response->assertOk();

        expect($response->json('data.total_weight'))->toBe(80);
        expect($response->json('data.is_valid'))->toBeFalse(); // Invalid due to zero weight component

        $invalidComponent = collect($response->json('data.component_validations'))
            ->firstWhere('assessment_name', 'Invalid Assignment');
        expect($invalidComponent['is_valid'])->toBeFalse();
        expect($invalidComponent['errors'])->toContain('Component weight must be greater than 0');
    }
}