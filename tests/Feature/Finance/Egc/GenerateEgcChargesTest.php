<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Finance\Actions\Egc\GenerateEgcChargesAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Egc\PreviewEgcChargeGenerationQuery;
use App\Modules\Finance\Support\EgcBlockFinanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeEgcChargeStudent(array $state = []): Student
{
    $intakeSemester = Semester::factory()->create();
    $cv = CurriculumVersion::factory()->state(['semester_id' => $intakeSemester->id])->create();

    return Student::factory()->state(array_merge([
        'curriculum_version_id' => $cv->id,
        'intake_semester_id' => $intakeSemester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'gc_total_levels' => 6,
    ], $state))->create();
}

/**
 * @return int[]
 */
function voidGeneratedEgcChargeIds(Student $student, Semester $semester): array
{
    $blocks = EgcBlock::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->orderBy('block_number')
        ->get();
    $chargeIds = egcBlockCharges($blocks)->pluck('id')->map(fn (int $id): int => $id)->values()->all();

    FinanceCharge::query()
        ->whereIn('id', $chargeIds)
        ->update(['status' => FinanceCharge::STATUS_VOID, 'voided_at' => now(), 'void_reason' => 'Regression setup']);
    DB::table('invoice_lines')
        ->whereIn('charge_id', $chargeIds)
        ->update(['status' => 'void', 'voided_at' => now(), 'void_reason' => 'Regression setup']);

    return $chargeIds;
}

/** @return Collection<int, FinanceCharge> keyed by EGC block id */
function egcBlockCharges(Collection $blocks): Collection
{
    return app(EgcBlockFinanceResolver::class)->chargesFor($blocks);
}

/** @return Collection<int, EgcBlock> */
function egcBlocksWithCharges(Collection $blocks): Collection
{
    $chargesByBlock = egcBlockCharges($blocks);

    return $blocks->filter(fn (EgcBlock $block): bool => $chargesByBlock->has($block->id));
}

function makeInProgressAcademicRecord(Student $student, Semester $semester, int $level): AcademicRecord
{
    $unit = Unit::factory()->state([
        'unit_type' => 'egc',
        'level' => $level,
    ])->create();
    $courseOfferingId = DB::table('course_offerings')->insertGetId([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'syllabus_template_id' => null,
        'lecture_id' => null,
        'campus_id' => $student->campus_id,
        'section_code' => null,
        'max_capacity' => 30,
        'current_enrollment' => 0,
        'waitlist_capacity' => 10,
        'current_waitlist' => 0,
        'delivery_mode' => 'in_person',
        'schedule_days' => json_encode(['Monday']),
        'schedule_time_start' => null,
        'schedule_time_end' => null,
        'location' => null,
        'is_active' => true,
        'enrollment_status' => 'open',
        'registration_start_date' => null,
        'registration_end_date' => null,
        'special_requirements' => null,
        'notes' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return AcademicRecord::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'campus_id' => $student->campus_id,
        'program_id' => $student->program_id,
        'course_offering_id' => $courseOfferingId,
        'unit_id' => $unit->id,
        'completion_status' => 'in_progress',
        'enrollment_date' => now()->toDateString(),
    ])->create();
}

it('generates 2 egc blocks and charges for a student', function () {
    $semester = Semester::factory()->create();
    $student = makeEgcChargeStudent(['status' => 'intake_pre_uni_gc', 'gc_current_level' => 1]);

    $results = GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    expect($results['created'])->toBe(2);
    expect($results['errors'])->toBeEmpty();

    expect(EgcBlock::where('student_id', $student->id)->where('semester_id', $semester->id)->count())->toBe(2);
    expect(FinanceCharge::where('student_id', $student->id)->where('semester_id', $semester->id)->count())->toBe(2);

    $charges = FinanceCharge::where('student_id', $student->id)->get();
    foreach ($charges as $charge) {
        expect((int) $charge->amount)->toBe(15_000_000);
        expect($charge->charge_type)->toBe(FinanceCharge::TYPE_EGC_LEVEL_FEE);
    }
});

