<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\ReverseScholarshipSemesterAdjustmentAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Services\StudentFinancialImportService;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentContract;
use App\Shared\Contracts\Finance\StudentFeeSummaryReader;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/**
 * Full fixture: campus, source/target semesters, student with a 30% scholarship,
 * maker, and a checker holding approve_scholarship_adjustment at the campus.
 *
 * @return array{campus: Campus, source: Semester, target: Semester, student: Student, definition: ScholarshipDefinition, award: StudentScholarshipAward, maker: User, checker: User}
 */
function adjustmentApplyContext(): array
{
    $campus = Campus::factory()->create();
    $source = Semester::factory()->create();
    $target = Semester::factory()->create();

    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => $source->id,
        'intake_semester_id' => $source->id,
    ]);

    $definition = ScholarshipDefinition::create([
        'code' => 'ADJ30',
        'name' => '30 percent adjustable',
        'description' => '30%',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    $award = StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $maker = User::factory()->create();
    $checker = User::factory()->create();

    $role = Role::firstOrCreate(['code' => 'adjustment_checker_test'], ['name' => 'Adjustment Checker Test']);
    $permission = Permission::firstOrCreate(
        ['code' => 'approve_scholarship_adjustment'],
        ['name' => 'approve_scholarship_adjustment', 'display_name' => 'Approve adjustment', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );

    DB::table('role_permissions')->insertOrIgnore([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('campus_user_roles')->insert([
        'user_id' => $checker->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $checker->id);

    return compact('campus', 'source', 'target', 'student', 'definition', 'award', 'maker', 'checker');
}

function adjustmentApplyData(array $ctx, array $overrides = []): ScholarshipAdjustmentData
{
    $defaults = [
        'student_id' => $ctx['student']->id,
        'source_semester_id' => $ctx['source']->id,
        'target_semester_id' => $ctx['target']->id,
        'adjusted_amount' => 15.0,
        'reason' => 'Failed 2 courses in source semester',
        'academic_dossier_id' => 9001,
        'maker_user_id' => $ctx['maker']->id,
        'checker_user_id' => $ctx['checker']->id,
        'award_fingerprint' => ScholarshipAdjustmentData::fingerprint(
            (string) $ctx['award']->scholarship_code,
            (string) $ctx['definition']->type,
            (string) $ctx['definition']->amount,
        ),
    ];

    $merged = array_merge($defaults, $overrides);

    return new ScholarshipAdjustmentData(...$merged);
}

function adjustmentApplyTuitionCharge(array $ctx, float $amount = 20_000_000): FinanceCharge
{
    // A canonical obligation (with currency) is required for a valid
    // settlement position when the invoice cache is rebuilt.
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => null,
        'source_system' => 'finance-test',
        'source_kind' => 'scholarship-adjustment-test',
        'source_ref' => 'tuition:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'tuition:test',
        'pricing_snapshot' => ['catalog_rule_version' => 'tuition:test'],
        'accepted_at' => now(),
    ]);

    return app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $ctx['student']->id,
        'semester_id' => $ctx['target']->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Tuition',
    ]);
}

function adjustmentScholarshipDiscount(array $ctx): ?InvoiceDiscount
{
    return InvoiceDiscount::query()
        ->where('discount_type', 'scholarship')
        ->where('reference_id', $ctx['award']->id)
        ->latest('id')
        ->first();
}

it('accepts an adjustment whose maker and checker are the same user', function () {
    // Product decision: holding the approve permission is enough, and both user
    // ids stay on the record. Finance must not re-impose a separation rule that
    // Academic does not apply, or a legitimate approval lands in review.
    $ctx = adjustmentApplyContext();
    $result = app(ScholarshipAdjustmentContract::class)
        ->apply(adjustmentApplyData($ctx, ['maker_user_id' => $ctx['checker']->id]));

    expect($result->accepted)->toBeTrue()
        ->and($result->status)->not->toBe('maker_is_checker');
});

it('rejects a checker without the permission at the student campus', function () {
    $ctx = adjustmentApplyContext();
    $otherCampusChecker = User::factory()->create();

    $result = app(ScholarshipAdjustmentContract::class)
        ->apply(adjustmentApplyData($ctx, ['checker_user_id' => $otherCampusChecker->id]));

    expect($result->accepted)->toBeFalse()
        ->and($result->status)->toBe('checker_not_authorized');
});

it('rejects a stale award fingerprint', function () {
    $ctx = adjustmentApplyContext();

    $result = app(ScholarshipAdjustmentContract::class)
        ->apply(adjustmentApplyData($ctx, ['award_fingerprint' => 'stale-fingerprint']));

    expect($result->accepted)->toBeFalse()
        ->and($result->status)->toBe('award_fingerprint_mismatch');
});

it('rejects an adjusted value above the original', function () {
    $ctx = adjustmentApplyContext();

    $result = app(ScholarshipAdjustmentContract::class)
        ->apply(adjustmentApplyData($ctx, ['adjusted_amount' => 45.0]));

    expect($result->accepted)->toBeFalse()
        ->and($result->status)->toBe('adjusted_out_of_bounds');
});

it('rejects a duplicate active adjustment for the same student and target semester', function () {
    $ctx = adjustmentApplyContext();
    $contract = app(ScholarshipAdjustmentContract::class);

    expect($contract->apply(adjustmentApplyData($ctx))->accepted)->toBeTrue();

    $duplicate = $contract->apply(adjustmentApplyData($ctx, ['adjusted_amount' => 10.0]));

    expect($duplicate->accepted)->toBeFalse()
        ->and($duplicate->status)->toBe('duplicate_active_adjustment');
});

it('stores pending_apply when no invoice exists and generation then applies the adjusted discount', function () {
    $ctx = adjustmentApplyContext();

    $result = app(ScholarshipAdjustmentContract::class)->apply(adjustmentApplyData($ctx));

    expect($result->accepted)->toBeTrue()
        ->and($result->status)->toBe(ScholarshipSemesterAdjustment::STATUS_PENDING_APPLY);

    // Generation resolves through the adjustment: 15% of 20M, not 30%.
    $charge = adjustmentApplyTuitionCharge($ctx);
    $charge->refresh();

    expect((float) $charge->discount_amount)->toBe(3_000_000.0);
});

it('refreshes an existing unpaid invoice to the adjusted amount and marks applied', function () {
    $ctx = adjustmentApplyContext();

    $charge = adjustmentApplyTuitionCharge($ctx);
    $invoice = $charge->fresh(['invoiceLines.invoice'])->invoiceLines->first()->invoice;

    expect((float) adjustmentScholarshipDiscount($ctx)->amount)->toBe(6_000_000.0)
        ->and((float) $invoice->fresh()->cached_total_amount)->toBe(14_000_000.0);

    $result = app(ScholarshipAdjustmentContract::class)->apply(adjustmentApplyData($ctx));

    expect($result->accepted)->toBeTrue()
        ->and($result->status)->toBe(ScholarshipSemesterAdjustment::STATUS_APPLIED);

    expect((float) adjustmentScholarshipDiscount($ctx)->amount)->toBe(3_000_000.0)
        ->and((float) $invoice->fresh()->cached_total_amount)->toBe(17_000_000.0);
});

it('zeroes the ledger discount on full suspension and the invoice total changes', function () {
    $ctx = adjustmentApplyContext();

    $charge = adjustmentApplyTuitionCharge($ctx);
    $invoice = $charge->fresh(['invoiceLines.invoice'])->invoiceLines->first()->invoice;

    expect((float) $invoice->fresh()->cached_total_amount)->toBe(14_000_000.0);

    $result = app(ScholarshipAdjustmentContract::class)
        ->apply(adjustmentApplyData($ctx, ['adjusted_amount' => 0.0]));

    expect($result->accepted)->toBeTrue()
        ->and($result->status)->toBe(ScholarshipSemesterAdjustment::STATUS_APPLIED);

    expect((float) adjustmentScholarshipDiscount($ctx)->amount)->toBe(0.0)
        ->and((float) $invoice->fresh()->cached_total_amount)->toBe(20_000_000.0);
});

it('computes the adjusted discount over TOTAL tuition on a multi-charge invoice', function () {
    $ctx = adjustmentApplyContext();

    $first = adjustmentApplyTuitionCharge($ctx, 12_000_000);
    $invoice = $first->fresh(['invoiceLines.invoice'])->invoiceLines->first()->invoice;

    // Second tuition charge lands on the SAME draft invoice.
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => null,
        'source_system' => 'finance-test',
        'source_kind' => 'scholarship-adjustment-test',
        'source_ref' => 'tuition:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 8_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'tuition:test',
        'pricing_snapshot' => ['catalog_rule_version' => 'tuition:test'],
        'accepted_at' => now(),
    ]);

    app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $ctx['student']->id,
        'semester_id' => $ctx['target']->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 8_000_000,
        'description' => 'Tuition part 2',
        'invoice_id' => $invoice->id,
    ]);

    $result = app(ScholarshipAdjustmentContract::class)->apply(adjustmentApplyData($ctx));

    expect($result->status)->toBe(ScholarshipSemesterAdjustment::STATUS_APPLIED);

    // 15% of 20M total — NOT 15% of the last charge (8M).
    expect((float) adjustmentScholarshipDiscount($ctx)->amount)->toBe(3_000_000.0);
});

