<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentFormAssignment;
use App\Models\User;
use App\Modules\Engagement\Models\Form;
use App\Modules\Engagement\Models\FormResponse;
use App\Modules\Engagement\Models\FormTarget;
use App\Modules\Engagement\Models\FormVersion;
use App\Modules\Engagement\Models\QueryTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createStudentPortalForm(Campus $campus, string $type = 'query', bool $mandatory = false): array
{
    $form = Form::create([
        'code' => strtoupper($type).'-'.uniqid('', false),
        'type' => $type,
        'title' => ucfirst($type).' form',
        'status' => 'active',
        'created_by' => User::factory()->create()->id,
    ]);
    $version = FormVersion::create([
        'form_id' => $form->id,
        'version_no' => 1,
        'is_published' => true,
        'effective_from' => now()->subDay(),
    ]);
    $question = $version->questions()->create([
        'code' => 'Q1',
        'text' => 'Please provide your feedback.',
        'type' => 'short_text',
        'is_required' => true,
        'order_index' => 1,
    ]);
    $target = FormTarget::create([
        'form_id' => $form->id,
        'form_version_id' => $version->id,
        'campus_id' => $campus->id,
        'scope_type' => 'global',
        'status' => 'active',
        'start_at' => now()->subDay(),
        'submission_limit_per_user' => 1,
        'is_mandatory' => $mandatory,
    ]);

    return [$form, $question, $target];
}

it('returns only active survey forms available to the authenticated student campus', function (): void {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $student = createPortalStudent($campus);
    [$survey] = createStudentPortalForm($campus, 'survey');
    createStudentPortalForm($otherCampus, 'survey');

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.forms.index', ['type' => 'survey']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $survey->id);
});

it('validates the student form type before querying available forms', function (): void {
    $student = createPortalStudent(Campus::factory()->create());

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.forms.index', ['type' => 'unsupported']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', 'type')
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');
});

it('submits an eligible query form and records a response for the student', function (): void {
    $campus = Campus::factory()->create();
    $student = createPortalStudent($campus);
    [$form, $question, $target] = createStudentPortalForm($campus);

    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.forms.query.submit', $form), [
        'campus_id' => $campus->id,
        'target_scope_type' => $target->scope_type,
        'answers' => [[
            'question_id' => $question->id,
            'answer_text' => 'I need assistance with this class.',
        ]],
    ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $response = FormResponse::query()
        ->where('form_id', $form->id)
        ->where('submitted_by_student_id', $student->id)
        ->sole();

    expect($response->form_target_id)->toBe($target->id);
    expect($response->answers()->value('answer_text'))->toBe('I need assistance with this class.');
    expect(QueryTicket::where('response_id', $response->id)->count())->toBe(1);
});

it('submits an eligible survey and completes its student assignment without creating a query ticket', function (): void {
    $campus = Campus::factory()->create();
    $student = createPortalStudent($campus);
    [$survey, $question, $target] = createStudentPortalForm($campus, 'survey', mandatory: true);
    $assignment = StudentFormAssignment::create([
        'student_id' => $student->id,
        'form_target_id' => $target->id,
        'status' => 'not_started',
    ]);

    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.forms.surveys.submit', $survey), [
        'campus_id' => $campus->id,
        'target_scope_type' => $target->scope_type,
        'answers' => [[
            'question_id' => $question->id,
            'answer_text' => 'The survey was helpful.',
        ]],
    ])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $response = FormResponse::query()
        ->where('form_id', $survey->id)
        ->where('submitted_by_student_id', $student->id)
        ->sole();

    expect($response->form_target_id)->toBe($target->id)
        ->and(QueryTicket::where('response_id', $response->id)->count())->toBe(0)
        ->and($assignment->fresh()->status)->toBe('completed')
        ->and($assignment->fresh()->response_id)->toBe($response->id);
});

