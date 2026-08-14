<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Modules\Facilities\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Academic\Delivery\Actions\BulkDeleteCourseOfferingsAction;
use App\Modules\Academic\Delivery\Actions\BulkRegisterCourseOfferingStudentsAction;
use App\Modules\Academic\Delivery\Actions\ChangeCourseOfferingRoomAction;
use App\Modules\Academic\Delivery\Actions\DeleteCourseOfferingAction;
use App\Modules\Academic\Delivery\Actions\EnrollStudentInCourseOfferingAction;
use App\Modules\Academic\Delivery\Actions\MoveStudentBetweenCourseOfferingSectionsAction;
use App\Modules\Academic\Delivery\Actions\RemoveCourseOfferingRosterMemberAction;
use App\Modules\Academic\Delivery\Actions\RemoveStudentFromCourseOfferingAction;
use App\Modules\Academic\Delivery\Actions\SearchCourseOfferingStudentsAction;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingDeletionException;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingRoomChangeException;
use App\Shared\Contracts\Academic\CourseRosterReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('changes every class session room through Delivery after Facilities approves each slot', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
    ]);
    $oldRoom = Room::factory()->create(['campus_id' => $campus->id]);
    $newRoom = Room::factory()->create(['campus_id' => $campus->id]);
    $firstSession = ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'room_id' => $oldRoom->id,
        'session_date' => now()->addWeek()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '11:00',
        'status' => 'scheduled',
    ]);
    $secondSession = ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'room_id' => $oldRoom->id,
        'session_date' => now()->addWeeks(2)->toDateString(),
        'start_time' => '13:00',
        'end_time' => '15:00',
        'status' => 'scheduled',
    ]);

    ChangeCourseOfferingRoomAction::run([
        'course_offering_id' => $offering->id,
        'campus_id' => $campus->id,
        'room_id' => $newRoom->id,
        'requested_by_user_id' => 1,
    ]);

    expect($firstSession->fresh()->room_id)->toBe($newRoom->id)
        ->and($secondSession->fresh()->room_id)->toBe($newRoom->id);
});

it('discovers and bulk-registers student codes through Registry and Delivery', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create(['unit_type' => 'general']);
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'academic_status' => 'active',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'enrollment_status' => 'open',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);

    $discovered = SearchCourseOfferingStudentsAction::run($offering, [$student->student_id], $campus->id);
    $result = BulkRegisterCourseOfferingStudentsAction::run($offering, [$student->student_id], $campus->id);

    expect($discovered[0]['exists'])->toBeTrue()
        ->and($discovered[0]['is_eligible'])->toBeTrue()
        ->and($result['success_count'])->toBe(1)
        ->and(CourseRegistration::query()->where('course_offering_id', $offering->id)->where('student_id', $student->id)->exists())->toBeTrue();
});

it('rejects a room change when Facilities reports an occupied session slot', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
    ]);
    $otherOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
    ]);
    $oldRoom = Room::factory()->create(['campus_id' => $campus->id]);
    $newRoom = Room::factory()->create(['campus_id' => $campus->id]);
    $date = now()->addWeek()->toDateString();
    $session = ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'room_id' => $oldRoom->id,
        'session_date' => $date,
        'start_time' => '09:00',
        'end_time' => '11:00',
        'status' => 'scheduled',
    ]);
    ClassSession::factory()->create([
        'course_offering_id' => $otherOffering->id,
        'room_id' => $newRoom->id,
        'session_date' => $date,
        'start_time' => '10:00',
        'end_time' => '12:00',
        'status' => 'scheduled',
    ]);

    expect(fn () => ChangeCourseOfferingRoomAction::run([
        'course_offering_id' => $offering->id,
        'campus_id' => $campus->id,
        'room_id' => $newRoom->id,
        'requested_by_user_id' => 1,
    ]))->toThrow(
        CourseOfferingRoomChangeException::class,
        'Selected room is not available for the course offering schedule.',
    );

    expect($session->fresh()->room_id)->toBe($oldRoom->id);
});

