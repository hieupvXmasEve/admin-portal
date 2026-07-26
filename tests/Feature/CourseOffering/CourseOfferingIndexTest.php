<?php

declare(strict_types=1);

use App\Constants\CourseOfferingRoutes;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Module;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->activeSemester = Semester::factory()->active()->create([
        'name' => 'Fall 2025',
        'code' => 'FALL2025',
    ]);
    $this->oldSemester = Semester::factory()->create([
        'name' => 'Spring 2025',
        'code' => 'SPR2025',
        'is_active' => false,
        'is_archived' => false,
    ]);

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn(['view_course_offering']);

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
});

it('uses global semester context for course offering list, stats, and module options', function () {
    $aiUnit = createIndexUnit('AI101', 'AI Foundations');
    $businessUnit = createIndexUnit('BUS101', 'Business Foundations');
    $historyUnit = createIndexUnit('HIS101', 'Historical Unit');

    $aiModule = createIndexModule($this->campus, 'AI-MOD', 'AI Module');
    $businessModule = createIndexModule($this->campus, 'BUS-MOD', 'Business Module');
    $historyModule = createIndexModule($this->campus, 'HIS-MOD', 'Historical Module');

    $aiModule->units()->attach($aiUnit->id, ['order' => 1, 'grading_type' => 'grade']);
    $businessModule->units()->attach($businessUnit->id, ['order' => 1, 'grading_type' => 'grade']);
    $historyModule->units()->attach($historyUnit->id, ['order' => 1, 'grading_type' => 'grade']);

    $visibleOffering = createIndexOffering($this, $aiUnit, $this->activeSemester, ['section_code' => 'A1']);
    createIndexOffering($this, $businessUnit, $this->activeSemester, ['section_code' => 'B1']);
    createIndexOffering($this, $historyUnit, $this->oldSemester, ['section_code' => 'H1']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route(CourseOfferingRoutes::INDEX, ['module_id' => (string) $aiModule->id]))
        ->assertInertia(fn ($page) => $page
            ->component('CourseOfferings/Index')
            ->where('statistics.total_offerings', 2)
            ->where('statistics.active_offerings', 2)
            ->where('filters.module_id', (string) $aiModule->id)
            ->where('courseOfferings.data.0.id', $visibleOffering->id)
            ->where('courseOfferings.data.0.unit.modules.0.name', 'AI Module')
            ->has('courseOfferings.data', 1)
            ->has('moduleOptions', 2)
            ->where('moduleOptions.0.label', 'AI Module')
            ->where('moduleOptions.1.label', 'Business Module'));
});

it('returns statistics scoped to the current campus and requested semester', function (): void {
    $unit = createIndexUnit('STAT101', 'Statistics');
    createIndexOffering($this, $unit, $this->activeSemester, [
        'current_enrollment' => 8,
        'max_capacity' => 10,
        'enrollment_status' => 'open',
    ]);
    createIndexOffering($this, $unit, $this->oldSemester, [
        'current_enrollment' => 4,
        'max_capacity' => 4,
        'enrollment_status' => 'cancelled',
        'is_active' => false,
    ]);
    CourseOffering::factory()->create([
        'campus_id' => $this->campus->id,
        'semester_id' => $this->activeSemester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 2,
        'max_capacity' => 4,
        'is_active' => false,
        'enrollment_status' => 'closed',
        'deleted_at' => now(),
    ]);
    CourseOffering::factory()->create([
        'campus_id' => Campus::factory()->create()->id,
        'semester_id' => $this->activeSemester->id,
        'unit_id' => $unit->id,
        'current_enrollment' => 20,
        'max_capacity' => 20,
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->getJson(route(CourseOfferingRoutes::API_STATISTICS, ['semester_id' => $this->activeSemester->id]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total_offerings', 2)
        ->assertJsonPath('data.active_offerings', 1)
        ->assertJsonPath('data.full_offerings', 0)
        ->assertJsonPath('data.cancelled_offerings', 0)
        ->assertJsonPath('data.total_enrollment', 10)
        ->assertJsonPath('data.total_capacity', 14)
        ->assertJsonPath('data.enrollment_rate', 71.43);
});

function createIndexUnit(string $code, string $name): Unit
{
    return Unit::factory()->create([
        'code' => $code,
        'name' => $name,
        'credit_points' => 3,
        'level' => 100,
        'unit_type' => 'general',
    ]);
}

function createIndexModule(Campus $campus, string $code, string $name): Module
{
    return Module::query()->create([
        'campus_id' => $campus->id,
        'code' => $code,
        'name' => $name,
        'grading_type' => 'grade',
        'total_credits' => 3,
    ]);
}

function createIndexOffering(object $context, Unit $unit, Semester $semester, array $overrides = []): CourseOffering
{
    return CourseOffering::factory()->create(array_merge([
        'campus_id' => $context->campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'enrollment_status' => 'open',
        'course_status' => 'not_started',
        'is_active' => true,
        'current_enrollment' => 0,
    ], $overrides));
}
