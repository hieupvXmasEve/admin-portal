<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CampusUserRole;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Delivery\Actions\AssignInstructorAction;
use App\Modules\Academic\Delivery\Exceptions\InstructorAssignmentException;
use App\Modules\Academic\Delivery\Support\LecturerCourseService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('assigns an eligible lecturer to an offering in their campus', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $lecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'employment_status' => 'active',
        'is_active' => true,
        'is_available_for_assignment' => true,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'lecture_id' => null,
    ]);

    AssignInstructorAction::run([
        'course_offering_id' => $offering->id,
        'lecture_id' => $lecturer->id,
    ]);

    expect($offering->fresh()->lecture_id)->toBe($lecturer->id);
});

it('preserves lecturer course visibility after assignment', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $lecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'employment_status' => 'active',
        'is_active' => true,
        'is_available_for_assignment' => true,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'lecture_id' => null,
    ]);
    ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'lecture_id' => $lecturer->id,
    ]);

    AssignInstructorAction::run([
        'course_offering_id' => $offering->id,
        'lecture_id' => $lecturer->id,
    ]);

    $visibleOfferingIds = app(LecturerCourseService::class)
        ->getCourseOfferings($lecturer)
        ->pluck('id');

    expect($visibleOfferingIds)->toContain($offering->id);
});

it('rejects an ineligible lecturer without changing the offering assignment', function (): void {
    $campus = Campus::factory()->create();
    $lecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'employment_status' => 'suspended',
        'is_active' => true,
        'is_available_for_assignment' => true,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'lecture_id' => null,
    ]);

    expect(fn (): mixed => AssignInstructorAction::run([
        'course_offering_id' => $offering->id,
        'lecture_id' => $lecturer->id,
    ]))->toThrow(InstructorAssignmentException::class);

    expect($offering->fresh()->lecture_id)->toBeNull();
});

it('rejects a lecturer whose Workforce expertise does not cover the offering unit', function (): void {
    $campus = Campus::factory()->create();
    $lecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'employment_status' => 'active',
        'is_active' => true,
        'is_available_for_assignment' => true,
        'expertise_areas' => ['Data Science'],
    ]);
    $unit = Unit::factory()->create([
        'code' => 'LAW101',
        'name' => 'Legal Foundations',
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'unit_id' => $unit->id,
        'lecture_id' => null,
    ]);

    expect(fn (): mixed => AssignInstructorAction::run([
        'course_offering_id' => $offering->id,
        'lecture_id' => $lecturer->id,
    ]))->toThrow(InstructorAssignmentException::class);

    expect($offering->fresh()->lecture_id)->toBeNull();
});

it('rejects an assignment that exceeds the lecturer workload limit', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $lecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'max_teaching_hours_per_week' => 1,
    ]);
    CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'lecture_id' => $lecturer->id,
        'schedule_days' => ['Monday'],
        'schedule_time_start' => '09:00:00',
        'schedule_time_end' => '10:00:00',
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'lecture_id' => null,
        'schedule_days' => ['Tuesday'],
        'schedule_time_start' => '09:00:00',
        'schedule_time_end' => '10:00:00',
    ]);

    expect(fn (): mixed => AssignInstructorAction::run([
        'course_offering_id' => $offering->id,
        'lecture_id' => $lecturer->id,
    ]))->toThrow(InstructorAssignmentException::class);

    expect($offering->fresh()->lecture_id)->toBeNull();
});

it('rejects an assignment that conflicts with the lecturer timetable', function (): void {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $lecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'max_teaching_hours_per_week' => 80,
    ]);
    CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'lecture_id' => $lecturer->id,
        'schedule_days' => ['Monday'],
        'schedule_time_start' => '09:00:00',
        'schedule_time_end' => '11:00:00',
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'lecture_id' => null,
        'schedule_days' => ['Monday'],
        'schedule_time_start' => '10:00:00',
        'schedule_time_end' => '12:00:00',
    ]);

    expect(fn (): mixed => AssignInstructorAction::run([
        'course_offering_id' => $offering->id,
        'lecture_id' => $lecturer->id,
    ]))->toThrow(InstructorAssignmentException::class);

    expect($offering->fresh()->lecture_id)->toBeNull();
});

it('keeps the staff bulk-assignment route and redirect response compatible', function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $lecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'employment_status' => 'active',
        'is_active' => true,
        'is_available_for_assignment' => true,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'lecture_id' => null,
    ]);
    $user = User::factory()->create();

    grantInstructorAssignmentPermission($user, $campus, 'edit_course_offering');
    app()->instance('campus', $campus);

    actingAs($user)
        ->withSession(['current_campus_id' => $campus->id])
        ->from('/course-offerings')
        ->post(route('api.course-offerings.bulk-assign-lectures'), [
            'assignments' => [[
                'course_offering_id' => $offering->id,
                'lecture_id' => $lecturer->id,
            ]],
        ])
        ->assertRedirect('/course-offerings')
        ->assertSessionHas('inertia.flash_data.success', 'Successfully assigned lectures to 1 course offerings.');

    expect($offering->fresh()->lecture_id)->toBe($lecturer->id);
});

it('rolls back the bulk assignment when a later lecturer is ineligible', function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $eligibleLecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'employment_status' => 'active',
        'is_active' => true,
        'is_available_for_assignment' => true,
    ]);
    $ineligibleLecturer = Lecture::factory()->create([
        'campus_id' => $campus->id,
        'employment_status' => 'suspended',
        'is_active' => true,
        'is_available_for_assignment' => true,
    ]);
    $firstOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'lecture_id' => null,
    ]);
    $secondOffering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'lecture_id' => null,
    ]);
    $user = User::factory()->create();

    grantInstructorAssignmentPermission($user, $campus, 'edit_course_offering');
    app()->instance('campus', $campus);

    actingAs($user)
        ->withSession(['current_campus_id' => $campus->id])
        ->post(route('api.course-offerings.bulk-assign-lectures'), [
            'assignments' => [
                [
                    'course_offering_id' => $firstOffering->id,
                    'lecture_id' => $eligibleLecturer->id,
                ],
                [
                    'course_offering_id' => $secondOffering->id,
                    'lecture_id' => $ineligibleLecturer->id,
                ],
            ],
        ])
        ->assertUnprocessable();

    expect($firstOffering->fresh()->lecture_id)->toBeNull()
        ->and($secondOffering->fresh()->lecture_id)->toBeNull();
});

function grantInstructorAssignmentPermission(User $user, Campus $campus, string $permissionCode): void
{
    Cache::flush();

    $permission = Permission::firstOrCreate(
        ['code' => $permissionCode],
        ['name' => $permissionCode],
    );
    $role = Role::factory()->create(['code' => 'instructor_assignment_'.Str::lower(Str::random(10))]);

    RolePermission::create([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
    ]);
    CampusUserRole::create([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
    ]);
}
