<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\Academic\PendingScholarshipAdjustmentReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createDossierForStatus(string $status, ?int $studentId = null, ?int $targetSemesterId = null): ScholarshipAdjustmentDossier
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = $studentId !== null
        ? Student::find($studentId)
        : Student::factory()->forCampus($campus)->state(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $semester->id])->create();
    $maker = User::factory()->create();

    return ScholarshipAdjustmentDossier::query()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'source_semester_id' => $semester->id,
        'target_semester_id' => $targetSemesterId ?? $semester->id,
        'status' => $status,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => 'SCH-01',
        'original_type' => 'percentage',
        'original_amount' => 10,
        'created_by_user_id' => $maker->id,
    ]);
}

it('reports in-flight statuses as true and terminal statuses as false', function () {
    $semester = Semester::factory()->create();
    $inFlight = createDossierForStatus(ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED, targetSemesterId: $semester->id);
    $terminal = createDossierForStatus(ScholarshipAdjustmentDossier::STATUS_APPLIED, targetSemesterId: $semester->id);
    $noDossierStudent = Student::factory()->state(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $semester->id])->create();

    $queryCountBefore = 0;
    DB::listen(function () use (&$queryCountBefore) {
        $queryCountBefore++;
    });

    $map = app(PendingScholarshipAdjustmentReader::class)->inFlightByStudent(
        [$inFlight->student_id, $terminal->student_id, $noDossierStudent->id],
        $semester->id,
    );

    expect($queryCountBefore)->toBe(1)
        ->and($map[$inFlight->student_id])->toBeTrue()
        ->and($map[$terminal->student_id])->toBeFalse()
        ->and($map[$noDossierStudent->id])->toBeFalse();
});

it('returns an empty map for an empty student id list', function () {
    $map = app(PendingScholarshipAdjustmentReader::class)->inFlightByStudent([], 1);

    expect($map)->toBe([]);
});
