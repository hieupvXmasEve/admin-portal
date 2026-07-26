<?php

declare(strict_types=1);

use App\Actions\Lecture\GetTeachingHoursAction;
use App\Models\Campus;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-05-31 10:00:00'));

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HN']);
    $this->otherCampus = Campus::factory()->create(['code' => 'DN']);

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    bindTeachingHoursPermissions(['view_lecturer', 'export_lecturer']);
});

afterEach(function () {
    Carbon::setTestNow();
});

function bindTeachingHoursPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn($permissions);

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
}

function seedTeachingHoursReport(object $context): array
{
    $activeSemester = Semester::factory()->active()->create([
        'code' => 'SPR2026',
        'name' => 'Spring 2026',
        'start_date' => '2026-04-01',
        'end_date' => '2026-07-31',
    ]);
    $oldSemester = Semester::factory()->create([
        'code' => 'FALL2025',
        'name' => 'Fall 2025',
        'is_active' => false,
        'start_date' => '2025-09-01',
        'end_date' => '2025-12-31',
    ]);
    $au001 = Unit::factory()->create(['code' => 'AU001', 'name' => 'Academic Unit 1']);
    $au002 = Unit::factory()->create(['code' => 'AU002', 'name' => 'Academic Unit 2']);

    $lecturer = Lecture::factory()->create([
        'campus_id' => $context->campus->id,
        'employee_id' => 'EMP001',
        'first_name' => 'Dung',
        'last_name' => 'Nguyen',
        'email' => 'trangnk16@swin.edu.vn',
        'employment_type' => 'part_time',
    ]);
    $otherCampusLecturer = Lecture::factory()->create(['campus_id' => $context->otherCampus->id]);

    $au001Offering = CourseOffering::factory()->create([
        'campus_id' => $context->campus->id,
        'semester_id' => $activeSemester->id,
        'unit_id' => $au001->id,
        'lecture_id' => $lecturer->id,
        'section_code' => 'A',
    ]);
    $au002Offering = CourseOffering::factory()->create([
        'campus_id' => $context->campus->id,
        'semester_id' => $activeSemester->id,
        'unit_id' => $au002->id,
        'lecture_id' => $lecturer->id,
        'section_code' => 'B',
    ]);
    $oldOffering = CourseOffering::factory()->create([
        'campus_id' => $context->campus->id,
        'semester_id' => $oldSemester->id,
        'unit_id' => $au001->id,
        'lecture_id' => $lecturer->id,
        'section_code' => 'OLD',
    ]);
    $otherCampusOffering = CourseOffering::factory()->create([
        'campus_id' => $context->otherCampus->id,
        'semester_id' => $activeSemester->id,
        'unit_id' => $au001->id,
        'lecture_id' => $otherCampusLecturer->id,
        'section_code' => 'X',
    ]);

    ClassSession::factory()->create([
        'course_offering_id' => $au001Offering->id,
        'room_id' => null,
        'lecture_id' => $lecturer->id,
        'session_date' => '2026-04-20',
        'start_time' => '08:00:00',
        'end_time' => '09:30:00',
        'duration_minutes' => 90,
        'session_type' => 'lecture',
        'status' => 'completed',
    ]);
    ClassSession::factory()->create([
        'course_offering_id' => $au002Offering->id,
        'room_id' => null,
        'lecture_id' => $lecturer->id,
        'session_date' => '2026-05-20',
        'start_time' => '10:00:00',
        'end_time' => '12:00:00',
        'duration_minutes' => 120,
        'session_type' => 'lecture',
        'status' => 'completed',
    ]);
    ClassSession::factory()->create([
        'course_offering_id' => $oldOffering->id,
        'room_id' => null,
        'lecture_id' => $lecturer->id,
        'session_date' => '2025-10-10',
        'start_time' => '08:00:00',
        'end_time' => '13:00:00',
        'duration_minutes' => 300,
        'session_type' => 'lecture',
        'status' => 'completed',
    ]);
    ClassSession::factory()->create([
        'course_offering_id' => $otherCampusOffering->id,
        'room_id' => null,
        'lecture_id' => $otherCampusLecturer->id,
        'session_date' => '2026-04-20',
        'start_time' => '08:00:00',
        'end_time' => '18:00:00',
        'duration_minutes' => 600,
        'session_type' => 'lecture',
        'status' => 'completed',
    ]);

    return [$activeSemester, $lecturer];
}

it('defaults to the active semester with blank dates and summarizes lecturer identity and courses', function () {
    [$activeSemester] = seedTeachingHoursReport($this);

    $action = new GetTeachingHoursAction;
    $filters = $action->normalizeFilters([]);
    $result = $action->execute($filters, $this->campus->id);
    $row = collect($result->items())->first();

    expect($filters)
        ->semester_id->toBe((string) $activeSemester->id)
        ->date_from->toBe('')
        ->date_to->toBe('')
        ->and($result->total())->toBe(1)
        ->and($row['lecture_name'])->toBe('Dung Nguyen')
        ->and($row['email_account'])->toBe('trangnk16')
        ->and($row['employee_id'])->toBe('EMP001')
        ->and($row['employment_type_label'])->toBe('Part time')
        ->and($row['course_list'])->toBe('AU001 (A), AU002 (B)')
        ->and($row['teaching_subjects'])->toBe('AU001, AU002')
        ->and($row['total_hours'])->toBe(3.5);
});

it('filters teaching hours and taught courses by the requested date range', function () {
    [$activeSemester] = seedTeachingHoursReport($this);

    $action = new GetTeachingHoursAction;
    $filters = $action->normalizeFilters([
        'semester_id' => (string) $activeSemester->id,
        'date_from' => '2026-04-16',
        'date_to' => '2026-05-15',
    ]);
    $row = collect($action->execute($filters, $this->campus->id)->items())->first();

    expect($row['course_list'])
        ->toBe('AU001 (A)')
        ->and($row['teaching_subjects'])->toBe('AU001')
        ->and($row['session_count'])->toBe(1)
        ->and($row['total_hours'])->toBe(1.5);
});

it('exports teaching hours with the current filter contract', function () {
    [$activeSemester] = seedTeachingHoursReport($this);

    $response = actingAs($this->user)
        ->get(route('lectures.teaching-hours.export', [
            'semester_id' => (string) $activeSemester->id,
            'date_from' => '2026-04-16',
            'date_to' => '2026-05-15',
        ]));

    $response->assertOk();

    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('teaching_hours_export_2026-05-31.xlsx');
});
