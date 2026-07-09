<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Audit\GetFinanceAuditGraphQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

require_once __DIR__.'/audit_fixtures.php';

it('renders payment fan-out and DNG multi-charge edges', function () {
    [$student, $invoice, $lines, $payment, $dng, $charges] = seedFanOutFixture();

    $graph = app(GetFinanceAuditGraphQuery::class)
        ->handle(['type' => 'student', 'id' => $student->id]);

    expect($graph['student_id'])->toBe($student->id);

    $nodeKeys = collect($graph['nodes'])->pluck('key');
    expect($nodeKeys)->toContain("payment:{$payment->id}")
        ->and($nodeKeys)->toContain("dng:{$dng->id}");
    foreach ($charges as $charge) {
        expect($nodeKeys)->toContain("charge:{$charge->id}");
    }

    $paymentEdges = collect($graph['edges'])->where('from', "payment:{$payment->id}");
    expect($paymentEdges->count())->toBe(2);

    $dngEdges = collect($graph['edges'])
        ->where('from', "dng:{$dng->id}")
        ->where('kind', 'dng_charge');
    expect($dngEdges->count())->toBe(2);
});

it('flags cache drift in derived_balance when cached_paid_amount is stale', function () {
    [$student, $invoice] = seedStaleCacheFixture();

    $graph = app(GetFinanceAuditGraphQuery::class)
        ->handle(['type' => 'invoice', 'id' => $invoice->id]);

    $row = collect($graph['derived_balance'])->firstWhere('invoice_id', $invoice->id);
    expect($row)->not->toBeNull()
        ->and($row['drift'])->toBeTrue()
        ->and($row['derived_paid'])->toBe(500.0)
        ->and((float) $row['cached_paid_amount'])->toBe(0.0);
});

it('bounds the default graph to the most recent semester with activity', function () {
    $student = auditFixtureStudent();
    $olderSemesterId = (int) $student->intake_semester_id;
    $newerSemester = Semester::factory()->create(); // higher id => most recent

    $makeInvoice = function (int $semesterId, string $number) use ($student): StudentInvoice {
        return StudentInvoice::create([
            'invoice_number' => $number,
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
            'subtotal' => 1000, 'discount_total' => 0, 'total_amount' => 1000, 'paid_amount' => 0,
            'cached_total_amount' => 1000, 'cached_paid_amount' => 0,
        ]);
    };

    $olderInvoice = $makeInvoice($olderSemesterId, 'INV-OLD-1');
    $newerInvoice = $makeInvoice($newerSemester->id, 'INV-NEW-1');

    $graph = app(GetFinanceAuditGraphQuery::class)
        ->handle(['type' => 'student', 'id' => $student->id]);

    $nodeKeys = collect($graph['nodes'])->pluck('key');
    expect($nodeKeys)->toContain("invoice:{$newerInvoice->id}")
        ->and($nodeKeys)->not->toContain("invoice:{$olderInvoice->id}")
        ->and(collect($graph['derived_balance'])->pluck('invoice_id'))->toContain($newerInvoice->id)
        ->and(collect($graph['derived_balance'])->pluck('invoice_id'))->not->toContain($olderInvoice->id);
});

it('emits ledger_entries carrying signed payment applications for the timeline', function () {
    [$student] = seedFanOutFixture();

    $graph = app(GetFinanceAuditGraphQuery::class)
        ->handle(['type' => 'student', 'id' => $student->id]);

    expect($graph['ledger_entries'])->toBeArray()
        ->and(collect($graph['ledger_entries'])->where('type', 'payment_application')->count())->toBe(2);
});
