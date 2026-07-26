<?php

declare(strict_types=1);

use App\Constants\UnitRoutes;
use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Catalog\Actions\GenerateUnitImportTemplateAction;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    foreach (['view_unit', 'create_unit', 'edit_unit', 'delete_unit'] as $permission) {
        grantCatalogUnitPermission($this->user, $this->campus, $permission);
    }

    session(['current_campus_id' => $this->campus->id]);
});

function grantCatalogUnitPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();
    $permission = Permission::firstOrCreate(['code' => $permissionCode], ['name' => $permissionCode]);
    $role = Role::factory()->create(['code' => 'catalog-unit_'.Str::lower(Str::random(10))]);
    RolePermission::create(['role_id' => $role->id, 'permission_id' => $permission->id]);
    CampusUserRole::create(['user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id]);
}

it('preserves unit listing filters and Inertia props', function (): void {
    $matching = Unit::factory()->create(['code' => 'CAT101', 'name' => 'Catalog Unit']);
    Unit::factory()->create(['code' => 'OTH101', 'name' => 'Other Unit']);

    actingAs($this->user)
        ->get(route(UnitRoutes::INDEX, ['search' => 'Catalog', 'per_page' => 15]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Units/Index')
            ->where('filters.search', 'Catalog')
            ->where('filters.per_page', '15')
            ->has('units.data', 1)
            ->where('units.data.0.id', $matching->id));
});

it('creates and validates a unit through the Catalog-owned routes', function (): void {
    actingAs($this->user)
        ->post(route(UnitRoutes::STORE), [
            'code' => 'CAT102',
            'name' => 'Catalog Unit Two',
            'credit_points' => 3,
            'level' => 1,
            'unit_type' => 'general',
        ])
        ->assertRedirect(route(UnitRoutes::INDEX));

    expect(Unit::query()->where('code', 'CAT102')->value('name'))->toBe('Catalog Unit Two');

    actingAs($this->user)
        ->post(route('units.validate-code'), ['code' => 'CAT102'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.valid', false);
});

it('validates prerequisite expressions through the Catalog endpoint without persisting conditions', function (): void {
    $prerequisite = Unit::factory()->create(['code' => 'ABC12345']);

    actingAs($this->user)
        ->post(route('units.validate-prerequisite-expression'), ['expression' => $prerequisite->code])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.valid', true)
        ->assertJsonPath('message', 'Expression is valid');

    expect($prerequisite->prerequisiteGroups()->count())->toBe(0);
});

it('validates Catalog unit export filters before generating a spreadsheet', function (): void {
    actingAs($this->user)
        ->get(route(UnitRoutes::EXPORT_EXCEL, [
            'credit_points_from' => 10,
            'credit_points_to' => 5,
        ]))
        ->assertInvalid(['credit_points_to']);
});

it('rejects unit import previews outside the temporary import directory', function (): void {
    actingAs($this->user)
        ->post(route(UnitRoutes::IMPORT_PREVIEW), [
            'file_path' => '../sensitive.xlsx',
        ])
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Invalid import file path.');
});

it('generates supported Catalog import workbook layouts', function (): void {
    $path = app(GenerateUnitImportTemplateAction::class)->handle('combined');
    $spreadsheet = IOFactory::load($path);

    expect($spreadsheet->getSheetNames())->toBe([
        'Units',
        'Prerequisites',
        'Equivalents',
        'Syllabus',
        'Assessment Components',
        'Assessment Details',
        'Instructions',
    ])
        ->and($spreadsheet->getSheetByName('Units')?->getCell('A1')->getValue())->toBe('Code*')
        ->and($spreadsheet->getSheetByName('Prerequisites')?->getCell('D1')->getValue())->toBe('Condition Type*');
});
