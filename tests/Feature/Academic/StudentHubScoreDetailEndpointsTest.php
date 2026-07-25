<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
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
use App\Shared\Contracts\Academic\StudentHubAssessmentEvidenceReader;
use App\Shared\Contracts\Academic\StudentHubCourseOutcomeEvidenceReader;
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
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
        'grade_breakdown' => ['engine' => 'default_weighted_percentage', 'final_grade' => '82'],
    ]);
    $customSyllabus = SyllabusTemplate::factory()->create([
        'grading_scheme' => ['engine' => 'metropolia_v1', 'scale' => '0-5'],
    ]);
    $customOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'syllabus_template_id' => $customSyllabus->id,
        'unit_id' => $customSyllabus->unit_id,
    ]);
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $customOffering->id,
        'semester_id' => $semester->id,
        'unit_id' => $customSyllabus->unit_id,
        'campus_id' => $campus->id,
        'final_letter_grade' => '5',
        'final_percentage' => 82,
        'grade_breakdown' => [
            'engine' => 'metropolia_v1',
            'scale' => '0-5',
            'final_grade' => '5',
            'fg_rounded' => 5,
            'passed' => true,
        ],
    ]);
    $user = User::factory()->create();
    session(['current_campus_id' => $campus->id]);
    app()->singleton('campus', fn () => $campus);
    $permissions = Mockery::mock(PermissionService::class);
    $permissions->shouldReceive('getUserPermissions')->andReturn(['view_student_summary']);
    app()->singleton(PermissionService::class, fn () => $permissions);

    $assessmentEvidence = app(StudentHubAssessmentEvidenceReader::class)->forStudent((int) $student->id);
    $outcomeEvidence = app(StudentHubCourseOutcomeEvidenceReader::class)->forStudent((int) $student->id);

    expect($assessmentEvidence)->toHaveCount(1)
        ->and($assessmentEvidence[0]->courseCode)->toBe('DETAIL101')
        ->and($assessmentEvidence[0]->scores[0]->assessmentName)->toBe('Written Exam')
        ->and($outcomeEvidence)->toHaveCount(2)
        ->and(collect($outcomeEvidence)->firstWhere('courseOfferingId', $offering->id)->gradeDisplay)->toBeNull()
        ->and(collect($outcomeEvidence)->firstWhere('courseOfferingId', $customOffering->id)->gradeDisplay)->toMatchArray([
            'scheme_engine' => 'metropolia_v1',
            'scale' => 'numeric_0_5',
            'final_label' => '5',
            'final_numeric' => 5,
            'pass_status' => 'passed',
            'components' => [],
        ]);

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
