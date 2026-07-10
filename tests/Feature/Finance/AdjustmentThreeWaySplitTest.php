<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateStaffDebitAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\Integrity\FinanceInvariantRegistry;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->user = User::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
    ]);

    session(['current_campus_id' => $this->campus->id, '_token' => 'adjustment-csrf']);
    app()->singleton('campus', fn () => $this->campus);

    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')->andReturn([
        'create_finance_charges',
        'view_finance_charges',
    ]);
    app()->instance(PermissionService::class, $mock);
});

it('creates a positive adjustment debit through finance intake (manual_adjustment)', function (): void {
    $response = actingAs($this->user)->post(route('finance.charges.store'), [
        '_token' => 'adjustment-csrf',
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_ADJUSTMENT,
        'adjustment_intent' => CreateStaffDebitAction::ADJUSTMENT_INTENT_POSITIVE_DEBIT,
        'amount' => 500_000,
        'description' => 'Staff positive adjustment debit',
    ]);

    $obligation = FinanceObligation::query()->firstOrFail();
    $charge = FinanceCharge::query()->where('finance_obligation_id', $obligation->id)->firstOrFail();
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    $response->assertRedirect(route('finance.charges.show', $charge));

    expect($obligation->obligation_type)->toBe(FinanceCharge::TYPE_ADJUSTMENT)
        ->and($obligation->source_system)->toBe(CreateStaffDebitAction::SOURCE_SYSTEM)
        ->and($obligation->source_kind)->toBe('manual_adjustment')
        ->and($obligation->source_ref)->toStartWith('manual_adjustment:')
        ->and($obligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
        ->and((float) $obligation->amount)->toBe(500_000.0)
        ->and($charge->charge_type)->toBe(FinanceCharge::TYPE_ADJUSTMENT)
        ->and((float) $charge->amount)->toBe(500_000.0)
        ->and($charge->source_type)->toBeNull()
        ->and($charge->source_id)->toBeNull()
        ->and((float) $line->amount_snapshot)->toBe(500_000.0);
});

it('requires adjustment_intent when charge_type is adjustment', function (): void {
    actingAs($this->user)
        ->from(route('finance.charges.create'))
        ->post(route('finance.charges.store'), [
            '_token' => 'adjustment-csrf',
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_ADJUSTMENT,
            'amount' => 100_000,
            'description' => 'Missing intent free-signed charge must fail',
        ])
        ->assertRedirect(route('finance.charges.create'))
        ->assertSessionHasErrors('adjustment_intent');

    expect(FinanceCharge::query()->count())->toBe(0)
        ->and(FinanceObligation::query()->count())->toBe(0);
});

it('rejects negative amount on the adjustment form (no negative charge rows)', function (): void {
    actingAs($this->user)
        ->from(route('finance.charges.create'))
        ->post(route('finance.charges.store'), [
            '_token' => 'adjustment-csrf',
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_ADJUSTMENT,
            'adjustment_intent' => CreateStaffDebitAction::ADJUSTMENT_INTENT_POSITIVE_DEBIT,
            'amount' => -250_000,
            'description' => 'Negative credit disguised as adjustment',
        ])
        ->assertRedirect(route('finance.charges.create'))
        ->assertSessionHasErrors('amount');

    expect(FinanceCharge::query()->count())->toBe(0)
        ->and(FinanceObligation::query()->count())->toBe(0);
});

it('rejects credit_memo intent with a clear message pointing at the credit entitlement flow', function (): void {
    actingAs($this->user)
        ->from(route('finance.charges.create'))
        ->post(route('finance.charges.store'), [
            '_token' => 'adjustment-csrf',
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_ADJUSTMENT,
            'adjustment_intent' => CreateStaffDebitAction::ADJUSTMENT_INTENT_CREDIT_MEMO,
            'amount' => 100_000,
            'description' => 'Should be a credit memo not a charge',
        ])
        ->assertRedirect(route('finance.charges.create'))
        ->assertSessionHasErrors('adjustment_intent');

    $errors = session('errors');
    expect($errors)->not->toBeNull()
        ->and($errors->first('adjustment_intent'))->toContain('credit')
        ->and(FinanceCharge::query()->count())->toBe(0);
});

it('rejects settlement_correction intent so it is never a charge row', function (): void {
    actingAs($this->user)
        ->from(route('finance.charges.create'))
        ->post(route('finance.charges.store'), [
            '_token' => 'adjustment-csrf',
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_ADJUSTMENT,
            'adjustment_intent' => CreateStaffDebitAction::ADJUSTMENT_INTENT_SETTLEMENT_CORRECTION,
            'amount' => 100_000,
            'description' => 'Pure reallocation must not mint a charge',
        ])
        ->assertRedirect(route('finance.charges.create'))
        ->assertSessionHasErrors('adjustment_intent');

    $errors = session('errors');
    expect($errors)->not->toBeNull()
        ->and($errors->first('adjustment_intent'))->toContain('reallocation')
        ->and(FinanceCharge::query()->count())->toBe(0);
});

it('does not allow settlement_correction as an adjustment charge source_kind in the registry', function (): void {
    $definition = ObligationTypeRegistry::get(FinanceCharge::TYPE_ADJUSTMENT);

    expect($definition->allowedSourceKinds)
        ->toContain('manual_adjustment')
        ->toContain('defer_forfeit')
        ->not->toContain('settlement_correction');
});

it('guards active signed (negative) adjustment rows via integrity invariant', function (): void {
    $registry = app(FinanceInvariantRegistry::class);
    $invariant = $registry->find('INV-16');

    expect($invariant)->not->toBeNull()
        ->and($invariant->label)->toContain('adjustment');

    // Zero-row baseline: no active negative adjustments.
    $count = (int) DB::selectOne(str_replace('{scope}', '1=1', $invariant->countSqlTemplate))->c;
    expect($count)->toBe(0);

    // Inject a legacy-shaped signed adjustment (DB CHECK still allows adjustment any sign).
    DB::table('finance_charges')->insert([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_ADJUSTMENT,
        'amount' => -50_000,
        'description' => 'legacy signed adjustment exception candidate',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $countAfter = (int) DB::selectOne(str_replace('{scope}', '1=1', $invariant->countSqlTemplate))->c;
    expect($countAfter)->toBe(1);
});

it('exposes adjustment intents on the charge create page', function (): void {
    actingAs($this->user)
        ->get(route('finance.charges.create', ['student_id' => $this->student->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Charges/Create')
            ->has('adjustmentIntents')
            ->where('adjustmentIntents.0.value', CreateStaffDebitAction::ADJUSTMENT_INTENT_POSITIVE_DEBIT)
        );
});