it('routes an invoice with payments to finance_review_required without touching the ledger', function () {
    $ctx = adjustmentApplyContext();

    $charge = adjustmentApplyTuitionCharge($ctx);
    $invoice = $charge->fresh(['invoiceLines.invoice'])->invoiceLines->first()->invoice;
    $invoice->forceFill(['cached_paid_amount' => 5_000_000])->save();

    $result = app(ScholarshipAdjustmentContract::class)->apply(adjustmentApplyData($ctx));

    expect($result->accepted)->toBeTrue()
        ->and($result->status)->toBe(ScholarshipSemesterAdjustment::STATUS_FINANCE_REVIEW_REQUIRED);

    // Approval survives; ledger untouched.
    expect((float) adjustmentScholarshipDiscount($ctx)->amount)->toBe(6_000_000.0)
        ->and(ScholarshipSemesterAdjustment::query()->count())->toBe(1);
});

it('hard-refuses a financial import row for a student with an active adjustment', function () {
    $ctx = adjustmentApplyContext();

    app(ScholarshipAdjustmentContract::class)->apply(adjustmentApplyData($ctx));

    $service = new StudentFinancialImportService;
    $method = new ReflectionMethod($service, 'processScholarshipAssignment');

    $outcome = $method->invoke($service, $ctx['student'], 'ADJ30', 5);

    expect($outcome['success'])->toBeFalse()
        ->and($outcome['message'])->toContain('active scholarship adjustment');

    // Award untouched.
    expect($ctx['award']->fresh()->scholarship_code)->toBe('ADJ30');
});

