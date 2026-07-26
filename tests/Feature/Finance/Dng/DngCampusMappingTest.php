<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngCampusMapping;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create(['code' => 'DN']);
    $this->user = User::factory()->create();

    session(['current_campus_id' => $this->campus->id, '_token' => 'dng-campus-mapping-csrf']);
    app()->singleton('campus', fn (): Campus => $this->campus);
});

function grantDngCampusMappingPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn($permissions);
    app()->instance(CampusPermissionReader::class, $permissionService);
}

it('resolves DNG codes exclusively from Finance-owned mappings', function (): void {
    $this->campus->forceFill(['dng_code' => 'LEGACY-DN'])->save();
    DngCampusMapping::query()->create([
        'campus_id' => $this->campus->id,
        'provider_code' => 'FAUDN',
    ]);

    expect($this->campus->toArray())->not->toHaveKey('dng_code')
        ->and(app(DngCampusCodeResolver::class)->requireCurrentCampusCode())->toBe('FAUDN');
});

it('raises an actionable validation error when a campus has no DNG mapping', function (): void {
    expect(fn (): string => app(DngCampusCodeResolver::class)->requireCurrentCampusCode())
        ->toThrow(ValidationException::class, 'DNG code is not configured for the selected campus.');
});

it('defines a resumable, audited legacy backfill migration', function (): void {
    $migration = file_get_contents(database_path('migrations/2026_07_18_123539_create_finance_dng_campus_mappings_table.php'));

    expect($migration)->toContain("Schema::hasTable('finance_dng_campus_mappings')")
        ->and($migration)->toContain("\$table->unique('campus_id')")
        ->and($migration)->toContain("\$table->unique('provider_code')")
        ->and($migration)->toContain("'created_by_user_id' => null")
        ->and($migration)->toContain("'updated_by_user_id' => null");
});

it('lets authorized Finance staff view and update the current campus mapping with audit actors', function (): void {
    grantDngCampusMappingPermissions([
        'view_finance_dng_campus_mappings',
        'manage_finance_dng_campus_mappings',
    ]);

    actingAs($this->user)
        ->get(route('finance.dng.campus-mapping.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/DngCampusMapping/Show')
            ->where('campus.id', $this->campus->id)
            ->where('mapping', null));

    actingAs($this->user)
        ->put(route('finance.dng.campus-mapping.update'), [
            '_token' => 'dng-campus-mapping-csrf',
            'provider_code' => 'FAUDN',
        ])
        ->assertRedirect();

    $mapping = DngCampusMapping::query()->sole();

    expect($mapping->provider_code)->toBe('FAUDN')
        ->and($mapping->created_by_user_id)->toBe($this->user->id)
        ->and($mapping->updated_by_user_id)->toBe($this->user->id);
});

it('rejects a DNG provider code already mapped to another campus', function (): void {
    grantDngCampusMappingPermissions(['manage_finance_dng_campus_mappings']);
    $otherCampus = Campus::factory()->create();
    DngCampusMapping::query()->create([
        'campus_id' => $otherCampus->id,
        'provider_code' => 'FAUDN',
    ]);

    actingAs($this->user)
        ->from(route('finance.dng.campus-mapping.show'))
        ->put(route('finance.dng.campus-mapping.update'), [
            '_token' => 'dng-campus-mapping-csrf',
            'provider_code' => 'FAUDN',
        ])
        ->assertSessionHasErrors('provider_code');

    expect(DngCampusMapping::query()->where('campus_id', $this->campus->id)->exists())->toBeFalse();
});
