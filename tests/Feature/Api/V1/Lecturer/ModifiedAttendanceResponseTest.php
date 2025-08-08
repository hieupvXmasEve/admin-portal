<?php

declare(strict_types=1);

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Modified Session Attendance API Response', function () {
    it('returns student-focused attendance data structure', function () {
        $lecturer = Lecture::factory()->create();
        $courseOffering = CourseOffering::factory()->create([
            'primary_lecture_id' => $lecturer->id,
        ]);
        $session = ClassSession::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'lecture_id' => $lecturer->id,
        ]);

        $response = $this->actingAs($lecturer, 'api')
            ->getJson("/api/v1/lecturer/courses/sessions/{$session->id}/attendance");

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
                        'session_type',
                        'delivery_mode',
                        'attendance_required',
                        'attendance_marked',
                        'expected_attendees',
                        'actual_attendees',
                        'course' => [
                            'id',
                            'unit_code',
                            'unit_name',
                            'section_code',
                            'semester',
                        ],
                    ],
                    'students' => [
                        '*' => [
                            'student_id',
                            'student_number',
                            'full_name',
                            'email',
                            'phone',
                            'student_info',
                            'attendance' => [
                                'id',
                                'status',
                                'status_label',
                                'status_color',
                                'check_in_time',
                                'check_out_time',
                                'minutes_late',
                                'minutes_present',
                                'participation_level',
                                'participation_score',
                                'notes',
                                'excuse_reason',
                                'recording_method',
                                'is_verified',
                                'recorded_by',
                                'recorded_at',
                                'can_edit',
                                'can_verify',
                            ],
                        ],
                    ],
                    'summary' => [
                        'total_enrolled',
                        'attendance_counts' => [
                            'present',
                            'absent',
                            'late',
                            'excused',
                            'not_marked',
                        ],
                        'attendance_rate',
                        'completion_rate',
                        'needs_attention',
                        'quick_stats' => [
                            'present_percentage',
                            'absent_percentage',
                            'late_percentage',
                            'excused_percentage',
                            'not_marked_percentage',
                        ],
                    ],
                    'actions' => [
                        'can_mark_all_present',
                        'can_mark_all_absent',
                        'can_export',
                        'can_send_notifications',
                        'can_save_draft',
                        'can_generate_report',
                    ],
                    'recommendations',
                ],
            ]);

        // Verify the main data structure is student-focused
        expect($response->json('data'))->toHaveKey('students');
        expect($response->json('data'))->toHaveKey('summary');
        expect($response->json('data.summary'))->toHaveKey('attendance_counts');
    });
});