it('reverses an applied adjustment and restores the original discount', function () {
    $ctx = adjustmentApplyContext();

    $charge = adjustmentApplyTuitionCharge($ctx);
    $invoice = $charge->fresh(['invoiceLines.invoice'])->invoiceLines->first()->invoice;

    $applied = app(ScholarshipAdjustmentContract::class)->apply(adjustmentApplyData($ctx));

    expect((float) adjustmentScholarshipDiscount($ctx)->amount)->toBe(3_000_000.0);

    $reversal = app(ReverseScholarshipSemesterAdjustmentAction::class)
        ->handle((int) $applied->adjustment_id, (int) $ctx['checker']->id);

    expect($reversal->accepted)->toBeTrue()
        ->and($reversal->status)->toBe(ScholarshipSemesterAdjustment::STATUS_REVERSED);

    expect((float) adjustmentScholarshipDiscount($ctx)->amount)->toBe(6_000_000.0)
        ->and((float) $invoice->fresh()->cached_total_amount)->toBe(14_000_000.0);

    // Reversed rows leave the invariant free — a new adjustment is acceptable again.
    $again = app(ScholarshipAdjustmentContract::class)
        ->apply(adjustmentApplyData($ctx, ['adjusted_amount' => 10.0]));

    expect($again->accepted)->toBeTrue();
});

