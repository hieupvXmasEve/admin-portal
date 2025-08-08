<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->lecturer = Lecture::factory()->create();
    $this->courseOffering = CourseOffering::factory()->create([
        'primary_lecture_id' => $this->lecturer->id,
    ]);
    $this->session = ClassSession::factory()->create([
        'course_offering_id' => $this->courseOffering->id,
        'instructor_id' => $this->lecturer->id,
    ]);
});

describe('Session Attendance API', function () {
    it('can access session attendance endpoint', function () {
        $response = $this->actingAs($this->lecturer, 'api')
            ->getJson("/api/v1/lecturer/courses/sessions/{$this->session->id}/attendance");

        // The response should not be a 404 or 500, indicating the route is working
        expect($response->status())->not->toBe(404);
        expect($response->status())->not->toBe(500);
    });

    it('can access attendance generation endpoint', function () {
        $response = $this->actingAs($this->lecturer, 'api')
            ->postJson("/api/v1/lecturer/courses/sessions/{$this->session->id}/attendance/generate");

        // The response should not be a 404 or 500, indicating the route is working
        expect($response->status())->not->toBe(404);
        expect($response->status())->not->toBe(500);
    });
});
