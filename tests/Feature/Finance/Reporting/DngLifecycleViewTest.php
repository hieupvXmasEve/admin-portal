<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

require_once __DIR__.'/../Batch/helpers.php';

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();

    session([
        'current_campus_id' => $this->campus->id,
        'current_semester_id' => $this->semester->id,
    ]);
});

function dngStudent(Campus $campus, Semester $semester, string $code, string $status = 'intake_course'): Student
{
    return Student::factory()->forCampus($campus)->create([
        'student_id' => $code,
        'status' => $status,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeDng(Student $student, array $attributes = []): DngPaymentRequest
{
    $createdAt = $attributes['created_at'] ?? null;
    unset($attributes['created_at']);

    $request = DngPaymentRequest::query()->create(array_merge([
        'student_id' => $student->id,
        'campus_code' => 'CMP',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'ITEM-'.uniqid(),
        'amount' => 1_000_000,
        'status' => DngPaymentRequest::STATUS_PENDING,
    ], $attributes));

    if ($createdAt !== null) {
        $request->created_at = $createdAt;
        $request->save();
    }

    return $request->fresh();
}

function dngCharge(Student $student, Semester $semester, float $amount = 1_000_000): FinanceCharge
{
    return FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
}

function dngRows(TestResponse $response): Collection
{
    return collect($response->original->getData()['page']['props']['dng_lifecycle']['rows']['data']);
}

function getDng(string $extraQuery = ''): TestResponse
{
    $base = route('finance.reporting.index', ['view' => 'dng-lifecycle']);
    $url = $extraQuery === '' ? $base : $base.'&'.$extraQuery;

    return test()->get($url);
}

it('renders dng lifecycle props with summary, breakdowns, filters, and filter options', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = dngStudent($this->campus, $this->semester, 'DNG-001');
    makeDng($student, ['status' => DngPaymentRequest::STATUS_FAILED, 'semester_id' => $this->semester->id]);

    $this->actingAs($this->user)
        ->get(route('finance.reporting.index', ['view' => 'dng-lifecycle']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Reporting/Index', false)
            ->where('active_view', 'dng-lifecycle')
            ->where('views.2.key', 'dng-lifecycle')
            ->where('views.2.status', 'implemented')
            ->where('views.2.obeys_semester', false)
            ->has('dng_lifecycle.rows')
            ->has('dng_lifecycle.summary')
            ->has('dng_lifecycle.breakdowns.by_attention_bucket')
            ->has('dng_lifecycle.breakdowns.by_status')
            ->has('dng_lifecycle.filters')
            ->has('dng_lifecycle.filter_options.attention_buckets')
            ->has('dng_lifecycle.filter_options.webhook_states')
            ->where('dng_lifecycle.meta.obeys_semester', false)
            ->where('dng_lifecycle.meta.selected_semester_id', $this->semester->id)
            ->where('dng_lifecycle.meta.scan_cap', 2000)
            ->where('dng_lifecycle.meta.truncated', false)
            ->has('dng_lifecycle.meta.total_matched')
            ->has('dng_lifecycle.computed_at')
        );
});

it('does not hard-filter rows by the global finance semester', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    // A request whose lineage is an entirely different semester must STILL be
    // visible while the global semester is the active one — this is the core
    // contract of the DNG lifecycle lens.
    $otherSemester = Semester::factory()->create();
    $student = dngStudent($this->campus, $this->semester, 'DNG-OTHER-SEM');
    makeDng($student, [
        'status' => DngPaymentRequest::STATUS_FAILED,
        'semester_id' => $otherSemester->id,
    ]);

    $response = $this->actingAs($this->user)->get(
        route('finance.reporting.index', ['view' => 'dng-lifecycle'])
    )->assertOk();

    $row = dngRows($response)->firstWhere('student.student_code', 'DNG-OTHER-SEM');

    expect($row)->not->toBeNull()
        ->and($row['related_semester_state'])->toBe('single')
        ->and($row['outside_selected_semester'])->toBeTrue()
        ->and(collect($row['related_semesters'])->pluck('id'))->toContain($otherSemester->id);
});

it('resolves semester lineage across direct, charge, and multi-charge links', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $semesterB = Semester::factory()->create();

    // Multi-semester: direct semester_id = A, plus a pivot charge in semester B.
    $student = dngStudent($this->campus, $this->semester, 'DNG-MULTI');
    $request = makeDng($student, [
        'status' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
        'semester_id' => $this->semester->id,
    ]);
    $charge = dngCharge($student, $semesterB);
    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $request->id,
        'finance_charge_id' => $charge->id,
        'amount' => 1_000_000,
    ]);

    // Unknown: no semester anywhere.
    $unknownStudent = dngStudent($this->campus, $this->semester, 'DNG-UNKNOWN');
    makeDng($unknownStudent, ['status' => DngPaymentRequest::STATUS_PENDING, 'semester_id' => null]);

    $response = $this->actingAs($this->user)->get(
        route('finance.reporting.index', ['view' => 'dng-lifecycle'])
    )->assertOk();

    $rows = dngRows($response);
    $multi = $rows->firstWhere('student.student_code', 'DNG-MULTI');
    $unknown = $rows->firstWhere('student.student_code', 'DNG-UNKNOWN');

    expect($multi['related_semester_state'])->toBe('multi')
        ->and(collect($multi['related_semesters'])->pluck('id'))->toContain($this->semester->id, $semesterB->id)
        ->and($unknown['related_semester_state'])->toBe('unknown')
        ->and($unknown['related_semesters'])->toBe([]);
});

