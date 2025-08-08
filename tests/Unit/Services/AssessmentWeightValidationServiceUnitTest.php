<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Services\AssessmentWeightValidationService;
use Mockery;
use Tests\TestCase;

class AssessmentWeightValidationServiceUnitTest extends TestCase
{
    private AssessmentWeightValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AssessmentWeightValidationService;
    }

    public function test_validates_course_weights_with_no_syllabus(): void
    {
        $courseOffering = Mockery::mock(CourseOffering::class);
        $courseOffering->shouldReceive('getAttribute')->with('syllabus')->andReturn(null);

        $result = $this->service->validateCourseWeights($courseOffering);

        $this->assertArrayHasKey('total_weight', $result);
        $this->assertArrayHasKey('is_valid', $result);
        $this->assertArrayHasKey('exceeds_limit', $result);
        $this->assertArrayHasKey('missing_weight', $result);
        $this->assertArrayHasKey('component_validations', $result);
        $this->assertArrayHasKey('errors', $result);

        $this->assertEquals(0, $result['total_weight']);
        $this->assertFalse($result['is_valid']);
        $this->assertFalse($result['exceeds_limit']);
        $this->assertEquals(100, $result['missing_weight']);
        $this->assertEmpty($result['component_validations']);
        $this->assertContains('No syllabus found for this course offering', $result['errors']);
    }

    public function test_validates_component_weights_with_valid_structure(): void
    {
        // Create mock component
        $component = Mockery::mock(AssessmentComponent::class);
        $component->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $component->shouldReceive('getAttribute')->with('name')->andReturn('Assignment');
        $component->shouldReceive('getAttribute')->with('weight')->andReturn(50);

        // Mock details collection
        $details = collect([]);
        $component->shouldReceive('getAttribute')->with('details')->andReturn($details);

        $result = $this->service->validateComponentWeights($component);

        $this->assertEquals(1, $result['assessment_id']);
        $this->assertEquals('Assignment', $result['assessment_name']);
        $this->assertEquals(50, $result['current_weight']);
        $this->assertEquals(0, $result['detail_weights_sum']);
        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validates_component_with_zero_weight(): void
    {
        $component = Mockery::mock(AssessmentComponent::class);
        $component->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $component->shouldReceive('getAttribute')->with('name')->andReturn('Invalid Assignment');
        $component->shouldReceive('getAttribute')->with('weight')->andReturn(0);

        $details = collect([]);
        $component->shouldReceive('getAttribute')->with('details')->andReturn($details);

        $result = $this->service->validateComponentWeights($component);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Component weight must be greater than 0', $result['errors']);
    }

    public function test_validates_component_with_weight_over_100(): void
    {
        $component = Mockery::mock(AssessmentComponent::class);
        $component->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $component->shouldReceive('getAttribute')->with('name')->andReturn('Invalid Assignment');
        $component->shouldReceive('getAttribute')->with('weight')->andReturn(120);

        $details = collect([]);
        $component->shouldReceive('getAttribute')->with('details')->andReturn($details);

        $result = $this->service->validateComponentWeights($component);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Component weight cannot exceed 100%', $result['errors']);
    }

    public function test_validates_new_component_weight_within_limits(): void
    {
        $courseOffering = Mockery::mock(CourseOffering::class);
        $syllabus = Mockery::mock();
        $syllabus->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $courseOffering->shouldReceive('getAttribute')->with('syllabus')->andReturn($syllabus);

        $componentData = ['weight' => 30];

        // Mock existing components query that returns collection with total weight 60
        $existingComponent = Mockery::mock(AssessmentComponent::class);
        $existingComponent->shouldReceive('getAttribute')->with('weight')->andReturn(60);

        $existingComponents = collect([$existingComponent]);
        $existingComponents->shouldReceive('sum')->with('weight')->andReturn(60);

        // Mock the AssessmentComponent::where query chain
        $queryBuilder = Mockery::mock();
        $queryBuilder->shouldReceive('when')->andReturnSelf();
        $queryBuilder->shouldReceive('get')->andReturn($existingComponents);

        AssessmentComponent::shouldReceive('where')->with('syllabus_id', 1)->andReturn($queryBuilder);

        $result = $this->service->validateNewComponentWeight($courseOffering, $componentData);

        $this->assertTrue($result['is_valid']);
        $this->assertEquals(90, $result['total_weight']);
        $this->assertEquals(10, $result['remaining_weight']);
        $this->assertEquals(60, $result['existing_weight']);
        $this->assertEquals(30, $result['new_weight']);
    }

    public function test_validates_new_component_weight_exceeding_limits(): void
    {
        $courseOffering = Mockery::mock(CourseOffering::class);
        $syllabus = Mockery::mock();
        $syllabus->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $courseOffering->shouldReceive('getAttribute')->with('syllabus')->andReturn($syllabus);

        $componentData = ['weight' => 30];

        // Mock existing components query that returns collection with total weight 80
        $existingComponent = Mockery::mock(AssessmentComponent::class);
        $existingComponent->shouldReceive('getAttribute')->with('weight')->andReturn(80);

        $existingComponents = collect([$existingComponent]);
        $existingComponents->shouldReceive('sum')->with('weight')->andReturn(80);

        // Mock the AssessmentComponent::where query chain
        $queryBuilder = Mockery::mock();
        $queryBuilder->shouldReceive('when')->andReturnSelf();
        $queryBuilder->shouldReceive('get')->andReturn($existingComponents);

        AssessmentComponent::shouldReceive('where')->with('syllabus_id', 1)->andReturn($queryBuilder);

        $result = $this->service->validateNewComponentWeight($courseOffering, $componentData);

        $this->assertFalse($result['is_valid']);
        $this->assertEquals(110, $result['total_weight']);
        $this->assertEquals(0, $result['remaining_weight']);
        $this->assertContains('Adding this component would exceed 100% total weight. Current total: 80%, New component: 30%, Total would be: 110%', $result['errors']);
    }

    public function test_validates_new_detail_weight_within_component_limits(): void
    {
        $component = Mockery::mock(AssessmentComponent::class);
        $component->shouldReceive('getAttribute')->with('weight')->andReturn(50);

        $detailData = ['weight' => 20];

        // Mock existing details query
        $existingDetail = Mockery::mock(AssessmentComponentDetail::class);
        $existingDetail->shouldReceive('getAttribute')->with('weight')->andReturn(25);

        $existingDetails = collect([$existingDetail]);
        $existingDetails->shouldReceive('sum')->with('weight')->andReturn(25);

        // Mock the details() relationship
        $detailsRelation = Mockery::mock();
        $detailsRelation->shouldReceive('when')->andReturnSelf();
        $detailsRelation->shouldReceive('get')->andReturn($existingDetails);

        $component->shouldReceive('details')->andReturn($detailsRelation);

        $result = $this->service->validateNewDetailWeight($component, $detailData);

        $this->assertTrue($result['is_valid']);
        $this->assertEquals(45, $result['total_detail_weight']);
        $this->assertEquals(5, $result['remaining_weight']);
        $this->assertEquals(25, $result['existing_weight']);
        $this->assertEquals(20, $result['new_weight']);
        $this->assertEquals(50, $result['component_weight']);
    }

    public function test_validates_new_detail_weight_exceeding_component_limits(): void
    {
        $component = Mockery::mock(AssessmentComponent::class);
        $component->shouldReceive('getAttribute')->with('weight')->andReturn(50);

        $detailData = ['weight' => 40];

        // Mock existing details query
        $existingDetail = Mockery::mock(AssessmentComponentDetail::class);
        $existingDetail->shouldReceive('getAttribute')->with('weight')->andReturn(25);

        $existingDetails = collect([$existingDetail]);
        $existingDetails->shouldReceive('sum')->with('weight')->andReturn(25);

        // Mock the details() relationship
        $detailsRelation = Mockery::mock();
        $detailsRelation->shouldReceive('when')->andReturnSelf();
        $detailsRelation->shouldReceive('get')->andReturn($existingDetails);

        $component->shouldReceive('details')->andReturn($detailsRelation);

        $result = $this->service->validateNewDetailWeight($component, $detailData);

        $this->assertFalse($result['is_valid']);
        $this->assertEquals(65, $result['total_detail_weight']);
        $this->assertEquals(0, $result['remaining_weight']);
        $this->assertContains('Adding this detail would exceed component weight. Component weight: 50%, Existing details: 25%, New detail: 40%, Total would be: 65%', $result['errors']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
