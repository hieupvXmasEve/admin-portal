<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function grantAcademicReportPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'academic-report_'.Str::lower(Str::random(10))]);

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

it('redirects guests away from the academic report page', function (): void {
    get(route('academic.report.index'))->assertRedirect(route('login'));
});

it('forbids users without view_academic_report from the report page', function (): void {
    actingAs(User::factory()->create());

    get(route('academic.report.index'))->assertForbidden();
});

it('renders the academic report page with filter options', function (): void {
    $user = User::factory()->create();
    actingAs($user);
    grantAcademicReportPermission($user, $this->campus, 'view_academic_report');

    get(route('academic.report.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/Report/Index')
            ->where('filters.active.campus_id', $this->campus->id)
            ->has('filters.options.campuses')
            ->has('filters.options.semesters')
            ->has('filters.options.programs'));
});
