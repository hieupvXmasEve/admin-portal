<?php

declare(strict_types=1);

use App\Exports\SurveyRunAggregateExport;
use App\Models\Answer;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\FormSection;
use App\Models\FormTarget;
use App\Models\FormVersion;
use App\Models\Lecture;
use App\Models\Option;
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

    bindSurveyResultPermissions(['view_survey_results_aggregate']);
});

afterEach(function () {
    Carbon::setTestNow();
});

function bindSurveyResultPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn($permissions);

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
}

function seedSurveyAggregateRun(object $context): array
{
    $semester = Semester::factory()->active()->create(['name' => 'Spring 2026']);
    $unit = Unit::factory()->create(['code' => 'CS101', 'name' => 'Programming Fundamentals']);
    $lecture = Lecture::factory()->create([
        'campus_id' => $context->campus->id,
        'title' => 'Dr.',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'lecture_id' => $lecture->id,
        'campus_id' => $context->campus->id,
        'section_code' => 'A',
    ]);

    $form = Form::create([
        'code' => 'COURSE-SURVEY',
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

    $section = FormSection::create([
        'form_version_id' => $version->id,
        'title' => 'Teaching Quality',
        'order_index' => 1,
    ]);

    $ratingQuestion = Question::create([
        'form_version_id' => $version->id,
        'section_id' => $section->id,
        'code' => 'Q1',
        'text' => 'How would you rate the lecturer?',
        'type' => 'rating',
        'order_index' => 1,
    ]);

    $choiceQuestion = Question::create([
        'form_version_id' => $version->id,
        'section_id' => $section->id,
        'code' => 'Q2',
        'text' => 'Would you recommend this class?',
        'type' => 'single_choice',
        'order_index' => 2,
    ]);

    $yesOption = Option::create([
        'question_id' => $choiceQuestion->id,
        'value' => 'yes',
        'label' => 'Yes',
        'order_index' => 1,
    ]);

    Option::create([
        'question_id' => $choiceQuestion->id,
        'value' => 'no',
        'label' => 'No',
        'order_index' => 2,
    ]);

    $textQuestion = Question::create([
        'form_version_id' => $version->id,
        'section_id' => $section->id,
        'code' => 'Q3',
        'text' => 'What should be improved?',
        'type' => 'long_text',
        'order_index' => 3,
    ]);

    $target = FormTarget::create([
        'form_id' => $form->id,
        'form_version_id' => $version->id,
        'campus_id' => $context->campus->id,
        'semester_id' => (string) $semester->id,
        'scope_type' => 'course',
        'scope_id' => $courseOffering->id,
        'status' => 'closed',
        'start_at' => now()->subWeek(),
        'end_at' => now(),
        'submission_limit_per_user' => 1,
        'is_mandatory' => true,
    ]);

    $student = Student::factory()->forCampus($context->campus)->create([
        'student_id' => 'SV001',
        'full_name' => 'Student One',
        'intake_semester_id' => $semester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ]);

    $response = FormResponse::create([
        'form_id' => $form->id,
        'form_version_id' => $version->id,
        'form_target_id' => $target->id,
        'campus_id' => $context->campus->id,
        'target_scope_type' => 'course',
        'target_scope_id' => $courseOffering->id,
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
        'question_id' => $ratingQuestion->id,
        'answer_number' => 5,
    ]);

    $choiceAnswer = Answer::create([
        'response_id' => $response->id,
        'question_id' => $choiceQuestion->id,
    ]);
    $choiceAnswer->selectedOptions()->attach($yesOption->id);

    Answer::create([
        'response_id' => $response->id,
        'question_id' => $textQuestion->id,
        'answer_text' => 'More practical exercises.',
    ]);

    return [$target, $student];
}

it('lists only current-campus survey runs matching the index filters', function (): void {
    [$target] = seedSurveyAggregateRun($this);
    $otherCampus = Campus::factory()->create(['code' => 'HCM']);
    $foreignTarget = FormTarget::create([
        'form_id' => $target->form_id,
        'form_version_id' => $target->form_version_id,
        'campus_id' => $otherCampus->id,
        'semester_id' => $target->semester_id,
        'scope_type' => 'course',
        'scope_id' => $target->scope_id,
        'status' => 'closed',
        'start_at' => now()->subWeek(),
        'end_at' => now(),
        'submission_limit_per_user' => 1,
        'is_mandatory' => true,
    ]);
    bindSurveyResultPermissions(['view_survey']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('forms.admin.results.index', [
            'search' => 'Course Evaluation',
            'semester_id' => $target->semester_id,
            'status' => 'closed',
            'sort' => 'form_title',
            'direction' => 'asc',
            'per_page' => 5,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Admin/results/Index')
            ->where('filters.search', 'Course Evaluation')
            ->where('filters.semester_id', (string) $target->semester_id)
            ->where('filters.status', 'closed')
            ->where('filters.sort', 'form_title')
            ->where('filters.direction', 'asc')
            ->where('runs.total', 1)
            ->where('runs.data.0.id', $target->id)
        );

    expect($foreignTarget->campus_id)->not->toBe($this->campus->id);
});

it('renders an aggregate survey result page with current-run header data', function (): void {
    [$target] = seedSurveyAggregateRun($this);

    actingAs($this->user)
        ->get(route('forms.admin.results.aggregate', [
            'target' => $target,
            'return' => 'status=closed&per_page=15',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Admin/results/Aggregate')
            ->where('target.id', $target->id)
            ->where('header.form_title', 'Course Evaluation')
            ->where('header.responses_done', 1)
            ->where('overall.average', 5)
            ->where('navigation.return_params', 'status=closed&per_page=15')
        );
});

it('renders raw survey responses with response filters', function (): void {
    [$target, $student] = seedSurveyAggregateRun($this);
    bindSurveyResultPermissions(['view_survey_results_raw']);

    actingAs($this->user)
        ->get(route('forms.admin.results.raw', [
            'target' => $target,
            'search' => $student->student_id,
            'status' => 'submitted',
            'sort' => 'submitted_at',
            'direction' => 'asc',
            'per_page' => 5,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Admin/results/Raw')
            ->where('target.id', $target->id)
            ->where('filters.search', $student->student_id)
            ->where('filters.status', 'submitted')
            ->where('filters.sort', 'submitted_at')
            ->where('filters.direction', 'asc')
            ->where('responses.total', 1)
        );
});

it('renders current-campus program survey stats for the selected semester', function (): void {
    [$target] = seedSurveyAggregateRun($this);

    actingAs($this->user)
        ->get(route('forms.admin.results.stats', ['semester_id' => $target->semester_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Admin/results/Stats')
            ->where('filters.semester_id', (string) $target->semester_id)
            ->where('stats.totals.submissions', 1)
            ->where('stats.totals.high_rated_count', 1)
            ->where('stats.totals.percent', 100)
        );
});

it('requires raw and aggregate-result permissions for their respective result pages', function (): void {
    [$target] = seedSurveyAggregateRun($this);

    bindSurveyResultPermissions(['view_survey_results_aggregate']);

    actingAs($this->user)
        ->get(route('forms.admin.results.raw', $target))
        ->assertForbidden();

    bindSurveyResultPermissions(['view_survey_results_raw']);

    actingAs($this->user)
        ->get(route('forms.admin.results.stats'))
        ->assertForbidden();
});

it('does not expose foreign-campus or non-survey targets through result routes', function (): void {
    [$target] = seedSurveyAggregateRun($this);
    $otherCampus = Campus::factory()->create(['code' => 'HCM']);
    $foreignTarget = FormTarget::create([
        'form_id' => $target->form_id,
        'form_version_id' => $target->form_version_id,
        'campus_id' => $otherCampus->id,
        'semester_id' => $target->semester_id,
        'scope_type' => 'course',
        'scope_id' => $target->scope_id,
        'status' => 'closed',
        'start_at' => now()->subWeek(),
        'end_at' => now(),
        'submission_limit_per_user' => 1,
        'is_mandatory' => true,
    ]);
    $queryForm = Form::create([
        'code' => 'QUERY-RESULT-TARGET',
        'type' => 'query',
        'title' => 'Query target',
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $queryVersion = FormVersion::create([
        'form_id' => $queryForm->id,
        'version_no' => 1,
        'is_published' => true,
        'effective_from' => now()->subDay(),
    ]);
    $queryTarget = FormTarget::create([
        'form_id' => $queryForm->id,
        'form_version_id' => $queryVersion->id,
        'campus_id' => $this->campus->id,
        'scope_type' => 'global',
        'status' => 'closed',
        'start_at' => now()->subWeek(),
        'end_at' => now(),
        'submission_limit_per_user' => 1,
        'is_mandatory' => false,
    ]);

    bindSurveyResultPermissions(['view_survey_results_aggregate']);

    actingAs($this->user)
        ->get(route('forms.admin.results.aggregate', $foreignTarget))
        ->assertNotFound();

    actingAs($this->user)
        ->get(route('forms.admin.results.aggregate.download', $foreignTarget))
        ->assertNotFound();

    bindSurveyResultPermissions(['view_survey_results_raw']);

    actingAs($this->user)
        ->get(route('forms.admin.results.raw', $foreignTarget))
        ->assertNotFound();

    bindSurveyResultPermissions(['view_survey_results_aggregate']);

    actingAs($this->user)
        ->get(route('forms.admin.results.aggregate', $queryTarget))
        ->assertNotFound();
});

it('validates index and response-list filters before executing result queries', function (): void {
    [$target] = seedSurveyAggregateRun($this);
    bindSurveyResultPermissions(['view_survey']);

    actingAs($this->user)
        ->get(route('forms.admin.results.index', ['status' => 'invalid']))
        ->assertSessionHasErrors('status');

    bindSurveyResultPermissions(['view_survey_results_aggregate']);

    actingAs($this->user)
        ->get(route('forms.admin.results.aggregate', [
            'target' => $target,
            'per_page' => 101,
        ]))
        ->assertSessionHasErrors('per_page');

    bindSurveyResultPermissions(['view_survey_results_raw']);

    actingAs($this->user)
        ->get(route('forms.admin.results.raw', [
            'target' => $target,
            'sort' => 'invalid',
        ]))
        ->assertSessionHasErrors('sort');
});

it('downloads aggregate survey results for a course class without student identifiers', function () {
    [$target, $student] = seedSurveyAggregateRun($this);

    Excel::fake();

    actingAs($this->user)
        ->get(route('forms.admin.results.aggregate.download', $target))
        ->assertOk();

    Excel::assertDownloaded(
        'survey_results_CS101_A_2026-05-31_100000.xlsx',
        function (SurveyRunAggregateExport $export) use ($student) {
            $content = collect($export->array())->flatten()->implode(' | ');

            expect($content)
                ->toContain('Course Evaluation')
                ->toContain('CS101')
                ->toContain('Programming Fundamentals')
                ->toContain('Teaching Quality')
                ->toContain('How would you rate the lecturer?')
                ->toContain('5')
                ->not->toContain($student->student_id)
                ->not->toContain($student->full_name);

            return true;
        },
    );
});

it('requires aggregate survey result permission for the download route', function () {
    [$target] = seedSurveyAggregateRun($this);
    bindSurveyResultPermissions([]);

    actingAs($this->user)
        ->get(route('forms.admin.results.aggregate.download', $target))
        ->assertForbidden();
});
