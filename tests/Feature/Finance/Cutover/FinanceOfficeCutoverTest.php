<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
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
        app()->instance(PermissionService::class, $mock);

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

it('routes DNG payment-request creation entry points to Batch Studio with business naming', function () {
    $files = [
        'menu' => file_get_contents(base_path('resources/js/constants/menu-sidebar.ts')),
        'phase_shortcuts' => file_get_contents(base_path('resources/js/components/finance/cockpit/PhaseShortcuts.vue')),
        'lookup_bar' => file_get_contents(base_path('resources/js/components/finance/lookup/SendToBatchBar.vue')),
        'hub' => file_get_contents(base_path('resources/js/pages/Finance/BatchStudio/Hub.vue')),
        'dng_wizard' => file_get_contents(base_path('resources/js/pages/Finance/BatchStudio/DngPush.vue')),
    ];

    foreach ($files as $name => $contents) {
        expect($contents)->not->toContain('DNG Worklist', "Primary {$name} copy must not expose DNG Worklist");
        expect($contents)->not->toContain('Đẩy DNG hàng loạt', "Primary {$name} copy must use business naming");
    }

    expect($files['menu'])->toContain('Lập yêu cầu thanh toán DNG')
        ->and($files['menu'])->toContain('financeRoutes.batchStudio.dng()')
        ->and($files['phase_shortcuts'])->toContain('financeRoutes.batchStudio.dng()')
        ->and($files['lookup_bar'])->toContain('Lập yêu cầu thanh toán DNG')
        ->and($files['dng_wizard'])->toContain('Gửi yêu cầu sang DNG');
});

it('routes HP and EGC generation entry points to Batch Studio charges with prefill', function () {
    $menu = file_get_contents(base_path('resources/js/constants/menu-sidebar.ts'));
    $phaseShortcuts = file_get_contents(base_path('resources/js/components/finance/cockpit/PhaseShortcuts.vue'));

    expect($menu)->not->toContain('Generate HP (Tuition)')
        ->and($menu)->not->toContain('EGC · Generate Charges')
        ->and($menu)->toContain('Sinh HP/Tuition')
        ->and($menu)->toContain('Sinh phí EGC')
        ->and($phaseShortcuts)->toContain("financeRoutes.batchStudio.charges({ fee_category: 'major' })")
        ->and($phaseShortcuts)->toContain("financeRoutes.batchStudio.charges({ fee_category: 'egc' })");
});

it('redirects old HP and EGC charge-generation GET routes to Batch Studio charges', function () {
    $semester = Semester::factory()->create();

    $majorUser = grantFinanceCutover(['view_finance_charges']);
    actingAs($majorUser)
        ->get(route('finance.major.charges.index', [
            'semester_id' => $semester->id,
            'search' => 'SV001',
            'ignore_student_ids' => 'SV009',
            'unknown' => 'drop-me',
        ]))
        ->assertRedirect(route('finance.batch-studio.charges', [
            'fee_category' => 'major',
            'semester_id' => $semester->id,
            'search' => 'SV001',
            'ignore_student_ids' => 'SV009',
        ]));

    $egcUser = grantFinanceCutover(['view_egc_finance_operations']);
    actingAs($egcUser)
        ->get(route('finance.egc.charges.index', [
            'semester_id' => $semester->id,
            'search' => 'EGC001',
        ]))
        ->assertRedirect(route('finance.batch-studio.charges', [
            'fee_category' => 'egc',
            'semester_id' => $semester->id,
            'search' => 'EGC001',
        ]));
});

it('blocks old HP and EGC direct write routes from creating charges', function () {
    $semester = Semester::factory()->create();

    $majorUser = grantFinanceCutover(['create_finance_charges']);
    actingAs($majorUser)
        ->withSession(['_token' => 'cutover-token'])
        ->post(route('finance.major.charges.store'), [
            '_token' => 'cutover-token',
            'semester_id' => $semester->id,
            'due_date' => now()->addDays(30)->toDateString(),
        ])
        ->assertGone();

    $egcUser = grantFinanceCutover(['generate_egc_finance_charges']);
    actingAs($egcUser)
        ->withSession(['_token' => 'cutover-token'])
        ->post(route('finance.egc.charges.store'), [
            '_token' => 'cutover-token',
            'semester_id' => $semester->id,
            'due_date' => now()->addDays(30)->toDateString(),
        ])
        ->assertGone();

    expect(FinanceCharge::count())->toBe(0);
});

it('sanitizes Batch Studio charge-generation prefill', function () {
    $semester = Semester::factory()->create();
    $user = grantFinanceCutover(['view_finance_batch_studio']);

    actingAs($user)
        ->get(route('finance.batch-studio.charges', [
            'fee_category' => 'egc',
            'semester_id' => $semester->id,
            'search' => 'EGC001',
            'ignore_student_ids' => "EGC009\nEGC010",
            'unsafe_amount' => 999999999,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/BatchStudio/ChargeGeneration')
            ->where('prefill.fee_category', 'egc')
            ->where('prefill.semester_id', $semester->id)
            ->where('prefill.scope.filters.search', 'EGC001')
            ->missing('prefill.scope.filters.unsafe_amount')
        );
});