it('allows generating charge from level zero', function () {
    $semester = Semester::factory()->create();
    $student = makeEgcChargeStudent(['status' => 'intake_pre_uni_gc', 'gc_current_level' => 0, 'gc_total_levels' => 6]);

    $results = GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 1,
            'current_level' => 0,
        ]],
    ]);

    expect($results['created'])->toBe(1);
    expect(FinanceCharge::where('student_id', $student->id)->value('description'))->toContain('Level 0');
});

it('generates 1 block and creates a deferred block for next semester', function () {
    $semester = Semester::factory()->create();
    $student = makeEgcChargeStudent(['status' => 'intake_pre_uni_gc', 'gc_current_level' => 1]);

    GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 1,
            'current_level' => 1,
        ]],
    ]);

    // Should have 1 block with charge + 1 deferred block with no charge
    $blocks = EgcBlock::where('student_id', $student->id)->get();
    expect(egcBlocksWithCharges($blocks))->toHaveCount(1);
    expect($blocks->count() - egcBlocksWithCharges($blocks)->count())->toBe(1);
    expect(FinanceCharge::where('student_id', $student->id)->count())->toBe(1);
});

it('does not create a charge when current level reached total levels', function () {
    $semester = Semester::factory()->create();
    $student = makeEgcChargeStudent([
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 6,
        'gc_total_levels' => 6,
    ]);

    $results = GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 1,
            'current_level' => 6,
        ]],
    ]);

    expect($results['created'])->toBe(0);
    expect($results['skipped'])->toBe(1);
    expect(FinanceCharge::where('student_id', $student->id)->count())->toBe(0);
});

it('does not create deferred block at the total level boundary', function () {
    $semester = Semester::factory()->create();
    $student = makeEgcChargeStudent([
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 5,
        'gc_total_levels' => 6,
    ]);

    GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 1,
            'current_level' => 5,
        ]],
    ]);

    $blocks = EgcBlock::where('student_id', $student->id)->get();
    expect(egcBlocksWithCharges($blocks))->toHaveCount(1);
    expect($blocks->count() - egcBlocksWithCharges($blocks)->count())->toBe(0);
    expect(FinanceCharge::where('student_id', $student->id)->count())->toBe(1);
});

it('charges the next level when the student is still studying the current level', function () {
    $semester = Semester::factory()->create();
    $student = makeEgcChargeStudent([
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 2,
        'gc_total_levels' => 6,
    ]);

    makeInProgressAcademicRecord($student, $semester, 2);

    $results = GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 1,
            'current_level' => 2,
        ]],
    ]);

    // Studying level 2 ⇒ bill level 3 (effective start = current + 1).
    expect($results['created'])->toBe(1)
        ->and($results['errors'])->toBeEmpty()
        ->and(FinanceCharge::where('student_id', $student->id)->value('description'))
        ->toContain('Level 3');
});

it('sets is_retake = true when prior block failed with attendance >= 80%', function () {
    $semester1 = Semester::factory()->create();
    $semester2 = Semester::factory()->create();
    $student = makeEgcChargeStudent(['status' => 'intake_pre_uni_gc', 'gc_current_level' => 1]);

    // Prior semester: block failed with good attendance, no retake discount consumed
    EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'block_number' => 1,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 85.0,
        'retake_discount_id' => null,
    ])->create();

    GenerateEgcChargesAction::run([
        'semester_id' => $semester2->id,
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 1,
            'current_level' => 1,
        ]],
    ]);

    $retakeBlock = egcBlocksWithCharges(EgcBlock::where('student_id', $student->id)
        ->where('semester_id', $semester2->id)
        ->get())->first();

    expect($retakeBlock)->not->toBeNull();
    expect($retakeBlock->is_retake)->toBeTrue();

    // Finance charge is still full price
    $charge = app(EgcBlockFinanceResolver::class)->chargeFor($retakeBlock);
    expect((int) $charge->amount)->toBe(15_000_000);
});

