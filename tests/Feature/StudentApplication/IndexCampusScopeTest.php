<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\StudentApplication;
use App\Models\User;
use App\Modules\Upload\Models\ApplicationDocument;
use App\Modules\Upload\Models\ApplicationDocumentType;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn(['view_student_application']);
    $this->app->singleton(CampusPermissionReader::class, fn () => $permissionService);

    // The user is currently working in $this->campus.
    $this->app->singleton('campus', fn () => $this->campus);
    session(['current_campus_id' => $this->campus->id]);

    $this->staff = User::factory()->create(['type' => UserType::STAFF]);
});

function scopedApplication(Campus $campus): StudentApplication
{
    return StudentApplication::factory()->pending()->create([
        'campus_code' => $campus->code,
        'student_code' => 'S'.fake()->unique()->numerify('#######'),
    ]);
}

it('lists only applications for the current campus', function () {
    $mine = scopedApplication($this->campus);
    scopedApplication($this->otherCampus); // must not appear

    $this->actingAs($this->staff)
        ->get(route('student-applications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('StudentApplications/Index')
            ->has('applications.data', 1)
            ->where('applications.data.0.id', $mine->id)
            ->where('currentCampus.code', $this->campus->code)
        );
});

it('exposes documents grouped by type and the document-type columns', function () {
    ApplicationDocumentType::factory()->create(['code' => 'transcript', 'name' => 'Transcript', 'order' => 1]);

    $application = scopedApplication($this->campus);
    ApplicationDocument::factory()->count(3)->forApplication($application)->create([
        'file_type_code' => 'transcript',
    ]);

    $this->actingAs($this->staff)
        ->get(route('student-applications.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('documentTypes.0.code', 'transcript')
            ->has('applications.data.0.documents_by_type.transcript', 3)
        );
});

it('renders the focused documents view for an application', function () {
    $application = scopedApplication($this->campus);
    ApplicationDocument::factory()->forApplication($application)->create([
        'file_type_code' => 'transcript',
        'link' => 'https://drive.google.com/file/d/ABC/preview',
    ]);

    $this->actingAs($this->staff)
        ->get(route('student-applications.documents', $application))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('StudentApplications/Documents')
            ->where('application.id', $application->id)
            ->has('documentChecklist.groups')
        );
});
