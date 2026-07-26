<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn(['view_retake_course']);

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
