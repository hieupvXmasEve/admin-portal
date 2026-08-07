<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedScholarshipReviewSkipStudent(): array
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
            'student_id' => 'AUS20002',
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

    return compact('student', 'semester', 'campus');
}

function createInFlightDossier(array $ctx): ScholarshipAdjustmentDossier
{
    $maker = User::factory()->create();

    return ScholarshipAdjustmentDossier::query()->create([
        'student_id' => $ctx['student']->id,
        'campus_id' => $ctx['campus']->id,
        'source_semester_id' => $ctx['semester']->id,
        'target_semester_id' => $ctx['semester']->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => 'SCH-01',
        'original_type' => 'percentage',
        'original_amount' => 10,
        'created_by_user_id' => $maker->id,
    ]);
}

it('skips tuition_term generation for a student with an in-flight scholarship dossier', function () {
    $ctx = seedScholarshipReviewSkipStudent();
    createInFlightDossier($ctx);

    $stats = GenerateBatchChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$ctx['student']->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $tuitionCharge = FinanceCharge::query()
        ->where('student_id', $ctx['student']->id)
        ->where('semester_id', $ctx['semester']->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->first();

    expect($tuitionCharge)->toBeNull()
        ->and($stats['skipped_count'])->toBe(1)
        ->and($stats['deferred_scholarship_review'])->toBe([$ctx['student']->id]);
});

it('still generates tuition for a student with no in-flight dossier (regression)', function () {
    $ctx = seedScholarshipReviewSkipStudent();

    $stats = GenerateBatchChargesAction::run([
        'semester_id' => $ctx['semester']->id,
        'scope_type' => 'upload_list',
        'uploaded_student_ids' => [$ctx['student']->student_id],
        'charge_types' => [FinanceCharge::TYPE_TUITION_TERM],
        'skip_if_issued_or_paid' => true,
        'only_update_draft' => true,
        'merge_invoice' => true,
    ]);

    $tuitionCharge = FinanceCharge::query()
        ->where('student_id', $ctx['student']->id)
        ->where('semester_id', $ctx['semester']->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->first();

    expect($tuitionCharge)->not->toBeNull()
        ->and($stats['deferred_scholarship_review'])->toBe([]);
});
