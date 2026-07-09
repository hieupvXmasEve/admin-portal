<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
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

if (! function_exists('seedCockpitSettlementCandidate')) {
    function seedCockpitSettlementCandidate(Campus $campus, Semester $semester, string $studentCode): void
    {
        $student = Student::factory()
            ->forCampus($campus)
            ->state([
                'student_id' => $studentCode,
                'intake' => 1,
                'intake_semester_id' => $semester->id,
            ])
            ->create();

        $invoice = StudentInvoice::query()->create([
            'invoice_number' => 'INV-'.$studentCode,
            'student_id' => $student->id,
            'billing_cycle_id' => null,
            'semester_id' => $semester->id,
            'status' => 'pending',
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 0,
            'discount_total' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
        ]);

        $charge = FinanceCharge::query()->create([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'billing_cycle_id' => null,
            'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'amount' => 15000000,
            'description' => 'Cockpit settlement candidate',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => 15000000,
            'description_snapshot' => 'Cockpit settlement candidate',
            'status' => 'active',
        ]);

        Payment::query()->create([
            'student_id' => $student->id,
            'amount' => 10000000,
            'method' => Payment::METHOD_IMPORT,
            'source' => 'import',
            'paid_at' => now(),
            'status' => Payment::STATUS_COMPLETED,
        ]);
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
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

it('uses the current-campus settlement ready count for the unallocated queue', function () {
    $otherCampus = Campus::factory()->create();
    seedCockpitSettlementCandidate($this->campus, $this->semester, 'AUS-CURRENT');
    seedCockpitSettlementCandidate($otherCampus, $this->semester, 'AUS-OTHER');

    $user = grantCockpit(['view_finance_cockpit', 'allocate_finance_payment']);

    actingAs($user)->get('/finance/cockpit')
        ->assertInertia(function ($page) {
            $queues = collect($page->toArray()['props']['queues']);
            $unallocated = $queues->firstWhere('key', 'unallocated');

            expect($unallocated['count'])->toBe(1)
                ->and($unallocated['scope_badge'])->toBe('campus')
                ->and($unallocated['action_url'])->toBe(route('finance.operations.settlement.index', ['readiness' => 'ready']));
        });
});

it('labels cockpit campus queues as the current campus scope', function () {
    $card = file_get_contents(base_path('resources/js/components/finance/cockpit/QueueCard.vue'));

    expect($card)
        ->toContain("campus: 'Campus hiện tại'")
        ->not->toContain("campus: 'Toàn campus'");
});
