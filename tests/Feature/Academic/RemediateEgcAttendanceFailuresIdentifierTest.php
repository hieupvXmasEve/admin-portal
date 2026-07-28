<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Actions\RemediateEgcAttendanceFailuresAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The remediation command is driven by hand, so its `--student` argument accepts
 * either a student code or a raw id. These pin that resolution, which now runs
 * through StudentReferenceReader rather than a direct Student query.
 */
beforeEach(function (): void {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);

    $this->student = Student::factory()->create([
        'campus_id' => $this->campus->id,
        'student_id' => 'EGC0001',
        'intake' => 2026,
        'intake_semester_id' => $this->semester->id,
    ]);

    $unit = Unit::factory()->create(['unit_type' => 'egc']);
    $template = SyllabusTemplate::query()->create([
        'unit_id' => $unit->id,
        'title' => 'EGC template',
        'version' => '1.0',
        'is_active' => true,
        'min_attendance_threshold' => 80.00,
        'min_grade_threshold' => 60.00,
    ]);

    $offering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $unit->id,
        'syllabus_template_id' => $template->id,
        'campus_id' => $this->campus->id,
    ]);

    // Passes on grade, fails only on attendance: exactly the candidate shape
    // the remediation targets.
    AcademicRecord::factory()->create([
        'student_id' => $this->student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'unit_id' => $unit->id,
        'campus_id' => $this->campus->id,
        'final_percentage' => 75,
        'attendance_percentage' => 50,
        'meets_attendance_requirement' => false,
        'is_passed' => false,
        'override_pass' => false,
    ]);
});

it('finds the candidate by student code', function (): void {
    $result = RemediateEgcAttendanceFailuresAction::run(dryRun: true, studentIdentifier: 'EGC0001');

    expect($result['candidates'])->toBe(1);
});

it('finds the same candidate by raw student id', function (): void {
    $result = RemediateEgcAttendanceFailuresAction::run(
        dryRun: true,
        studentIdentifier: (string) $this->student->id,
    );

    expect($result['candidates'])->toBe(1);
});

it('finds nothing for an unknown identifier', function (): void {
    $result = RemediateEgcAttendanceFailuresAction::run(dryRun: true, studentIdentifier: 'NOPE9999');

    expect($result['candidates'])->toBe(0);
});
