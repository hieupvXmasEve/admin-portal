<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Finance\TuitionChargeExistenceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createTuitionCharge(int $studentId, int $semesterId, string $status = FinanceCharge::STATUS_ACTIVE): FinanceCharge
{
    return FinanceCharge::query()->create([
        'student_id' => $studentId,
        'semester_id' => $semesterId,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Tuition term',
        'effective_at' => now(),
        'status' => $status,
    ]);
}

it('reports only students with an active tuition_term charge for the semester', function () {
    $semester = Semester::factory()->create();
    $studentState = ['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $semester->id];
    $charged = Student::factory()->state($studentState)->create();
    $voided = Student::factory()->state($studentState)->create();
    $uncharged = Student::factory()->state($studentState)->create();

    createTuitionCharge($charged->id, $semester->id);
    createTuitionCharge($voided->id, $semester->id, FinanceCharge::STATUS_VOID);

    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    $map = app(TuitionChargeExistenceReader::class)->tuitionTermChargedByStudent(
        [$charged->id, $voided->id, $uncharged->id],
        $semester->id,
    );

    expect($queryCount)->toBe(1)
        ->and($map[$charged->id])->toBeTrue()
        ->and($map[$voided->id])->toBeFalse()
        ->and($map[$uncharged->id])->toBeFalse();
});

it('returns an empty map for an empty student id list', function () {
    $map = app(TuitionChargeExistenceReader::class)->tuitionTermChargedByStudent([], 1);

    expect($map)->toBe([]);
});