it('bulk deletes empty offerings through Delivery and removes their registrations', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offerings = CourseOffering::factory()->count(2)->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'current_enrollment' => 0,
    ]);
    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offerings->first()->id,
        'semester_id' => $semester->id,
        'registration_status' => 'dropped',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);

    $registrationCount = BulkDeleteCourseOfferingsAction::run([
        'course_offering_ids' => $offerings->pluck('id')->map(fn (int $id): int => $id)->all(),
        'campus_id' => $campus->id,
    ]);

    expect($registrationCount)->toBe(1)
        ->and(CourseOffering::query()->whereKey($offerings->pluck('id'))->exists())->toBeFalse();
});

it('deletes an empty course offering through Delivery and removes its registrations', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'current_enrollment' => 0,
    ]);
    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'dropped',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);

    $registrationCount = DeleteCourseOfferingAction::run([
        'course_offering_id' => (int) $offering->id,
        'campus_id' => (int) $campus->id,
    ]);

    expect($registrationCount)->toBe(1)
        ->and(CourseOffering::query()->find($offering->id))->toBeNull()
        ->and(CourseRegistration::query()->where('course_offering_id', $offering->id)->exists())->toBeFalse();
});

it('does not delete a course offering that still has enrolled students', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'current_enrollment' => 1,
    ]);

    expect(fn (): int => DeleteCourseOfferingAction::run([
        'course_offering_id' => (int) $offering->id,
        'campus_id' => (int) $campus->id,
    ]))->toThrow(CourseOfferingDeletionException::class, 'Cannot delete course offering because it has 1 enrolled student(s).');

    expect(CourseOffering::query()->find($offering->id))->not->toBeNull();
});

it('does not delete a completed course offering', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'course_status' => 'completed',
        'current_enrollment' => 0,
    ]);

    expect(fn (): int => DeleteCourseOfferingAction::run([
        'course_offering_id' => (int) $offering->id,
        'campus_id' => (int) $campus->id,
    ]))->toThrow(CourseOfferingDeletionException::class, 'Cannot delete a completed course. You can only view or duplicate it.');

    expect(CourseOffering::query()->find($offering->id))->not->toBeNull();
});

it('does not delete a course offering with scheduled sessions', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'current_enrollment' => 0,
    ]);
    ClassSession::factory()->create(['course_offering_id' => $offering->id]);

    expect(fn (): int => DeleteCourseOfferingAction::run([
        'course_offering_id' => (int) $offering->id,
        'campus_id' => (int) $campus->id,
    ]))->toThrow(CourseOfferingDeletionException::class, 'Cannot delete course offering because it has 1 scheduled session(s).');

    expect(CourseOffering::query()->find($offering->id))->not->toBeNull();
});

it('enrols a student through Delivery and closes an offering when its final seat is taken', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create(['credit_points' => 4]);
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'max_capacity' => 1,
        'current_enrollment' => 0,
        'enrollment_status' => 'open',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);

    $registration = EnrollStudentInCourseOfferingAction::run([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'registration_status' => 'confirmed',
        'registration_method' => 'admin_override',
    ]);

    expect($registration->student_id)->toBe($student->id)
        ->and($registration->course_offering_id)->toBe($offering->id)
        ->and((float) $registration->credit_hours)->toBe(4.0)
        ->and($offering->fresh()->current_enrollment)->toBe(1)
        ->and($offering->fresh()->enrollment_status)->toBe('closed');
});

