<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exports student attendance summary csv with expected columns and values', function () {
    $campus = Campus::factory()->create(['name' => 'Hanoi Campus']);
    $program = Program::factory()->create(['name' => 'Computer Science']);
    $semester = Semester::factory()->create([
        'code' => 'SPR2026',
        'name' => 'Spring 2026',
        'start_date' => now()->subMonths(2),
    ]);
    $unit = Unit::factory()->create([
        'code' => 'CS101',
        'name' => 'Intro to Programming',
        'credit_points' => 5,
    ]);
    $lecture = Lecture::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);
    $syllabus = SyllabusTemplate::factory()->create([
        'unit_id' => $unit->id,
        'min_attendance_threshold' => 80,
        'min_grade_threshold' => 60,
    ]);
    $student = Student::factory()->forCampus($campus)->create([
        'student_id' => 'SV-001',
        'full_name' => 'Nguyen Van A',
        'email' => 'a@example.com',
        'program_id' => $program->id,
        'intake' => 2026,
        'intake_semester_id' => $semester->id,
    ]);
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'lecture_id' => $lecture->id,
        'syllabus_template_id' => $syllabus->id,
        'section_code' => 'A',
    ]);
    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $courseOffering->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'program_id' => $program->id,
        'campus_id' => $campus->id,
        'attendance_percentage' => 92.50,
        'total_present' => 18,
        'total_absences' => 1,
        'total_late' => 1,
        'total_not_recorded' => 0,
        'total_class_sessions' => 20,
        'meets_attendance_requirement' => true,
        'final_percentage' => 78.00,
        'final_letter_grade' => 'B',
        'grade_points' => 3.12,
        'grade_status' => 'final',
        'completion_status' => 'completed',
        'is_passed' => true,
        'failure_reason' => null,
        'override_pass' => false,
        'override_reason' => null,
        'attempt_number' => 1,
        'is_repeat_course' => false,
    ]);

    $outputPath = storage_path('app/reports/test-student-attendance-report.csv');

    $this->artisan('export:student-attendance-report', ['--output' => $outputPath])
        ->expectsOutputToContain('Export completed.')
        ->assertExitCode(0);

    expect(file_exists($outputPath))->toBeTrue();

    $lines = file($outputPath, FILE_IGNORE_NEW_LINES);
    expect($lines)->toHaveCount(2);

    $headers = str_getcsv($lines[0]);
    expect($headers)->toContain('student_code', 'attendance_percentage', 'is_passed', 'attempt_number');

    $row = str_getcsv($lines[1]);
    expect($row[0])->toBe('SV-001')
        ->and($row[1])->toBe('Nguyen Van A')
        ->and($row[6])->toBe('SPR2026')
        ->and($row[8])->toBe('CS101')
        ->and($row[12])->toBe('Jane Doe')
        ->and($row[13])->toBe('92.50')
        ->and($row[20])->toBe('yes')
        ->and($row[27])->toBe('PASS')
        ->and($row[31])->toBe('1')
        ->and($row[32])->toBe('no');

    @unlink($outputPath);
});

it('exports an empty csv with headers when there are no academic records', function () {
    $outputPath = storage_path('app/reports/test-empty-student-attendance-report.csv');

    $this->artisan('export:student-attendance-report', ['--output' => $outputPath])
        ->assertExitCode(0);

    $lines = file($outputPath, FILE_IGNORE_NEW_LINES);
    expect($lines)->toHaveCount(1)
        ->and(str_getcsv($lines[0]))->toContain('student_code', 'unit_code', 'semester_code');

    @unlink($outputPath);
});
