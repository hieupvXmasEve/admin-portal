<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantCockpit')) {
    function grantCockpit(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(PermissionService::class);
        $mock->shouldReceive('getUserPermissions')->andReturn($codes);
        app()->singleton(PermissionService::class, fn () => $mock);

        return $user;
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    Semester::factory()->create(['is_active' => true]);
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

it('renders the cockpit with kpi, queues and phase', function () {
    $user = grantCockpit(['view_finance_cockpit']);

    actingAs($user)->get('/finance/cockpit')
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Cockpit/Index')
            ->has('kpi.total_receivable')
            ->has('kpi.collected_pct')
            ->has('queues')
            ->has('phase.key')
            ->where('queues.0.key', 'webhook_errors')
            ->has('queues.0.count')
            ->has('queues.0.obeys_semester')
            ->has('queues.0.scope_badge'));
});

it('denies the cockpit without view_finance_cockpit', function () {
    $user = grantCockpit(['view_finance_operations_dashboard']);
    actingAs($user)->get('/finance/cockpit')->assertForbidden();
});

it('marks webhook/unallocated queues as semester-agnostic and due/lifecycle as semester-bound', function () {
    $user = grantCockpit(['view_finance_cockpit']);

    actingAs($user)->get('/finance/cockpit')
        ->assertInertia(function ($page) {
            $queues = collect($page->toArray()['props']['queues']);
            expect($queues->firstWhere('key', 'webhook_errors')['obeys_semester'])->toBeFalse();
            expect($queues->firstWhere('key', 'unallocated')['obeys_semester'])->toBeFalse();
            expect($queues->firstWhere('key', 'dng_due')['obeys_semester'])->toBeTrue();
            expect($queues->firstWhere('key', 'lifecycle')['obeys_semester'])->toBeTrue();
        });
});

it('uses the active semester when session has no explicit semester selection', function () {
    $active = Semester::query()->where('is_active', true)->first();
    $user = grantCockpit(['view_finance_cockpit']);

    actingAs($user)->get('/finance/cockpit')
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Cockpit/Index')
            ->has('kpi')
            ->has('queues'));
});

it('routes installment retry queue to the Batch Studio DNG wizard', function () {
    $user = grantCockpit(['view_finance_cockpit', 'create_finance_payments']);

    actingAs($user)->get('/finance/cockpit')
        ->assertInertia(function ($page) {
            $queues = collect($page->toArray()['props']['queues']);

            expect($queues->firstWhere('key', 'installment_failures')['action_url'])
                ->toBe(route('finance.batch-studio.dng'));
        });
});
