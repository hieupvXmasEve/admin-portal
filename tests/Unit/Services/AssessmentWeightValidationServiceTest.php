<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Models\Syllabus;
use App\Services\AssessmentWeightValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentWeightValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AssessmentWeightValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AssessmentWeightValidationService();
    }

    /** @test */
    public function it_validates_course_weights_with_no_syllabus(): void
    {
        $courseOffering = new CourseOffering(['syllabus_id' => null]);

        $result = $this->service->validateCourseWeights($courseOffering);

        expect($result)->toHaveKeys([
            'total_weight',
            'is_valid',
            'exceeds_limit',
            'missing_weight',
            'component_validations',
            'errors'
        ]);

        expect($result['total_weight'])->toBe(0);
        expect($result['is_valid'])->toBeFalse();
        expect($result['exceeds_limit'])->toBeFalse();
        expect($result['missing_weight'])->toBe(100);
        expect($result['component_validations'])->toBeEmpty();
        expect($result['errors'])->toContain('No syllabus found for this course offering');
    }

    /** @test */
    public function it_validates_course_weights_with_no_components(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['syllabus_id' => $syllabus->id]);

        $result = $this->service->validateCourseWeights($courseOffering);

        expect($result['total_weight'])->toBe(0);
        expect($result['is_valid'])->toBeFalse();
        expect($result['exceeds_limit'])->toBeFalse();
        expect($result['missing_weight'])->toBe(100);
        expect($result['component_validations'])->toBeEmpty();
        expect($result['errors'])->toContain('No assessment components found for this course');
    }

    /** @test */
    public function it_validates_course_weights_with_perfect_100_percent(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['syllabus_id' => $syllabus->id]);

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

        $result = $this->service->validateCourseWeights($courseOffering);

        expect($result['total_weight'])->toBe(100);
        expect($result['is_valid'])->toBeTrue();
        expect($result['exceeds_limit'])->toBeFalse();
        expect($result['missing_weight'])->toBe(0);
        expect($result['component_validations'])->toHaveCount(2);
        expect($result['errors'])->toBeEmpty();
    }

    /** @test */
    public function it_validates_course_weights_exceeding_100_percent(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['syllabus_id' => $syllabus->id]);

        // Create components that total more than 100%
        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 60,
            'name' => 'Assignment'
        ]);

        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 50,
            'name' => 'Final Exam'
        ]);

        $result = $this->service->validateCourseWeights($courseOffering);

        expect($result['total_weight'])->toBe(110);
        expect($result['is_valid'])->toBeFalse();
        expect($result['exceeds_limit'])->toBeTrue();
        expect($result['missing_weight'])->toBe(0);
        expect($result['errors'])->toContain('Total assessment weight (110%) exceeds 100%');
    }

    /** @test */
    public function it_validates_course_weights_under_100_percent(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['syllabus_id' => $syllabus->id]);

        // Create components that total less than 100%
        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 30,
            'name' => 'Assignment'
        ]);

        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 40,
            'name' => 'Final Exam'
        ]);

        $result = $this->service->validateCourseWeights($courseOffering);

        expect($result['total_weight'])->toBe(70);
        expect($result['is_valid'])->toBeFalse();
        expect($result['exceeds_limit'])->toBeFalse();
        expect($result['missing_weight'])->toBe(30);
        expect($result['errors'])->toContain('Total assessment weight (70%) is less than 100%');
    }

    /** @test */
    public function it_validates_component_weights_with_valid_details(): void
    {
        $syllabus = Syllabus::factory()->create();
        $component = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 50,
            'name' => 'Assignment'
        ]);

        // Create details that total exactly the component weight
        AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'weight' => 30,
            'name' => 'Part A'
        ]);

        AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'weight' => 20,
            'name' => 'Part B'
        ]);

        $result = $this->service->validateComponentWeights($component->fresh());

        expect($result['assessment_id'])->toBe($component->id);
        expect($result['assessment_name'])->toBe('Assignment');
        expect($result['current_weight'])->toBe(50);
        expect($result['detail_weights_sum'])->toBe(50);
        expect($result['is_valid'])->toBeTrue();
        expect($result['errors'])->toBeEmpty();
    }

    /** @test */
    public function it_validates_component_weights_with_exceeding_details(): void
    {
        $syllabus = Syllabus::factory()->create();
        $component = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 50,
            'name' => 'Assignment'
        ]);

        // Create details that exceed the component weight
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

        $result = $this->service->validateComponentWeights($component->fresh());

        expect($result['detail_weights_sum'])->toBe(70);
        expect($result['is_valid'])->toBeFalse();
        expect($result['errors'])->toContain('Sum of detail weights (70%) exceeds component weight (50%)');
    }

    /** @test */
    public function it_validates_component_weights_with_insufficient_details(): void
    {
        $syllabus = Syllabus::factory()->create();
        $component = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 50,
            'name' => 'Assignment'
        ]);

        // Create details that are less than the component weight
        AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'weight' => 20,
            'name' => 'Part A'
        ]);

        AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'weight' => 15,
            'name' => 'Part B'
        ]);

        $result = $this->service->validateComponentWeights($component->fresh());

        expect($result['detail_weights_sum'])->toBe(35);
        expect($result['is_valid'])->toBeTrue(); // This is a warning, not an error
        expect($result['errors'])->toContain('Sum of detail weights (35%) is less than component weight (50%) - 15% unaccounted');
    }

    /** @test */
    public function it_validates_component_with_zero_weight(): void
    {
        $syllabus = Syllabus::factory()->create();
        $component = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 0,
            'name' => 'Invalid Assignment'
        ]);

        $result = $this->service->validateComponentWeights($component);

        expect($result['is_valid'])->toBeFalse();
        expect($result['errors'])->toContain('Component weight must be greater than 0');
    }

    /** @test */
    public function it_validates_component_with_weight_over_100(): void
    {
        $syllabus = Syllabus::factory()->create();
        $component = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 120,
            'name' => 'Invalid Assignment'
        ]);

        $result = $this->service->validateComponentWeights($component);

        expect($result['is_valid'])->toBeFalse();
        expect($result['errors'])->toContain('Component weight cannot exceed 100%');
    }

    /** @test */
    public function it_validates_new_component_weight_within_limits(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['syllabus_id' => $syllabus->id]);

        // Create existing component that uses 60%
        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 60
        ]);

        $componentData = ['weight' => 30];

        $result = $this->service->validateNewComponentWeight($courseOffering, $componentData);

        expect($result['is_valid'])->toBeTrue();
        expect($result['total_weight'])->toBe(90);
        expect($result['remaining_weight'])->toBe(10);
        expect($result['existing_weight'])->toBe(60);
        expect($result['new_weight'])->toBe(30);
    }

    /** @test */
    public function it_validates_new_component_weight_exceeding_limits(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['syllabus_id' => $syllabus->id]);

        // Create existing component that uses 80%
        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 80
        ]);

        $componentData = ['weight' => 30];

        $result = $this->service->validateNewComponentWeight($courseOffering, $componentData);

        expect($result['is_valid'])->toBeFalse();
        expect($result['total_weight'])->toBe(110);
        expect($result['remaining_weight'])->toBe(0);
        expect($result['errors'])->toContain('Adding this component would exceed 100% total weight. Current total: 80%, New component: 30%, Total would be: 110%');
    }

    /** @test */
    public function it_generates_weight_distribution_summary(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['syllabus_id' => $syllabus->id]);

        $component1 = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 40,
            'name' => 'Assignment',
            'type' => 'assignment'
        ]);

        $component2 = AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 60,
            'name' => 'Final Exam',
            'type' => 'exam'
        ]);

        // Add some details to component1
        AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component1->id,
            'weight' => 25
        ]);

        AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component1->id,
            'weight' => 15
        ]);

        $result = $this->service->getWeightDistributionSummary($courseOffering);

        expect($result['total_weight'])->toBe(100);
        expect($result['components_count'])->toBe(2);
        expect($result['remaining_weight'])->toBe(0);
        expect($result['is_complete'])->toBeTrue();
        expect($result['exceeds_limit'])->toBeFalse();

        expect($result['weight_distribution'])->toHaveCount(2);
        expect($result['weight_distribution'][0]['name'])->toBe('Assignment');
        expect($result['weight_distribution'][0]['weight'])->toBe(40);
        expect($result['weight_distribution'][0]['details_count'])->toBe(2);
        expect($result['weight_distribution'][0]['details_weight_sum'])->toBe(40);
        expect($result['weight_distribution'][0]['details_weight_remaining'])->toBe(0);
    }

    /** @test */
    public function it_generates_recommendations_for_missing_weight(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['syllabus_id' => $syllabus->id]);

        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 70
        ]);

        $validation = $this->service->validateCourseWeights($courseOffering);
        $distribution = $this->service->getWeightDistributionSummary($courseOffering);
        $recommendations = $this->invokeMethod($this->service, 'generateRecommendations', [$validation, $distribution]);

        expect($recommendations)->toContainEqual([
            'type' => 'missing_weight',
            'message' => 'Add 30% more assessment weight to reach 100%',
            'severity' => 'warning'
        ]);
    }

    /** @test */
    public function it_generates_recommendations_for_exceeding_weight(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['syllabus_id' => $syllabus->id]);

        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 120
        ]);

        $validation = $this->service->validateCourseWeights($courseOffering);
        $distribution = $this->service->getWeightDistributionSummary($courseOffering);
        $recommendations = $this->invokeMethod($this->service, 'generateRecommendations', [$validation, $distribution]);

        expect($recommendations)->toContainEqual([
            'type' => 'exceeds_limit',
            'message' => 'Reduce assessment weights by 20% to not exceed 100%',
            'severity' => 'error'
        ]);
    }

    /** @test */
    public function it_generates_validation_report(): void
    {
        $syllabus = Syllabus::factory()->create();
        $courseOffering = CourseOffering::factory()->create([
            'syllabus_id' => $syllabus->id,
            'course_code' => 'CS101',
            'course_title' => 'Introduction to Programming',
            'section_code' => 'A'
        ]);

        AssessmentComponent::factory()->create([
            'syllabus_id' => $syllabus->id,
            'weight' => 100
        ]);

        $report = $this->service->generateWeightValidationReport($courseOffering);

        expect($report)->toHaveKeys([
            'course_offering',
            'validation_timestamp',
            'validation_results',
            'weight_distribution',
            'recommendations'
        ]);

        expect($report['course_offering']['course_code'])->toBe('CS101');
        expect($report['course_offering']['course_title'])->toBe('Introduction to Programming');
        expect($report['course_offering']['section_code'])->toBe('A');
        expect($report['validation_results']['is_valid'])->toBeTrue();
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invokeMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}