it('sets is_retake = false when entitlement already consumed', function () {
    $semester1 = Semester::factory()->create();
    $semester2 = Semester::factory()->create();
    $student = makeEgcChargeStudent(['status' => 'intake_pre_uni_gc', 'gc_current_level' => 1]);

    // Create a real discount to satisfy the FK constraint
    $invoice = StudentInvoice::create([
        'invoice_number' => 'TEST-'.rand(1000, 9999),
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30),
    ]);
    $consumedDiscount = InvoiceDiscount::create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'scholarship',
        'amount' => 7_500_000,
        'status' => 'active',
        'description' => 'Scholarship',
        'discount_source' => 'tests',
        'reference_id' => 1,
    ]);

    // Prior block failed but retake_discount_id is already set (entitlement consumed)
    EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'block_number' => 1,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 85.0,
        'retake_discount_id' => $consumedDiscount->id,
    ])->create();

    GenerateEgcChargesAction::run([
        'semester_id' => $semester2->id,
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 1,
            'current_level' => 1,
        ]],
    ]);

    $block = egcBlocksWithCharges(EgcBlock::where('student_id', $student->id)
        ->where('semester_id', $semester2->id)
        ->get())->first();

    expect($block->is_retake)->toBeFalse();
});

it('sets is_retake = false when attendance < 80%', function () {
    $semester1 = Semester::factory()->create();
    $semester2 = Semester::factory()->create();
    $student = makeEgcChargeStudent(['status' => 'intake_pre_uni_gc', 'gc_current_level' => 1]);

    EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'block_number' => 1,
        'level_number' => 1,
        'result' => EgcBlock::RESULT_FAIL,
        'attendance_rate' => 70.0, // below 80%
        'retake_discount_id' => null,
    ])->create();

    GenerateEgcChargesAction::run([
        'semester_id' => $semester2->id,
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 1,
            'current_level' => 1,
        ]],
    ]);

    $block = egcBlocksWithCharges(EgcBlock::where('student_id', $student->id)
        ->where('semester_id', $semester2->id)
        ->get())->first();

    expect($block->is_retake)->toBeFalse();
});

it('skips duplicate blocks via unique constraint guard', function () {
    $semester = Semester::factory()->create();
    $student = makeEgcChargeStudent(['status' => 'intake_pre_uni_gc', 'gc_current_level' => 1]);

    // First run
    GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    // Second run (should skip, not duplicate)
    $results = GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    expect(EgcBlock::where('student_id', $student->id)->where('semester_id', $semester->id)->count())->toBe(2);
    expect($results['skipped'])->toBeGreaterThan(0);
});

it('reissues voided same semester EGC block charges without creating later block numbers', function () {
    $semester = Semester::factory()->create();
    $student = makeEgcChargeStudent(['status' => 'intake_pre_uni_gc', 'gc_current_level' => 1]);

    GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    $originalBlocks = EgcBlock::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->orderBy('block_number')
        ->get();
    $originalChargeIds = egcBlockCharges($originalBlocks)->pluck('id')->values()->all();

    voidGeneratedEgcChargeIds($student, $semester);

    $results = GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    $blocks = EgcBlock::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->orderBy('block_number')
        ->get();

    expect($results['created'])->toBe(2)
        ->and($blocks)->toHaveCount(2)
        ->and($blocks->pluck('id')->all())->toBe($originalBlocks->pluck('id')->all())
        ->and($blocks->pluck('block_number')->all())->toBe([1, 2])
        ->and(egcBlockCharges($blocks)->pluck('id')->intersect($originalChargeIds)->all())->toBe([]);

    expect(FinanceCharge::query()
        ->whereIn('id', egcBlockCharges($blocks)->pluck('id'))
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->count())->toBe(2);
});

