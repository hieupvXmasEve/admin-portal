<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('provides display names for lecturers in the quick edit class session modal', function (): void {
    $campus = Campus::factory()->create();
    $user = User::factory()->create();
    $lecturer = Lecture::factory()->for($campus)->create([
        'first_name' => 'Linh',
        'last_name' => 'Nguyen',
        'employment_status' => 'active',
        'is_active' => true,
    ]);
    $offering = CourseOffering::factory()->create([
        'campus_id' => $campus->id,
        'semester_id' => Semester::factory(),
        'unit_id' => Unit::factory(),
    ]);
    $session = ClassSession::factory()->create([
        'course_offering_id' => $offering->id,
        'lecture_id' => $lecturer->id,
        'status' => 'scheduled',
    ]);

    app()->singleton('campus', fn () => $campus);
    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, function () {
        $permissions = Mockery::mock(CampusPermissionReader::class);
        $permissions->shouldReceive('permissionCodesForUserId')->andReturn(['edit_class_session']);

        return $permissions;
    });

    actingAs($user)
        ->withSession(['current_campus_id' => $campus->id])
        ->get(route('class-sessions.quick-edit', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ClassSessions/modals/QuickEdit')
            ->where('lecturers.0.id', $lecturer->id)
            ->where('lecturers.0.display_name', 'Linh Nguyen'));
});
