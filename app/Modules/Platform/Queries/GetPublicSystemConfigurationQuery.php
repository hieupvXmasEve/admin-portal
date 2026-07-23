<?php

declare(strict_types=1);

namespace App\Modules\Platform\Queries;

use App\Modules\Platform\Support\SystemConfigurationStore;

final class GetPublicSystemConfigurationQuery
{
    public function __construct(private readonly SystemConfigurationStore $configuration) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return $this->configuration->public();
    }

    public function value(string $key): mixed
    {
        return $this->configuration->publicValue($key);
    }
}
