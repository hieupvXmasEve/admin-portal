<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\AI\Support\CampusScopeSnapshot;
use App\Modules\AI\Support\QueryPlan;
use App\Modules\AI\Support\QueryPlanValidator;
use App\Modules\AI\Support\Tools\ToolRegistry;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authorizedUser = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HCM']);
    $this->otherCampus = Campus::factory()->create(['code' => 'HN']);

    Semester::factory()->create([
        'code' => '2026-T1',
        'name' => 'Term 1 2026',
        'is_active' => true,
    ]);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturnUsing(function (User $user, ?int $campusId = null): array {
            // Authorized at both campuses so campus enforcement (not permission) is under test.
            if ($user->id === $this->authorizedUser->id) {
                return ['view_ai_metrics', 'view_finance_reporting'];
            }

            return [];
        });

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
});

function prefactorPlan(array $filters = []): QueryPlan
{
    return QueryPlan::fromArray([
        'metric' => 'finance_collection_summary',
        'filters' => array_merge(['semester' => 'current'], $filters),
        'group_by' => [],
    ]);
}

it('accepts an explicitly supplied campus and validates a matching campus filter against it', function () {
    $result = app(QueryPlanValidator::class)->validate(
        prefactorPlan(['campus_id' => $this->campus->id]),
        $this->authorizedUser,
        $this->campus,
    );

    expect($result->allowed())->toBeTrue()
        ->and($result->safeErrorCode())->toBeNull()
        ->and($result->toArray()['campus_scope_snapshot'])->toBe(['campus_ids' => [$this->campus->id]]);
});

it('rejects a campus filter that mismatches the explicitly supplied campus', function () {
    $result = app(QueryPlanValidator::class)->validate(
        prefactorPlan(['campus_id' => $this->otherCampus->id]),
        $this->authorizedUser,
        $this->campus,
    );

    expect($result->allowed())->toBeFalse()
        ->and($result->safeErrorCode())->toBe('forbidden_by_campus_scope');
});

it('keeps web-chat campus resolution unchanged when no campus is supplied (falls back to the bound campus)', function () {
    // Mirror the web-chat path: SetCampus middleware binds the session campus into the container.
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $result = app(QueryPlanValidator::class)->validate(
        prefactorPlan(['campus_id' => $this->campus->id]),
        $this->authorizedUser,
        // No explicit campus — resolution must fall back to app('campus') exactly as before.
    );

    expect($result->allowed())->toBeTrue()
        ->and($result->safeErrorCode())->toBeNull()
        ->and($result->toArray()['campus_scope_snapshot'])->toBe(['campus_ids' => [$this->campus->id]]);

    // And the implicit fallback still enforces scope against a mismatching filter.
    $denied = app(QueryPlanValidator::class)->validate(
        prefactorPlan(['campus_id' => $this->otherCampus->id]),
        $this->authorizedUser,
    );

    expect($denied->safeErrorCode())->toBe('forbidden_by_campus_scope');
});

it('represents a span of multiple campuses in the scope-snapshot shape', function () {
    $snapshot = CampusScopeSnapshot::span([$this->campus->id, $this->otherCampus->id, $this->campus->id]);

    expect($snapshot->spansMultipleCampuses())->toBeTrue()
        ->and($snapshot->scope())->toBe(CampusScopeSnapshot::SCOPE_SPAN)
        ->and($snapshot->toArray())->toBe([
            'campus_ids' => [$this->campus->id, $this->otherCampus->id],
        ]);

    $all = CampusScopeSnapshot::allCampus([$this->campus->id, $this->otherCampus->id]);
    expect($all->scope())->toBe(CampusScopeSnapshot::SCOPE_ALL_CAMPUS)
        ->and($all->campusIds())->toBe([$this->campus->id, $this->otherCampus->id]);

    // The single/none shapes stay byte-identical to the historical chat snapshot.
    expect(CampusScopeSnapshot::single($this->campus->id)->toArray())->toBe(['campus_ids' => [$this->campus->id]])
        ->and(CampusScopeSnapshot::single(null)->toArray())->toBe(['campus_ids' => []]);
});

it('registers clarification_required as a safe error code across the tool catalogs', function () {
    $registry = app(ToolRegistry::class);

    foreach (['query_metrics', 'search_entities', 'get_entity_profile'] as $tool) {
        expect($registry->definition($tool)->toArray()['safe_error_codes'])
            ->toContain('clarification_required');
    }
});
