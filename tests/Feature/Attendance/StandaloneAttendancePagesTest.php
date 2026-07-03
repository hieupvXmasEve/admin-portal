<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * ADR 0013 phase B: the standalone staff Attendance pages are demoted to
 * cross-offering reporting only. Per-offering recording lives in the Course
 * Offering Cockpit (see tests/Feature/CourseOffering/CourseOfferingRecordAttendanceTest.php).
 */
beforeEach(function () {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->unit = Unit::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn([
        'view_course_offering',
        'create_course_offering',
        'edit_course_offering',
        'delete_course_offering',
    ]);
    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);

    $this->offering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'campus_id' => $this->campus->id,
        'course_status' => 'in_progress',
        'enrollment_status' => 'open',
        'current_enrollment' => 0,
        'is_canvas_synced' => false,
    ]);

    $this->session = ClassSession::factory()->create([
        'course_offering_id' => $this->offering->id,
        'status' => 'completed',
        'session_date' => '2026-01-15',
        'start_time' => '09:00:00',
        'end_time' => '11:00:00',
        'session_type' => 'lecture',
        'delivery_mode' => 'in_person',
    ]);

    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    $this->attendance = Attendance::create([
        'class_session_id' => $this->session->id,
        'student_id' => $this->student->id,
        'status' => 'present',
        'recording_method' => 'manual',
    ]);
});

it('keeps the attendance index as a cross-offering reporting view', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get('/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/Index')
            ->has('attendances.data', 1)
            ->where('attendances.data.0.id', $this->attendance->id)
            // The reporting view deep-links back to the offering's cockpit
            // using this id, so it must be present on the loaded relation.
            ->where('attendances.data.0.class_session.course_offering_id', $this->offering->id));
});

it('removes the create attendance record page', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get('/attendance/create')
        ->assertNotFound();
});

it('removes the store attendance record endpoint', function () {
    // GET /attendance (index) still resolves at this URI, so POST is a
    // recognized-path-but-wrong-method 405 rather than a 404 — still
    // confirms the recording action is gone.
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->post('/attendance', [
            'class_session_id' => $this->session->id,
            'student_id' => $this->student->id,
            'status' => 'present',
            'recording_method' => 'manual',
        ])
        ->assertStatus(405);

    expect(Attendance::where('class_session_id', $this->session->id)->count())->toBe(1);
});

it('removes the show attendance record page', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get("/attendance/{$this->attendance->id}")
        ->assertNotFound();
});

it('removes the edit attendance record page', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get("/attendance/{$this->attendance->id}/edit")
        ->assertNotFound();
});

it('removes the update attendance record endpoint', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->put("/attendance/{$this->attendance->id}", [
            'class_session_id' => $this->session->id,
            'student_id' => $this->student->id,
            'status' => 'late',
            'recording_method' => 'manual',
        ])
        ->assertNotFound();

    expect($this->attendance->fresh()->status)->toBe('present');
});

it('removes the destroy attendance record endpoint', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->delete("/attendance/{$this->attendance->id}")
        ->assertNotFound();

    expect(Attendance::find($this->attendance->id))->not->toBeNull();
});

it('removes the bulk-update attendance endpoint', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->post('/api/attendance/bulk-update', [
            'attendance_ids' => [$this->attendance->id],
            'status' => 'absent',
        ])
        ->assertNotFound();

    expect($this->attendance->fresh()->status)->toBe('present');
});

it('removes the duplicate bulk-update path that used to live under class-sessions routes', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->post('/attendance/bulk-update', [
            'attendance_ids' => [$this->attendance->id],
            'status' => 'absent',
        ])
        ->assertNotFound();
});