it('refuses a reversal by an actor without the campus permission', function () {
    $ctx = adjustmentApplyContext();

    adjustmentApplyTuitionCharge($ctx);
    $applied = app(ScholarshipAdjustmentContract::class)->apply(adjustmentApplyData($ctx));

    $stranger = User::factory()->create();

    $reversal = app(ReverseScholarshipSemesterAdjustmentAction::class)
        ->handle((int) $applied->adjustment_id, (int) $stranger->id);

    expect($reversal->accepted)->toBeFalse()
        ->and($reversal->status)->toBe('actor_not_authorized');
});

it('refuses to reverse when the invoice has payments', function () {
    $ctx = adjustmentApplyContext();

    $charge = adjustmentApplyTuitionCharge($ctx);
    $invoice = $charge->fresh(['invoiceLines.invoice'])->invoiceLines->first()->invoice;

    $applied = app(ScholarshipAdjustmentContract::class)->apply(adjustmentApplyData($ctx));

    $invoice->forceFill(['cached_paid_amount' => 5_000_000])->save();

    $reversal = app(ReverseScholarshipSemesterAdjustmentAction::class)
        ->handle((int) $applied->adjustment_id, (int) $ctx['checker']->id);

    expect($reversal->accepted)->toBeFalse()
        ->and($reversal->status)->toBe('finance_review_required');

    expect($applied->adjustment_id)->not->toBeNull()
        ->and(ScholarshipSemesterAdjustment::query()->find($applied->adjustment_id)->status)
        ->toBe(ScholarshipSemesterAdjustment::STATUS_APPLIED);
});

it('exposes the scholarship breakdown in the fee summary without touching settlement lines', function () {
    $ctx = adjustmentApplyContext();

    adjustmentApplyTuitionCharge($ctx);

    $summaryBefore = app(StudentFeeSummaryReader::class)
        ->execute((int) $ctx['student']->id);
    $semesterBefore = collect($summaryBefore['billing_by_semester'])
        ->firstWhere('semester_id', $ctx['target']->id);

    expect($semesterBefore['scholarship_breakdown'])->toBeNull();

    app(ScholarshipAdjustmentContract::class)->apply(adjustmentApplyData($ctx));

    $summary = app(StudentFeeSummaryReader::class)
        ->execute((int) $ctx['student']->id);
    $semesterRow = collect($summary['billing_by_semester'])
        ->firstWhere('semester_id', $ctx['target']->id);

    expect($semesterRow['scholarship_breakdown'])->not->toBeNull()
        ->and($semesterRow['scholarship_breakdown']['label'])->toBe('GIẢM TRỪ HỌC BỔNG')
        ->and($semesterRow['scholarship_breakdown']['original_amount'])->toBe(6_000_000.0)
        ->and($semesterRow['scholarship_breakdown']['effective_amount'])->toBe(3_000_000.0)
        ->and($semesterRow['scholarship_breakdown']['deduction_amount'])->toBe(3_000_000.0);

    // Settlement line collection untouched: exactly one scholarship discount
    // line, at the adjusted amount — no synthetic breakdown rows.
    $invoiceRow = collect($semesterRow['invoices'])->first();
    $discountLines = collect($invoiceRow['lines'])->filter(fn (array $line) => $line['category'] === 'Discount');

    expect($discountLines)->toHaveCount(1)
        ->and((float) $discountLines->first()['amount'])->toBe(-3_000_000.0);
});
