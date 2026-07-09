<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateManualFeeDebitAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Services\PermissionService;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\Exceptions\InvalidFinanceIntakePayload;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

    session(['current_campus_id' => $this->campus->id, '_token' => 'manual-fee-csrf']);
    app()->singleton('campus', fn () => $this->campus);

    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')->andReturn([
        'create_finance_charges',
        'view_finance_charges',
    ]);
    app()->instance(PermissionService::class, $mock);

    // Catalog rule for retake — used by pricing-rule failure test.
    DB::table('finance_pricing_catalog_items')->insert([
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 1_500_000,
        'currency' => 'VND',
        'rule_version' => 'retake_fee:v1',
        'description' => 'Fixed retake fee',
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('creates manual_fee through intake as accepted obligation with charge and invoice line', function (): void {
    $response = actingAs($this->user)->post(route('finance.charges.store'), [
        '_token' => 'manual-fee-csrf',
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'amount' => 250_000,
        'description' => 'Ad-hoc manual fee for intake cutover',
    ]);

    $obligation = FinanceObligation::query()->firstOrFail();
    $charge = FinanceCharge::query()->where('finance_obligation_id', $obligation->id)->firstOrFail();
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    $response->assertRedirect(route('finance.charges.show', $charge));

    expect($obligation->obligation_type)->toBe(FinanceCharge::TYPE_MANUAL_FEE)
        ->and($obligation->source_system)->toBe(CreateManualFeeDebitAction::SOURCE_SYSTEM)
        ->and($obligation->source_kind)->toBe(CreateManualFeeDebitAction::SOURCE_KIND)
        ->and($obligation->source_ref)->toStartWith('manual_fee:')
        ->and($obligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
        ->and((float) $obligation->amount)->toBe(250_000.0)
        ->and($obligation->currency)->toBe('VND')
        ->and($obligation->pricing_rule_version)->toBe('manual_fee:staff_supplied')
        ->and($obligation->billing_account_id)->not->toBeNull()
        ->and($charge->charge_type)->toBe(FinanceCharge::TYPE_MANUAL_FEE)
        ->and($charge->student_id)->toBe($this->student->id)
        ->and((float) $charge->amount)->toBe(250_000.0)
        ->and($charge->source_type)->toBeNull()
        ->and($charge->source_id)->toBeNull()
        ->and((float) $line->amount_snapshot)->toBe(250_000.0);
});

it('rejects validation failures for the manual fee form', function (): void {
    actingAs($this->user)
        ->from(route('finance.charges.create'))
        ->post(route('finance.charges.store'), [
            '_token' => 'manual-fee-csrf',
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
            'amount' => 100_000,
            // description missing
        ])
        ->assertRedirect(route('finance.charges.create'))
        ->assertSessionHasErrors('description');

    actingAs($this->user)
        ->from(route('finance.charges.create'))
        ->post(route('finance.charges.store'), [
            '_token' => 'manual-fee-csrf',
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
            'amount' => 0,
            'description' => 'Zero amount is invalid for debit manual_fee',
        ])
        ->assertRedirect(route('finance.charges.create'))
        ->assertSessionHasErrors('amount');

    expect(FinanceObligation::query()->count())->toBe(0)
        ->and(FinanceCharge::query()->count())->toBe(0);
});

it('rejects source-supplied final pricing values on the manual fee form', function (): void {
    actingAs($this->user)
        ->from(route('finance.charges.create'))
        ->post(route('finance.charges.store'), [
            '_token' => 'manual-fee-csrf',
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
            'amount' => 100_000,
            'description' => 'Should reject pricing_rule_version',
            'pricing_rule_version' => 'manual_fee:attacker',
            'currency' => 'USD',
        ])
        ->assertRedirect(route('finance.charges.create'))
        ->assertSessionHasErrors(['pricing_rule_version', 'currency']);

    expect(FinanceObligation::query()->count())->toBe(0);
});

it('surfaces missing pricing-catalog errors clearly for catalog-priced intake types', function (): void {
    // exam_resit_fee is catalog_fixed — no active rule seeded for it in this test.
    expect(fn () => app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'exam_resit_attempt',
        source_ref: 'RESIT-NO-PRICE',
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_EXAM_RESIT_FEE,
        facts: [
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'description' => 'Missing catalog rule',
        ],
    )))->toThrow(RuntimeException::class, 'No active Finance pricing catalog item for exam_resit_fee.');
});

it('rejects amount on catalog-priced intake while allowing staff amount for manual_fee', function (): void {
    expect(fn () => app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'course_retake_registration',
        source_ref: 'RET-PRICE-BYPASS',
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_RETAKE_FEE,
        facts: [
            'student_id' => $this->student->id,
            'semester_id' => $this->semester->id,
            'amount' => 999,
        ],
    )))->toThrow(InvalidFinanceIntakePayload::class);

    $result = app(CreateManualFeeDebitAction::class)->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'amount' => 75_000,
        'description' => 'Staff-supplied manual fee',
    ]);

    expect($result->amount)->toBe(75_000.0)
        ->and(FinanceObligation::query()->findOrFail($result->finance_obligation_id)->pricing_rule_version)
        ->toBe('manual_fee:staff_supplied');
});

it('renders the manual charge create page for authorized staff', function (): void {
    actingAs($this->user)
        ->get(route('finance.charges.create', ['student_id' => $this->student->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Charges/Create')
            ->has('chargeTypes')
            ->has('semesters')
            ->where('student.id', $this->student->id)
        );
});
