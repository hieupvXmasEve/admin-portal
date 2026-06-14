<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Operations\GetDueItemsSummaryQuery;
use App\Modules\Finance\Queries\Operations\ListDueItemsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not double-count due-today items in upcoming summary bucket', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    app()->singleton('campus', fn () => $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'status' => 'intake_course',
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_gc' => $semester->id,
            'intake_course' => $semester->id,
            'intake_major' => $semester->id,
        ])
        ->create();

    DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'item_id' => 'TODAY-1',
        'fee_type' => 'tuition',
        'description' => 'Due today',
        'semester_id' => $semester->id,
        'due_date' => now()->startOfDay(),
        'amount' => 1000000,
        'status' => 'pushed_to_dng',
    ]);

    DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'item_id' => 'TOMORROW-1',
        'fee_type' => 'tuition',
        'description' => 'Due tomorrow',
        'semester_id' => $semester->id,
        'due_date' => now()->startOfDay()->addDay(),
        'amount' => 2000000,
        'status' => 'pushed_to_dng',
    ]);

    $summary = app(GetDueItemsSummaryQuery::class)->handle($semester->id);
    $upcomingList = app(ListDueItemsQuery::class)->handle($semester->id, 'upcoming', null);
    $dueTodayList = app(ListDueItemsQuery::class)->handle($semester->id, 'due_today', null);
    $overdueList = app(ListDueItemsQuery::class)->handle($semester->id, 'overdue', null);

    expect($summary['due_today_count'])->toBe(1)
        ->and($summary['upcoming_count'])->toBe(1)
        ->and($upcomingList->total())->toBe(1)
        ->and($dueTodayList->total())->toBe(1)
        ->and($summary['upcoming_count'] + $summary['due_today_count'] + $summary['overdue_count'])
        ->toBe($upcomingList->total() + $dueTodayList->total() + $overdueList->total());
});