<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use App\Modules\Finance\Models\FinanceSetting;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $campus = Campus::factory()->create();
    session(['current_campus_id' => $campus->id, '_token' => 'finance-settings-csrf']);
    app()->singleton('campus', fn (): Campus => $campus);
});

function grantFinanceSettingsPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn($permissions);
    app()->instance(CampusPermissionReader::class, $permissionService);
}

it('shows a read-only view when the user lacks manage permission', function (): void {
    grantFinanceSettingsPermissions(['view_finance_settings']);

    actingAs($this->user)
        ->get(route('finance.settings.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Settings/Show')
            ->where('can_manage', false)
            ->where('settings.credit_offset_enabled', false)
            ->where('settings.credit_offset_min_balance', 0));
});

it('lets authorized Finance staff view and update credit offset settings', function (): void {
    grantFinanceSettingsPermissions([
        'view_finance_settings',
        'manage_finance_settings',
    ]);

    actingAs($this->user)
        ->get(route('finance.settings.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Settings/Show')
            ->where('can_manage', true));

    actingAs($this->user)
        ->put(route('finance.settings.update'), [
            '_token' => 'finance-settings-csrf',
            'credit_offset_enabled' => true,
            'credit_offset_min_balance' => 5_000_000,
        ])
        ->assertRedirect();

    $settings = FinanceSetting::current();

    expect($settings->credit_offset_enabled)->toBeTrue()
        ->and((float) $settings->credit_offset_min_balance)->toBe(5_000_000.0);
});

it('rejects an update from a user without manage permission', function (): void {
    grantFinanceSettingsPermissions(['view_finance_settings']);

    actingAs($this->user)
        ->put(route('finance.settings.update'), [
            '_token' => 'finance-settings-csrf',
            'credit_offset_enabled' => true,
            'credit_offset_min_balance' => 5_000_000,
        ])
        ->assertForbidden();

    expect(FinanceSetting::current()->credit_offset_enabled)->toBeFalse();
});

it('rejects a negative minimum balance', function (): void {
    grantFinanceSettingsPermissions(['manage_finance_settings']);

    actingAs($this->user)
        ->from(route('finance.settings.show'))
        ->put(route('finance.settings.update'), [
            '_token' => 'finance-settings-csrf',
            'credit_offset_enabled' => true,
            'credit_offset_min_balance' => -1,
        ])
        ->assertSessionHasErrors('credit_offset_min_balance');
});
