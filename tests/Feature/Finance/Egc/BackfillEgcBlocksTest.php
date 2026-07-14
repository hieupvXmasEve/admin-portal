<?php

declare(strict_types=1);

use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumVersion;
use App\Models\EgcBlock;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\EgcBlockFinanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeBackfillStudent(array $state = []): Student
{
    $intakeSemester = Semester::factory()->create();
    $cv = CurriculumVersion::factory()->state(['semester_id' => $intakeSemester->id])->create();

    return Student::factory()->state(array_merge([
        'curriculum_version_id' => $cv->id,
        'intake_semester_id' => $intakeSemester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'status' => 'intake_pre_uni_gc',
    ], $state))->create();
}

function makeBackfillCourseOffering(int $semesterId, int $unitId): CourseOffering
{
    $attributes = CourseOffering::factory()->state([
        'semester_id' => $semesterId,
        'unit_id' => $unitId,
    ])->raw();

    unset($attributes['drop_deadline'], $attributes['withdrawal_deadline']);

    return CourseOffering::query()->create($attributes);
}

function giveBackfillChargeCanonicalObligation(FinanceCharge $charge): FinanceCharge
{
    $obligation = FinanceObligation::query()->firstOrCreate([
        'source_system' => 'finance',
        'source_kind' => 'legacy_egc_charge',
        'source_ref' => 'legacy-egc-charge:'.$charge->id,
        'obligation_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
    ], [
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $charge->amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test:legacy_egc_charge',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);

    $charge->update(['finance_obligation_id' => $obligation->id]);

    return $charge->fresh();
}

function attachBackfillCharge(EgcBlock $block, FinanceCharge $charge): EgcBlock
{
    app(EgcBlockFinanceResolver::class)->bindExistingCharge(
        $block,
        giveBackfillChargeCanonicalObligation($charge),
    );

    return $block->fresh();
}

it('prefers actual level charge matches during backfill and rewrites fallback charge descriptions', function () {
    $semester = Semester::factory()->create();
    $student = makeBackfillStudent();
    $unit = Unit::factory()->state(['unit_type' => 'egc', 'level' => 2])->create();

    $firstOffering = makeBackfillCourseOffering($semester->id, $unit->id);
    $secondOffering = makeBackfillCourseOffering($semester->id, $unit->id);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $firstOffering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $secondOffering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now()->addMinute(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 2,
        'is_retake' => true,
    ]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-BACKFILL-001',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(7),
    ]);

    $levelTwoCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 2 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $predictedLevelThreeCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 3 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $levelTwoCharge = giveBackfillChargeCanonicalObligation($levelTwoCharge);
    $predictedLevelThreeCharge = giveBackfillChargeCanonicalObligation($predictedLevelThreeCharge);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $predictedLevelThreeCharge->id,
        'amount_snapshot' => $predictedLevelThreeCharge->amount,
        'description_snapshot' => $predictedLevelThreeCharge->description,
    ]);

    $this->artisan('egc:backfill-blocks')->assertExitCode(0);

    $blocks = EgcBlock::where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->orderBy('block_number')
        ->get();

    expect($blocks)->toHaveCount(2);
    $chargesByBlock = app(EgcBlockFinanceResolver::class)->chargesFor($blocks);
    expect($chargesByBlock->get($blocks[0]->id)?->id)->toBe($levelTwoCharge->id);
    expect($chargesByBlock->get($blocks[1]->id)?->id)->toBe($predictedLevelThreeCharge->id);

    expect($predictedLevelThreeCharge->fresh()->description)->toBe('EGC Level 2 Fee');
    expect(InvoiceLine::where('charge_id', $predictedLevelThreeCharge->id)->value('description_snapshot'))
        ->toBe('EGC Level 2 Fee');
});

