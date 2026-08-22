<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['code' => 'FALL2025-C']);

    // Creates an academic_records + transcript_entries pair with the given
    // pass/earned-credit state, returning [AcademicRecord, TranscriptEntry].
    $this->makePair = function (bool $isPassed, float $earned): array {
        $student = Student::factory()->forCampus($this->campus)->create([
            'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential',
            'intake_semester_id' => $this->semester->id,
        ]);
        $unit = Unit::factory()->create(['credit_points' => 3.0]);
        $offering = CourseOffering::factory()->create([
            'semester_id' => $this->semester->id, 'unit_id' => $unit->id, 'campus_id' => $this->campus->id,
        ]);
        $record = AcademicRecord::factory()->create([
            'student_id' => $student->id, 'semester_id' => $this->semester->id, 'unit_id' => $unit->id,
            'program_id' => $student->program_id, 'campus_id' => $this->campus->id,
            'course_offering_id' => $offering->id, 'credit_points' => 3.0,
            'credit_points_earned' => $earned, 'credit_hours_earned' => $earned,
            'final_percentage' => 55.0, 'grade_status' => 'final', 'excluded_from_gpa' => false,
            'is_passed' => $isPassed,
        ]);
        $entry = TranscriptEntry::query()->create([
            'course_result_id' => $record->id, 'student_id' => $record->student_id,
            'course_offering_id' => $record->course_offering_id, 'semester_id' => $record->semester_id,
            'unit_id' => $record->unit_id, 'program_id' => $record->program_id, 'campus_id' => $record->campus_id,
            'attempt_number' => 1, 'final_percentage' => 55.0, 'final_letter_grade' => 'F',
            'credit_points' => 3.0, 'credit_points_earned' => $earned, 'quality_points' => 165.0,
            'is_passed' => $isPassed, 'excluded_from_gpa' => false, 'affects_academic_standing' => true,
            'affects_graduation_requirement' => true, 'satisfies_prerequisite' => $isPassed, 'finalized_at' => now(),
        ]);

        return [$record, $entry];
    };
});

it('requires exactly one of --dry-run or --commit', function () {
    artisan('academic:fix-failed-record-earned-credits')->assertFailed();
    artisan('academic:fix-failed-record-earned-credits', ['--dry-run' => true, '--commit' => true])->assertFailed();
});

it('reports affected rows on a dry run without writing', function () {
    [$record, $entry] = ($this->makePair)(false, 3.0);

    artisan('academic:fix-failed-record-earned-credits', ['--dry-run' => true])->assertSuccessful();

    expect((float) $record->fresh()->credit_points_earned)->toBe(3.0)
        ->and((float) $entry->fresh()->credit_points_earned)->toBe(3.0);
});

it('zeroes earned credits on failed rows in both tables on commit, leaving others untouched', function () {
    [$failedRecord, $failedEntry] = ($this->makePair)(false, 3.0);
    [$passedRecord, $passedEntry] = ($this->makePair)(true, 3.0);
    [$failedZeroRecord, $failedZeroEntry] = ($this->makePair)(false, 0.0);

    artisan('academic:fix-failed-record-earned-credits', ['--commit' => true])->assertSuccessful();

    // Failed + earned>0 → both earned columns zeroed on the record, points on the entry.
    expect((float) $failedRecord->fresh()->credit_points_earned)->toBe(0.0)
        ->and((float) $failedRecord->fresh()->credit_hours_earned)->toBe(0.0)
        ->and((float) $failedEntry->fresh()->credit_points_earned)->toBe(0.0)
        // Passing row untouched.
        ->and((float) $passedRecord->fresh()->credit_points_earned)->toBe(3.0)
        ->and((float) $passedRecord->fresh()->credit_hours_earned)->toBe(3.0)
        ->and((float) $passedEntry->fresh()->credit_points_earned)->toBe(3.0)
        // Already-zero failed row untouched (and still zero).
        ->and((float) $failedZeroRecord->fresh()->credit_points_earned)->toBe(0.0)
        ->and((float) $failedZeroEntry->fresh()->credit_points_earned)->toBe(0.0);
});
