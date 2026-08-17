<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceSetting;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Queries\Egc\PreviewEgcChargeGenerationQuery;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    app()->instance('campus', $this->campus);
});

function setEgcPreviewCreditOffset(bool $enabled, float $minBalance): void
{
    FinanceSetting::current()->update([
        'credit_offset_enabled' => $enabled,
        'credit_offset_min_balance' => $minBalance,
    ]);
}

it('defaults unapplied credit and projected offset to zero when the setting is disabled', function () {
    setEgcPreviewCreditOffset(enabled: false, minBalance: 0);
    $student = makeBatchEgcStudent($this->campus, $this->semester, 'EPCO'.random_int(100000000, 999999999));

    $query = app(PreviewEgcChargeGenerationQuery::class);
    $row = $query->collectClassificationRows($this->semester->id, ['student_ids' => [$student->id]], null)->first();

    expect($row['eligibility_status'])->toBe('eligible')
        ->and($row['unapplied_credit'])->toBe(0.0)
        ->and($row['credit_offset_projected'])->toBe(0.0);
});

it('projects the credit offset for an eligible EGC student the same way HP does', function () {
    setEgcPreviewCreditOffset(enabled: true, minBalance: 10_000_000);
    $student = makeBatchEgcStudent($this->campus, $this->semester, 'EPCO'.random_int(100000000, 999999999));

    app(BillingAccountProvisioner::class)->forStudent($student->id);
    Payment::create([
        'student_id' => $student->id,
        'amount' => 20_000_000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $query = app(PreviewEgcChargeGenerationQuery::class);
    $row = $query->collectClassificationRows($this->semester->id, ['student_ids' => [$student->id]], null)->first();

    // Level 1 student, fresh generation charges 2 blocks x 15,000,000 = 30,000,000
    // net amount — the 20M unapplied cash is fully absorbed as a projected offset.
    $netAmount = (float) collect($row['chargeable_levels'])->sum('amount');
    expect($netAmount)->toBe(30_000_000.0)
        ->and($row['unapplied_credit'])->toBe(20_000_000.0)
        ->and($row['credit_offset_projected'])->toBe(20_000_000.0);
});

it('caps the projected EGC offset at the chargeable-levels net amount when the balance exceeds it', function () {
    setEgcPreviewCreditOffset(enabled: true, minBalance: 10_000_000);
    $student = makeBatchEgcStudent($this->campus, $this->semester, 'EPCO'.random_int(100000000, 999999999));

    app(BillingAccountProvisioner::class)->forStudent($student->id);
    Payment::create([
        'student_id' => $student->id,
        'amount' => 50_000_000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $query = app(PreviewEgcChargeGenerationQuery::class);
    $row = $query->collectClassificationRows($this->semester->id, ['student_ids' => [$student->id]], null)->first();

    expect($row['unapplied_credit'])->toBe(50_000_000.0)
        ->and($row['credit_offset_projected'])->toBe(30_000_000.0);
});

it('does not project an EGC offset when the balance is below the configured threshold', function () {
    setEgcPreviewCreditOffset(enabled: true, minBalance: 25_000_000);
    $student = makeBatchEgcStudent($this->campus, $this->semester, 'EPCO'.random_int(100000000, 999999999));

    app(BillingAccountProvisioner::class)->forStudent($student->id);
    Payment::create([
        'student_id' => $student->id,
        'amount' => 20_000_000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $query = app(PreviewEgcChargeGenerationQuery::class);
    $row = $query->collectClassificationRows($this->semester->id, ['student_ids' => [$student->id]], null)->first();

    expect($row['unapplied_credit'])->toBe(20_000_000.0)
        ->and($row['credit_offset_projected'])->toBe(0.0);
});

it('projects the credit offset on the reissue branch too', function () {
    setEgcPreviewCreditOffset(enabled: true, minBalance: 10_000_000);
    $student = makeBatchEgcStudent($this->campus, $this->semester, 'EPCO'.random_int(100000000, 999999999));
    seedBatchEgcBlockCharge($student, $this->semester, 1, 1, FinanceCharge::STATUS_VOID, 'void');
    seedBatchEgcBlockCharge($student, $this->semester, 2, 2, FinanceCharge::STATUS_VOID, 'void');

    app(BillingAccountProvisioner::class)->forStudent($student->id);
    Payment::create([
        'student_id' => $student->id,
        'amount' => 20_000_000,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);

    $query = app(PreviewEgcChargeGenerationQuery::class);
    $row = $query->collectClassificationRows($this->semester->id, ['student_ids' => [$student->id]], null)->first();

    // Two voided blocks (levels 1, 2) become reissue candidates: 2 x 15,000,000
    // net — same shape as the fresh branch, just via a different code path.
    expect($row['eligibility_status'])->toBe('eligible')
        ->and($row['generation_mode'])->toBe('reissue')
        ->and($row['unapplied_credit'])->toBe(20_000_000.0)
        ->and($row['credit_offset_projected'])->toBe(20_000_000.0);
});
