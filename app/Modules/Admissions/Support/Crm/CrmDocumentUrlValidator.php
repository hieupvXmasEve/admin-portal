<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Support\Crm;

/**
 * CRM document URLs are rendered with `<a :href>` in the staff UI
 * (resources/js/pages/StudentApplications/Show.vue), so this is a trust
 * boundary control, not hygiene: `javascript:`/`data:` URLs must never reach
 * storage. Scheme-only (`https`) — no host allowlist, because the CRM mixes
 * Google Drive links with its own hosts and a host list would silently drop
 * real documents (validation V4).
 */
final class CrmDocumentUrlValidator
{
    /**
     * @return string|null the URL if it is a valid `https` link, otherwise null
     */
    public function validate(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme) || strtolower($scheme) !== 'https') {
            return null;
        }

        return $url;
    }
}
