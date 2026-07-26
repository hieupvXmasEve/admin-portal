<?php

declare(strict_types=1);

namespace App\Modules\Upload\Queries;

use App\Modules\Upload\Support\UploadPlatform;

class GetUploadContextsQuery
{
    public function __construct(private UploadPlatform $uploads) {}

    public function handle(): array
    {
        $contexts = [];

        foreach ($this->uploads->getAvailableContexts() as $context) {
            $config = $this->uploads->getContextConfiguration($context);

            if ($config['internal'] ?? false) {
                continue;
            }

            $contexts[$context] = [
                'max_size' => $config['max_size'],
                'max_size_mb' => round($config['max_size'] / 1024, 2),
                'allowed_types' => $config['allowed_types'],
                'allowed_extensions' => $config['allowed_extensions'] ?? [],
                'public' => $config['public'] ?? true,
                'directory' => $config['directory'],
            ];
        }

        return $contexts;
    }
}