it('classifies the accepted attention buckets', function () {
    Carbon::setTestNow('2026-06-18 12:00:00');
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = dngStudent($this->campus, $this->semester, 'DNG-BUCKETS');

    $failed = makeDng($student, ['status' => DngPaymentRequest::STATUS_FAILED, 'error_message' => 'gateway down']);
    $paidUninvoiced = makeDng($student, ['status' => DngPaymentRequest::STATUS_PAID_UNINVOICED]);
    $stale = makeDng($student, [
        'status' => DngPaymentRequest::STATUS_PENDING,
        'created_at' => now()->subMinutes(90),
    ]);
    $overdue = makeDng($student, [
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'due_date' => now()->subDays(2),
    ]);

    $response = $this->actingAs($this->user)->get(
        route('finance.reporting.index', ['view' => 'dng-lifecycle'])
    )->assertOk();

    $rows = dngRows($response)->keyBy('request_id');

    expect($rows[$failed->id]['attention_buckets'])->toContain('failed_request')
        ->and($rows[$paidUninvoiced->id]['attention_buckets'])->toContain('paid_uninvoiced')
        ->and($rows[$stale->id]['attention_buckets'])->toContain('pending_stale')
        ->and($rows[$overdue->id]['attention_buckets'])->toContain('overdue_pushed');

    $summary = $response->original->getData()['page']['props']['dng_lifecycle']['summary'];
    expect($summary['failed_request_count'])->toBe(1)
        ->and($summary['paid_uninvoiced_count'])->toBe(1)
        ->and($summary['pending_stale_count'])->toBe(1)
        ->and($summary['overdue_pushed_count'])->toBe(1)
        ->and($summary['needs_attention_count'])->toBe(4);

    Carbon::setTestNow();
});

it('flags webhook problems and payment bridge state from events', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = dngStudent($this->campus, $this->semester, 'DNG-WEBHOOK');
    $request = makeDng($student, ['status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG]);

    // A clean event plus a problem event — the drilldown must focus the problem one.
    DngWebhookEvent::create([
        'dng_payment_request_id' => $request->id,
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        'payload_hash' => hash('sha256', 'wh-ok-'.$request->id),
        'payload' => ['Amount' => 1_000_000],
        'is_valid_checksum' => true,
        'processing_status' => DngWebhookEvent::STATUS_PROCESSED,
    ]);
    $problem = DngWebhookEvent::create([
        'dng_payment_request_id' => $request->id,
        'event_type' => DngWebhookEvent::EVENT_PAYMENT_INVOICED,
        'payload_hash' => hash('sha256', 'wh-bad-'.$request->id),
        'payload' => ['Amount' => 1_000_000],
        'is_valid_checksum' => false,
        'processing_status' => DngWebhookEvent::STATUS_MISMATCH,
    ]);

    $response = $this->actingAs($this->user)->get(
        route('finance.reporting.index', ['view' => 'dng-lifecycle', 'search' => 'DNG-WEBHOOK'])
    )->assertOk();

    $row = dngRows($response)->firstWhere('student.student_code', 'DNG-WEBHOOK');

    expect($row['webhook_state'])->toBe('invalid_checksum')
        ->and($row['attention_buckets'])->toContain('webhook_problem')
        ->and($row['payment_bridge'])->toBe('not_bridged')
        ->and($row['webhook_event_count'])->toBe(2)
        ->and($row['drilldowns']['has_webhooks'])->toBeTrue()
        ->and($row['drilldowns']['webhook_event_id'])->toBe($problem->id);
});

it('applies column-derived bridge and invoice filters via db pushdown plus php net', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = dngStudent($this->campus, $this->semester, 'DNG-DERIVED');
    $bridged = makeDng($student, ['status' => DngPaymentRequest::STATUS_PAID_INVOICED, 'payment_id' => null]);
    // give one request a bridged payment by attaching a payment id
    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 1_000_000,
        'method' => Payment::METHOD_CASH,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
    ]);
    $bridged->update(['payment_id' => $payment->id]);

    makeDng($student, ['status' => DngPaymentRequest::STATUS_PENDING]);

    // payment_bridge=bridged keeps only the request with a payment.
    $byBridge = $this->actingAs($this->user)->get(
        route('finance.reporting.index', ['view' => 'dng-lifecycle', 'payment_bridge' => 'bridged'])
    )->assertOk();
    expect(dngRows($byBridge)->pluck('request_id'))->toContain($bridged->id)->toHaveCount(1);

    // invoice_state=invoiced keeps the paid_invoiced request.
    $byInvoice = $this->actingAs($this->user)->get(
        route('finance.reporting.index', ['view' => 'dng-lifecycle', 'invoice_state' => 'invoiced'])
    )->assertOk();
    expect(dngRows($byInvoice)->every(fn (array $r) => $r['invoice_state'] === 'invoiced'))->toBeTrue()
        ->and(dngRows($byInvoice)->pluck('request_id'))->toContain($bridged->id);
});