it('does not reveal a form from another campus through the student API', function (): void {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $student = createPortalStudent($campus);
    [$foreignForm] = createStudentPortalForm($otherCampus);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.forms.query.show', $foreignForm))
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('does not allow a student to select another campus through form routes', function (): void {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $student = createPortalStudent($campus);
    [$foreignForm, $question, $foreignTarget] = createStudentPortalForm($otherCampus);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.forms.index', [
        'type' => 'query',
        'campus_id' => $otherCampus->id,
    ]))
        ->assertForbidden()
        ->assertJsonPath('success', false);

    $this->getJson(route('v1.student.forms.query.runs', ['campus_id' => $otherCampus->id]))
        ->assertForbidden()
        ->assertJsonPath('success', false);

    $this->getJson(route('v1.student.forms.query.show', [
        'form' => $foreignForm,
        'campus_id' => $otherCampus->id,
    ]))
        ->assertForbidden()
        ->assertJsonPath('success', false);

    $this->postJson(route('v1.student.forms.query.submit', $foreignForm), [
        'campus_id' => $otherCampus->id,
        'target_scope_type' => $foreignTarget->scope_type,
        'answers' => [[
            'question_id' => $question->id,
            'answer_text' => 'This response must not be submitted.',
        ]],
    ])
        ->assertForbidden()
        ->assertJsonPath('success', false);

    expect(FormResponse::where('form_id', $foreignForm->id)->count())->toBe(0);
});

it('returns eligible query runs with their detailed form contract', function (): void {
    $campus = Campus::factory()->create();
    $student = createPortalStudent($campus);
    [$form, $question, $target] = createStudentPortalForm($campus, 'query');
    createStudentPortalForm(Campus::factory()->create(), 'query');

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.forms.query.runs'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $form->id)
        ->assertJsonPath('data.0.current_version.id', $target->form_version_id)
        ->assertJsonPath('data.0.current_version.questions.0.id', $question->id)
        ->assertJsonPath('data.0.targets.0.id', $target->id);
});

it('shows an eligible query form with its current version and active target', function (): void {
    $campus = Campus::factory()->create();
    $student = createPortalStudent($campus);
    [$form, $question, $target] = createStudentPortalForm($campus, 'query');

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.forms.query.show', $form))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $form->id)
        ->assertJsonPath('data.type', 'query')
        ->assertJsonPath('data.current_version.id', $target->form_version_id)
        ->assertJsonPath('data.current_version.questions.0.id', $question->id)
        ->assertJsonPath('data.targets.0.id', $target->id);
});

it('returns only active mandatory pending survey assignments', function (): void {
    $campus = Campus::factory()->create();
    $student = createPortalStudent($campus);
    [$survey, , $mandatoryTarget] = createStudentPortalForm($campus, 'survey', mandatory: true);
    [, , $optionalTarget] = createStudentPortalForm($campus, 'survey');

    $mandatoryAssignment = StudentFormAssignment::create([
        'student_id' => $student->id,
        'form_target_id' => $mandatoryTarget->id,
        'status' => 'not_started',
    ]);
    StudentFormAssignment::create([
        'student_id' => $student->id,
        'form_target_id' => $optionalTarget->id,
        'status' => 'not_started',
    ]);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.forms.surveys.pending'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mandatoryAssignment->id)
        ->assertJsonPath('data.0.form_id', $survey->id)
        ->assertJsonPath('data.0.status', 'not_started')
        ->assertJsonPath('data.0.is_mandatory', true);
});

it('preserves the survey show route contract for an eligible survey', function (): void {
    $campus = Campus::factory()->create();
    $student = createPortalStudent($campus);
    [$survey, $question, $target] = createStudentPortalForm($campus, 'survey', mandatory: true);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.forms.surveys.show', $survey))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $survey->id)
        ->assertJsonPath('data.type', 'survey')
        ->assertJsonPath('data.current_version.id', $target->form_version_id)
        ->assertJsonPath('data.current_version.questions.0.id', $question->id)
        ->assertJsonPath('data.targets.0.id', $target->id);
});

function createPortalStudent(Campus $campus): Student
{
    $semester = Semester::factory()->create();

    return Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
}
