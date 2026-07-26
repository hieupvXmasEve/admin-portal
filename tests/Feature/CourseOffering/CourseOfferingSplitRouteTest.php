<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Shared\Contracts\Identity\AvailableLecturerReader;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('preserves split guard redirects for offerings that are already sections', function (): void {
    [$campus, $user] = splitRouteAccess();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'section_code' => 'A',
        'current_enrollment' => 1,
    ]);

    actingAs($user)
        ->withSession(['current_campus_id' => $campus->id])
        ->from('/course-offerings/'.$offering->id)
        ->get(route('course-offerings.split.show', $offering))
        ->assertRedirect('/course-offerings/'.$offering->id)
        ->assertSessionHas('error', 'This course offering is already a section and cannot be split further.');
});

it('renders the split form with active registrations and Workforce-provided lecturers', function (): void {
    [$campus, $user] = splitRouteAccess();
    $semester = Semester::factory()->create();
    $unit = Unit::factory()->create();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'section_code' => null,
        'current_enrollment' => 1,
    ]);
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    CourseRegistration::query()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $semester->id,
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
    app()->singleton(AvailableLecturerReader::class, fn () => new class implements AvailableLecturerReader
    {
        public function all(): array
        {
            return [[
                'id' => 88,
                'first_name' => 'Linh',
                'last_name' => 'Nguyen',
                'email' => 'linh@example.test',
                'academic_rank' => 'Professor',
            ]];
        }
    });

    actingAs($user)
        ->withSession(['current_campus_id' => $campus->id])
        ->get(route('course-offerings.split.show', $offering))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('CourseOfferings/Split')
            ->where('courseOffering.id', $offering->id)
            ->where('enrolledStudents.0.student_id', $student->student_id)
            ->where('lectures.0.email', 'linh@example.test'));
});

it('maps split validation failures back to the split page with the legacy error', function (): void {
    [$campus, $user] = splitRouteAccess();
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'section_code' => null,
        'current_enrollment' => 3,
    ]);
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);

    actingAs($user)
        ->withSession(['current_campus_id' => $campus->id, '_token' => 'test-token'])
        ->from('/course-offerings/'.$offering->id.'/split')
        ->post(route('course-offerings.split.perform', $offering), [
            '_token' => 'test-token',
            'number_of_sections' => 2,
            'assignment_mode' => 'custom',
            'sections' => [
                ['section_code' => 'A', 'max_capacity' => 10, 'lecture_id' => null, 'location' => null, 'student_ids' => [$student->id]],
                ['section_code' => 'B', 'max_capacity' => 10, 'lecture_id' => null, 'location' => null, 'student_ids' => [$student->id]],
            ],
        ])
        ->assertRedirect('/course-offerings/'.$offering->id.'/split')
        ->assertSessionHas('error', 'All enrolled students must be assigned to sections.');
});

/** @return array{Campus, User} */
function splitRouteAccess(): array
{
    $campus = Campus::factory()->create();
    $user = User::factory()->create();

    app()->singleton('campus', fn () => $campus);
    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, function () {
        $permissions = Mockery::mock(CampusPermissionReader::class);
        $permissions->shouldReceive('permissionCodesForUserId')->andReturn(['edit_course_offering']);

        return $permissions;
    });

    return [$campus, $user];
}
