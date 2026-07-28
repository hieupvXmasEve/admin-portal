<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\AssessmentComponentDetailScore;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Delivery\Queries\GetCourseOfferingScoresQuery;
use App\Modules\Academic\Delivery\Support\AssessmentManagementService;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->lecturerUser = User::factory()->create([
        'type' => UserType::LECTURER,
        'status' => User::STATUS_ACTIVE,
    ]);
    $this->lecturer = Lecture::factory()->create([
        'user_id' => $this->lecturerUser->id,
        'campus_id' => $this->campus->id,
        'is_active' => true,
        'employment_status' => 'active',
        'is_available_for_assignment' => true,
    ]);
    $this->unit = Unit::factory()->create([
        'code' => 'DATABASES',
        'name' => 'Relational databases',
        'credit_points' => 3.0,
    ]);
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    $this->makeSyllabus = function (string $title = 'Metropolia: Relational databases'): SyllabusTemplate {
        return SyllabusTemplate::create([
            'unit_id' => $this->unit->id,
            'title' => $title,
            'version' => '1.0',
            'total_hours' => 60,
            'total_sessions' => 15,
            'learning_outcomes' => [],
            'grading_criteria' => [],
            'required_materials' => [],
            'is_default' => true,
            'is_active' => true,
            'min_grade_threshold' => 60,
            'grading_scheme' => [
                'engine' => 'metropolia_v1',
                'scale' => 'pass_fail',
                'components' => [
                    ['code' => 'ASSIGNMENT', 'label' => 'Assignments', 'conversion' => ['type' => 'pass_fail', 'min_pct' => 80]],
                ],
            ],
        ]);
    };

    $this->makeOffering = function (SyllabusTemplate $syllabus, array $overrides = []): CourseOffering {
        $offering = CourseOffering::factory()->create(array_merge([
            'semester_id' => $this->semester->id,
            'unit_id' => $this->unit->id,
            'lecture_id' => $this->lecturer->id,
            'campus_id' => $this->campus->id,
            'syllabus_template_id' => $syllabus->id,
            'grading_type' => 'pass_fail',
            'course_status' => 'in_progress',
            'enrollment_status' => 'open',
            'is_canvas_synced' => false,
        ], $overrides));

        ClassSession::factory()->create([
            'course_offering_id' => $offering->id,
            'lecture_id' => $this->lecturer->id,
            'status' => 'scheduled',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        return $offering;
    };

    $this->makeAssignmentDetail = function (SyllabusTemplate $syllabus): AssessmentComponentDetail {
        $component = AssessmentComponent::factory()->create([
            'syllabus_template_id' => $syllabus->id,
            'name' => 'Assignments',
            'code' => 'ASSIGNMENT',
            'type' => 'assignment',
            'weight' => 100,
        ]);

        return AssessmentComponentDetail::factory()->create([
            'assessment_component_id' => $component->id,
            'name' => 'Assignments',
            'weight' => 100,
            'max_points' => 100,
        ]);
    };

    $this->enrollStudentWithStaleRecord = function (CourseOffering $offering): AcademicRecord {
        CourseRegistration::query()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $offering->semester_id,
            'registration_status' => 'confirmed',
            'registration_date' => now(),
            'registration_method' => 'admin_override',
            'credit_hours' => 3.0,
            'credit_points' => 3.0,
            'attempt_number' => 1,
            'is_retake' => false,
            'retake_fee' => 0.00,
            'is_retake_paid' => 'no',
        ]);

        return AcademicRecord::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $offering->semester_id,
            'unit_id' => $this->unit->id,
            'campus_id' => $this->campus->id,
            'final_percentage' => 0,
            'final_letter_grade' => 'F',
            'grade_points' => 0,
            'quality_points' => 0,
            'credit_hours' => 3.0,
            'credit_hours_earned' => 0,
            'credit_points' => 3.0,
            'credit_points_earned' => 0,
            'grade_status' => 'in_progress',
            'completion_status' => 'in_progress',
            'enrollment_date' => now()->toDateString(),
            'is_passed' => false,
            'grade_breakdown' => ['engine' => 'default_weighted_percentage'],
        ]);
    };
});

