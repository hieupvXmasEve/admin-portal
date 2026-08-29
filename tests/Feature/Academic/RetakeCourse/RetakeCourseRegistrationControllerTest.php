<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CurriculumUnit;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn(['view_retake_course', 'create_retake_course']);

    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
});

it('returns scalar filter defaults when the retake list has no query params', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('academic.retake-course.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/RetakeCourse/Index')
            ->where('filters.search', '')
            ->where('filters.status', null)
            ->where('filters.operation_state', null)
            ->where('filters.semester_id', null)
            ->where('filters.unit_id', null)
            ->where('filters.sort', null)
            ->where('filters.direction', null)
            ->where('filters.per_page', 15));
});

it('returns normalized scalar filters from retake list query params', function () {
    $semester = Semester::factory()->create();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('academic.retake-course.index', [
            'search' => 'AUH14972',
            'operation_state' => 'paid_waiting_class',
            'semester_id' => $semester->id,
            'sort' => 'created_at',
            'direction' => 'asc',
            'per_page' => 25,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/RetakeCourse/Index')
            ->where('filters.search', 'AUH14972')
            ->where('filters.operation_state', 'paid_waiting_class')
            ->where('filters.semester_id', $semester->id)
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'asc')
            ->where('filters.per_page', 25));
});

it('stores a registration when the date pickers submit empty strings for unset dates', function () {
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'campus_id' => $this->campus->id,
        'enrollment_status' => 'open',
        'course_status' => 'not_started',
    ]);
    CurriculumUnit::factory()->create([
        'curriculum_version_id' => $student->curriculum_version_id,
        'unit_id' => $offering->unit_id,
        'semester_id' => $semester->id,
    ]);
    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $this->campus->id,
        'unit_id' => $offering->unit_id,
        'course_offering_id' => $offering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
        'failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED,
    ]);
    DB::table('finance_pricing_catalog_items')->insert([
        'obligation_type' => \App\Modules\Finance\Models\FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 1_500_000,
        'currency' => 'VND',
        'rule_version' => 'retake_fee:v1',
        'description' => 'Fixed retake fee',
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.retake-course.store'), [
            '_token' => 'test-token',
            'student_id' => $student->id,
            'unit_id' => $offering->unit_id,
            'original_academic_record_id' => $record->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $semester->id,
            'campus_id' => $this->campus->id,
            'registration_start_date' => '',
            'registration_end_date' => '',
            'notes' => '',
        ])
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect(route('academic.retake-course.index'));

    expect(\App\Models\CourseRetakeRegistration::query()->where('student_id', $student->id)->exists())->toBeTrue();
});
