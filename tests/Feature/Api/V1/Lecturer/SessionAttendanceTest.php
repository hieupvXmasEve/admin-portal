<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Lecture;
use App\Models\Student;
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
    $this->students = Student::factory()->count(3)->create();

    // Enroll students in the course
    foreach ($this->students as $student) {
        CourseRegistration::factory()->create([
            'course_offering_id' => $this->courseOffering->id,
            'student_id' => $student->id,
            'registration_status' => 'enrolled',
        ]);
    }
});

describe('GET lecturer/courses/sessions/{session}/attendance', function () {
    it('returns attendance list for specific session', function () {
        $response = $this->actingAs($this->lecturer, 'api')
            ->getJson("/api/v1/lecturer/courses/sessions/{$this->session->id}/attendance");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'session' => [
                        'id',
                        'title',
                        'date',
                        'start_time',
                        'end_time',
                        'status',
                        'attendance_marked',
                        'course' => [
                            'id',
                            'unit_code',
                            'unit_name',
                            'section_code',
                        ],
                    ],
                    'attendance_data' => [
                        '*' => [
                            'student_id',
                            'student_number',
                            'full_name',
                            'email',
                            'status',
                            'check_in_time',
                            'minutes_late',
                            'participation_score',
                            'notes',
                            'attendance_id',
                        ],
                    ],
                    'statistics' => [
                        'total_students',
                        'present',
                        'absent',
                        'excused',
                        'not_marked',
                        'attendance_rate',
                    ],
                ],
            ]);

        expect($response->json('data.attendance_data'))->toHaveCount(3);
        expect($response->json('data.statistics.total_students'))->toBe(3);
    });

    it('returns not found for non-existent session', function () {
        $response = $this->actingAs($this->lecturer, 'api')
            ->getJson('/api/v1/lecturer/courses/sessions/99999/attendance');

        $response->assertNotFound();
    });

    it('denies access to session not belonging to lecturer', function () {
        $otherLecturer = Lecture::factory()->create();
        $otherSession = ClassSession::factory()->create([
            'instructor_id' => $otherLecturer->id,
        ]);

        $response = $this->actingAs($this->lecturer, 'api')
            ->getJson("/api/v1/lecturer/courses/sessions/{$otherSession->id}/attendance");

        $response->assertNotFound();
    });

    it('includes existing attendance records', function () {
        // Create some attendance records
        Attendance::factory()->create([
            'class_session_id' => $this->session->id,
            'student_id' => $this->students[0]->id,
            'status' => 'present',
            'check_in_time' => now(),
        ]);

        Attendance::factory()->create([
            'class_session_id' => $this->session->id,
            'student_id' => $this->students[1]->id,
            'status' => 'absent',
        ]);

        $response = $this->actingAs($this->lecturer, 'api')
            ->getJson("/api/v1/lecturer/courses/sessions/{$this->session->id}/attendance");

        $response->assertOk();

        $attendanceData = collect($response->json('data.attendance_data'));

        $presentStudent = $attendanceData->where('student_id', $this->students[0]->id)->first();
        $absentStudent = $attendanceData->where('student_id', $this->students[1]->id)->first();
        $notMarkedStudent = $attendanceData->where('student_id', $this->students[2]->id)->first();

        expect($presentStudent['status'])->toBe('present');
        expect($absentStudent['status'])->toBe('absent');
        expect($notMarkedStudent['status'])->toBe('not_marked');
    });
});

describe('POST lecturer/courses/sessions/{session}/attendance/generate', function () {
    it('generates attendance records for all enrolled students', function () {
        expect(Attendance::where('class_session_id', $this->session->id)->count())->toBe(0);

        $response = $this->actingAs($this->lecturer, 'api')
            ->postJson("/api/v1/lecturer/courses/sessions/{$this->session->id}/attendance/generate");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'session_id',
                    'total_records_created',
                    'students',
                ],
            ]);

        expect(Attendance::where('class_session_id', $this->session->id)->count())->toBe(3);
        expect($response->json('data.total_records_created'))->toBe(3);

        // Verify all records have default 'absent' status
        $attendanceRecords = Attendance::where('class_session_id', $this->session->id)->get();
        foreach ($attendanceRecords as $record) {
            expect($record->status)->toBe('absent');
            expect($record->recorded_by_lecture_id)->toBe($this->lecturer->id);
        }
    });

    it('does not create duplicates when attendance records already exist', function () {
        // Create some existing attendance records
        Attendance::factory()->create([
            'class_session_id' => $this->session->id,
            'student_id' => $this->students[0]->id,
            'status' => 'present',
        ]);

        expect(Attendance::where('class_session_id', $this->session->id)->count())->toBe(1);

        $response = $this->actingAs($this->lecturer, 'api')
            ->postJson("/api/v1/lecturer/courses/sessions/{$this->session->id}/attendance/generate");

        $response->assertOk();

        // Should create 2 new records (for the remaining students) + 1 existing = 3 total
        expect(Attendance::where('class_session_id', $this->session->id)->count())->toBe(3);
        expect($response->json('data.total_records_created'))->toBe(2);

        // Verify the existing record wasn't modified
        $existingRecord = Attendance::where('class_session_id', $this->session->id)
            ->where('student_id', $this->students[0]->id)
            ->first();
        expect($existingRecord->status)->toBe('present');
    });

    it('returns existing records when all attendance already exists', function () {
        // Create attendance for all students
        foreach ($this->students as $student) {
            Attendance::factory()->create([
                'class_session_id' => $this->session->id,
                'student_id' => $student->id,
                'status' => 'present',
            ]);
        }

        $response = $this->actingAs($this->lecturer, 'api')
            ->postJson("/api/v1/lecturer/courses/sessions/{$this->session->id}/attendance/generate");

        $response->assertOk();
        expect($response->json('data.total_records_created'))->toBe(0);
        expect($response->json('message'))->toContain('already exist');
    });

    it('returns not found for non-existent session', function () {
        $response = $this->actingAs($this->lecturer, 'api')
            ->postJson('/api/v1/lecturer/courses/sessions/99999/attendance/generate');

        $response->assertNotFound();
    });

    it('denies access to session not belonging to lecturer', function () {
        $otherLecturer = Lecture::factory()->create();
        $otherSession = ClassSession::factory()->create([
            'instructor_id' => $otherLecturer->id,
        ]);

        $response = $this->actingAs($this->lecturer, 'api')
            ->postJson("/api/v1/lecturer/courses/sessions/{$otherSession->id}/attendance/generate");

        $response->assertNotFound();
    });

    it('handles session with no enrolled students gracefully', function () {
        // Remove all course registrations
        CourseRegistration::where('course_offering_id', $this->courseOffering->id)->delete();

        $response = $this->actingAs($this->lecturer, 'api')
            ->postJson("/api/v1/lecturer/courses/sessions/{$this->session->id}/attendance/generate");

        $response->assertOk();
        expect($response->json('data.total_records_created'))->toBe(0);
        expect($response->json('message'))->toContain('No enrolled students');
    });
});
