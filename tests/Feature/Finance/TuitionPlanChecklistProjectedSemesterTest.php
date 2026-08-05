<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Modules\Finance\Queries\GetStudentFeeSummaryQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/**
 * A term with no charge yet still belongs to a semester — the billing path
 * already counts semesters from the intake to decide which term to bill, so
 * the checklist can count the same way to say WHICH semester a term lands in.
 * Without it a 0đ term reads as "Term 2 — không thu học phí" with no way to
 * tell which semester was waived.
 */
it('names the projected semester for tuition plan terms that have no charge yet', function () {
    $campus = Campus::factory()->create();

    // Far-future dates so no semester seeded by another fixture can slip
    // between these three: term N maps to the Nth semester starting at or
    // after the intake, across ALL semesters, not just this test's.
    $intake = Semester::factory()->create(['name' => 'SPRING2099', 'start_date' => '2099-01-05', 'is_archived' => false]);
    $summer = Semester::factory()->create(['name' => 'SUMMER2099', 'start_date' => '2099-05-04', 'is_archived' => false]);
    $fall = Semester::factory()->create(['name' => 'FALL2099', 'start_date' => '2099-09-01', 'is_archived' => false]);

    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 1,
        'intake_semester_id' => $intake->id,
        'intake_major' => $intake->id,
        'status' => 'intake_major',
    ]);

    $plan = TuitionPlan::create([
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $intake->id,
        'total_amount' => 90_000_000,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    // Term 2 is the summer term this programme does not charge for.
    foreach ([1 => 45_000_000, 2 => 0, 3 => 45_000_000] as $number => $amount) {
        TuitionPlanTerm::create([
            'tuition_plan_id' => $plan->id,
            'term_number' => $number,
            'amount' => $amount,
        ]);
    }

    $terms = collect(app(GetStudentFeeSummaryQuery::class)->execute((int) $student->id)['tuition_plan_checklist']['terms'])
        ->keyBy('term_number');

    expect($terms[1]['semester_name'])->toBe($intake->name)
        ->and($terms[2]['semester_name'])->toBe($summer->name)
        ->and($terms[3]['semester_name'])->toBe($fall->name);

    // The waived term is the one operators need to identify by semester.
    expect($terms[2]['payment_status'])->toBe('waived')
        ->and($terms[2]['required_amount'])->toBe(0.0);

    // Derived, not recorded — surfaces must be able to say so.
    expect($terms[2]['is_projected_semester'])->toBeTrue()
        ->and($terms[2]['semester_id'])->toBe($summer->id);
});
