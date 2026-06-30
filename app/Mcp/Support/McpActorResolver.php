<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Models\User;

/**
 * FROZEN CONTRACT (Issue 01 spike → consumed by Issue 02 / Step 2).
 *
 * Resolves the OAuth-authenticated Passport identity into a Swinx actor, applying the
 * active-status gate (ADR-0010): the resolved user's status must be exactly the active
 * status — every other status (suspended, banned, locked, pending, inactive, …) is
 * refused, read directly off the user record (not via the cached permission list) so
 * revocation/offboarding is immediate.
 *
 * The actor is acquired from the `api` guard INSIDE the MCP tool's handle() — the
 * package injects only `array $arguments`, so there is no DI of the user.
 *
 * A concrete implementation ships in the identity slice; this interface only freezes
 * the shape the later slices code against.
 */
interface McpActorResolver
{
    /**
     * Resolve + active-status-gate the current OAuth user into a Swinx actor.
     *
     * @throws \RuntimeException when no OAuth user is authenticated or the user is not active
     */
    public function resolve(): User;
}