it('sorts the lecturer grade table through Student Registry references', function (): void {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);
    $detail = ($this->makeAssignmentDetail)($syllabus);
    $this->student->update(['student_id' => 'STU-Z', 'full_name' => 'Zed Student']);
    ($this->enrollStudentWithStaleRecord)($offering);

    $anotherStudent = Student::factory()->forCampus($this->campus)->create([
        'student_id' => 'STU-A',
        'full_name' => 'Ana Student',
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);
    CourseRegistration::query()->create([
        'student_id' => $anotherStudent->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $offering->semester_id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3.0,
        'credit_points' => 3.0,
        'attempt_number' => 1,
        'is_retake' => false,
        'retake_fee' => 0.00,
        'is_retake_paid' => 'no',
    ]);

    foreach ([$this->student, $anotherStudent] as $student) {
        AssessmentComponentDetailScore::query()->create([
            'assessment_component_detail_id' => $detail->id,
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'points_earned' => 80,
            'percentage_score' => 80,
            'status' => 'graded',
            'score_status' => 'final',
        ]);
    }

    Sanctum::actingAs($this->lecturer);

    $this->getJson("/api/v1/lecturer/courses/{$offering->id}/assessments/details/{$detail->id}/grades?per_page=5&sort_by=student_name&sort_order=asc")
        ->assertOk()
        ->assertJsonPath('data.data.0.student.student_id', 'STU-A')
        ->assertJsonPath('data.data.1.student.student_id', 'STU-Z');
});

it('resolves lecturer grading data from the student route identifier', function (): void {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);
    ($this->makeAssignmentDetail)($syllabus);
    ($this->enrollStudentWithStaleRecord)($offering);

    Sanctum::actingAs($this->lecturer);

    $this->getJson("/api/v1/lecturer/courses/{$offering->id}/assessments/grade/student/{$this->student->id}")
        ->assertOk()
        ->assertJsonPath('data.student.student_id', $this->student->student_id);
});

it('returns not found for missing or malformed lecturer grading student identifiers', function (): void {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);

    Sanctum::actingAs($this->lecturer);

    $this->getJson("/api/v1/lecturer/courses/{$offering->id}/assessments/grade/student/999999999")
        ->assertNotFound();
    $this->getJson("/api/v1/lecturer/courses/{$offering->id}/assessments/grade/student/not-a-number")
        ->assertNotFound();
});

it('serializes Student Registry references on assessment exception rows', function (): void {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);
    $detail = ($this->makeAssignmentDetail)($syllabus);
    $score = AssessmentComponentDetailScore::query()->create([
        'assessment_component_detail_id' => $detail->id,
        'student_id' => $this->student->id,
        'course_offering_id' => $offering->id,
        'points_earned' => 80,
        'percentage_score' => 80,
        'status' => 'graded',
        'score_status' => 'final',
        'late_excuse' => 'Transport disruption',
        'late_excuse_approved' => false,
    ]);

    $rows = app(AssessmentManagementService::class)->getPendingLateExcuses($offering);

    expect($rows->first()?->id)->toBe($score->id)
        ->and($rows->first()?->toArray()['student'] ?? null)->toMatchArray([
            'id' => $this->student->id,
            'student_id' => $this->student->student_id,
            'full_name' => $this->student->full_name,
        ]);
});

