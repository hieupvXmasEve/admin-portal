<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Lecture;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissions = Mockery::mock(CampusPermissionReader::class);
    $permissions->shouldReceive('permissionCodesForUserId')->andReturn(['view_lecturer', 'export_lecturer']);

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissions);
});

it('returns the canonical API envelope for the lecture search helpers', function (): void {
    $matchingLecture = Lecture::factory()->create([
        'campus_id' => $this->campus->id,
        'employee_id' => 'LECTURE-001',
        'first_name' => 'Teaching',
        'last_name' => 'Hours',
        'is_active' => true,
        'is_available_for_assignment' => true,
        'employment_status' => 'active',
    ]);
    Lecture::factory()->create(['campus_id' => $this->campus->id, 'is_active' => false]);
    Lecture::factory()->create(['campus_id' => Campus::factory()->create()->id]);

    actingAs($this->user)
        ->getJson(route('api.lectures.search', ['query' => 'Teaching', 'campus_id' => $this->campus->id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Lectures retrieved successfully')
        ->assertJsonPath('data.0.id', $matchingLecture->id);

    actingAs($this->user)
        ->getJson(route('api.admin.lectures.api-index', ['is_active' => 'false']))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Lecturers retrieved successfully')
        ->assertJsonCount(1, 'data');

    actingAs($this->user)
        ->getJson(route('api.lectures.statistics', ['campus_id' => $this->campus->id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_lectures', 2);

    actingAs($this->user)
        ->getJson(route('api.lectures.statistics', ['campus_id' => 'all']))
        ->assertOk()
        ->assertJsonPath('data.total_lectures', 3);
});

it('rejects invalid lecture API filters through their FormRequests', function (): void {
    actingAs($this->user)
        ->getJson(route('api.admin.lectures.api-index', ['is_active' => 'invalid']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', 'is_active');
});
