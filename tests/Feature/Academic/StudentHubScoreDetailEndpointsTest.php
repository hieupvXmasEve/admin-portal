<?php

declare(strict_types=1);

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('serves the existing score-detail and lazy-score payloads through Delivery queries', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create(['code' => 'DETAIL101', 'name' => 'Details']);
    $syllabus = SyllabusTemplate::factory()->create(['unit_id' => $unit->id]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'syllabus_template_id' => $syllabus->id,
    ]);
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $component = AssessmentComponent::factory()->create([
        'syllabus_template_id' => $syllabus->id,
        'name' => 'Final Exam',
        'type' => 'exam',
        'weight' => 100,
    ]);
    $detail = AssessmentComponentDetail::factory()->create([
        'assessment_component_id' => $component->id,
        'name' => 'Written Exam',
        'max_points' => 100,
        'due_date' => now()->toDateString(),
    ]);
    AssessmentComponentDetailScore::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'assessment_component_detail_id' => $detail->id,
        'points_earned' => 82,
        'percentage_score' => 82,
        'status' => 'graded',
        'score_status' => 'final',
        'graded_at' => now(),
        'instructor_feedback' => 'Good work.',
    ]);
    $user = User::factory()->create();
    session(['current_campus_id' => $campus->id]);
    app()->singleton('campus', fn () => $campus);
    $permissions = Mockery::mock(PermissionService::class);
    $permissions->shouldReceive('getUserPermissions')->andReturn(['view_student_summary']);
    app()->singleton(PermissionService::class, fn () => $permissions);

    actingAs($user)
        ->getJson(route('students.academic-summary.score-details', $student).'?course_offering_id='.$offering->id)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.course_info.code', 'DETAIL101')
        ->assertJsonPath('data.assessment_components.0.component_name', 'Final Exam')
        ->assertJsonPath('data.assessment_components.0.details.0.instructor_feedback', 'Good work.');

    actingAs($user)
        ->getJson(route('students.academic-summary.course-scores', [$student, $offering]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.pagination.total', 1)
        ->assertJsonPath('data.data.0.assessment_name', 'Written Exam');
});