it('backfills egc blocks for transitioned intake_course students with active egc charges', function () {
    $semester = Semester::factory()->create();
    $student = makeBackfillStudent(['status' => 'intake_course']);

    $unitLevel3 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 3])->create();
    $unitLevel4 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 4])->create();

    $offering1 = makeBackfillCourseOffering($semester->id, $unitLevel3->id);
    $offering2 = makeBackfillCourseOffering($semester->id, $unitLevel4->id);

    foreach ([$offering1, $offering2] as $index => $offering) {
        CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $semester->id,
            'registration_status' => 'confirmed',
            'registration_date' => now()->addMinutes($index),
            'registration_method' => 'admin_override',
            'credit_hours' => 3,
            'credit_points' => 3,
            'attempt_number' => $index + 1,
        ]);
    }

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-BACKFILL-COURSE-001',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(7),
    ]);

    $charge1 = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 3 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $charge2 = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 4 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $charge1 = giveBackfillChargeCanonicalObligation($charge1);
    $charge2 = giveBackfillChargeCanonicalObligation($charge2);

    foreach ([$charge1, $charge2] as $charge) {
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => $charge->amount,
            'description_snapshot' => $charge->description,
        ]);
    }

    $this->artisan('egc:backfill-blocks')->assertExitCode(0);

    $blocks = EgcBlock::where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->orderBy('block_number')
        ->get();

    expect($blocks)->toHaveCount(2);
    expect($blocks->pluck('level_number')->all())->toBe([3, 4]);
    expect(app(EgcBlockFinanceResolver::class)->chargesFor($blocks)->pluck('id')->values()->all())
        ->toBe([$charge1->id, $charge2->id]);
});

it('does not create extra egc block from unmatched active charge when charges exceed registrations', function () {
    $semester = Semester::factory()->create();
    $student = makeBackfillStudent(['status' => 'intake_course']);

    $unitLevel2 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 2])->create();
    $offering = makeBackfillCourseOffering($semester->id, $unitLevel2->id);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-BACKFILL-FALLBACK-001',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(7),
    ]);

    $charge1 = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 2 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $charge2 = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 3 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $charge1 = giveBackfillChargeCanonicalObligation($charge1);
    $charge2 = giveBackfillChargeCanonicalObligation($charge2);

    foreach ([$charge1, $charge2] as $charge) {
        InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => $charge->amount,
            'description_snapshot' => $charge->description,
        ]);
    }

    $this->artisan('egc:backfill-blocks')->assertExitCode(0);

    $blocks = EgcBlock::where('student_id', $student->id)
        ->where('semester_id', $semester->id)
        ->orderBy('block_number')
        ->get();

    expect($blocks)->toHaveCount(1);
    expect($blocks->pluck('level_number')->all())->toBe([2]);
    expect(app(EgcBlockFinanceResolver::class)->chargesFor($blocks)->pluck('id')->values()->all())
        ->toBe([$charge1->id]);
    expect(FinanceCharge::query()->find($charge2->id))->not->toBeNull();
});

it('backfills fall2025 egc blocks for deferred students with historical registrations', function () {
    $semester1 = Semester::factory()->create();
    $semester2 = Semester::factory()->create();
    $student = makeBackfillStudent(['status' => 'deferred']);

    $unitLevel2 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 2])->create();
    $unitLevel3 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 3])->create();

    $offering1 = makeBackfillCourseOffering($semester1->id, $unitLevel2->id);
    $offering2 = makeBackfillCourseOffering($semester1->id, $unitLevel3->id);
    $offering3 = makeBackfillCourseOffering($semester2->id, $unitLevel3->id);

    foreach ([$offering1, $offering2, $offering3] as $index => $offering) {
        CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $offering->semester_id,
            'registration_status' => 'confirmed',
            'registration_date' => now()->addMinutes($index),
            'registration_method' => 'admin_override',
            'credit_hours' => 3,
            'credit_points' => 3,
            'attempt_number' => $index + 1,
        ]);
    }

    $invoice1 = StudentInvoice::create([
        'invoice_number' => 'INV-BACKFILL-DEFERRED-001',
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'status' => 'draft',
        'due_date' => now()->addDays(7),
    ]);

    foreach (['EGC Level 2 Fee', 'EGC Level 3 Fee'] as $description) {
        $charge = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $semester1->id,
            'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'amount' => 15_000_000,
            'description' => $description,
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);
        $charge = giveBackfillChargeCanonicalObligation($charge);

        InvoiceLine::create([
            'invoice_id' => $invoice1->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => $charge->amount,
            'description_snapshot' => $charge->description,
        ]);
    }

    $this->artisan('egc:backfill-blocks')->assertExitCode(0);

    $semester1Blocks = EgcBlock::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester1->id)
        ->orderBy('block_number')
        ->get();

    $semester2Blocks = EgcBlock::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester2->id)
        ->orderBy('block_number')
        ->get();

    expect($semester1Blocks)->toHaveCount(2);
    expect($semester1Blocks->pluck('level_number')->all())->toBe([2, 3]);
    expect(app(EgcBlockFinanceResolver::class)->chargesFor($semester1Blocks))->toHaveCount(2);
    expect($semester2Blocks)->toHaveCount(1);
    expect($semester2Blocks->first()?->level_number)->toBe(3);
});

