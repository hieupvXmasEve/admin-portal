<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Platform;

interface SystemConfigurationWriter
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function update(array $attributes): array;
}
