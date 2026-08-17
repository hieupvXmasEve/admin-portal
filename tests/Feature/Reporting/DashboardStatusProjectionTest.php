<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Academic\Progression\Models\ProgramEnrollment;
use App\Modules\Academic\Progression\Support\StudentLifecycleStatusReader;
use App\Shared\Contracts\Platform\StaffDashboardStatsReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
});

function dashboardStudent(object $ctx, string $code, string $columnStatus): Student
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

function dashboardEnrollment(Student $student, string $enrollmentStatus, ?string $studyStage = null): ProgramEnrollment
{
    return ProgramEnrollment::create([
        'student_id' => $student->id,
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'intake_semester_id' => $student->intake_semester_id,
        'is_primary' => true,
        'enrollment_status' => $enrollmentStatus,
        'study_stage' => $studyStage,
        'source_type' => 'test_program_enrollment',
        'source_id' => $student->id,
        'source_snapshot' => [],
        'materialized_at' => now(),
    ]);
}

it('keeps by_status summing to total, including pending_course_opening and active (H5)', function (): void {
    $intake = dashboardStudent($this, 'DASH001', 'deferred');
    dashboardEnrollment($intake, 'active', 'intake_course');

    $pendingCourseOpening = dashboardStudent($this, 'DASH002', 'pending_course_opening');

    $activeNoStage = dashboardStudent($this, 'DASH003', 'active');
    dashboardEnrollment($activeNoStage, 'active', null);

    $fallback = dashboardStudent($this, 'DASH004', 'dropout');

    $stats = app(StaffDashboardStatsReader::class)->statsForCampus((int) $this->campus->id);
    $byStatus = $stats['students']['by_status'];
    $total = $stats['students']['total'];

    expect(array_sum($byStatus))->toBe($total)
        ->and($total)->toBe(4)
        ->and($byStatus['intake_course'])->toBe(1)
        ->and($byStatus['pending_course_opening'])->toBe(1)
        ->and($byStatus['active'])->toBe(1)
        ->and($byStatus['dropout'])->toBe(1);
});

it('agrees the SQL aggregate with the PHP StudentLifecycleStatusReader tally', function (): void {
    $students = collect(range(1, 6))->map(function (int $i) {
        $student = dashboardStudent($this, "DASHR{$i}", 'deferred');
        dashboardEnrollment($student, 'active', 'intake_course');

        return $student;
    });

    $ids = $students->map(fn (Student $s): int => (int) $s->id)->all();
    $expectedTally = collect(app(StudentLifecycleStatusReader::class)->statusesFor($ids))
        ->countBy()
        ->all();

    $stats = app(StaffDashboardStatsReader::class)->statsForCampus((int) $this->campus->id);
    $byStatus = $stats['students']['by_status'];

    foreach ($expectedTally as $status => $count) {
        expect($byStatus[$status] ?? 0)->toBe($count);
    }
});
