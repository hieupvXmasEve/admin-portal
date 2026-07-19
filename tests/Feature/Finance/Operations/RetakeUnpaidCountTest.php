<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\DeferCase;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\User;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Operations\GetBillingDashboardStatsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts retake unpaid students by invoice lifecycle status not raw paid cache', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    app()->singleton('campus', fn () => $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
        ])
        ->create();

    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 2,
        'is_retake' => true,
    ]);

    StudentInvoice::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'invoice_number' => 'INV-PAID-CACHE-LIE',
        'status' => 'paid',
        'due_date' => now()->addDays(7),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);

    $stats = app(GetBillingDashboardStatsQuery::class)->handle($semester->id);

    expect($stats['retake_unpaid_count'])->toBe(0);
});

it('counts every campus defer case even when its student is not dashboard-eligible', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $futureSemester = Semester::factory()->create();
    $program = Program::factory()->create();
    $actor = User::factory()->create();

    app()->singleton('campus', fn () => $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'intake_semester_id' => $futureSemester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
        ])
        ->create();

    foreach ([DeferCase::POLICY_PRESERVE, DeferCase::POLICY_PRESERVE, DeferCase::POLICY_FORFEIT] as $feePolicy) {
        $actionLog = StudentActionLog::create([
            'student_id' => $student->id,
            'action_type' => StudentActionType::ACADEMIC_DEFER,
            'reason' => 'Dashboard defer count',
            'from_semester_id' => $semester->id,
            'changed_by_user_id' => $actor->id,
        ]);

        DeferCase::create([
            'student_action_log_id' => $actionLog->id,
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'scope_type' => DeferCase::SCOPE_FULL,
            'fee_policy' => $feePolicy,
            'effective_at' => now()->toDateString(),
            'changed_by_user_id' => $actor->id,
        ]);
    }

    $stats = app(GetBillingDashboardStatsQuery::class)->handle($semester->id);

    expect($stats)
        ->toMatchArray([
            'defer_preserve_count' => 2,
            'defer_forfeit_count' => 1,
        ]);
});
