<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\SplitChargeIntoInstallmentsAction;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class)->group('audit-baseline');

it('rejects splitting a retake fee that does not support installments (P-05)', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);
    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 5_000_000,
        'description' => 'Retake fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    expect(fn () => app(SplitChargeIntoInstallmentsAction::class)->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 2_500_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 2_500_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]))->toThrow(ValidationException::class);
});

it('rejects splitting an exam resit fee that does not support installments (P-05)', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);
    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => 750_000,
        'description' => 'Exam resit fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    expect(fn () => app(SplitChargeIntoInstallmentsAction::class)->handle($charge->id, [
        ['installment_no' => 1, 'amount' => 375_000, 'due_date' => now()->addDays(30)->toDateString()],
        ['installment_no' => 2, 'amount' => 375_000, 'due_date' => now()->addDays(60)->toDateString()],
    ]))->toThrow(ValidationException::class);
});
