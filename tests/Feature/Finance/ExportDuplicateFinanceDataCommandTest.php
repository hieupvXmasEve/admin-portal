<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

require_once __DIR__.'/DataGuards/guard_fixtures.php';

/**
 * S-003 dry-run export: surfaces INV-6 duplicate invoices and INV-11 duplicate
 * webhook payload hashes for human-reviewed cleanup, and never mutates data.
 */
function seedDuplicateInvoiceGroup(): void
{
    $student = makeGuardStudent();
    $semester = Semester::factory()->create();

    foreach (['INV-DUP-A', 'INV-DUP-B'] as $number) {
        StudentInvoice::query()->create([
            'invoice_number' => $number,
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'status' => 'draft',
            'due_date' => now()->addDays(30),
        ]);
    }
}

function seedDuplicatePayloadHashGroup(): void
{
    foreach ([1, 2] as $i) {
        DB::table('dng_webhook_events')->insert([
            'event_type' => 'payment_invoiced',
            'payload_hash' => str_repeat('c', 64),
            'payload' => '{"x":'.$i.'}',
            'is_valid_checksum' => 1,
            'processing_status' => 'processed',
        ]);
    }
}

it('reports duplicate groups and exits successfully', function () {
    seedDuplicateInvoiceGroup();
    seedDuplicatePayloadHashGroup();

    $this->artisan('finance:export-duplicate-finance-data')
        ->expectsOutputToContain('READ-ONLY')
        ->expectsOutputToContain('INV-6')
        ->expectsOutputToContain('INV-11')
        ->assertExitCode(0);
});

it('never mutates data (read-only)', function () {
    seedDuplicateInvoiceGroup();
    seedDuplicatePayloadHashGroup();

    $invoicesBefore = DB::table('student_invoices')->count();
    $eventsBefore = DB::table('dng_webhook_events')->count();

    $this->artisan('finance:export-duplicate-finance-data')->assertExitCode(0);

    expect(DB::table('student_invoices')->count())->toBe($invoicesBefore)
        ->and(DB::table('dng_webhook_events')->count())->toBe($eventsBefore);
});

it('reports clean when there are no duplicates', function () {
    $this->artisan('finance:export-duplicate-finance-data')
        ->expectsOutputToContain('none')
        ->assertExitCode(0);
});

it('surfaces pivot-linked DNG requests (finance_charge_id NULL) in the JSON report', function () {
    $student = makeGuardStudent();
    $semester = Semester::factory()->create();

    // Duplicate invoice group; the canonical invoice carries a charge.
    $invoiceA = StudentInvoice::query()->create([
        'invoice_number' => 'INV-PIVOT-A',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);
    StudentInvoice::query()->create([
        'invoice_number' => 'INV-PIVOT-B',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 15_000_000,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => 'active',
    ]);

    InvoiceLine::query()->create([
        'invoice_id' => $invoiceA->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 15_000_000,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    // Aggregate DNG request: finance_charge_id is NULL, linkage only via pivot.
    $dngId = DB::table('dng_payment_requests')->insertGetId([
        'student_id' => $student->id,
        'campus_code' => 'AUH',
        'student_code' => 'SWB001',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-PIVOT-1',
        'amount' => 15_000_000,
        'status' => 'pushed_to_dng',
        'finance_charge_id' => null,
    ]);
    DB::table('dng_payment_request_charges')->insert([
        'dng_payment_request_id' => $dngId,
        'finance_charge_id' => $charge->id,
        'amount' => 15_000_000,
    ]);

    $path = storage_path('app/finance/test-pivot-export.json');
    $this->artisan('finance:export-duplicate-finance-data', ['--json' => $path])
        ->assertExitCode(0);

    $report = json_decode(file_get_contents($path), true);
    @unlink($path);

    $invoiceArow = collect($report['duplicate_invoice_groups'])
        ->flatMap(fn ($g) => $g['invoices'])
        ->firstWhere('invoice_id', $invoiceA->id);

    expect($invoiceArow)->not->toBeNull()
        ->and($invoiceArow['dng_requests'])->toHaveCount(1)
        ->and($invoiceArow['dng_requests'][0]['dng_request_id'])->toBe($dngId)
        ->and($invoiceArow['dng_requests'][0]['link_source'])->toBe('pivot')
        ->and((float) $invoiceArow['dng_requests'][0]['pivot_amount'])->toBe(15_000_000.0);
});
