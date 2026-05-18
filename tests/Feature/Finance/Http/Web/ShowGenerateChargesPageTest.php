<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Finance\Enums\NonAcademicChargeTypeEnum;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeGenerateChargesUser(Campus $campus): User
{
    $user = User::factory()->create();

    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')
        ->andReturn(['view_finance_operations_generate_charges']);
    app()->instance(PermissionService::class, $mock);

    // PermissionServiceProvider Gate check reads session('current_campus_id').
    session(['current_campus_id' => $campus->id]);

    return $user;
}

// ---------------------------------------------------------------------------
// Props contract: feeTypes, semesters, currentCampus all present
// ---------------------------------------------------------------------------

it('renders Finance/Operations/GenerateCharges with feeTypes semesters and currentCampus props', function () {
    $campus   = Campus::factory()->create(['name' => 'Test Campus']);
    $semester = Semester::factory()->active()->create(['name' => 'HK1 2025']);
    $user     = makeGenerateChargesUser($campus);

    app()->singleton('campus', fn () => $campus);

    $this->actingAs($user)
        ->get(route('finance.operations.generate-charges'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Finance/Operations/GenerateCharges')
                ->has('feeTypes')
                ->has('semesters')
                ->has('currentCampus')
                ->where('feeTypes', NonAcademicChargeTypeEnum::forSelect())
                ->where('currentCampus.id', $campus->id),
        );
});

// ---------------------------------------------------------------------------
// feeTypes shape: each entry has value + label keys; bhyt is present
// ---------------------------------------------------------------------------

it('passes feeTypes entries with value and label keys', function () {
    $campus = Campus::factory()->create();
    Semester::factory()->active()->create();
    $user = makeGenerateChargesUser($campus);

    app()->singleton('campus', fn () => $campus);

    $this->actingAs($user)
        ->get(route('finance.operations.generate-charges'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Finance/Operations/GenerateCharges')
                ->where('feeTypes.0.value', 'bhyt')
                ->where('feeTypes.0.label', NonAcademicChargeTypeEnum::BHYT->label()),
        );
});

// ---------------------------------------------------------------------------
// semesters list contains the seeded semester
// ---------------------------------------------------------------------------

it('passes all semesters in the prop', function () {
    $campus   = Campus::factory()->create();
    $semester = Semester::factory()->active()->create(['name' => 'HK2 2025']);
    $user     = makeGenerateChargesUser($campus);

    app()->singleton('campus', fn () => $campus);

    $this->actingAs($user)
        ->get(route('finance.operations.generate-charges'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Finance/Operations/GenerateCharges')
                ->has('semesters', 1)
                ->where('semesters.0.id', $semester->id),
        );
});

// currentCampus null path is not testable via HTTP: CheckCampusSelected middleware redirects
// to select-campus when no current_campus_id in session. The null guard in the controller
// exists for programmatic/unit use; web requests always have a campus pre-selected.

// ---------------------------------------------------------------------------
// Unauthenticated request redirects to login
// ---------------------------------------------------------------------------

it('redirects unauthenticated users to login', function () {
    $this->get(route('finance.operations.generate-charges'))
        ->assertRedirectToRoute('login');
});

// ---------------------------------------------------------------------------
// Template download: returns a downloadable CSV file with student_code header
// ---------------------------------------------------------------------------

it('downloads the non-academic charges CSV template as a file attachment', function () {
    $campus = Campus::factory()->create();
    $user   = makeGenerateChargesUser($campus);

    app()->singleton('campus', fn () => $campus);

    $response = $this->actingAs($user)
        ->get(route('finance.operations.non-academic-charges-template'));

    $response->assertStatus(200);

    // Content-Disposition header should indicate a downloadable file named non_academic_charges_template.csv
    $disposition = $response->headers->get('content-disposition') ?? '';
    expect($disposition)->toContain('non_academic_charges_template.csv');
});

it('redirects unauthenticated users away from the template download', function () {
    $this->get(route('finance.operations.non-academic-charges-template'))
        ->assertRedirectToRoute('login');
});
