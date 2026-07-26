<?php

declare(strict_types=1);

use App\Constants\SemesterRoutes;
use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Academic\Catalog\Models\CampusPeriodSchedule;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();

    foreach (['view_semester', 'create_semester', 'edit_semester'] as $permission) {
        grantAcademicPeriodPermission($this->user, $this->campus, $permission);
    }

    session(['current_campus_id' => $this->campus->id]);
});

function grantAcademicPeriodPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'academic-period_'.Str::lower(Str::random(10))]);

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

it('preserves the academic period listing props and filters', function (): void {
    $matching = Semester::factory()->create([
        'code' => '2027-SPRING',
        'name' => 'Spring 2027',
        'is_active' => false,
        'is_archived' => false,
    ]);
    Semester::factory()->create(['name' => 'Fall 2027']);

    actingAs($this->user)
        ->get(route(SemesterRoutes::INDEX, [
            'search' => 'Spring',
            'filter' => ['is_archived' => 'false'],
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Semesters/Index')
            ->where('filters.search', 'Spring')
            ->where('filters.is_archived', false)
            ->has('semesters.data', 1)
            ->where('semesters.data.0.id', $matching->id));
});

it('creates an institution-wide academic period through the existing staff contract', function (): void {
    actingAs($this->user)
        ->post(route(SemesterRoutes::STORE), [
            'code' => '2027-SUMMER',
            'name' => 'Summer 2027',
            'start_date' => '2027-05-01',
            'end_date' => '2027-08-31',
            'enrollment_start_date' => '2027-03-01',
            'enrollment_end_date' => '2027-04-30',
            'is_active' => false,
            'is_archived' => false,
        ])
        ->assertRedirect(route(SemesterRoutes::INDEX));

    expect(Semester::query()->where('code', '2027-SUMMER')->value('name'))->toBe('Summer 2027');
});

it('stores one campus schedule per academic period without duplicating the period', function (): void {
    $academicPeriod = Semester::factory()->create();
    $secondCampus = Campus::factory()->create();

    actingAs($this->user)
        ->put(route('semesters.campus-schedules.upsert', $academicPeriod), [
            'campus_id' => $this->campus->id,
            'operating_start_date' => '2027-01-02',
            'operating_end_date' => '2027-04-30',
            'registration_start_date' => '2026-12-01',
            'registration_end_date' => '2027-01-01',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    actingAs($this->user)
        ->put(route('semesters.campus-schedules.upsert', $academicPeriod), [
            'campus_id' => $this->campus->id,
            'operating_start_date' => '2027-01-03',
            'operating_end_date' => '2027-05-01',
        ])
        ->assertOk();

    actingAs($this->user)
        ->put(route('semesters.campus-schedules.upsert', $academicPeriod), [
            'campus_id' => $secondCampus->id,
            'registration_start_date' => '2026-12-02',
            'registration_end_date' => '2027-01-02',
        ])
        ->assertOk();

    expect(CampusPeriodSchedule::query()
        ->where('semester_id', $academicPeriod->id)
        ->count())->toBe(2)
        ->and(Semester::query()->whereKey($academicPeriod)->count())->toBe(1);
});

it('exposes campus schedules alongside the institution-wide academic period list', function (): void {
    $academicPeriod = Semester::factory()->create();
    $schedule = CampusPeriodSchedule::query()->create([
        'semester_id' => $academicPeriod->id,
        'campus_id' => $this->campus->id,
        'operating_start_date' => '2027-01-02',
        'operating_end_date' => '2027-04-30',
    ]);

    actingAs($this->user)
        ->get(route(SemesterRoutes::INDEX))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('campuses.0.id', $this->campus->id)
            ->where("campus_period_schedules.{$academicPeriod->id}.0.id", $schedule->id)
            ->where("campus_period_schedules.{$academicPeriod->id}.0.campus_id", $this->campus->id));
});
