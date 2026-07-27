<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

function grantLecturerPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'lecturer_'.Str::lower(Str::random(10))]);

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

beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
});

it('redirects guests away from the lecturers index', function (): void {
    get(route('lectures.index'))->assertRedirect(route('login'));
});

it('forbids users without view_lecturer from the lecturers index', function (): void {
    actingAs(User::factory()->create());

    get(route('lectures.index'))->assertForbidden();
});

it('renders the lecturers index with semester and unit-type options', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantLecturerPermission($user, $this->campus, 'view_lecturer');

    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create(['unit_type' => 'cs']);
    CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
        'campus_id' => $this->campus->id,
        'is_active' => true,
    ]);

    get(route('lectures.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Lectures/Index')
            ->has('semesters')
            ->where('unitTypeOptions.0.value', 'cs'));
});

it('creates a lecture and a linked lecturer user account', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantLecturerPermission($user, $this->campus, 'create_lecturer');

    post(route('lectures.store'), [
        'employee_id' => 'EMP9001',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada.lovelace@example.test',
        'campus_id' => $this->campus->id,
        'academic_rank' => 'lecturer',
        'hire_date' => '2020-01-01',
        'employment_type' => 'full_time',
        'employment_status' => 'active',
        'password' => 'secret123',
    ])->assertRedirect(route('lectures.index'));

    $lecture = Lecture::where('employee_id', 'EMP9001')->firstOrFail();
    expect($lecture->user)->not->toBeNull()
        ->and($lecture->user->email)->toBe('ada.lovelace@example.test')
        ->and($lecture->user->type->value ?? $lecture->user->type)->not->toBeNull();
});

it('updates a lecture and its linked user account', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantLecturerPermission($user, $this->campus, 'edit_lecturer');

    $lecture = Lecture::factory()->create([
        'campus_id' => $this->campus->id,
        'first_name' => 'Old',
        'last_name' => 'Name',
        'email' => 'old.name@example.test',
    ]);

    put(route('lectures.update', $lecture), [
        'employee_id' => $lecture->employee_id,
        'first_name' => 'New',
        'last_name' => 'Name',
        'email' => 'new.name@example.test',
        'campus_id' => $this->campus->id,
        'academic_rank' => $lecture->academic_rank,
        'hire_date' => (string) $lecture->hire_date,
        'employment_type' => $lecture->employment_type,
        'employment_status' => $lecture->employment_status,
    ])->assertRedirect(route('lectures.index'));

    $lecture->refresh();
    expect($lecture->first_name)->toBe('New')
        ->and($lecture->user?->email)->toBe('new.name@example.test');
});

it('refuses to delete a lecture with assigned course offerings', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantLecturerPermission($user, $this->campus, 'delete_lecturer');

    $lecture = Lecture::factory()->create(['campus_id' => $this->campus->id]);
    $semester = Semester::factory()->create();
    $unit = Unit::factory()->create();
    CourseOffering::factory()->create([
        'unit_id' => $unit->id,
        'semester_id' => $semester->id,
        'campus_id' => $this->campus->id,
        'lecture_id' => $lecture->id,
    ]);

    delete(route('lectures.destroy', $lecture))->assertRedirect();

    expect(Lecture::find($lecture->id))->not->toBeNull();
});