it('blocks reissue when a live DNG request is linked to a voided EGC block charge', function () {
    $semester = Semester::factory()->create();
    $student = makeEgcChargeStudent(['status' => 'intake_pre_uni_gc', 'gc_current_level' => 1]);

    GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    $chargeIds = voidGeneratedEgcChargeIds($student, $semester);

    $request = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => (string) DB::table('campuses')->where('id', $student->campus_id)->value('code'),
        'student_code' => $student->student_id,
        'fee_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'description' => 'EGC live DNG guard',
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(7)->toDateString(),
        'item_id' => 'EGC-LIVE-'.$student->id.'-'.$chargeIds[0],
        'amount' => 15_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
    DngPaymentRequestCharge::create([
        'dng_payment_request_id' => $request->id,
        'finance_charge_id' => $chargeIds[0],
        'amount' => 15_000_000,
    ]);

    $results = GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    expect($results['created'])->toBe(0)
        ->and($results['skipped'])->toBe(2)
        ->and($results['errors'][0]['error'])->toBe('live_dng_review_required')
        ->and(EgcBlock::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->count())->toBe(2)
        ->and(FinanceCharge::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->count())->toBe(0);
});

it('blocks reissue when a bridged payment is linked to a voided EGC block charge', function () {
    $semester = Semester::factory()->create();
    $student = makeEgcChargeStudent(['status' => 'intake_pre_uni_gc', 'gc_current_level' => 1]);

    GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    $chargeIds = voidGeneratedEgcChargeIds($student, $semester);
    $invoiceLineId = (int) InvoiceLine::query()
        ->where('charge_id', $chargeIds[0])
        ->value('id');
    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 15_000_000,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'test',
        'external_ref' => 'EGC-PAY-'.$student->id,
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
    PaymentApplication::create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $invoiceLineId,
        'amount' => 15_000_000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    $results = GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    expect($results['created'])->toBe(0)
        ->and($results['skipped'])->toBe(2)
        ->and($results['errors'][0]['error'])->toBe('paid_dng_or_payment_review_required')
        ->and(FinanceCharge::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->count())->toBe(0);
});

it('blocks EGC generation when defer logic marks the selected semester non-billable', function () {
    $semester = Semester::factory()->create();
    $student = makeEgcChargeStudent(['status' => 'intake_pre_uni_gc', 'gc_current_level' => 1]);
    $offering = CourseOffering::factory()->state([
        'semester_id' => $semester->id,
        'campus_id' => $student->campus_id,
    ])->create();

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'defer',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
    ]);

    $results = GenerateEgcChargesAction::run([
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(30)->toDateString(),
        'students' => [[
            'student_id' => $student->id,
            'block_count' => 2,
            'current_level' => 1,
        ]],
    ]);

    expect($results['created'])->toBe(0)
        ->and($results['skipped'])->toBe(2)
        ->and($results['errors'][0]['error'])->toBe('deferred_non_billable')
        ->and(EgcBlock::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->count())->toBe(0);
});

it('filters preview by name student id email and paginates eligible rows', function () {
    $semester = Semester::factory()->create();

    makeEgcChargeStudent([
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 0,
        'gc_total_levels' => 6,
        'full_name' => 'Alice Example',
        'student_id' => 'SE000001',
        'email' => 'alice@example.com',
    ]);

    makeEgcChargeStudent([
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 1,
        'gc_total_levels' => 6,
        'full_name' => 'Bob Example',
        'student_id' => 'SE000002',
        'email' => 'bob@example.com',
    ]);

    foreach (range(3, 21) as $index) {
        makeEgcChargeStudent([
            'status' => 'intake_pre_uni_gc',
            'gc_current_level' => 1,
            'gc_total_levels' => 6,
            'student_id' => sprintf('SE%06d', $index),
            'email' => "student{$index}@example.com",
        ]);
    }

    $query = app(PreviewEgcChargeGenerationQuery::class);

    $byEmail = $query->handle($semester->id, ['search' => 'alice@example.com', 'per_page' => 20, 'page' => 1]);
    $byStudentId = $query->handle($semester->id, ['search' => 'SE000002', 'per_page' => 20, 'page' => 1]);
    $paginated = $query->handle($semester->id, ['per_page' => 20, 'page' => 2]);

    expect($byEmail['summary']['eligible_count'])->toBe(1);
    expect($byEmail['eligible_students']->items()[0]['current_level'])->toBe(0);
    expect(strtoupper((string) $byStudentId['eligible_students']->items()[0]['student_name']))->toBe('BOB EXAMPLE');
    expect($paginated['eligible_students']->total())->toBe(21);
    expect($paginated['eligible_students']->currentPage())->toBe(2);
    expect($paginated['eligible_students']->lastPage())->toBe(2);
});

