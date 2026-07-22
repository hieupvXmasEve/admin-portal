<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Identity\DTO\ImpersonationAdministrator;
use App\Shared\Contracts\Identity\DTO\ImpersonationTokenResult;

interface LecturerImpersonationTokenIssuer
{
    public function impersonate(string $identifier, ImpersonationAdministrator $administrator): ImpersonationTokenResult;
}
