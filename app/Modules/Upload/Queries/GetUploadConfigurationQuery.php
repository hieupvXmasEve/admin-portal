<?php

declare(strict_types=1);

namespace App\Modules\Upload\Queries;

use App\Modules\Upload\Support\UploadPlatform;

class GetUploadConfigurationQuery
{
    public function __construct(private UploadPlatform $uploads) {}

    public function handle(): array
    {
        $contexts = [];

        foreach ($this->uploads->getAvailableContexts() as $context) {
            $config = $this->uploads->getContextConfiguration($context);

            $contexts[$context] = [
                'max_size' => $config['max_size'],
                'allowed_types' => $config['allowed_types'],
                'allowed_extensions' => $config['allowed_extensions'] ?? [],
                'directory' => $config['directory'],
                'generate_thumbnails' => $config['generate_thumbnails'] ?? false,
                'public' => $config['public'] ?? true,
                'disk' => $config['disk'] ?? 'images',
            ];
        }

        return [
            'contexts' => $contexts,
            'defaults' => config('uploads.defaults'),
            'security' => config('uploads.security'),
            'performance' => config('uploads.performance'),
        ];
    }
}
