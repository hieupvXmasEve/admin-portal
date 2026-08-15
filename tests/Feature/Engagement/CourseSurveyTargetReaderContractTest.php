<?php

declare(strict_types=1);

use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentFormAssignment;
use App\Modules\Engagement\Models\Form;
use App\Modules\Engagement\Models\FormTarget;
use App\Modules\Engagement\Models\FormVersion;
use App\Shared\Contracts\Engagement\CourseSurveyTargetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Academic reads a course offering's survey target through this contract
 * rather than importing the FormTarget model, which would trip the
 * zero-tolerance cross_context_concrete_imports boundary rule.
 */
function createSurveyForm(array $attributes = []): Form
{
    return Form::create(array_merge([
        'code' => 'FORM-'.uniqid('', false),
        'type' => 'survey',
        'title' => 'Course feedback',
        'status' => 'active',
    ], $attributes));
}

function createSurveyTarget(Form $form, CourseOffering $courseOffering, array $attributes = []): FormTarget
{
    $version = FormVersion::create([
        'form_id' => $form->id,
        'version_no' => $form->versions()->count() + 1,
        'is_published' => true,
        'effective_from' => now()->subDay(),
    ]);

    return FormTarget::create(array_merge([
        'form_id' => $form->id,
        'form_version_id' => $version->id,
        'scope_type' => 'course',
        'scope_id' => $courseOffering->id,
        'status' => 'active',
        'start_at' => now()->subDay(),
        'end_at' => now()->addDay(),
        'submission_limit_per_user' => 1,
        'is_mandatory' => false,
    ], $attributes));
}

it('returns the survey target for a course offering with completion stats', function (): void {
    $courseOffering = CourseOffering::factory()->create();
    $form = createSurveyForm(['title' => 'Course feedback', 'code' => 'FORM-ABC']);
    $target = createSurveyTarget($form, $courseOffering);

    $semester = Semester::factory()->create();
    $students = Student::factory()->count(3)->create([
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);
    StudentFormAssignment::create(['student_id' => $students[0]->id, 'form_target_id' => $target->id, 'status' => 'completed']);
    StudentFormAssignment::create(['student_id' => $students[1]->id, 'form_target_id' => $target->id, 'status' => 'completed']);
    StudentFormAssignment::create(['student_id' => $students[2]->id, 'form_target_id' => $target->id, 'status' => 'not_started']);

    $result = app(CourseSurveyTargetReader::class)->forCourseOffering($courseOffering->id);

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($target->id)
        ->and($result->formId)->toBe($form->id)
        ->and($result->status)->toBe('active')
        ->and($result->formTitle)->toBe('Course feedback')
        ->and($result->formCode)->toBe('FORM-ABC')
        ->and($result->totalAssignments)->toBe(3)
        ->and($result->completedAssignments)->toBe(2);
});

it('returns null when the course offering has no survey target', function (): void {
    $courseOffering = CourseOffering::factory()->create();

    expect(app(CourseSurveyTargetReader::class)->forCourseOffering($courseOffering->id))->toBeNull();
});

it('returns the latest target when a course offering has more than one', function (): void {
    $courseOffering = CourseOffering::factory()->create();
    $form = createSurveyForm();

    $older = createSurveyTarget($form, $courseOffering);
    $older->forceFill(['created_at' => now()->subWeek()])->save();

    $latest = createSurveyTarget($form, $courseOffering);

    $result = app(CourseSurveyTargetReader::class)->forCourseOffering($courseOffering->id);

    expect($result->id)->toBe($latest->id);
});
