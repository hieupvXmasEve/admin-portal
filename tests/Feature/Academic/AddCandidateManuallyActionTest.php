<?php

declare(strict_types=1);

use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\AddCandidateManuallyAction;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a manual dossier for an eligible student', function () {
    $source = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $target = Semester::factory()->create(['start_date' => now()]);
    $student = Student::factory()->state(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $source->id])->create(['student_id' => 'MAN'.random_int(100000, 999999)]);
    $actor = User::factory()->create();

    $definition = ScholarshipDefinition::create([
        'code' => 'MAN'.uniqid(),
        'name' => 'Manual test scholarship',
        'description' => 'test',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $dossier = app(AddCandidateManuallyAction::class)->run(
        $student->student_id,
        $source->id,
        $target->id,
        'Ngoại lệ theo yêu cầu phòng CTSV.',
        $actor->id,
    );

    expect($dossier->student_id)->toBe($student->id);
});

it('refuses manual add when the student already has an active tuition_term charge for the target semester', function () {
    $source = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $target = Semester::factory()->create(['start_date' => now()]);
    $student = Student::factory()->state(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $source->id])->create(['student_id' => 'MAN'.random_int(100000, 999999)]);
    $actor = User::factory()->create();

    FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $target->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Tuition term',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    expect(fn () => app(AddCandidateManuallyAction::class)->run(
        $student->student_id,
        $source->id,
        $target->id,
        'Ngoại lệ theo yêu cầu phòng CTSV.',
        $actor->id,
    ))->toThrow(DomainException::class);
});
