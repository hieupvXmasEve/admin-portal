<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Resolves the OAuth-authenticated Passport identity into a Swinx actor and enforces the
 * active-status gate (ADR-0010): the resolved user's status must be exactly
 * {@see User::STATUS_ACTIVE} — every other status (suspended, banned, locked, pending,
 * inactive, …) is refused, read directly off the user record (not the cached permission
 * list) so offboarding/revocation takes effect immediately even while a token is valid.
 */
class ActiveUserMcpActorResolver implements McpActorResolver
{
    private const SAFE_NO_ACTOR = 'unauthenticated';

    private const SAFE_INACTIVE = 'forbidden_inactive_account';

    public function __construct(private readonly AuthFactory $auth) {}

    public function resolve(): User
    {
        $user = $this->auth->guard('api')->user();

        if (! $user instanceof User) {
            throw new McpToolException(
                safeErrorCode: self::SAFE_NO_ACTOR,
                message: 'No authenticated MCP actor.',
            );
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            throw new McpToolException(
                safeErrorCode: self::SAFE_INACTIVE,
                message: 'This Swinx account is not active and cannot use the connector.',
                permissionResult: 'denied',
            );
        }

        return $user;
    }
}
