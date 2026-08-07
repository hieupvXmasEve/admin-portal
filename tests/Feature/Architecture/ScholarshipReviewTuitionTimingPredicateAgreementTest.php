<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Academic\PendingScholarshipAdjustmentReader;
use App\Shared\Contracts\Finance\TuitionChargeExistenceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The timing invariant (skip-tuition-generation-pending-scholarship-review)
 * only holds if the generate-side gate (Reader A) and the candidate-side
 * exclusion (Reader B) never both say "go" for the same student — an
 * in-flight dossier and an active tuition_term charge for one semester are
 * mutually exclusive by construction, so at most one reader should ever
 * report true for a given (student, semester) pair.
 */
it('never reports both an in-flight dossier and an already-charged tuition_term for the same student and semester', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $maker = \App\Models\User::factory()->create();

    $dossierOnly = Student::factory()->forCampus($campus)->state(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $semester->id])->create();
    ScholarshipAdjustmentDossier::query()->create([
        'student_id' => $dossierOnly->id,
        'campus_id' => $campus->id,
        'source_semester_id' => $semester->id,
        'target_semester_id' => $semester->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => 'SCH-01',
        'original_type' => 'percentage',
        'original_amount' => 10,
        'created_by_user_id' => $maker->id,
    ]);

    $chargedOnly = Student::factory()->forCampus($campus)->state(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $semester->id])->create();
    FinanceCharge::query()->create([
        'student_id' => $chargedOnly->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Tuition term',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $neither = Student::factory()->forCampus($campus)->state(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $semester->id])->create();

    $studentIds = [$dossierOnly->id, $chargedOnly->id, $neither->id];

    $inFlight = app(PendingScholarshipAdjustmentReader::class)->inFlightByStudent($studentIds, $semester->id);
    $charged = app(TuitionChargeExistenceReader::class)->tuitionTermChargedByStudent($studentIds, $semester->id);

    foreach ($studentIds as $studentId) {
        expect($inFlight[$studentId] && $charged[$studentId])->toBeFalse();
    }

    expect($inFlight[$dossierOnly->id])->toBeTrue()
        ->and($charged[$dossierOnly->id])->toBeFalse()
        ->and($charged[$chargedOnly->id])->toBeTrue()
        ->and($inFlight[$chargedOnly->id])->toBeFalse()
        ->and($inFlight[$neither->id])->toBeFalse()
        ->and($charged[$neither->id])->toBeFalse();
});
