<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FIN-04 / FIN-07: a scholarship discount applied during charge creation must be
 * capped at the charge amount so the charge balance can never go negative.
 */
it('caps a fixed scholarship at the tuition charge amount during charge creation', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);

    // fixed_amount scholarship LARGER than the tuition charge
    $scholarship = ScholarshipDefinition::create([
        'code' => 'BIG_FIXED',
        'name' => 'Oversized Fixed Scholarship',
        'description' => 'Larger than the charge',
        'type' => 'fixed_amount',
        'amount' => 50_000_000,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $scholarship->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $charge = app(CreateFinanceChargeAction::class)->handle([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 15_000_000,
        'description' => 'Tuition',
    ]);

    $charge->refresh();

    // discount capped at charge → balance floored at 0, never negative
    expect((float) $charge->discount_amount)->toBe(15_000_000.0)
        ->and((float) $charge->balance)->toBe(0.0)
        ->and((float) $charge->balance)->toBeGreaterThanOrEqual(0.0);
});

it('applies a percentage scholarship without exceeding the charge', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);

    $scholarship = ScholarshipDefinition::create([
        'code' => 'PCT30',
        'name' => '30 percent',
        'description' => '30%',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $scholarship->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $charge = app(CreateFinanceChargeAction::class)->handle([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 20_000_000,
        'description' => 'Tuition',
    ]);

    $charge->refresh();

    expect((float) $charge->discount_amount)->toBe(6_000_000.0)
        ->and((float) $charge->balance)->toBe(14_000_000.0);
});
