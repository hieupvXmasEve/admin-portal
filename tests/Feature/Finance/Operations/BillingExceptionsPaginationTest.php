<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Queries\Operations\GetBillingExceptionCountsQuery;
use App\Modules\Finance\Queries\Operations\ListBillingExceptionsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('paginates billing exceptions in the database without loading all rows into php', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    app()->singleton('campus', fn () => $campus);

    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id]);

    foreach (range(1, 25) as $i) {
        $student = Student::factory()
            ->forCampus($campus)
            ->forProgram($program)
            ->state([
                'student_id' => sprintf('EXC-PAGE-%02d', $i),
                'intake_semester_id' => $semester->id,
                'intake' => 1,
                'intake_mode' => 'sequential',
                'status' => 'intake_course',
            ])
            ->create();

        CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $semester->id,
            'registration_status' => 'confirmed',
            'registration_date' => now(),
            'registration_method' => 'admin_override',
            'credit_hours' => 3,
            'credit_points' => 3,
            'attempt_number' => 1,
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($semester->id);
    $page1 = app(ListBillingExceptionsQuery::class)
        ->handle($semester->id, 'missing_charge', 'http://localhost/exceptions');
    $queries = DB::getQueryLog();

    expect($counts['missing_charge'])->toBe(25)
        ->and($page1->total())->toBe(25)
        ->and($page1->count())->toBe(20)
        ->and(count($queries))->toBeLessThan(10);
});