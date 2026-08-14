<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Engagement\Actions\ProvisionCourseSurveyAction;
use App\Modules\Engagement\Models\Form;
use App\Modules\Engagement\Models\FormVersion;
use App\Shared\Contracts\Academic\DTO\CourseOfferingSurveyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('provisions an active mandatory survey run for a course offering', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $courseOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
    ]);
    $form = Form::create([
        'code' => 'COURSE-SURVEY',
        'type' => 'survey',
        'title' => 'Course Evaluation',
        'status' => 'active',
        'created_by' => User::factory()->create()->id,
    ]);
    $version = FormVersion::create([
        'form_id' => $form->id,
        'version_no' => 1,
        'is_published' => true,
        'effective_from' => now()->subDay(),
    ]);

    $target = app(ProvisionCourseSurveyAction::class)
        ->provisionForCourseOffering(surveyContext($courseOffering), $form->id);

    expect($target->form_id)->toBe($form->id)
        ->and($target->form_version_id)->toBe($version->id)
        ->and($target->campus_id)->toBe($campus->id)
        ->and($target->scope_type)->toBe('course')
        ->and($target->scope_id)->toBe($courseOffering->id)
        ->and($target->semester_id)->toBe((string) $semester->id)
        ->and($target->status)->toBe('active')
        ->and($target->is_mandatory)->toBeTrue();
});

it('rejects duplicate course surveys and forms that are not surveys', function (): void {
    $campus = Campus::factory()->create();
    $courseOffering = CourseOffering::factory()->create(['campus_id' => $campus->id]);
    $survey = Form::create([
        'code' => 'COURSE-SURVEY',
        'type' => 'survey',
        'title' => 'Course Evaluation',
        'status' => 'active',
        'created_by' => User::factory()->create()->id,
    ]);
    FormVersion::create([
        'form_id' => $survey->id,
        'version_no' => 1,
        'is_published' => true,
        'effective_from' => now()->subDay(),
    ]);
    $query = Form::create([
        'code' => 'COURSE-QUERY',
        'type' => 'query',
        'title' => 'Course Query',
        'status' => 'active',
        'created_by' => User::factory()->create()->id,
    ]);

    $action = app(ProvisionCourseSurveyAction::class);

    expect(fn () => $action->provisionForCourseOffering(surveyContext($courseOffering), $query->id))
        ->toThrow(ValidationException::class);

    $action->provisionForCourseOffering(surveyContext($courseOffering), $survey->id);

    expect(fn () => $action->provisionForCourseOffering(surveyContext($courseOffering), $survey->id))
        ->toThrow(ValidationException::class);
});

function surveyContext(CourseOffering $courseOffering): CourseOfferingSurveyContext
{
    return new CourseOfferingSurveyContext(
        courseOfferingId: $courseOffering->id,
        campusId: $courseOffering->campus_id,
        semesterId: $courseOffering->semester_id,
        unitType: null,
        semesterEndDate: null,
    );
}
