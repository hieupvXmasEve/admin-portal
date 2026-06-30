<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Models\User;
use App\Modules\AI\Support\CampusScopeSnapshot;

/**
 * FROZEN CONTRACT (Issue 01 spike → consumed by Issue 02 / Step 2 + Step 4).
 *
 * Resolves the campus scope for an MCP tool call from an explicit `campus_id` argument
 * using Clarification-first AI behavior (ADR-0010):
 *
 *  - explicit `campus_id`           → validate ∈ the actor's permitted campuses (else
 *                                      the existing `forbidden_by_campus_scope` deny);
 *  - omitted, exactly one permitted → use that campus;
 *  - omitted, multiple permitted &
 *    not an All-campus AI scope holder → a `clarification_required` result (a
 *                                        clarification, NOT a deny);
 *  - All-campus AI scope holder omitting it → span every permitted campus.
 *
 * The result is expressed as the multi-campus-capable {@see CampusScopeSnapshot}.
 * A concrete implementation ships in the identity slice; this interface only freezes
 * the shape the later slices code against.
 */
interface McpCampusResolver
{
    /**
     * @return CampusScopeSnapshot the resolved (possibly multi-campus) campus scope
     */
    public function resolve(User $actor, ?int $campusId): CampusScopeSnapshot;
}
