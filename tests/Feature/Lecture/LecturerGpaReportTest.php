<?php

declare(strict_types=1);

use App\Constants\LectureRoutes;
use App\Exports\LecturerGpaReportExport;
use App\Models\Answer;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\FormSection;
use App\Models\FormTarget;
use App\Models\FormVersion;
use App\Models\Lecture;
use App\Models\Question;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentFormAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\PermissionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-05-31 10:00:00'));

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HN']);

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    bindLecturerGpaPermissions(['view_lecturer', 'view_survey_results_aggregate']);
});

afterEach(function () {
    Carbon::setTestNow();
});

function bindLecturerGpaPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn($permissions);

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
}

function createLecturerGpaSurveyTarget(object $context, CourseOffering $offering, array $lecturerEvaluationScores): FormTarget
{
    $form = Form::create([
        'code' => 'COURSE-SURVEY-'.$offering->id,
        'type' => 'survey',
        'title' => 'Course Evaluation',
        'description' => 'Course survey',
        'status' => 'active',
        'created_by' => $context->user->id,
    ]);

    $version = FormVersion::create([
        'form_id' => $form->id,
        'version_no' => 1,
        'is_published' => true,
        'effective_from' => now()->subDay(),
    ]);

    $lecturerSection = FormSection::create([
        'form_version_id' => $version->id,
        'title' => 'Lecturer Evaluation',
        'order_index' => 1,
    ]);

    $lecturerQuestion = Question::create([
        'form_version_id' => $version->id,
        'section_id' => $lecturerSection->id,
        'code' => 'LECTURER-RATING',
        'text' => 'Lecturer explains the subject clearly.',
        'type' => 'rating',
        'order_index' => 1,
    ]);

    $courseSection = FormSection::create([
        'form_version_id' => $version->id,
        'title' => 'Course Content',
        'order_index' => 2,
    ]);

    $courseQuestion = Question::create([
        'form_version_id' => $version->id,
        'section_id' => $courseSection->id,
        'code' => 'COURSE-RATING',
        'text' => 'Course materials are useful.',
        'type' => 'rating',
        'order_index' => 2,
    ]);

    $target = FormTarget::create([
        'form_id' => $form->id,
        'form_version_id' => $version->id,
        'campus_id' => $offering->campus_id,
        'semester_id' => (string) $offering->semester_id,
        'scope_type' => 'course',
        'scope_id' => $offering->id,
        'status' => 'closed',
        'start_at' => now()->subWeek(),
        'end_at' => now(),
        'submission_limit_per_user' => 1,
        'is_mandatory' => true,
    ]);

    foreach (array_values($lecturerEvaluationScores) as $index => $score) {
        $student = Student::factory()->forCampus($context->campus)->create([
            'student_id' => sprintf('SV%s%03d', $offering->id, $index + 1),
            'intake_semester_id' => $offering->semester_id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ]);

        $response = FormResponse::create([
            'form_id' => $form->id,
            'form_version_id' => $version->id,
            'form_target_id' => $target->id,
            'campus_id' => $offering->campus_id,
            'target_scope_type' => 'course',
            'target_scope_id' => $offering->id,
            'submitted_by_student_id' => $student->id,
            'anonymized' => true,
            'status' => 'submitted',
            'origin' => 'web',
            'submitted_at' => now(),
        ]);

        StudentFormAssignment::create([
            'student_id' => $student->id,
            'form_target_id' => $target->id,
            'status' => 'completed',
            'response_id' => $response->id,
            'completed_at' => now(),
        ]);

        Answer::create([
            'response_id' => $response->id,
            'question_id' => $lecturerQuestion->id,
            'answer_number' => $score,
        ]);

        Answer::create([
            'response_id' => $response->id,
            'question_id' => $courseQuestion->id,
            'answer_number' => 1,
        ]);
    }

    return $target;
}

