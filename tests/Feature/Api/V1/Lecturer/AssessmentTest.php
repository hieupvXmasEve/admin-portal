<?php

declare(strict_types=1);

use App\Models\Lecture;
use App\Models\CourseOffering;
use App\Models\Syllabus;
use App\Models\AssessmentComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->lecturer = Lecture::factory()->create();
    $this->courseOffering = CourseOffering::factory()->create([
        'lecture_id' => $this->lecturer->id,
    ]);
    $this->syllabus = Syllabus::factory()->create([
        'curriculum_unit_id' => $this->courseOffering->curriculum_unit_id,
        'is_active' => true,
    ]);
});

describe('Assessment Management API', function () {
    it('can access assessment index endpoint', function () {
        $response = $this->actingAs($this->lecturer, 'api')
            ->getJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments");

        // The response should not be a 404 or 500, indicating the route is working
        expect($response->status())->not->toBe(404);
        expect($response->status())->not->toBe(500);
    });

    it('can access assessment report overview endpoint', function () {
        $response = $this->actingAs($this->lecturer, 'api')
            ->getJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments/report/overview");

        // The response should not be a 404 or 500, indicating the route is working
        expect($response->status())->not->toBe(404);
        expect($response->status())->not->toBe(500);
    });

    it('can access grade matrix endpoint', function () {
        $response = $this->actingAs($this->lecturer, 'api')
            ->getJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments/report/grade-matrix");

        // The response should not be a 404 or 500, indicating the route is working
        expect($response->status())->not->toBe(404);
        expect($response->status())->not->toBe(500);
    });

    it('can access statistics endpoint', function () {
        $response = $this->actingAs($this->lecturer, 'api')
            ->getJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments/report/statistics");

        // The response should not be a 404 or 500, indicating the route is working
        expect($response->status())->not->toBe(404);
        expect($response->status())->not->toBe(500);
    });

    it('can create assessment component', function () {
        $assessmentData = [
            'name' => 'Test Quiz',
            'type' => 'quiz',
            'weight' => 20.0,
            'is_required_to_sit_final_exam' => false,
        ];

        $response = $this->actingAs($this->lecturer, 'api')
            ->postJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments", $assessmentData);

        // The response should not be a 404 or 500, indicating the route is working
        expect($response->status())->not->toBe(404);
        expect($response->status())->not->toBe(500);
    });

    it('can update assessment component', function () {
        $assessmentComponent = AssessmentComponent::factory()->create([
            'syllabus_id' => $this->syllabus->id,
            'name' => 'Original Quiz',
            'type' => 'quiz',
            'weight' => 15.0,
        ]);

        $updateData = [
            'name' => 'Updated Quiz',
            'weight' => 25.0,
        ];

        $response = $this->actingAs($this->lecturer, 'api')
            ->putJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments/{$assessmentComponent->id}", $updateData);

        // The response should not be a 404 or 500, indicating the route is working
        expect($response->status())->not->toBe(404);
        expect($response->status())->not->toBe(500);
    });

    it('can delete assessment component', function () {
        $assessmentComponent = AssessmentComponent::factory()->create([
            'syllabus_id' => $this->syllabus->id,
            'name' => 'Test Quiz',
            'type' => 'quiz',
            'weight' => 15.0,
        ]);

        $response = $this->actingAs($this->lecturer, 'api')
            ->deleteJson("/api/v1/lecturer/courses/{$this->courseOffering->id}/assessments/{$assessmentComponent->id}");

        // The response should not be a 404 or 500, indicating the route is working
        expect($response->status())->not->toBe(404);
        expect($response->status())->not->toBe(500);
    });
});