it('ignores student codes from newline separated filter input', function () {
    $semester = Semester::factory()->create();

    makeEgcChargeStudent([
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 0,
        'gc_total_levels' => 6,
        'full_name' => 'Ignored Student',
        'student_id' => 'SEIGNORE1',
        'email' => 'ignored@example.com',
    ]);

    makeEgcChargeStudent([
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 1,
        'gc_total_levels' => 6,
        'full_name' => 'Kept Student',
        'student_id' => 'SEKEEP1',
        'email' => 'kept@example.com',
    ]);

    $query = app(PreviewEgcChargeGenerationQuery::class);
    $preview = $query->handle($semester->id, [
        'ignore_student_ids' => ['SEIGNORE1'],
        'per_page' => 20,
        'page' => 1,
    ]);

    expect($preview['summary']['eligible_count'])->toBe(1);
    expect($preview['eligible_students']->items()[0]['student_code'])->toBe('SEKEEP1');
});

it('reports full-scope projected totals across all eligible students, not just the current page (UI-SAFE-3)', function () {
    $semester = Semester::factory()->create();

    // Two eligible students; each charges 2 levels (current_level 1, total 6).
    makeEgcChargeStudent([
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 1,
        'gc_total_levels' => 6,
        'student_id' => 'SEPROJ001',
        'email' => 'proj1@example.com',
    ]);
    makeEgcChargeStudent([
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 1,
        'gc_total_levels' => 6,
        'student_id' => 'SEPROJ002',
        'email' => 'proj2@example.com',
    ]);

    // Page size 1 → current page shows a single student, but the projection must
    // reflect BOTH eligible students (what execute will actually generate).
    $preview = app(PreviewEgcChargeGenerationQuery::class)
        ->handle($semester->id, ['per_page' => 20, 'page' => 1]);

    expect($preview['summary']['eligible_count'])->toBe(2)
        ->and($preview['summary']['projected_block_count'])->toBe(4) // 2 students × 2 blocks
        // 4 blocks × 15,000,000 fallback fee
        ->and((float) $preview['summary']['projected_total_amount'])->toBe(60_000_000.0);
});

it('uses Unit.base_fee for the preview chargeable-level amount (FIN-06)', function () {
    $semester = Semester::factory()->create();

    Unit::create([
        'code' => 'EGC-PVL1',
        'name' => 'EGC Preview Level 1',
        'credit_points' => 0,
        'retake_fee' => 0,
        'unit_type' => 'egc',
        'level' => 1,
        'base_fee' => 10_000_000,
    ]);

    makeEgcChargeStudent([
        'status' => 'intake_pre_uni_gc',
        'gc_current_level' => 1,
        'gc_total_levels' => 6,
        'student_id' => 'SEFEE001',
        'email' => 'fee1@example.com',
    ]);

    $preview = app(PreviewEgcChargeGenerationQuery::class)
        ->handle($semester->id, ['per_page' => 20, 'page' => 1]);

    $levels = $preview['eligible_students']->items()[0]['chargeable_levels'];

    // Level 1 comes from the seeded unit base_fee; level 2 falls back to the flat fee.
    expect($levels[0]['amount'])->toBe(10_000_000)
        ->and($levels[1]['amount'])->toBe(15_000_000);
});
