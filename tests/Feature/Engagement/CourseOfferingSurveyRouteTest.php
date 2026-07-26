<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('keeps survey provisioning scoped to the selected campus', function (): void {
    $selectedCampus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $user = User::factory()->create();
    $form = Form::create([
        'code' => 'COURSE-SURVEY',
        'type' => 'survey',
        'title' => 'Course Evaluation',
        'status' => 'active',
        'created_by' => $user->id,
    ]);
    $offering = CourseOffering::factory()->create(['campus_id' => $otherCampus->id]);

    app()->singleton('campus', fn () => $selectedCampus);
    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, function () {
        $permissions = Mockery::mock(CampusPermissionReader::class);
        $permissions->shouldReceive('permissionCodesForUserId')->andReturn(['edit_course_offering']);

        return $permissions;
    });

    actingAs($user)
        ->withSession(['current_campus_id' => $selectedCampus->id, '_token' => 'test-token'])
        ->post(route('course-offerings.create-survey', $offering), ['_token' => 'test-token', 'form_id' => $form->id])
        ->assertNotFound();
});

it('provisions a course survey then returns to the current page with the legacy flash message', function (): void {
    $campus = Campus::factory()->create();
    $user = User::factory()->create();
    $form = Form::create([
        'code' => 'COURSE-SURVEY',
        'type' => 'survey',
        'title' => 'Course Evaluation',
        'status' => 'active',
        'created_by' => $user->id,
    ]);
    FormVersion::create([
        'form_id' => $form->id,
        'version_no' => 1,
        'is_published' => true,
        'effective_from' => now()->subDay(),
    ]);
    $offering = CourseOffering::factory()->create(['campus_id' => $campus->id]);

    app()->singleton('campus', fn () => $campus);
    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, function () {
        $permissions = Mockery::mock(CampusPermissionReader::class);
        $permissions->shouldReceive('permissionCodesForUserId')->andReturn(['edit_course_offering']);

        return $permissions;
    });

    actingAs($user)
        ->withSession(['current_campus_id' => $campus->id, '_token' => 'test-token'])
        ->from('/course-offerings/'.$offering->id)
        ->post(route('course-offerings.create-survey', $offering), ['_token' => 'test-token', 'form_id' => $form->id])
        ->assertRedirect('/course-offerings/'.$offering->id);

    expect(session(SessionKey::FLASH_DATA))->toMatchArray([
        'message' => 'Survey created and assigned to students successfully.',
    ])->and($offering->formTargets()->count())->toBe(1);
});
