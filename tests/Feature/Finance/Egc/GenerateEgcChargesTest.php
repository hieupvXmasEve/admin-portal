<?php

declare(strict_types=1);

use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Actions\Egc\GenerateEgcChargesAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    ], $state))->create();
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
