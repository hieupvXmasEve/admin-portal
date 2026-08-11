<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Modules\Finance\Models\FinanceSetting;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Queries\Major\PreviewMajorChargeGenerationQuery;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    app()->instance('campus', $this->campus);
});

function setMajorPreviewCreditOffset(bool $enabled, float $minBalance): void
{
    FinanceSetting::current()->update([
        'credit_offset_enabled' => $enabled,
        'credit_offset_min_balance' => $minBalance,
    ]);
}

it('surfaces program name and batch context for every eligible row', function () {
    setMajorPreviewCreditOffset(enabled: false, minBalance: 0);
    $student = makeBatchHpStudent($this->campus, $this->semester, 'MPCO'.random_int(100000000, 999999999));

    $query = app(PreviewMajorChargeGenerationQuery::class);
    $rows = $query->collectClassificationRows($this->semester->id, ['student_ids' => [$student->id]], null);
    $row = $rows->first();

    expect($row['eligibility_status'])->toBe('eligible')
        ->and($row['program_name'])->not->toBeNull()
        ->and($row['unapplied_credit'])->toBe(0.0)
        ->and($row['credit_offset_projected'])->toBe(0.0);

    $context = $query->batchContext($this->semester->id);
    expect($context['charge_semester_id'])->toBe($this->semester->id)
        ->and($context['charge_semester_code'])->toBe($this->semester->code)
        ->and($context['credit_offset_enabled'])->toBeFalse();
});

it('projects the credit offset when enabled and the balance meets the threshold', function () {
    setMajorPreviewCreditOffset(enabled: true, minBalance: 10_000_000);
    $student = makeBatchHpStudent($this->campus, $this->semester, 'MPCO'.random_int(100000000, 999999999));

    app(BillingAccountProvisioner::class)->forStudent($student->id);
    Payment::create([
        'student_id' => $student->id,
        'amount' => 20_000_000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $query = app(PreviewMajorChargeGenerationQuery::class);
    $row = $query->collectClassificationRows($this->semester->id, ['student_ids' => [$student->id]], null)->first();

    // Tuition term amount seeded by makeBatchHpStudent is 45,000,000 — the
    // 20M unapplied cash is fully absorbed as a projected offset, capped at
    // the charge's own net_amount (which it doesn't reach here).
    expect($row['unapplied_credit'])->toBe(20_000_000.0)
        ->and($row['credit_offset_projected'])->toBe(20_000_000.0);
});

it('caps the projected offset at the charge net_amount when the balance exceeds it', function () {
    setMajorPreviewCreditOffset(enabled: true, minBalance: 10_000_000);
    $student = makeBatchHpStudent($this->campus, $this->semester, 'MPCO'.random_int(100000000, 999999999));

    app(BillingAccountProvisioner::class)->forStudent($student->id);
    Payment::create([
        'student_id' => $student->id,
        'amount' => 60_000_000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $query = app(PreviewMajorChargeGenerationQuery::class);
    $row = $query->collectClassificationRows($this->semester->id, ['student_ids' => [$student->id]], null)->first();

    // 60M unapplied vs. a 45M charge — projected offset must cap at net_amount.
    expect($row['unapplied_credit'])->toBe(60_000_000.0)
        ->and($row['net_amount'])->toBe(45_000_000.0)
        ->and($row['credit_offset_projected'])->toBe(45_000_000.0);
});

it('computes unapplied cash correctly in bulk across a mixed set of students', function () {
    setMajorPreviewCreditOffset(enabled: false, minBalance: 0);

    $withPayment = makeBatchHpStudent($this->campus, $this->semester, 'MPCO'.random_int(100000000, 999999999));
    $withoutPayment = makeBatchHpStudent($this->campus, $this->semester, 'MPCO'.random_int(100000000, 999999999));

    app(BillingAccountProvisioner::class)->forStudent($withPayment->id);
    Payment::create([
        'student_id' => $withPayment->id,
        'amount' => 7_000_000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $query = app(PreviewMajorChargeGenerationQuery::class);
    $rows = $query->collectClassificationRows(
        $this->semester->id,
        ['student_ids' => [$withPayment->id, $withoutPayment->id]],
        null,
    )->keyBy('student_id');

    expect($rows[$withPayment->id]['unapplied_credit'])->toBe(7_000_000.0)
        ->and($rows[$withoutPayment->id]['unapplied_credit'])->toBe(0.0);
});

it('does not project an offset when the balance is below the configured threshold', function () {
    setMajorPreviewCreditOffset(enabled: true, minBalance: 25_000_000);
    $student = makeBatchHpStudent($this->campus, $this->semester, 'MPCO'.random_int(100000000, 999999999));

    app(BillingAccountProvisioner::class)->forStudent($student->id);
    Payment::create([
        'student_id' => $student->id,
        'amount' => 20_000_000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $query = app(PreviewMajorChargeGenerationQuery::class);
    $row = $query->collectClassificationRows($this->semester->id, ['student_ids' => [$student->id]], null)->first();

    expect($row['unapplied_credit'])->toBe(20_000_000.0)
        ->and($row['credit_offset_projected'])->toBe(0.0);
});
