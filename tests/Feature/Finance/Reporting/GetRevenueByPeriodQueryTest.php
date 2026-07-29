<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Finance\Models\PaymentSurplusDisposition;
use App\Modules\Finance\Queries\Reporting\GetCollectionProgressSummaryQuery;
use App\Modules\Finance\Queries\Reporting\GetRevenueByPeriodQuery;
use App\Modules\Finance\Queries\Reporting\ListCollectionProgressQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

require_once __DIR__.'/RevenueReportTestHelpers.php';

it('excludes invalid lines and cancelled invoices from net_billed, counting invalid separately', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['start_date' => now()->subMonths(1)]);
    $student = revStudent($campus, $semester, 'REV-001');

    revBill($student, $semester, 5_000_000);
    // gross <= 0 line -> PAYABLE_LINE_NOT_COLLECTIBLE, excluded from every money column
    revBill($student, $semester, 0, recalculate: false);
    // cancelled invoice -> excluded at the line-loading query, never reaches a bucket
    revBill($student, $semester, 3_000_000, 'cancelled');

    $row = revRowFor(app(GetRevenueByPeriodQuery::class)->handle(), $semester->id);

    expect($row)->not->toBeNull()
        ->and($row['net_billed'])->toEqual(5_000_000.0)
        ->and($row['invalid_count'])->toBe(1)
        ->and($row['invalid_gross'])->toEqual(0.0);
});

it('matches cash to the Collection Progress paid_total for the same semester and campus', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['start_date' => now()->subMonths(2)]);
    $student = revStudent($campus, $semester, 'REV-RECON');
    [, $line] = revBill($student, $semester, 10_000_000);
    revPay($student, $line, 4_000_000);

    $row = revRowFor(
        app(GetRevenueByPeriodQuery::class)->handle(['semester_ids' => [$semester->id], 'campus_id' => $campus->id]),
        $semester->id,
    );

    app()->singleton('campus', fn () => $campus);
    $cpRows = app(ListCollectionProgressQuery::class)->collectRows($semester->id);
    $summary = app(GetCollectionProgressSummaryQuery::class)->fromRows($cpRows);

    expect($row['cash'])->toEqual($summary['paid_total']);
});

it('reports unattributed cash separate from refund/forfeit dispositions', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = revStudent($campus, $semester, 'REV-UNATTRIB');
    $approver = User::factory()->create();
    [, $line] = revBill($student, $semester, 1_000_000);

    // Pay 100, apply 60 to the invoice line -> 40 left on the payment.
    $payment = revPay($student, $line, 100, 60);
    PaymentSurplusDisposition::query()->create([
        'payment_id' => $payment->id,
        'idempotency_key' => (string) Str::uuid(),
        'type' => PaymentSurplusDisposition::TYPE_REFUND,
        'amount' => 10,
        'evidence' => [],
        'audit_signature' => str_repeat('a', 64),
        'approved_by' => $approver->id,
        'disposed_at' => now(),
    ]);

    $result = app(GetRevenueByPeriodQuery::class)->handle();

    expect($result['unattributed']['unapplied'])->toEqual(30.0)
        ->and($result['unattributed']['refund'])->toEqual(10.0)
        ->and($result['unattributed']['retain_forfeit'])->toEqual(0.0);
});

it('computes growth_pct from the immediately preceding semester and nulls the oldest period', function () {
    $campus = Campus::factory()->create();
    $older = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $newer = Semester::factory()->create(['start_date' => now()->subMonths(1)]);
    $student = revStudent($campus, $older, 'REV-GROWTH');
    revBill($student, $older, 4_000_000);
    revBill($student, $newer, 6_000_000);

    $result = app(GetRevenueByPeriodQuery::class)->handle();

    expect(revRowFor($result, $newer->id)['growth_pct'])->toEqual(round((6_000_000 - 4_000_000) / 4_000_000, 4))
        ->and(revRowFor($result, $older->id)['growth_pct'])->toBeNull();
});

it('nulls growth_pct instead of dividing by a zero previous-period net_billed', function () {
    $campus = Campus::factory()->create();
    $older = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $newer = Semester::factory()->create(['start_date' => now()->subMonths(1)]);
    $student = revStudent($campus, $older, 'REV-ZERO-BASE');
    // Older period has only an invalid (gross<=0) line, so its net_billed is 0.
    revBill($student, $older, 0, recalculate: false);
    revBill($student, $newer, 6_000_000);

    $result = app(GetRevenueByPeriodQuery::class)->handle();

    expect(revRowFor($result, $newer->id)['growth_pct'])->toBeNull();
});
