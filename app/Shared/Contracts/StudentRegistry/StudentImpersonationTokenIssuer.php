<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry;

use App\Shared\Contracts\Identity\DTO\ImpersonationAdministrator;
use App\Shared\Contracts\Identity\DTO\ImpersonationTokenResult;

interface StudentImpersonationTokenIssuer
{
    public function issue(string $identifier, ImpersonationAdministrator $administrator): ImpersonationTokenResult;
}
