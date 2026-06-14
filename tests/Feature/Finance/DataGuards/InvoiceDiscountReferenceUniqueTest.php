<?php

declare(strict_types=1);

use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\Semester;
use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

require_once __DIR__.'/guard_fixtures.php';

/**
 * DB-10: unique(invoice_id, discount_type, reference_id, discount_source) — the
 * exact key SettlementService::createOrRefreshInvoiceDiscount uses via firstOrNew.
 */
function uniqueGuardInvoice(): StudentInvoice
{
    $student = makeGuardStudent();
    $semester = Semester::factory()->create();

    return StudentInvoice::query()->create([
        'invoice_number' => 'INV-UNQ-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);
}

it('rejects a duplicate discount on the same invoice/type/reference/source', function () {
    $invoice = uniqueGuardInvoice();

    $row = [
        'invoice_id' => $invoice->id,
        'discount_type' => 'scholarship',
        'discount_source' => 'scholarship',
        'description' => 'Scholarship',
        'amount' => 4_500_000,
        'status' => 'active',
        'reference_id' => 77,
    ];

    DB::table('invoice_discounts')->insert($row);

    expect(fn () => DB::table('invoice_discounts')->insert($row))
        ->toThrow(QueryException::class);
});

it('rejects a duplicate discount even when reference_id is NULL', function () {
    $invoice = uniqueGuardInvoice();

    $row = [
        'invoice_id' => $invoice->id,
        'discount_type' => 'voucher',
        'discount_source' => 'manual',
        'description' => 'Manual voucher',
        'amount' => 1_000_000,
        'status' => 'active',
        'reference_id' => null,
    ];

    DB::table('invoice_discounts')->insert($row);

    // Without the COALESCE(reference_id,0) sentinel, MariaDB would treat the two
    // NULLs as distinct and allow this duplicate.
    expect(fn () => DB::table('invoice_discounts')->insert($row))
        ->toThrow(QueryException::class);
});

it('lets createOrRefreshInvoiceDiscount stay idempotent under the unique key', function () {
    $invoice = uniqueGuardInvoice();

    $charge = FinanceCharge::create([
        'student_id' => $invoice->student_id,
        'semester_id' => $invoice->semester_id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 15_000_000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => 'active',
    ]);

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 15_000_000,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    $service = app(SettlementService::class);

    $service->createOrRefreshInvoiceDiscount($invoice, 'scholarship', 4_500_000, 'scholarship', 'Scholarship', 77);
    $service->createOrRefreshInvoiceDiscount($invoice, 'scholarship', 6_000_000, 'scholarship', 'Scholarship', 77);

    $discounts = InvoiceDiscount::query()
        ->where('invoice_id', $invoice->id)
        ->where('discount_type', 'scholarship')
        ->where('reference_id', 77)
        ->get();

    expect($discounts)->toHaveCount(1)
        ->and((float) $discounts->first()->amount)->toBe(6_000_000.0);
});
