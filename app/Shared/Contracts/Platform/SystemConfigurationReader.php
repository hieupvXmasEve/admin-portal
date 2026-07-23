<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Platform;

interface SystemConfigurationReader
{
    public function get(string $key, mixed $default = null): mixed;
}
