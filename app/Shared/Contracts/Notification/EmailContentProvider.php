<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Notification;

/**
 * Cross-module contract for resolving email content (subject / html / text).
 *
 * Lives in `app/Shared/Contracts/Notification/` so other modules (e.g. Finance)
 * may depend on this interface without reaching into the Notification module's
 * internal namespace. See docs/rules/contracts.md.
 */
interface EmailContentProvider
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function subject(array $data): string;

    /**
     * @param  array<string, mixed>  $data
     */
    public function htmlBody(array $data): string;

    /**
     * @param  array<string, mixed>  $data
     */
    public function textBody(array $data): ?string;
}
