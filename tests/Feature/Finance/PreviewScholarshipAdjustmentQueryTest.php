<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentPreviewReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The preview drives what staff approve and what the student is told they owe,
 * so it must agree with the ledger's own resolver — including its clamp.
 */
function previewFixture(string $type, float $awardAmount, float $tuition): array
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 1,
        'intake_semester_id' => $semester->id,
    ]);

    $definition = ScholarshipDefinition::create([
        'code' => 'PREV'.uniqid(),
        'name' => 'Preview test',
        'description' => 'test',
        'type' => $type,
        'amount' => $awardAmount,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $target = Semester::factory()->create();

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-PREV-'.uniqid(),
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $target->id,
        'status' => 'pending',
        'due_date' => now()->addMonth(),
        'subtotal' => $tuition,
        'discount_total' => 0,
        'total_amount' => $tuition,
        'paid_amount' => 0,
    ]);

    $billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'tuition',
        'source_ref' => 'preview-'.$student->id,
        'obligation_type' => 'tuition',
        'amount' => $tuition,
        'pricing_rule_version' => 'test-v1',
        'semester_id' => $target->id,
        'status' => 'accepted',
        'currency' => 'VND',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);

    $charge = FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $target->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $tuition,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $tuition,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    return ['student' => $student, 'target' => $target];
}

it('shows the extra amount a percentage reduction costs the student', function () {
    ['student' => $student, 'target' => $target] = previewFixture('percentage', 30, 40_000_000);

    // 30% -> 15% of 40m: discount falls 12m -> 6m, so the student owes 6m more.
    $preview = app(ScholarshipAdjustmentPreviewReader::class)->preview($student->id, $target->id, 15);

    expect($preview->has_invoice)->toBeTrue()
        ->and($preview->tuition_base)->toBe(40_000_000.0)
        ->and($preview->current_discount)->toBe(12_000_000.0)
        ->and($preview->adjusted_discount)->toBe(6_000_000.0)
        ->and($preview->payable_before)->toBe(28_000_000.0)
        ->and($preview->payable_after)->toBe(34_000_000.0)
        ->and($preview->delta())->toBe(6_000_000.0);
});

it('shows the extra amount a fixed_amount reduction costs the student', function () {
    ['student' => $student, 'target' => $target] = previewFixture('fixed_amount', 20_000_000, 40_000_000);

    $preview = app(ScholarshipAdjustmentPreviewReader::class)->preview($student->id, $target->id, 15_000_000);

    expect($preview->current_discount)->toBe(20_000_000.0)
        ->and($preview->adjusted_discount)->toBe(15_000_000.0)
        ->and($preview->payable_after)->toBe(25_000_000.0)
        ->and($preview->delta())->toBe(5_000_000.0);
});

it('reports a zero delta when the tuition cap absorbs the reduction', function () {
    // Award 20m against 12m tuition: both the original and the reduced value
    // clamp to 12m, so the student pays exactly the same. Surfacing this as
    // delta = 0 is what lets the UI warn that the decision changes nothing.
    ['student' => $student, 'target' => $target] = previewFixture('fixed_amount', 20_000_000, 12_000_000);

    $preview = app(ScholarshipAdjustmentPreviewReader::class)->preview($student->id, $target->id, 15_000_000);

    expect($preview->current_discount)->toBe(12_000_000.0)
        ->and($preview->adjusted_discount)->toBe(12_000_000.0)
        ->and($preview->delta())->toBe(0.0);
});

it('charges the full tuition for a full suspension', function () {
    ['student' => $student, 'target' => $target] = previewFixture('percentage', 30, 40_000_000);

    $preview = app(ScholarshipAdjustmentPreviewReader::class)->preview($student->id, $target->id, 0);

    expect($preview->adjusted_discount)->toBe(0.0)
        ->and($preview->payable_after)->toBe(40_000_000.0)
        ->and($preview->delta())->toBe(12_000_000.0);
});

it('previews only the current position when no value is proposed', function () {
    ['student' => $student, 'target' => $target] = previewFixture('percentage', 30, 40_000_000);

    $preview = app(ScholarshipAdjustmentPreviewReader::class)->preview($student->id, $target->id, null);

    expect($preview->adjusted_discount)->toBeNull()
        ->and($preview->payable_after)->toBeNull()
        ->and($preview->delta())->toBeNull()
        ->and($preview->payable_before)->toBe(28_000_000.0);
});

it('reports no invoice rather than zero amounts when the semester is not billed yet', function () {
    ['student' => $student] = previewFixture('percentage', 30, 40_000_000);
    $unbilled = Semester::factory()->create();

    $preview = app(ScholarshipAdjustmentPreviewReader::class)->preview($student->id, $unbilled->id, 15);

    expect($preview->has_invoice)->toBeFalse()
        ->and($preview->tuition_base)->toBe(0.0);
});
