<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Support\CanvasGradeSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The finalized-semester alert fires from inside the Canvas write path, which is
 * gated on an external Canvas API. These tests exercise the decision directly
 * (origin present? GPA-relevant change? semester finalized?) without standing up
 * the whole Canvas sync rig — that plumbing is orthogonal to this gate.
 */
function invokeFinalizedFlag(CanvasGradeSyncService $service, AcademicRecord $record, ?array $origin): array
{
    $ref = new ReflectionObject($service);

    foreach (['syncOrigin' => $origin, 'finalizedSemesterCache' => [], 'finalizedSemesterAlerts' => []] as $name => $value) {
        $prop = $ref->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($service, $value);
    }

    $method = $ref->getMethod('flagIfFinalizedSemesterChange');
    $method->setAccessible(true);
    $method->invoke($service, $record);

    $alerts = $ref->getProperty('finalizedSemesterAlerts');
    $alerts->setAccessible(true);

    return array_values($alerts->getValue($service));
}

beforeEach(function () {
    $this->service = app(CanvasGradeSyncService::class);
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['code' => 'FALL2025-S']);
    $this->origin = ['source' => 'scheduled-sync', 'command' => 'academic-records:sync', 'run_at' => now()->toIso8601String()];

    $this->makeRecord = function (): AcademicRecord {
        $student = Student::factory()->forCampus($this->campus)->create([
            'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential',
            'intake_semester_id' => $this->semester->id,
        ]);
        $unit = Unit::factory()->create(['credit_points' => 3.0]);
        $offering = CourseOffering::factory()->create([
            'semester_id' => $this->semester->id, 'unit_id' => $unit->id, 'campus_id' => $this->campus->id,
        ]);

        return AcademicRecord::factory()->create([
            'student_id' => $student->id, 'semester_id' => $this->semester->id, 'unit_id' => $unit->id,
            'program_id' => $student->program_id, 'campus_id' => $this->campus->id,
            'course_offering_id' => $offering->id, 'credit_points' => 3.0,
            'final_percentage' => 80.0, 'grade_status' => 'final', 'excluded_from_gpa' => false, 'is_passed' => true,
        ]);
    };

    $this->finalize = function (): void {
        $student = Student::factory()->forCampus($this->campus)->create([
            'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential',
            'intake_semester_id' => $this->semester->id,
        ]);
        GpaCalculation::query()->create([
            'student_id' => $student->id, 'semester_id' => $this->semester->id,
            'semester_gpa' => 80, 'cumulative_gpa' => 80,
            'semester_quality_points' => 240, 'cumulative_quality_points' => 240,
            'semester_credit_points' => 3, 'cumulative_credit_points' => 3,
            'semester_credit_points_earned' => 3, 'cumulative_credit_points_earned' => 3,
            'academic_standing' => 'normal', 'is_finalized' => true, 'is_current' => true,
        ]);
    };
});

it('emits an attributable event and tallies a gpa change in a finalized semester', function () {
    ($this->finalize)();
    $record = ($this->makeRecord)();
    $record->update(['final_percentage' => 60.0]);

    $alerts = invokeFinalizedFlag($this->service, $record, $this->origin);

    $event = DB::table('activity_log')->where('log_name', 'finalized_semester_grade_changed')->first();
    expect($event)->not->toBeNull();

    $props = json_decode($event->properties, true);
    expect($props['source'])->toBe('scheduled-sync')
        ->and($props['command'])->toBe('academic-records:sync')
        ->and($props['semester_id'])->toBe($this->semester->id)
        ->and($props['student_id'])->toBe($record->student_id)
        ->and($event->causer_id)->toBeNull();

    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['changed_records'])->toBe(1);
});

it('emits nothing when the changed record is not in a finalized semester', function () {
    // No finalized GpaCalculation for this semester.
    $record = ($this->makeRecord)();
    $record->update(['final_percentage' => 60.0]);

    $alerts = invokeFinalizedFlag($this->service, $record, $this->origin);

    expect($alerts)->toBe([])
        ->and(DB::table('activity_log')->where('log_name', 'finalized_semester_grade_changed')->count())->toBe(0);
});

it('emits nothing on the human recalculate path (null origin)', function () {
    ($this->finalize)();
    $record = ($this->makeRecord)();
    $record->update(['final_percentage' => 60.0]);

    $alerts = invokeFinalizedFlag($this->service, $record, null);

    expect($alerts)->toBe([])
        ->and(DB::table('activity_log')->where('log_name', 'finalized_semester_grade_changed')->count())->toBe(0);
});

it('ignores a change that touches no gpa-relevant field', function () {
    ($this->finalize)();
    $record = ($this->makeRecord)();
    $record->update(['final_letter_grade' => 'A']); // not a GPA-relevant field

    $alerts = invokeFinalizedFlag($this->service, $record, $this->origin);

    expect($alerts)->toBe([])
        ->and(DB::table('activity_log')->where('log_name', 'finalized_semester_grade_changed')->count())->toBe(0);
});
