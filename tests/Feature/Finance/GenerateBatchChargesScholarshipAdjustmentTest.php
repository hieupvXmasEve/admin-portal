<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{student: Student, semester: Semester, definition: ScholarshipDefinition, award: StudentScholarshipAward}
 */
function seedBatchAdjustmentScenario(): array
{
    $campus = Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->create([
        'code' => 'FALL2025',
        'name' => 'Fall2025',
        'start_date' => '2025-09-01 00:00:00',
        'end_date' => '2025-12-31 00:00:00',
        'is_active' => true,
        'is_archived' => false,
    ]);
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

    session(['current_campus_id' => $campus->id]);
    app()->instance('campus', $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => 'AUS20001',
            'status' => 'intake_course',
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_gc' => $semester->id,
            'intake_course' => (string) $semester->id,
            'intake_major' => $semester->id,
            'gc_to_course_transition_semester' => (string) $semester->id,
        ])
        ->create();

    $plan = TuitionPlan::create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $semester->id,
        'total_amount' => 180000000,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 1,
        'amount' => 45000000,
        'due_date' => '2025-12-13',
    ]);

    $definition = ScholarshipDefinition::create([
        'code' => 'ASIA_PIONEER',
        'name' => 'Asia Pioneer',
        'description' => 'Scholarship test',
        'type' => 'percentage',
        'amount' => 40,
        'valid_from' => now()->subDay()->toDateString(),
        'valid_until' => now()->addDay()->toDateString(),
        'is_active' => true,
    ]);

    $award = StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    return compact('student', 'semester', 'definition', 'award');
}

function seedBatchAdjustment(array $ctx, float $adjusted): ScholarshipSemesterAdjustment
{
    $user = User::factory()->create();
    $approver = User::factory()->create();

    return ScholarshipSemesterAdjustment::create([
        'student_id' => $ctx['student']->id,
        'campus_id' => $ctx['student']->campus_id,
        'student_scholarship_award_id' => $ctx['award']->id,
        'scholarship_code' => $ctx['award']->scholarship_code,
        'source_semester_id' => $ctx['semester']->id,
        'target_semester_id' => $ctx['semester']->id,
        'original_type' => $ctx['definition']->type,
        'original_amount' => $ctx['definition']->amount,
        'award_fingerprint' => ScholarshipAdjustmentData::fingerprint(
            (string) $ctx['award']->scholarship_code,
            (string) $ctx['definition']->type,
            (string) $ctx['definition']->amount,
        ),
        'adjusted_amount' => $adjusted,
        'status' => ScholarshipSemesterAdjustment::STATUS_PENDING_APPLY,
        'reason' => 'Batch adjustment test',
        'academic_dossier_id' => 9001,
        'created_by_user_id' => $user->id,
        'approved_by_user_id' => $approver->id,
    ]);
}

function runBatchForScenario(array $ctx): array
{
    return GenerateBatchChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$ctx['student']->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);
}

function batchScholarshipDiscount(array $ctx): ?InvoiceDiscount
{
    return InvoiceDiscount::query()
        ->where('discount_type', 'scholarship')
        ->where('reference_id', $ctx['award']->id)
        ->latest('id')
        ->first();
}

it('honors a pending adjustment at batch generation and transitions it to applied', function () {
    $ctx = seedBatchAdjustmentScenario();
    $adjustment = seedBatchAdjustment($ctx, 20);

    runBatchForScenario($ctx);

    // 20% of 45M — not the award's 40%.
    expect((float) batchScholarshipDiscount($ctx)->amount)->toBe(9_000_000.0)
        ->and($adjustment->fresh()->status)->toBe(ScholarshipSemesterAdjustment::STATUS_APPLIED)
        ->and($adjustment->fresh()->applied_at)->not->toBeNull();
});

it('refreshes the stored discount when an adjustment arrives after the first batch run', function () {
    $ctx = seedBatchAdjustmentScenario();

    runBatchForScenario($ctx);

    expect((float) batchScholarshipDiscount($ctx)->amount)->toBe(18_000_000.0);

    $adjustment = seedBatchAdjustment($ctx, 20);

    runBatchForScenario($ctx);

    expect((float) batchScholarshipDiscount($ctx)->amount)->toBe(9_000_000.0)
        ->and($adjustment->fresh()->status)->toBe(ScholarshipSemesterAdjustment::STATUS_APPLIED);
});

it('zeroes the batch-generated discount on full suspension', function () {
    $ctx = seedBatchAdjustmentScenario();

    runBatchForScenario($ctx);

    expect((float) batchScholarshipDiscount($ctx)->amount)->toBe(18_000_000.0);

    seedBatchAdjustment($ctx, 0);

    runBatchForScenario($ctx);

    expect((float) batchScholarshipDiscount($ctx)->amount)->toBe(0.0);
});
