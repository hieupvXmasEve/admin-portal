<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Models\User;
use App\Modules\AI\Support\CampusScopeSnapshot;
use App\Services\PermissionService;

/**
 * Resolves the campus scope for an MCP tool call from an explicit `campus_id` argument
 * using Clarification-first AI behavior (ADR-0010):
 *
 *  - explicit `campus_id`                 → validate ∈ permitted campuses, else the
 *                                           `forbidden_by_campus_scope` deny;
 *  - omitted, exactly one permitted       → use it;
 *  - omitted, multiple permitted & not an
 *    All-campus AI scope holder           → `clarification_required` (a clarification);
 *  - All-campus AI scope holder omitting  → span every permitted campus.
 *
 * The permitted campus set is the campuses where the actor holds a role (the same
 * user↔campus↔role association `PermissionService` reads); the All-campus AI scope is the
 * dedicated `view_ai_all_campus` permission (never the finance all-campus permission).
 */
class PermissionMcpCampusResolver implements McpCampusResolver
{
    public const ALL_CAMPUS_PERMISSION = 'view_ai_all_campus';

    private const SAFE_CAMPUS_SCOPE = 'forbidden_by_campus_scope';

    private const SAFE_CLARIFICATION = 'clarification_required';

    public function __construct(private readonly PermissionService $permissionService) {}

    public function resolve(User $actor, ?int $campusId): CampusScopeSnapshot
    {
        $permitted = $this->permittedCampusIds($actor);

        if ($campusId !== null) {
            if (! in_array($campusId, $permitted, true)) {
                throw new McpToolException(
                    safeErrorCode: self::SAFE_CAMPUS_SCOPE,
                    message: 'You do not have a role at the requested campus.',
                    permissionResult: 'allowed',
                    campusScopeSnapshot: ['campus_ids' => [$campusId]],
                );
            }

            return CampusScopeSnapshot::single($campusId);
        }

        if ($this->holdsAllCampusScope($actor)) {
            return CampusScopeSnapshot::allCampus($permitted);
        }

        if (count($permitted) === 1) {
            return CampusScopeSnapshot::single($permitted[0]);
        }

        throw new McpToolException(
            safeErrorCode: self::SAFE_CLARIFICATION,
            message: 'Specify which campus you mean by passing campus_id.',
            permissionResult: 'allowed',
            campusScopeSnapshot: ['campus_ids' => $permitted],
            clarification: true,
            meta: ['candidate_campus_ids' => $permitted],
        );
    }

    /**
     * @return list<int>
     */
    private function permittedCampusIds(User $actor): array
    {
        return $actor->campusUserRoles()
            ->distinct()
            ->pluck('campus_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function holdsAllCampusScope(User $actor): bool
    {
        return in_array(
            self::ALL_CAMPUS_PERMISSION,
            $this->permissionService->getUserPermissions($actor, null),
            true,
        );
    }
}
