<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\Exceptions\InvalidFinanceIntakePayload;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();

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

it('auto-provisions exactly one billing account when a student is created', function (): void {
    $student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
    ]);

    $accounts = BillingAccount::query()->where('student_id', $student->id)->get();

    expect($accounts)->toHaveCount(1)
        ->and($student->billingAccount)->not->toBeNull()
        ->and($student->billingAccount->id)->toBe($accounts->first()->id);

    // Second provision is idempotent.
    $again = app(BillingAccountProvisioner::class)->forStudent((int) $student->id);
    expect($again->id)->toBe($accounts->first()->id)
        ->and(BillingAccount::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('stores billing account on new debit intake and materializes a student-keyed charge', function (): void {
    $student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
    ]);

    $result = app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'course_retake_registration',
        source_ref: 'RET-PAYER-0001',
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_RETAKE_FEE,
        facts: [
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'description' => 'Retake fee with payer key',
        ],
    ));

    $obligation = FinanceObligation::query()->findOrFail($result->finance_obligation_id);
    $charge = FinanceCharge::query()->findOrFail($result->finance_charge_id);
    $line = InvoiceLine::query()->findOrFail($result->invoice_line_id);
    $account = BillingAccount::query()->where('student_id', $student->id)->firstOrFail();

    expect($obligation->billing_account_id)->toBe($account->id)
        ->and($charge->student_id)->toBe($student->id)
        ->and($charge->finance_obligation_id)->toBe($obligation->id)
        ->and((float) $charge->amount)->toBe(1_500_000.0)
        ->and((float) $line->amount_snapshot)->toBe(1_500_000.0)
        ->and($charge->source_type)->toBeNull()
        ->and($charge->source_id)->toBeNull();
});

it('does not materialize when the billing account is not linked to a student', function (): void {
    $student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
    ]);

    // Pre-student style account (wave-6 applicant path): no student link.
    $preStudentAccount = BillingAccount::query()->create(['student_id' => null]);

    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $preStudentAccount->id,
        'source_system' => 'academic',
        'source_kind' => 'course_retake_registration',
        'source_ref' => 'RET-PRE-STUDENT',
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 1_500_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'retake_fee:v1',
        'pricing_snapshot' => ['catalog_rule_version' => 'retake_fee:v1'],
        'accepted_at' => now(),
    ]);

    expect(fn () => app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'course_retake_registration',
        source_ref: 'RET-PRE-STUDENT',
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_RETAKE_FEE,
        facts: [
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
        ],
    )))->toThrow(RuntimeException::class);

    expect(FinanceCharge::query()->where('finance_obligation_id', $obligation->id)->count())->toBe(0);
});

it('rejects source-supplied billing_account_id on intake', function (): void {
    $student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
    ]);

    $account = BillingAccount::query()->where('student_id', $student->id)->firstOrFail();

    app(FinanceIntakeContract::class)->request(new FinanceIntakeData(
        source_system: 'academic',
        source_kind: 'course_retake_registration',
        source_ref: 'RET-PAYER-FORBIDDEN',
        financial_effect: FinancialEffect::Debit,
        obligation_type: FinanceCharge::TYPE_RETAKE_FEE,
        facts: [
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'billing_account_id' => $account->id,
        ],
    ));
})->throws(InvalidFinanceIntakePayload::class);
