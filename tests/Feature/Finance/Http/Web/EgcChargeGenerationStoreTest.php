<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeEgcStoreUser(Campus $campus): User
{
    $user = User::factory()->create();

    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')
        ->andReturn(['view_egc_finance_operations', 'generate_egc_finance_charges']);
    app()->instance(PermissionService::class, $mock);

    session(['current_campus_id' => $campus->id]);

    return $user;
}

it('blocks the retired standalone EGC store route from creating charges', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $user = makeEgcStoreUser($campus);

    $this->actingAs($user)
        ->withSession(['_token' => 'test-csrf-token'])
        ->post(route('finance.egc.charges.store'), [
            '_token' => 'test-csrf-token',
            'semester_id' => $semester->id,
            'due_date' => now()->addDays(30)->toDateString(),
        ])
        ->assertGone();

    expect(FinanceCharge::query()->count())->toBe(0);
});
