<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Lecture;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\User;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('includes a grade_display object for each student row in the lecturer grade matrix', function () {
    $lecturer = Lecture::factory()->create([
        'user_id' => User::factory()->create([
            'type' => UserType::LECTURER,
            'status' => User::STATUS_ACTIVE,
        ])->id,
        'campus_id' => Campus::factory()->create()->id,
        'is_active' => true,
        'employment_status' => 'active',
    ]);
    $syllabus = SyllabusTemplate::factory()->create();
    $course = CourseOffering::factory()->create([
        'lecture_id' => $lecturer->id,
        'syllabus_template_id' => $syllabus->id,
    ]);
    ClassSession::factory()->create([
        'course_offering_id' => $course->id,
        'lecture_id' => $lecturer->id,
    ]);

    $registration = CourseRegistration::create([
        'student_id' => Student::factory()->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $course->semester_id])->id,
        'course_offering_id' => $course->id,
        'semester_id' => $course->semester_id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'credit_hours' => 3,
        'credit_points' => 10,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $registration->student_id,
        'course_offering_id' => $course->id,
        'semester_id' => $course->semester_id,
        'final_percentage' => 100,
        'final_letter_grade' => '5',
        'is_passed' => true,
        'grade_breakdown' => [
            'engine' => 'metropolia_v1',
            'scale' => '0-5',
            'components' => [
                'EXAM' => ['score_pct' => 88, 'converted_grade' => 5, 'gate_met' => null],
            ],
            'fg_rounded' => 5,
            'gates_passed' => true,
            'gate_failures' => [],
            'final_grade' => '5',
        ],
    ]);

    Sanctum::actingAs($lecturer);

    $this->getJson(route('v1.lecturer.assessments.report.grade-matrix', ['courseOffering' => $course->id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.grade_matrix.student_grades.0.grade_display.scheme_engine', 'metropolia_v1')
        ->assertJsonPath('data.grade_matrix.student_grades.0.grade_display.scale', 'numeric_0_5')
        ->assertJsonPath('data.grade_matrix.student_grades.0.grade_display.final_label', '5')
        ->assertJsonPath('data.grade_matrix.student_grades.0.grade_display.pass_status', 'passed');
});

it('returns a null grade_display when a student has no academic record', function () {
    $lecturer = Lecture::factory()->create([
        'user_id' => User::factory()->create([
            'type' => UserType::LECTURER,
            'status' => User::STATUS_ACTIVE,
        ])->id,
        'campus_id' => Campus::factory()->create()->id,
        'is_active' => true,
        'employment_status' => 'active',
    ]);
    $syllabus = SyllabusTemplate::factory()->create();
    $course = CourseOffering::factory()->create([
        'lecture_id' => $lecturer->id,
        'syllabus_template_id' => $syllabus->id,
    ]);
    ClassSession::factory()->create([
        'course_offering_id' => $course->id,
        'lecture_id' => $lecturer->id,
    ]);

    CourseRegistration::create([
        'student_id' => Student::factory()->create(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $course->semester_id])->id,
        'course_offering_id' => $course->id,
        'semester_id' => $course->semester_id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
        'credit_points' => 10,
    ]);

    Sanctum::actingAs($lecturer);

    $this->getJson(route('v1.lecturer.assessments.report.grade-matrix', ['courseOffering' => $course->id]))
        ->assertOk()
        ->assertJsonPath('data.grade_matrix.student_grades.0.grade_display', null);
});
