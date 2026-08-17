<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Operations\ListDueItemsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('paginates due items in the database instead of loading all rows into php', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    app()->singleton('campus', fn () => $campus);

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
        ])
        ->create();

    foreach (range(1, 25) as $i) {
        DngPaymentRequest::create([
            'student_id' => $student->id,
            'campus_code' => 'TEST',
            'student_code' => $student->student_id,
            'item_id' => "ITEM-{$i}",
            'fee_type' => 'tuition',
            'description' => "Request {$i}",
            'semester_id' => $semester->id,
            'due_date' => now()->addDays($i),
            'amount' => 100000,
            'status' => 'pushed_to_dng',
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $page1 = app(ListDueItemsQuery::class)->handle($semester->id, null, null);
    $queries = DB::getQueryLog();

    // Budget covers the paginated page plus the two per-page lifecycle-status
    // lookups (primary enrollment, then the legacy fallback for students that
    // were never materialized). It stays well below a per-row lookup for 20 rows.
    expect($page1->total())->toBe(25)
        ->and($page1->count())->toBe(20)
        ->and(count($queries))->toBeLessThan(7);
});
