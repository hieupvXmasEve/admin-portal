<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Delivery\Actions\BulkRegisterCourseOfferingStudentsAction;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use App\Shared\Contracts\StudentRegistry\StudentSerializedReferenceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    actingAs(User::factory()->create());
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
});

function registryStudent(object $ctx, string $code, string $columnStatus): Student
{
    return Student::factory()
        ->forCampus($ctx->campus)
        ->forProgram($ctx->program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => strtolower($code).'@example.com',
            'status' => $columnStatus,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $ctx->semester->id,
        ])
        ->create();
}

function registryPrimaryEnrollment(Student $student, string $enrollmentStatus, ?string $studyStage = null): ProgramEnrollment
{
    static $sequence = 0;
    $sequence++;

    return ProgramEnrollment::create([
        'student_id' => $student->id,
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $student->intake_semester_id,
        'is_primary' => true,
        'enrollment_status' => $enrollmentStatus,
        'study_stage' => $studyStage,
        'source_type' => 'test_program_enrollment',
        'source_id' => ($student->id * 1000) + $sequence,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);
}

function countQueriesTouching(string $needle, callable $callback): int
{
    $count = 0;
    $listener = function ($query) use (&$count, $needle): void {
        if (str_contains($query->sql, $needle)) {
            $count++;
        }
    };

    DB::listen($listener);
    $callback();

    return $count;
}

it('resolves a drifted student to the live enrollment status, not the stale column', function (): void {
    $student = registryStudent($this, 'REF001', 'deferred');
    registryPrimaryEnrollment($student, 'active', 'intake_course');

    $reference = app(StudentReferenceReader::class)->find((int) $student->id);

    expect($reference?->status)->toBe('intake_course')
        ->and($reference?->statusLabel)->toBe('Intake Course');
});

it('falls back to the legacy column when no enrollment was ever materialized', function (): void {
    $student = registryStudent($this, 'REF002', 'deferred');

    $reference = app(StudentReferenceReader::class)->find((int) $student->id);

    expect($reference?->status)->toBe('deferred');
});

it('collapses a withdrawn enrollment to dropout', function (): void {
    $student = registryStudent($this, 'REF003', 'intake_course');
    registryPrimaryEnrollment($student, 'withdrawn');

    $reference = app(StudentReferenceReader::class)->find((int) $student->id);

    expect($reference?->status)->toBe('dropout');
});

it('resolves findMany() for 10 students with exactly one program_enrollments query', function (): void {
    $students = collect(range(1, 10))->map(function (int $i) {
        $student = registryStudent($this, "REFM{$i}", 'deferred');
        registryPrimaryEnrollment($student, 'active', 'intake_course');

        return $student;
    });

    $reader = app(StudentReferenceReader::class);
    $ids = $students->map(fn (Student $s): int => (int) $s->id)->all();

    $queryCount = countQueriesTouching('program_enrollments', function () use ($reader, $ids): void {
        $references = $reader->findMany($ids);
        foreach ($references as $reference) {
            expect($reference->status)->toBe('intake_course');
        }
    });

    expect($queryCount)->toBe(1);
});

it('bulk-registers ~20 students with exactly one program_enrollments query inside the transaction', function (): void {
    $unit = Unit::factory()->create(['unit_type' => 'general']);
    $courseOffering = CourseOffering::factory()->create([
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 0,
        'max_capacity' => 50,
        'enrollment_status' => 'open',
        'course_status' => 'not_started',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);

    $codes = [];
    foreach (range(1, 20) as $i) {
        $student = registryStudent($this, "REFB{$i}", 'deferred');
        registryPrimaryEnrollment($student, 'active', 'intake_course');
        $codes[] = $student->student_id;
    }

    $queryCount = countQueriesTouching('program_enrollments', function () use ($courseOffering, $codes): void {
        BulkRegisterCourseOfferingStudentsAction::run($courseOffering, $codes, (int) $this->campus->id);
    });

    expect($queryCount)->toBe(1);
});

it('keeps findSerialized() status in agreement with find() for a drifted student (H8)', function (): void {
    $student = registryStudent($this, 'REF004', 'deferred');
    registryPrimaryEnrollment($student, 'active', 'intake_course');

    $reader = app(StudentReferenceReader::class);
    $serialized = app(StudentSerializedReferenceReader::class)
        ->findSerialized((int) $student->id);

    expect($serialized['status'])->toBe($reader->find((int) $student->id)?->status);
});
