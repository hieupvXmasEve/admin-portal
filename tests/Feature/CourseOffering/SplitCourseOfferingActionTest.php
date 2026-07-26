<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Actions\SplitCourseOfferingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('splits an offering atomically and transfers each active registration to its assigned section', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $unit = Unit::factory()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'section_code' => null,
        'current_enrollment' => 2,
        'max_capacity' => 30,
    ]);
    $firstStudent = splitStudent($campus, $semester);
    $secondStudent = splitStudent($campus, $semester);

    $firstRegistration = splitRegistration($firstStudent, $offering);
    $secondRegistration = splitRegistration($secondStudent, $offering);

    $sectionCount = SplitCourseOfferingAction::run([
        'course_offering_id' => $offering->id,
        'campus_id' => $campus->id,
        'sections' => [
            ['section_code' => 'A', 'max_capacity' => 15, 'student_ids' => [$firstStudent->id]],
            ['section_code' => 'B', 'max_capacity' => 15, 'student_ids' => [$secondStudent->id]],
        ],
    ]);

    $firstRegistration->refresh();
    $secondRegistration->refresh();

    expect($sectionCount)->toBe(2)
        ->and(CourseOffering::query()->find($offering->id))->toBeNull()
        ->and($firstRegistration->course_offering_id)->not->toBe($offering->id)
        ->and($secondRegistration->course_offering_id)->not->toBe($offering->id)
        ->and($firstRegistration->course_offering_id)->not->toBe($secondRegistration->course_offering_id)
        ->and(CourseOffering::query()->whereIn('id', [$firstRegistration->course_offering_id, $secondRegistration->course_offering_id])->count())->toBe(2);
});

function splitRegistration(Student $student, CourseOffering $offering): CourseRegistration
{
    return CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $offering->semester_id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
        'is_retake' => false,
        'retake_fee' => 0,
        'is_retake_paid' => 'no',
    ]);
}

function splitStudent(Campus $campus, Semester $semester): Student
{
    return Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
}