it('supports dng status, attention bucket, and outside-semester filters', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $otherSemester = Semester::factory()->create();
    $student = dngStudent($this->campus, $this->semester, 'DNG-FILTER');

    $failed = makeDng($student, ['status' => DngPaymentRequest::STATUS_FAILED, 'semester_id' => $otherSemester->id]);
    makeDng($student, ['status' => DngPaymentRequest::STATUS_RECONCILED, 'semester_id' => $this->semester->id]);

    // dng_status filter keeps only the failed request.
    $byStatus = $this->actingAs($this->user)->get(
        route('finance.reporting.index', ['view' => 'dng-lifecycle', 'dng_status' => 'failed'])
    )->assertOk();
    expect(dngRows($byStatus)->pluck('request_id'))->toContain($failed->id)->toHaveCount(1);

    // outside_selected_semester=outside keeps only the other-semester request.
    $outside = $this->actingAs($this->user)->get(
        route('finance.reporting.index', ['view' => 'dng-lifecycle', 'outside_selected_semester' => 'outside'])
    )->assertOk();
    expect(dngRows($outside)->every(fn (array $r) => $r['outside_selected_semester'] === true))->toBeTrue()
        ->and(dngRows($outside)->pluck('request_id'))->toContain($failed->id);

    // related_semester filter narrows to the chosen semester lineage.
    $related = $this->actingAs($this->user)->get(
        route('finance.reporting.index', ['view' => 'dng-lifecycle', 'related_semester' => $otherSemester->id])
    )->assertOk();
    expect(dngRows($related)->pluck('request_id'))->toContain($failed->id)->toHaveCount(1);
});

it('sorts attention rows ahead of clean rows regardless of recency', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = dngStudent($this->campus, $this->semester, 'DNG-SORT');

    // Clean request created most recently; failing request created earlier.
    makeDng($student, ['status' => DngPaymentRequest::STATUS_RECONCILED, 'created_at' => now()]);
    $failed = makeDng($student, ['status' => DngPaymentRequest::STATUS_FAILED, 'created_at' => now()->subDay()]);

    $response = $this->actingAs($this->user)->get(
        route('finance.reporting.index', ['view' => 'dng-lifecycle'])
    )->assertOk();

    $rows = dngRows($response);

    expect($rows->first()['request_id'])->toBe($failed->id)
        ->and($rows->first()['needs_attention'])->toBeTrue();
});

it('scopes rows to the current campus only', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $inScope = dngStudent($this->campus, $this->semester, 'DNG-IN');
    makeDng($inScope, ['status' => DngPaymentRequest::STATUS_FAILED]);

    $otherCampus = Campus::factory()->create();
    $otherStudent = dngStudent($otherCampus, $this->semester, 'DNG-OTHER-CAMPUS');
    makeDng($otherStudent, ['status' => DngPaymentRequest::STATUS_FAILED]);

    $response = $this->actingAs($this->user)->get(
        route('finance.reporting.index', ['view' => 'dng-lifecycle'])
    )->assertOk();

    $codes = dngRows($response)->pluck('student.student_code');

    expect($codes)->toContain('DNG-IN')->not->toContain('DNG-OTHER-CAMPUS');
});

it('exposes only read-only drilldowns and no mutation or export actions', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = dngStudent($this->campus, $this->semester, 'DNG-READONLY');
    makeDng($student, ['status' => DngPaymentRequest::STATUS_FAILED]);

    $this->actingAs($this->user)
        ->get(route('finance.reporting.index', ['view' => 'dng-lifecycle']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('actions.export_enabled', false)
            ->has('dng_lifecycle.rows.data.0.drilldowns.dng_request_id')
            ->has('dng_lifecycle.rows.data.0.drilldowns.student_id')
            ->missing('dng_lifecycle.rows.data.0.drilldowns.cancel')
            ->missing('dng_lifecycle.rows.data.0.drilldowns.retry')
            ->missing('dng_lifecycle.actions')
            ->missing('dng_lifecycle.permissions')
        );
});
