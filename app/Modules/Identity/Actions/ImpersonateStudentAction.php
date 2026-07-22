<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Shared\Contracts\Identity\DTO\ImpersonationAdministrator;
use App\Shared\Contracts\StudentRegistry\StudentImpersonationTokenIssuer;

final class ImpersonateStudentAction
{
    /** @param array{administrator:array{id:int, name:string, email:string}, email:string, device_name?:string, purpose?:string, ip_address:string, user_agent?:string|null} $data */
    public static function run(array $data): array
    {
        return app(StudentImpersonationTokenIssuer::class)->issue(
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
