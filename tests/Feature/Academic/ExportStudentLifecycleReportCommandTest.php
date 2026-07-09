<?php

declare(strict_types=1);

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Enums\StudentActionType;
use App\Models\AcademicProgressionEvent;
use App\Models\Campus;
use App\Models\EgcBlock;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exports student lifecycle rows for all semesters and campuses with gc data', function () {
    $user = User::factory()->create();
    $campus = Campus::factory()->create(['name' => 'Hanoi Campus']);
    $program = Program::factory()->create(['name' => 'Computer Science']);
    $intakeSemester = Semester::factory()->create([
        'code' => 'SPR2025',
        'name' => 'Spring 2025',
        'start_date' => '2025-01-15 00:00:00',
    ]);
    $laterSemester = Semester::factory()->create([
        'code' => 'FALL2025',
        'name' => 'Fall 2025',
        'start_date' => '2025-08-15 00:00:00',
    ]);
    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->create([
            'student_id' => 'SV-LC-001',
            'full_name' => 'Nguyen Van A',
            'status' => 'intake_pre_uni_gc',
            'intake' => 2025,
            'intake_semester_id' => $intakeSemester->id,
            'intake_gc' => $intakeSemester->id,
            'gc_starting_level' => 1,
            'gc_current_level' => 2,
            'user_id' => $user->id,
        ]);

    StudentActionLog::query()->create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::STUDENT_ENROLLMENT_NE->value,
        'reason' => 'NE enrollment',
        'changed_by_user_id' => $user->id,
        'from_semester_id' => $intakeSemester->id,
        'new_status' => 'intake_pre_uni_gc',
        'effective_at' => '2025-01-20 09:00:00',
    ]);

    AcademicProgressionEvent::create([
        'student_id' => $student->id,
        'event_type' => AcademicProgressionEventType::PLACEMENT_INITIALIZED->value,
        'semester_id' => $intakeSemester->id,
        'effective_at' => '2025-01-21 09:00:00',
        'trigger_source' => ProgressionTriggerSource::MANUAL_ADMIN->value,
        'created_by_user_id' => $user->id,
        'to_english_level' => 2,
    ]);

    EgcBlock::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $intakeSemester->id,
        'block_number' => 1,
        'level_number' => 2,
        'result' => EgcBlock::RESULT_PASS,
    ]);

    $outputPath = storage_path('app/reports/test-student-lifecycle-report.csv');

    $this->artisan('export:student-lifecycle-report', ['--output' => $outputPath])
        ->expectsOutputToContain('Export completed.')
        ->assertExitCode(0);

    expect(file_exists($outputPath))->toBeTrue();

    $lines = file($outputPath, FILE_IGNORE_NEW_LINES);
    expect($lines)->not->toBeEmpty();

    $headers = str_getcsv($lines[0]);
    expect($headers)->toContain(
        'status_at_semester',
        'gc_level_at_semester',
        'gc_block_1_level',
        'gc_current_level',
        'latest_action_type',
        'defer_start_semester',
    );

    $studentRows = collect(array_slice($lines, 1))
        ->map(fn (string $line): array => str_getcsv($line))
        ->filter(fn (array $row): bool => ($row[0] ?? null) === 'SV-LC-001')
        ->values();

    $eligibleSemesterCount = Semester::query()
        ->whereDate('start_date', '>=', $intakeSemester->start_date)
        ->count();

    expect($studentRows)->toHaveCount($eligibleSemesterCount)
        ->and($studentRows->pluck(6))->toContain('SPR2025', 'FALL2025');

    $springRow = $studentRows->firstWhere(fn (array $row): bool => $row[6] === 'SPR2025');
    $fallRow = $studentRows->firstWhere(fn (array $row): bool => $row[6] === 'FALL2025');

    expect($springRow)->not->toBeNull()
        ->and($springRow[9])->toBe('intake_pre_uni_gc')
        ->and($springRow[10])->toBe('intake_pre_uni_gc')
        ->and($springRow[11])->toBe('2')
        ->and($springRow[12])->toBe('2')
        ->and($springRow[13])->toBe('pass')
        ->and($springRow[16])->toBe('2')
        ->and($springRow[17])->toBe('1')
        ->and($springRow[18])->toBe(StudentActionType::STUDENT_ENROLLMENT_NE->value)
        ->and($springRow[22])->toBe('yes');

    expect($fallRow)->not->toBeNull()
        ->and($fallRow[9])->toBe('intake_pre_uni_gc')
        ->and($fallRow[11])->toBe('2');

    @unlink($outputPath);
});

it('exports headers only when there are no students', function () {
    $outputPath = storage_path('app/reports/test-empty-student-lifecycle-report.csv');

    $this->artisan('export:student-lifecycle-report', ['--output' => $outputPath])
        ->assertExitCode(0);

    $lines = file($outputPath, FILE_IGNORE_NEW_LINES);
    expect($lines)->toHaveCount(1)
        ->and(str_getcsv($lines[0]))->toContain('semester_code', 'status_at_semester', 'gc_level_at_semester');

    @unlink($outputPath);
});
