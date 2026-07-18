<?php

declare(strict_types=1);

use App\Actions\Academic\CalculateStudentSemesterGpaAction;
use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Actions\MarkCourseOfferingCompletedAction;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Shared\Contracts\Academic\CourseResultTranscriptWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('commits one progression-owned transcript entry from each finalized course result', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_status' => 'in_progress',
    ]);
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    app()->singleton('campus', fn () => $campus);

    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'credit_points' => 3,
        'credit_hours' => 3,
    ]);

    $courseResult = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'program_id' => $student->program_id,
        'campus_id' => $campus->id,
        'credit_points' => 3,
        'final_percentage' => 84,
        'final_letter_grade' => 'D',
        'is_passed' => true,
    ]);

    MarkCourseOfferingCompletedAction::run($offering);

    $entry = TranscriptEntry::query()->sole();

    expect($entry->course_result_id)->toBe($courseResult->id)
        ->and($entry->student_id)->toBe($student->id)
        ->and((float) $entry->final_percentage)->toBe(84.0)
        ->and($entry->is_passed)->toBeTrue()
        ->and($offering->fresh()->course_status)->toBe('completed');
});

it('calculates GPA from transcript entries rather than Delivery result persistence', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
    ]);

    TranscriptEntry::query()->create([
        'course_result_id' => 999999,
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'program_id' => $student->program_id,
        'campus_id' => $campus->id,
        'attempt_number' => 1,
        'final_percentage' => 82,
        'final_letter_grade' => 'D',
        'credit_points' => 3,
        'credit_points_earned' => 3,
        'quality_points' => 246,
        'is_passed' => true,
        'excluded_from_gpa' => false,
        'affects_academic_standing' => true,
        'affects_graduation_requirement' => true,
        'satisfies_prerequisite' => true,
        'finalized_at' => now(),
    ]);

    $gpa = app(CalculateStudentSemesterGpaAction::class)->execute($student, $semester->id);

    expect($gpa['gpa'])->toBe(82.0)
        ->and($gpa['quality_points'])->toBe(246.0)
        ->and($gpa['credit_points'])->toBe(3.0);
});

it('updates the existing transcript entry when a completed offering is recalculated', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_status' => 'in_progress',
    ]);
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    app()->singleton('campus', fn () => $campus);

    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'credit_points' => 3,
        'credit_hours' => 3,
    ]);

    $courseResult = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'program_id' => $student->program_id,
        'campus_id' => $campus->id,
        'credit_points' => 3,
        'final_percentage' => 80,
        'final_letter_grade' => 'D',
        'is_passed' => true,
    ]);

    MarkCourseOfferingCompletedAction::run($offering);

    $courseResult->update(['final_percentage' => 92]);
    MarkCourseOfferingCompletedAction::run($offering->fresh(), recalculate: true);

    expect(TranscriptEntry::query()->count())->toBe(1)
        ->and(TranscriptEntry::query()->sole()->course_result_id)->toBe($courseResult->id)
        ->and((float) TranscriptEntry::query()->sole()->final_percentage)->toBe(92.0);
});

it('rolls back finalized results and offering completion when transcript commit fails', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create(['credit_points' => 3.0]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_status' => 'in_progress',
    ]);
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    app()->singleton('campus', fn () => $campus);

    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'registered',
        'registration_date' => now(),
        'credit_points' => 3,
        'credit_hours' => 3,
    ]);

    $courseResult = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'program_id' => $student->program_id,
        'campus_id' => $campus->id,
        'credit_points' => 3,
        'final_percentage' => 84,
        'final_letter_grade' => 'D',
        'grade_status' => 'provisional',
        'completion_status' => 'enrolled',
        'is_passed' => true,
    ]);

    $writer = Mockery::mock(CourseResultTranscriptWriter::class);
    $writer->shouldReceive('commit')->once()->andThrow(new RuntimeException('Transcript unavailable'));
    app()->instance(CourseResultTranscriptWriter::class, $writer);

    expect(fn () => MarkCourseOfferingCompletedAction::run($offering))
        ->toThrow(RuntimeException::class, 'Transcript unavailable');

    expect($offering->fresh()->course_status)->toBe('in_progress')
        ->and($courseResult->fresh()->grade_status)->toBe('provisional')
        ->and(CourseRegistration::query()->sole()->registration_status)->toBe('registered')
        ->and(TranscriptEntry::query()->count())->toBe(0);
});
