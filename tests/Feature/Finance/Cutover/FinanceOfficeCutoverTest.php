<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantFinanceCutover')) {
    /**
     * @param  string[]  $codes
     */
    function grantFinanceCutover(array $codes): User
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

it('registers primary M1-M5 finance office routes with expected middleware', function () {
    $primaryRoutes = [
        'finance.cockpit.index' => 'view_finance_cockpit',
        'finance.batch-studio.hub' => 'view_finance_batch_studio',
        'finance.students.overview' => 'view_finance_student_overview',
        'finance.search' => 'view_finance_student_overview',
        'finance.audit.index' => 'view_finance_audit_workspace',
        'finance.charges.index' => 'view_finance_charges',
        'finance.invoices.index' => 'view_finance_invoices',
        'finance.payments.index' => 'view_finance_payments',
    ];

    foreach ($primaryRoutes as $name => $permission) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull("Route {$name} should be registered");
        expect($route->gatherMiddleware())->toContain("can:{$permission}");
    }
});

it('keeps the legacy billing dashboard reachable for dashboard-only operators', function () {
    $user = grantFinanceCutover(['view_finance_operations_dashboard']);

    actingAs($user)->get(route('finance.operations.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Finance/Operations/Dashboard'));
});

it('routes cockpit-permitted operators to the cockpit landing', function () {
    $user = grantFinanceCutover(['view_finance_cockpit']);

    actingAs($user)->get(route('finance.cockpit.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Finance/Cockpit/Index'));
});

it('keeps deep-link detail routes registered for audit and repair flows', function () {
    $deepLinks = [
        'finance.operations.generate-charges',
        'finance.charges.show',
        'finance.invoices.show',
        'finance.payments.show',
        'finance.dng.payment-requests.show',
        'finance.dng.webhook-events.show',
        'finance.operations.settlement.index',
    ];

    foreach ($deepLinks as $name) {
        expect(Route::getRoutes()->getByName($name))->not->toBeNull("Deep-link route {$name} must remain registered");
    }
});

it('denies cockpit without view_finance_cockpit even when dashboard permission exists', function () {
    $user = grantFinanceCutover(['view_finance_operations_dashboard']);

    actingAs($user)->get(route('finance.cockpit.index'))->assertForbidden();
});