it('creates missing semester invoice and charges for existing null-charge egc blocks', function () {
    $semester1 = Semester::factory()->create();
    $semester2 = Semester::factory()->create();
    $student = makeBackfillStudent();

    $unitLevel2 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 2])->create();
    $unitLevel3 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 3])->create();
    $unitLevel4 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 4])->create();
    $unitLevel5 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 5])->create();

    foreach ([
        [$semester1, $unitLevel2],
        [$semester1, $unitLevel3],
        [$semester2, $unitLevel4],
        [$semester2, $unitLevel5],
    ] as $index => [$semester, $unit]) {
        $offering = makeBackfillCourseOffering($semester->id, $unit->id);

        CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $semester->id,
            'registration_status' => 'confirmed',
            'registration_date' => now()->addMinutes($index),
            'registration_method' => 'admin_override',
            'credit_hours' => 3,
            'credit_points' => 3,
            'attempt_number' => $index + 1,
        ]);
    }

    $invoice1 = StudentInvoice::create([
        'invoice_number' => 'INV-BACKFILL-NULL-001',
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'status' => 'draft',
        'due_date' => now()->addDays(7),
    ]);

    $charge1 = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 2 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $charge2 = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 3 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    foreach ([$charge1, $charge2] as $charge) {
        InvoiceLine::create([
            'invoice_id' => $invoice1->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => $charge->amount,
            'description_snapshot' => $charge->description,
        ]);
    }

    $semesterOneBlockOne = EgcBlock::create([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'block_number' => 1,
        'level_number' => 2,
        'result' => EgcBlock::RESULT_PENDING,
    ]);
    attachBackfillCharge($semesterOneBlockOne, $charge1);

    $semesterOneBlockTwo = EgcBlock::create([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'block_number' => 2,
        'level_number' => 3,
        'result' => EgcBlock::RESULT_PENDING,
    ]);
    attachBackfillCharge($semesterOneBlockTwo, $charge2);

    EgcBlock::create([
        'student_id' => $student->id,
        'semester_id' => $semester2->id,
        'block_number' => 1,
        'level_number' => 4,
        'result' => EgcBlock::RESULT_PENDING,
    ]);

    EgcBlock::create([
        'student_id' => $student->id,
        'semester_id' => $semester2->id,
        'block_number' => 2,
        'level_number' => 5,
        'result' => EgcBlock::RESULT_PENDING,
    ]);

    $this->artisan('egc:backfill-blocks')->assertExitCode(0);

    $semester2Blocks = EgcBlock::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester2->id)
        ->orderBy('block_number')
        ->get();

    $semester2Charges = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester2->id)
        ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
        ->orderBy('id')
        ->get();

    expect(StudentInvoice::query()->where('student_id', $student->id)->where('semester_id', $semester2->id)->exists())->toBeTrue();
    expect($semester2Charges)->toHaveCount(2);
    expect($semester2Charges->pluck('description')->all())->toBe(['EGC Level 4 Fee', 'EGC Level 5 Fee']);
    expect(app(EgcBlockFinanceResolver::class)->chargesFor($semester2Blocks))->toHaveCount(2);
});

