<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Shared\Contracts\Academic\LecturerImpersonationTokenIssuer;
use App\Shared\Contracts\Identity\DTO\ImpersonationAdministrator;

final class ImpersonateLecturerAction
{
    /** @param array{administrator:array{id:int, name:string, email:string}, email:string, device_name?:string|null, purpose?:string|null, ip_address:string, user_agent?:string|null} $data */
    public static function run(array $data): array
    {
        return app(LecturerImpersonationTokenIssuer::class)->impersonate(
            $data['email'],
            new ImpersonationAdministrator(
                id: $data['administrator']['id'],
                name: $data['administrator']['name'],
                email: $data['administrator']['email'],
                ipAddress: $data['ip_address'],
                userAgent: $data['user_agent'] ?? null,
                deviceName: $data['device_name'] ?? null,
                purpose: $data['purpose'] ?? null,
            ),
        )->payload;
    }
}
