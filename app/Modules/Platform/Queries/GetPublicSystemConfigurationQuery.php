<?php

declare(strict_types=1);

namespace App\Modules\Platform\Queries;

final class GetPublicSystemConfigurationQuery
{
    public function __construct(private readonly GetSystemBrandingQuery $branding) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return $this->branding->handle();
    }

    public function value(string $key): mixed
    {
        $configuration = $this->handle();

        return match ($key) {
            'logo_full' => $configuration['logo_full_url'],
            'logo_text' => $configuration['logo_text_url'],
            'favicon' => $configuration['favicon_url'],
            'apple_touch_icon' => $configuration['apple_touch_icon_url'],
            default => $configuration[$key] ?? null,
        };
    }
}
