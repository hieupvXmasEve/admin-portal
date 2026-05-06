<?php

declare(strict_types=1);

use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\AcademicRecord;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\Unit;
use App\Modules\Finance\Actions\Egc\GenerateEgcChargesAction;
use App\Modules\Finance\Queries\Egc\PreviewEgcChargeGenerationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    expect(EgcBlock::where('student_id', $student->id)->whereNotNull('finance_charge_id')->count())->toBe(1);
    expect(EgcBlock::where('student_id', $student->id)->whereNull('finance_charge_id')->count())->toBe(1);
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

    expect(EgcBlock::where('student_id', $student->id)->whereNotNull('finance_charge_id')->count())->toBe(1);
    expect(EgcBlock::where('student_id', $student->id)->whereNull('finance_charge_id')->count())->toBe(0);
    expect(FinanceCharge::where('student_id', $student->id)->count())->toBe(1);
});

it('skips creating charge when student has not finished the current level', function () {
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

    expect($results['created'])->toBe(0);
    expect($results['skipped'])->toBe(1);
    expect(FinanceCharge::where('student_id', $student->id)->count())->toBe(0);
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

    $retakeBlock = EgcBlock::where('student_id', $student->id)
        ->where('semester_id', $semester2->id)
        ->whereNotNull('finance_charge_id')
        ->first();

    expect($retakeBlock)->not->toBeNull();
    expect($retakeBlock->is_retake)->toBeTrue();

    // Finance charge is still full price
    $charge = FinanceCharge::find($retakeBlock->finance_charge_id);
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

    $block = EgcBlock::where('student_id', $student->id)
        ->where('semester_id', $semester2->id)
        ->whereNotNull('finance_charge_id')
        ->first();

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

    $block = EgcBlock::where('student_id', $student->id)
        ->where('semester_id', $semester2->id)
        ->whereNotNull('finance_charge_id')
        ->first();

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
    expect($byStudentId['eligible_students']->items()[0]['student_name'])->toBe('Bob Example');
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
