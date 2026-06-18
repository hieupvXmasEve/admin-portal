<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
});

it('registers the reporting route with the reporting view permission', function () {
    $route = Route::getRoutes()->getByName('finance.reporting.index');

    expect($route)->not->toBeNull('Reporting route should be registered');
    expect($route->uri())->toBe('finance/reporting');
    expect($route->gatherMiddleware())->toContain('can:view_finance_reporting');
});

it('denies the reporting shell without view_finance_reporting', function () {
    grantFinance($this->user, ['view_finance_cockpit'], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.reporting.index'))
        ->assertForbidden();
});

it('renders the reporting shell with the default collection progress view', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.reporting.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Reporting/Index', false)
            ->where('active_view', 'collection-progress')
            ->where('views.0.key', 'fee-monitor')
            ->where('views.1.key', 'collection-progress')
            ->where('views.2.key', 'dng-lifecycle')
            ->where('views.0.status', 'implemented')
            ->where('views.1.status', 'implemented')
            ->where('views.2.status', 'implemented')
            ->where('actions.export_enabled', false)
            ->has('computed_at')
        );
});

it('persists the active reporting view from the URL state and normalizes invalid values', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $this->actingAs($this->user)
        ->get(route('finance.reporting.index', ['view' => 'dng-lifecycle']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Reporting/Index', false)
            ->where('active_view', 'dng-lifecycle')
        );

    $this->actingAs($this->user)
        ->get(route('finance.reporting.index', ['view' => 'export']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Reporting/Index', false)
            ->where('active_view', 'collection-progress')
        );
});

it('declares the reporting permission, shared semester access and sidebar entry', function () {
    $permissionConfig = file_get_contents(base_path('config/permission.php'));
    $seeder = file_get_contents(base_path('database/seeders/InitialSetup/RoleAndPermissionSeeder.php'));
    $inertiaMiddleware = file_get_contents(base_path('app/Http/Middleware/HandleInertiaRequests.php'));
    $routeConstants = file_get_contents(base_path('resources/js/constants/finance-routes.ts'));
    $routeHelpers = file_get_contents(base_path('resources/js/utils/routes.ts'));
    $menu = file_get_contents(base_path('resources/js/constants/menu-sidebar.ts'));

    expect($permissionConfig)->toContain("'view_finance_reporting' => 'view_finance_reporting'")
        ->and($seeder)->toContain("'view_finance_reporting'")
        ->and($inertiaMiddleware)->toContain("'view_finance_reporting'")
        ->and($routeConstants)->toContain("REPORTING_INDEX: 'finance.reporting.index'")
        ->and($routeHelpers)->toContain('reporting:')
        ->and($routeHelpers)->toContain('FINANCE_ROUTE_NAMES.REPORTING_INDEX')
        ->and($menu)->toContain('Finance Reporting')
        ->and($menu)->toContain('financeRoutes.reporting.index()')
        ->and($menu)->toContain("'view_finance_reporting'");
});
