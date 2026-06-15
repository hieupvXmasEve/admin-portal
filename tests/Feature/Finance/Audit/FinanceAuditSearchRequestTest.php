<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Finance\Http\Requests\Audit\FinanceAuditSearchRequest;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function bindAuditPermissions(array $codes): void
{
    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')->andReturn($codes);
    app()->singleton(PermissionService::class, fn () => $mock);
}

it('authorizes a user with the workspace permission', function () {
    $user = User::factory()->create();
    session(['current_campus_id' => 1]);
    bindAuditPermissions(['view_finance_audit_workspace']);

    $request = FinanceAuditSearchRequest::create('/finance/audit', 'GET');
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeTrue();
});

it('denies a user without the workspace permission', function () {
    $user = User::factory()->create();
    session(['current_campus_id' => 1]);
    bindAuditPermissions([]);

    $request = FinanceAuditSearchRequest::create('/finance/audit', 'GET');
    $request->setUserResolver(fn () => $user);

    expect($request->authorize())->toBeFalse();
});

it('accepts valid filters and rejects an invalid target_type', function () {
    $rules = (new FinanceAuditSearchRequest())->rules();

    expect(Validator::make(['q' => 'INV-1', 'target_type' => 'invoice', 'target_id' => 5], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['target_type' => 'nonsense'], $rules)->passes())->toBeFalse()
        ->and(Validator::make(['target_id' => 0], $rules)->passes())->toBeFalse();
});