it('creates missing charge on existing semester invoice for a null-charge egc block', function () {
    $semester1 = Semester::factory()->create();
    $semester2 = Semester::factory()->create();
    $student = makeBackfillStudent();

    $unitLevel2 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 2])->create();
    $unitLevel3 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 3])->create();
    $unitLevel4 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 4])->create();
    $unitLevel5 = Unit::factory()->state(['unit_type' => 'egc', 'level' => 5])->create();

    foreach ([
        [$semester1, $unitLevel2],
        [$semester1, $unitLevel3],
        [$semester2, $unitLevel4],
        [$semester2, $unitLevel5],
    ] as $index => [$semester, $unit]) {
        $offering = makeBackfillCourseOffering($semester->id, $unit->id);

        CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $semester->id,
            'registration_status' => 'confirmed',
            'registration_date' => now()->addMinutes($index),
            'registration_method' => 'admin_override',
            'credit_hours' => 3,
            'credit_points' => 3,
            'attempt_number' => $index + 1,
        ]);
    }

    $invoice1 = StudentInvoice::create([
        'invoice_number' => 'INV-BACKFILL-EXIST-001',
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'status' => 'draft',
        'due_date' => now()->addDays(7),
    ]);

    $charge1 = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 2 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $charge2 = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 3 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    foreach ([$charge1, $charge2] as $charge) {
        InvoiceLine::create([
            'invoice_id' => $invoice1->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => $charge->amount,
            'description_snapshot' => $charge->description,
        ]);
    }

    $invoice2 = StudentInvoice::create([
        'invoice_number' => 'INV-BACKFILL-EXIST-002',
        'student_id' => $student->id,
        'semester_id' => $semester2->id,
        'status' => 'draft',
        'due_date' => now()->addDays(7),
    ]);

    $charge3 = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester2->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => 'EGC Level 4 Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $charge1 = giveBackfillChargeCanonicalObligation($charge1);
    $charge2 = giveBackfillChargeCanonicalObligation($charge2);
    $charge3 = giveBackfillChargeCanonicalObligation($charge3);

    InvoiceLine::create([
        'invoice_id' => $invoice2->id,
        'charge_id' => $charge3->id,
        'amount_snapshot' => $charge3->amount,
        'description_snapshot' => $charge3->description,
    ]);

    $semesterOneBlockOne = EgcBlock::create([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'block_number' => 1,
        'level_number' => 2,
        'result' => EgcBlock::RESULT_PENDING,
    ]);
    attachBackfillCharge($semesterOneBlockOne, $charge1);

    $semesterOneBlockTwo = EgcBlock::create([
        'student_id' => $student->id,
        'semester_id' => $semester1->id,
        'block_number' => 2,
        'level_number' => 3,
        'result' => EgcBlock::RESULT_PENDING,
    ]);
    attachBackfillCharge($semesterOneBlockTwo, $charge2);

    $semesterTwoBlockOne = EgcBlock::create([
        'student_id' => $student->id,
        'semester_id' => $semester2->id,
        'block_number' => 1,
        'level_number' => 4,
        'result' => EgcBlock::RESULT_PENDING,
    ]);
    attachBackfillCharge($semesterTwoBlockOne, $charge3);

    EgcBlock::create([
        'student_id' => $student->id,
        'semester_id' => $semester2->id,
        'block_number' => 2,
        'level_number' => 5,
        'result' => EgcBlock::RESULT_PENDING,
    ]);

    $this->artisan('egc:backfill-blocks')->assertExitCode(0);

    $block = EgcBlock::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $semester2->id)
        ->where('block_number', 2)
        ->firstOrFail();

    $newCharge = app(EgcBlockFinanceResolver::class)->chargeFor($block);

    expect($newCharge)->not->toBeNull();
    expect($newCharge?->description)->toBe('EGC Level 5 Fee');
    expect(StudentInvoice::query()->where('student_id', $student->id)->where('semester_id', $semester2->id)->count())->toBe(1);
});