it('refreshes assignment grade display after lecturer bulk grading an in-progress manual course', function () {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);
    $detail = ($this->makeAssignmentDetail)($syllabus);
    $record = ($this->enrollStudentWithStaleRecord)($offering);

    Sanctum::actingAs($this->lecturer);

    $response = $this->postJson("/api/v1/lecturer/courses/{$offering->id}/assessments/details/{$detail->id}/bulk-grades", [
        'grades' => [
            [
                'student_id' => $this->student->id,
                'points_earned' => 80,
                'status' => 'graded',
            ],
        ],
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $record->refresh();
    expect($record->final_letter_grade)->toBe('P')
        ->and((float) $record->final_percentage)->toBe(80.0)
        ->and($record->grade_breakdown['engine'])->toBe('metropolia_v1')
        ->and($record->grade_breakdown['passed'])->toBeTrue();

    $scoresData = app(GetCourseOfferingScoresQuery::class)->handle($offering->fresh());
    $row = $scoresData['scores_grid'][0];
    $assignmentDisplay = collect($row['grade_display']['components'])->firstWhere('code', 'ASSIGNMENT');

    expect((float) $row['scores'][0]['percentage_score'])->toBe(80.0)
        ->and($row['grade_display']['scheme_engine'])->toBe('metropolia_v1')
        ->and($row['grade_display']['final_label'])->toBe('P')
        ->and($row['grade_display']['pass_status'])->toBe('passed')
        ->and($assignmentDisplay['converted_grade'])->toBe('P')
        ->and($assignmentDisplay['requirement_status'])->toBe('passed');
});

it('does not auto-aggregate lecturer grades for completed course offerings', function () {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus, ['course_status' => 'completed']);
    $detail = ($this->makeAssignmentDetail)($syllabus);
    $record = ($this->enrollStudentWithStaleRecord)($offering);

    Sanctum::actingAs($this->lecturer);

    $response = $this->postJson("/api/v1/lecturer/courses/{$offering->id}/assessments/details/{$detail->id}/bulk-grades", [
        'grades' => [
            [
                'student_id' => $this->student->id,
                'points_earned' => 80,
                'status' => 'graded',
            ],
        ],
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    expect($record->refresh()->grade_breakdown)->toBe(['engine' => 'default_weighted_percentage'])
        ->and($record->final_letter_grade)->toBe('F');
});

it('rejects assessment details from another syllabus template for the same unit', function () {
    $currentSyllabus = ($this->makeSyllabus)('Current Metropolia template');
    $oldSyllabus = ($this->makeSyllabus)('Old Metropolia template');
    $offering = ($this->makeOffering)($currentSyllabus);
    $oldDetail = ($this->makeAssignmentDetail)($oldSyllabus);
    ($this->enrollStudentWithStaleRecord)($offering);

    Sanctum::actingAs($this->lecturer);

    $response = $this->postJson("/api/v1/lecturer/courses/{$offering->id}/assessments/details/{$oldDetail->id}/bulk-grades", [
        'grades' => [
            [
                'student_id' => $this->student->id,
                'points_earned' => 80,
                'status' => 'graded',
            ],
        ],
    ]);

    $response->assertStatus(400)->assertJsonPath('success', false);

    expect(AssessmentComponentDetailScore::query()->count())->toBe(0);
});

it('returns the standard validation envelope for invalid lecturer assessment requests', function (): void {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);
    $detail = ($this->makeAssignmentDetail)($syllabus);

    Sanctum::actingAs($this->lecturer);

    $bulkResponse = $this->postJson("/api/v1/lecturer/courses/{$offering->id}/assessments/details/{$detail->id}/bulk-grades", [
        'grades' => [],
    ]);
    $statisticsResponse = $this->getJson("/api/v1/lecturer/courses/{$offering->id}/assessments/report/statistics?type=invalid");
    $gradeMatrixResponse = $this->getJson("/api/v1/lecturer/courses/{$offering->id}/assessments/report/grade-matrix?include_excluded=invalid");

    $bulkResponse->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.field', 'grades');
    $statisticsResponse->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.field', 'type');
    $gradeMatrixResponse->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.field', 'include_excluded');
});

it('preserves assessment authorization and invalid export-format responses', function (): void {
    $syllabus = ($this->makeSyllabus)();
    $offering = ($this->makeOffering)($syllabus);
    $detail = ($this->makeAssignmentDetail)($syllabus);
    $otherLecturer = Lecture::factory()->create([
        'campus_id' => $this->campus->id,
        'is_active' => true,
        'employment_status' => 'active',
        'is_available_for_assignment' => true,
    ]);

    Sanctum::actingAs($otherLecturer);

    $unauthorizedResponse = $this->postJson("/api/v1/lecturer/courses/{$offering->id}/assessments/details/{$detail->id}/bulk-grades", [
        'grades' => [],
    ]);

    Sanctum::actingAs($this->lecturer);

    $exportResponse = $this->getJson("/api/v1/lecturer/courses/{$offering->id}/assessments/details/{$detail->id}/export?format=pdf");

    $unauthorizedResponse->assertForbidden()
        ->assertJsonPath('success', false);
    $exportResponse->assertBadRequest()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Invalid export format. Supported formats: excel, csv');
});
