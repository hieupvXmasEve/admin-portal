<?php

declare(strict_types=1);

use App\Constants\ProgramRoutes;
use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
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

    grantCatalogPermission($this->user, $this->campus, 'view_program');
    grantCatalogPermission($this->user, $this->campus, 'create_program');

    session(['current_campus_id' => $this->campus->id]);
});

function grantCatalogPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'catalog_'.Str::lower(Str::random(10))]);

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

it('preserves program listing filters and Inertia props', function (): void {
    $matching = Program::factory()->create(['name' => 'Academic Catalog']);
    Program::factory()->create(['name' => 'Other Program']);

    actingAs($this->user)
        ->get(route(ProgramRoutes::INDEX, ['search' => 'Catalog', 'per_page' => 15]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('programs/Index')
            ->where('filters.search', 'Catalog')
            ->where('filters.per_page', '15')
            ->has('programs.data', 1)
            ->where('programs.data.0.id', $matching->id));
});

it('creates a program with the established validation and redirect contract', function (): void {
    actingAs($this->user)
        ->post(route(ProgramRoutes::STORE), [
            'name' => 'Data Science',
            'code' => 'DSC',
            'description' => 'A catalog-owned program.',
        ])
        ->assertRedirect(route(ProgramRoutes::INDEX));

    expect(Program::query()->where('code', 'DSC')->value('name'))->toBe('Data Science');
});

it('authorizes a specific program before rendering its detail contract', function (): void {
    $program = Program::factory()->create();

    actingAs($this->user)
        ->get(route(ProgramRoutes::SHOW, $program))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('programs/Show')
            ->where('program.id', $program->id));
});