it('rejects duplicate registrations without consuming another seat', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'max_capacity' => 2,
        'current_enrollment' => 1,
        'enrollment_status' => 'open',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);
    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);

    expect(fn (): mixed => EnrollStudentInCourseOfferingAction::run([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'registration_status' => 'confirmed',
        'registration_method' => 'admin_override',
    ]))->toThrow(ValidationException::class);

    expect($offering->fresh()->current_enrollment)->toBe(1)
        ->and(CourseRegistration::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('moves a roster member between sections and rolls back when the target is full', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $source = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 1,
        'enrollment_status' => 'open',
    ]);
    $target = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'max_capacity' => 1,
        'current_enrollment' => 1,
        'enrollment_status' => 'closed',
    ]);
    $registration = CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $source->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);

    expect(fn (): mixed => MoveStudentBetweenCourseOfferingSectionsAction::run([
        'student_id' => $student->id,
        'target_course_offering_id' => $target->id,
    ]))->toThrow(ValidationException::class);

    expect($registration->fresh()->course_offering_id)->toBe($source->id)
        ->and($source->fresh()->current_enrollment)->toBe(1)
        ->and($target->fresh()->current_enrollment)->toBe(1);
});

it('counts a forced section move in the full target offering', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $source = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 1,
        'enrollment_status' => 'open',
    ]);
    $target = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'max_capacity' => 1,
        'current_enrollment' => 1,
        'enrollment_status' => 'closed',
    ]);
    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $source->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);

    MoveStudentBetweenCourseOfferingSectionsAction::run([
        'student_id' => $student->id,
        'target_course_offering_id' => $target->id,
        'force_move' => true,
    ]);

    expect($source->fresh()->current_enrollment)->toBe(0)
        ->and($target->fresh()->current_enrollment)->toBe(2);
});

it('moves dependent Delivery evidence with the roster member atomically', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $source = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 1,
        'enrollment_status' => 'open',
    ]);
    $target = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'max_capacity' => 2,
        'current_enrollment' => 0,
        'enrollment_status' => 'open',
        'registration_start_date' => now()->subDay(),
        'registration_end_date' => now()->addDay(),
    ]);
    $registration = CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $source->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);
    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $source->id,
    ]);

    MoveStudentBetweenCourseOfferingSectionsAction::run([
        'student_id' => $student->id,
        'target_course_offering_id' => $target->id,
    ]);

    expect($registration->fresh()->course_offering_id)->toBe($target->id)
        ->and($record->fresh()->course_offering_id)->toBe($target->id)
        ->and($source->fresh()->current_enrollment)->toBe(0)
        ->and($target->fresh()->current_enrollment)->toBe(1);
});

it('removes a roster member through Delivery and releases their counted seat', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'max_capacity' => 2,
        'current_enrollment' => 1,
        'enrollment_status' => 'waitlist_only',
    ]);
    $registration = CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);

    RemoveStudentFromCourseOfferingAction::run($registration->id, $this->campus->id);

    expect(CourseRegistration::query()->find($registration->id))->toBeNull()
        ->and($offering->fresh()->current_enrollment)->toBe(0)
        ->and($offering->fresh()->enrollment_status)->toBe('open');
});

it('removes the roster member attempt history through the Progression contract', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $unit = Unit::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 1,
    ]);
    $registration = CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'credit_hours' => 3,
    ]);
    $attempt = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $offering->id,
    ]);

    RemoveCourseOfferingRosterMemberAction::run([
        'course_registration_id' => $registration->id,
    ]);

    expect(CourseRegistration::query()->find($registration->id))->toBeNull()
        ->and(AcademicRecord::withTrashed()->find($attempt->id))->toBeNull();
});

it('uses the Delivery roster reader for active members in registration order', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
    ]);
    $activeStudent = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $deferredStudent = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
        'status' => 'deferred',
    ]);
    foreach ([$activeStudent, $deferredStudent] as $student) {
        CourseRegistration::query()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $semester->id,
            'registration_status' => 'confirmed',
            'registration_date' => now(),
            'credit_hours' => 3,
        ]);
    }

    expect(app(CourseRosterReader::class)->activeStudentIds($offering->id))->toBe([$activeStudent->id]);
});
