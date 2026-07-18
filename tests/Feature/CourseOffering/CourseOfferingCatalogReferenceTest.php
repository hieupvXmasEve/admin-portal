<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\CourseOffering;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('publishes current-period, unit, and syllabus choices through the catalog contract', function (): void {
    $period = Semester::factory()->active()->create([
        'code' => '2026-FALL',
        'name' => 'Fall 2026',
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
    ]);
    $unit = Unit::factory()->create([
        'code' => 'CAT101',
        'name' => 'Catalog Foundations',
        'credit_points' => 3,
        'level' => 100,
        'unit_type' => 'general',
    ]);
    $syllabusTemplate = SyllabusTemplate::factory()->create([
        'unit_id' => $unit->id,
        'title' => 'Catalog Foundations Syllabus',
        'is_active' => true,
    ]);

    $catalog = app(CourseOfferingCatalogReader::class);

    expect($catalog->currentOfferingPeriod())
        ->not->toBeNull()
        ->id->toBe($period->id)
        ->and($catalog->offeringUnits()[0]->toArray())->toMatchArray([
            'unit_id' => $unit->id,
            'code' => 'CAT101',
            'name' => 'Catalog Foundations',
            'credit_points' => '3.00',
            'level' => 100,
            'unit_type' => 'general',
        ])
        ->and($catalog->offeringSyllabusTemplates()[0]->toArray())->toMatchArray([
            'id' => $syllabusTemplate->id,
            'unit_id' => $unit->id,
            'title' => 'Catalog Foundations Syllabus',
        ]);
});

it('preserves the legacy active-period selection when period dates are unset', function (): void {
    $firstActivePeriod = Semester::factory()->active()->create([
        'start_date' => null,
        'end_date' => null,
    ]);
    Semester::factory()->active()->create();

    expect(app(CourseOfferingCatalogReader::class)->currentOfferingPeriod()?->id)
        ->toBe($firstActivePeriod->id);
});

it('rejects a syllabus template that belongs to a different catalog unit', function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $user = User::factory()->create();
    $campus = Campus::factory()->create();
    $period = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $otherUnit = Unit::factory()->create();
    $syllabusTemplate = SyllabusTemplate::factory()->create(['unit_id' => $otherUnit->id]);

    grantCourseOfferingPermission($user, $campus, 'create_course');
    grantCourseOfferingPermission($user, $campus, 'create_course_offering');
    app()->instance('campus', $campus);

    actingAs($user)
        ->withSession(['current_campus_id' => $campus->id])
        ->from(route(CourseOfferingRoutes::CREATE))
        ->post(route(CourseOfferingRoutes::STORE), [
            'semester_id' => $period->id,
            'unit_id' => $unit->id,
            'syllabus_template_id' => $syllabusTemplate->id,
            'section_code' => 'CAT-01',
            'max_capacity' => 30,
            'delivery_mode' => 'in_person',
        ])
        ->assertSessionHasErrors([
            'syllabus_template_id' => 'Selected syllabus template does not exist',
        ]);

    expect(CourseOffering::query()->where('section_code', 'CAT-01')->exists())->toBeFalse();
});

it('creates an offering from catalog-owned references', function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $user = User::factory()->create();
    $campus = Campus::factory()->create();
    $period = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $syllabusTemplate = SyllabusTemplate::factory()->create(['unit_id' => $unit->id]);

    grantCourseOfferingPermission($user, $campus, 'create_course');
    grantCourseOfferingPermission($user, $campus, 'create_course_offering');
    app()->instance('campus', $campus);

    actingAs($user)
        ->withSession(['current_campus_id' => $campus->id])
        ->post(route(CourseOfferingRoutes::STORE), [
            'semester_id' => $period->id,
            'unit_id' => $unit->id,
            'syllabus_template_id' => $syllabusTemplate->id,
            'section_code' => 'CAT-01',
            'max_capacity' => 30,
            'delivery_mode' => 'in_person',
        ])
        ->assertRedirectToRoute(CourseOfferingRoutes::INDEX);

    $this->assertDatabaseHas('course_offerings', [
        'semester_id' => $period->id,
        'unit_id' => $unit->id,
        'syllabus_template_id' => $syllabusTemplate->id,
        'campus_id' => $campus->id,
        'section_code' => 'CAT-01',
    ]);
});

it('rejects an invalid catalog unit when editing an offering', function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $user = User::factory()->create();
    $campus = Campus::factory()->create();
    $period = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $syllabusTemplate = SyllabusTemplate::factory()->create(['unit_id' => $unit->id]);
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $period->id,
        'unit_id' => $unit->id,
        'syllabus_template_id' => $syllabusTemplate->id,
        'campus_id' => $campus->id,
        'section_code' => 'CAT-01',
        'course_status' => 'not_started',
        'enrollment_status' => 'open',
    ]);

    grantCourseOfferingPermission($user, $campus, 'edit_course');
    grantCourseOfferingPermission($user, $campus, 'edit_course_offering');
    app()->instance('campus', $campus);

    actingAs($user)
        ->withSession(['current_campus_id' => $campus->id])
        ->from(route(CourseOfferingRoutes::EDIT, $courseOffering))
        ->put(route(CourseOfferingRoutes::UPDATE, $courseOffering), [
            'semester_id' => $period->id,
            'unit_id' => 999999,
            'syllabus_template_id' => $syllabusTemplate->id,
            'section_code' => 'CAT-02',
            'max_capacity' => 30,
            'delivery_mode' => 'in_person',
        ])
        ->assertSessionHasErrors([
            'unit_id' => 'Selected unit does not exist',
        ]);

    expect($courseOffering->fresh()->section_code)->toBe('CAT-01');
});

function grantCourseOfferingPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'course_offering_'.Str::lower(Str::random(10))]);

    RolePermission::create([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);
    CampusUserRole::create([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
    ]);
}
