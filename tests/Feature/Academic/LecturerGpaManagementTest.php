<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function grantLecturerGpaPermission(User $user, Campus $campus, array $permissionCodes): void
{
    Cache::flush();

    $role = Role::factory()->create(['code' => 'lecturer-gpa_'.Str::lower(Str::random(10))]);

    foreach ($permissionCodes as $permissionCode) {
        $permission = Permission::firstOrCreate(
            ['code' => $permissionCode],
            ['name' => $permissionCode],
        );

        RolePermission::create([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

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

it('redirects guests away from the lecturer gpa page', function (): void {
    get(route('lectures.lecturers-gpa.index'))->assertRedirect(route('login'));
});

it('forbids users missing either required permission', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantLecturerGpaPermission($user, $this->campus, ['view_lecturer']);

    get(route('lectures.lecturers-gpa.index'))->assertForbidden();
});

it('renders the lecturer gpa page and resolves the active semester by default', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantLecturerGpaPermission($user, $this->campus, ['view_lecturer', 'view_survey_results_aggregate']);

    $activeSemester = Semester::factory()->active()->create();

    get(route('lectures.lecturers-gpa.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Lectures/LecturerGpa')
            ->where('filters.semester_id', (string) $activeSemester->id)
            ->has('semesters'));
});
