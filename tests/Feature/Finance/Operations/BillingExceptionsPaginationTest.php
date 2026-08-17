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
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\DTO\AcademicBillingRegistrationData;
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

    // Plan 260817-0017 phase 1: StudentReferenceReader::findMany() (called by
    // BillingExceptionCollector) now batch-resolves the live program_enrollments
    // projection for the StudentReference DTOs it builds — 2 more fixed queries,
    // not per-row, so the budget still scales O(1) with the page, not O(n).
    expect($counts['missing_charge'])->toBe(25)
        ->and($page1->total())->toBe(25)
        ->and($page1->count())->toBe(20)
        ->and(count($queries))->toBeLessThan(14);
});

it('scopes active retake lookups to the current registration chunk', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    $students = collect(['EXC-CHUNK-01', 'EXC-CHUNK-02'])
        ->map(fn (string $studentCode) => Student::factory()
            ->forCampus($campus)
            ->forProgram($program)
            ->create([
                'student_id' => $studentCode,
                'intake' => 1,
                'intake_mode' => 'sequential',
                'intake_semester_id' => $semester->id,
            ]));
    app()->singleton('campus', fn () => $campus);

    $chunks = $students
        ->values()
        ->map(fn (Student $student, int $index): array => [
            new AcademicBillingRegistrationData(
                id: 10_001 + $index,
                student_id: (int) $student->id,
                semester_id: (int) $semester->id,
                course_offering_id: 20_001 + $index,
                registration_status: 'confirmed',
                is_retake: true,
                created_at: now()->subSeconds($index)->toISOString(),
                offering_semester_id: (int) $semester->id,
                unit_name: 'Retake unit',
                unit_code: 'RET-'.$index,
                retake_source_refs: ['course-retake-registration:'.(30_001 + $index)],
            ),
        ])
        ->all();
    $gateway = Mockery::mock(AcademicFinanceChargeSourceGateway::class);
    $gateway->shouldReceive('billingExceptionRegistrationChunks')
        ->once()
        ->with($semester->id)
        ->andReturn((function () use ($chunks): Generator {
            foreach ($chunks as $chunk) {
                yield $chunk;
            }
        })());
    app()->instance(AcademicFinanceChargeSourceGateway::class, $gateway);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($semester->id);
    $retakeQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains(
            $query['query'],
            'finance_charges`.`finance_obligation_id',
        ));

    expect($counts['retake_no_charge'])->toBe(2)
        ->and($retakeQueries)->toHaveCount(2)
        ->and($retakeQueries->every(fn (array $query): bool => str_contains(
            $query['query'],
            'finance_obligations`.`source_ref',
        )))->toBeTrue();
});
