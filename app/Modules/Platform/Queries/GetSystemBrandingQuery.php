<?php

declare(strict_types=1);

namespace App\Modules\Platform\Queries;

use App\Modules\Platform\Models\SystemSetting;
use App\Modules\Platform\Support\SystemConfigurationStore;
use App\Shared\Contracts\Upload\FileUploadGateway;
use Throwable;

final class GetSystemBrandingQuery
{
    public function __construct(
        private readonly SystemConfigurationStore $configuration,
        private readonly FileUploadGateway $uploads,
    ) {}

    /** @return array<string, string|bool|null> */
    public function handle(): array
    {
        $configuration = $this->configuration->all();

        $logoFullUrl = $this->urlFor($configuration['logo_full_upload_id'] ?? null);
        $logoTextUrl = $this->urlFor($configuration['logo_text_upload_id'] ?? null);
        $faviconUrl = $this->urlFor($configuration['favicon_upload_id'] ?? null);
        $appleTouchIconUrl = $this->urlFor($configuration['apple_touch_icon_upload_id'] ?? null);

        return [
            'app_name' => (string) $configuration['app_name'],
            'copyright_text' => (string) $configuration['copyright_text'],
            'country' => (string) $configuration['country'],
            'survey_enabled' => (bool) $configuration['survey_enabled'],
            'logo_full_url' => $logoFullUrl,
            'logo_text_url' => $logoTextUrl,
            'favicon_url' => $faviconUrl,
            'apple_touch_icon_url' => $appleTouchIconUrl,
            'logo_full' => $logoFullUrl,
            'logo_text' => $logoTextUrl,
            'branding_version' => $this->version($configuration),
            'updated_at' => $this->updatedAt(),
        ];
    }

    private function urlFor(mixed $uploadId): ?string
    {
        if (! is_int($uploadId) && ! ctype_digit((string) $uploadId)) {
            return null;
        }

        try {
            return $this->uploads->availableUrlFor((int) $uploadId);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $configuration */
    private function version(array $configuration): string
    {
        return hash('xxh3', json_encode([
            $configuration['app_name'],
            $configuration['logo_full_upload_id'],
            $configuration['logo_text_upload_id'],
            $configuration['favicon_upload_id'],
            $configuration['apple_touch_icon_upload_id'],
        ], JSON_THROW_ON_ERROR));
    }

    private function updatedAt(): ?string
    {
        return SystemSetting::query()
            ->latest('updated_at')
            ->value('updated_at')?->toAtomString();
    }
}
