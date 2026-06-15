<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use App\Services\PermissionService;

const BATCH_STUDIO_CSRF = 'batch-studio-test-csrf';

/**
 * Grant a user a fixed set of finance permissions for the active campus and
 * bind the campus context the way HandleInertiaRequests + finance controllers expect.
 *
 * @param  string[]  $permissions
 */
function grantFinance(User $user, array $permissions, Campus $campus): void
{
    session(financeWebSession($campus));
    app()->singleton('campus', fn () => $campus);

    $mock = Mockery::mock(PermissionService::class);
    $mock->shouldReceive('getUserPermissions')->andReturn($permissions);
    app()->instance(PermissionService::class, $mock);
}

/**
 * @return array{current_campus_id: int, _token: string}
 */
function financeWebSession(Campus $campus): array
{
    return [
        'current_campus_id' => $campus->id,
        '_token' => BATCH_STUDIO_CSRF,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function financePostPayload(array $payload): array
{
    return array_merge(['_token' => BATCH_STUDIO_CSRF], $payload);
}