function seedLecturerGpaReportData(object $context): array
{
    $activeSemester = Semester::factory()->active()->create([
        'code' => '2026SP',
        'name' => 'Spring 2026',
    ]);
    $oldSemester = Semester::factory()->create([
        'code' => '2025FA',
        'name' => 'Fall 2025',
        'is_active' => false,
    ]);
    $otherCampus = Campus::factory()->create(['code' => 'HCM']);

    $au001 = Unit::factory()->create(['code' => 'AU001', 'name' => 'Academic Unit 1']);
    $au002 = Unit::factory()->create(['code' => 'AU002', 'name' => 'Academic Unit 2']);

    $lecturer = Lecture::factory()->create([
        'campus_id' => $context->campus->id,
        'employee_id' => 'GV001',
        'first_name' => 'Dung',
        'last_name' => 'Nguyen',
        'email' => 'trangnk16@swin.edu.vn',
        'employment_type' => 'part_time',
    ]);

    $offeringOne = CourseOffering::factory()->create([
        'semester_id' => $activeSemester->id,
        'unit_id' => $au001->id,
        'lecture_id' => $lecturer->id,
        'campus_id' => $context->campus->id,
        'section_code' => '1',
    ]);

    $offeringTwo = CourseOffering::factory()->create([
        'semester_id' => $activeSemester->id,
        'unit_id' => $au002->id,
        'lecture_id' => $lecturer->id,
        'campus_id' => $context->campus->id,
        'section_code' => '1',
    ]);

    $oldOffering = CourseOffering::factory()->create([
        'semester_id' => $oldSemester->id,
        'unit_id' => $au001->id,
        'lecture_id' => $lecturer->id,
        'campus_id' => $context->campus->id,
        'section_code' => 'OLD',
    ]);

    $otherCampusLecturer = Lecture::factory()->create(['campus_id' => $otherCampus->id]);
    $otherCampusOffering = CourseOffering::factory()->create([
        'semester_id' => $activeSemester->id,
        'unit_id' => $au001->id,
        'lecture_id' => $otherCampusLecturer->id,
        'campus_id' => $otherCampus->id,
        'section_code' => 'OTH',
    ]);

    createLecturerGpaSurveyTarget($context, $offeringOne, [5]);
    createLecturerGpaSurveyTarget($context, $offeringTwo, [1, 1, 1]);
    createLecturerGpaSurveyTarget($context, $oldOffering, [5]);
    createLecturerGpaSurveyTarget($context, $otherCampusOffering, [5]);

    return [$activeSemester, $lecturer];
}

it('shows active-semester lecturer GPA as an average of class-level evaluation scores', function () {
    [$activeSemester] = seedLecturerGpaReportData($this);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(LectureRoutes::LECTURER_GPA_INDEX))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Lectures/LecturerGpa')
            ->where('filters.semester_id', (string) $activeSemester->id)
            ->has('rows.data', 1)
            ->where('rows.data.0.lecturer_name', 'Dung Nguyen')
            ->where('rows.data.0.email_account', 'trangnk16')
            ->where('rows.data.0.employee_id', 'GV001')
            ->where('rows.data.0.type', 'part_time')
            ->where('rows.data.0.type_label', 'Part Time')
            ->where('rows.data.0.courses', ['AU001.1', 'AU002.1'])
            ->where('rows.data.0.gpa', 3)
            ->where('rows.data.0.evaluated_classes_count', 2)
            ->where('rows.data.0.classes_count', 2)
            ->where('rows.data.0.responses_count', 4));
});

it('downloads lecturer GPA export without student identifiers', function () {
    [$activeSemester] = seedLecturerGpaReportData($this);

    Excel::fake();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(LectureRoutes::LECTURER_GPA_EXPORT, [
            'semester_id' => $activeSemester->id,
        ]))
        ->assertOk();

    Excel::assertDownloaded(
        'lecturer_gpa_2026SP_2026-05-31_100000.xlsx',
        function (LecturerGpaReportExport $export): bool {
            $content = collect($export->array())->flatten()->implode(' | ');

            expect($content)
                ->toContain('Lecturer GPA Report')
                ->toContain('Spring 2026')
                ->toContain('Dung Nguyen')
                ->toContain('trangnk16')
                ->toContain('AU001.1, AU002.1')
                ->toContain('3')
                ->not->toContain('SV');

            return true;
        },
    );
});

it('requires lecturer and aggregate survey result permissions', function () {
    seedLecturerGpaReportData($this);
    bindLecturerGpaPermissions(['view_lecturer']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(LectureRoutes::LECTURER_GPA_INDEX))
        ->assertForbidden();
});
