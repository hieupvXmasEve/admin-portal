<?php

declare(strict_types=1);

namespace App\Modules\Notification\EmailContent;

use App\Modules\Notification\EmailContent\Types\DbEmailContentProvider;
use App\Modules\Notification\EmailContent\Types\DngPaymentPushedEmailContent;
use App\Modules\Notification\EmailContent\Types\DngPaymentReceivedEmailContent;
use App\Modules\Notification\EmailContent\Types\ParentPaymentReminderEmailContent;
use App\Modules\Notification\EmailContent\Types\PaymentReminderEmailContent;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Shared\Contracts\Notification\EmailContentProvider;
use App\Shared\Contracts\Notification\EmailContentResolver;
use InvalidArgumentException;

/**
 * Registry of EmailContentProvider implementations keyed by `type_key`.
 *
 * When `config('notifications.use_db_templates', true)` is true (the default
 * after P1 parity ships), every key resolves to a {@see DbEmailContentProvider}
 * bound to the matching {@see NotificationTemplateTypeKey}. When false, the
 * legacy hard-coded `*EmailContent` classes are resolved instead. The flag
 * exists for the P1 -> P2 transition window and will be removed in the
 * post-P2 cleanup (see config/notifications.php).
 *
 * Resolved providers are memoised per composite key `"$typeKey:$flagState"`
 * (where `$flagState` is `'db'` or `'legacy'`) so toggling the flag mid-
 * request rebuilds the binding rather than returning a stale provider. The
 * {@see DbEmailContentProvider} per-(typeKey, campusId) row cache persists
 * across multiple `resolve()` calls in the same request.
 *
 * To prevent stale state under long-running PHP runtimes (FrankenPHP today,
 * Octane in the future), {@see self::reset()} is invoked by listeners
 * registered in NotificationServiceProvider on Laravel's `RequestHandled`
 * and `JobProcessed` events. The reset clears the local memo and forwards
 * `reset()` to any cached provider that implements it.
 */
final class EmailContentRegistry implements EmailContentResolver
{
    /** @var array<string, class-string<EmailContentProvider>> */
    private array $legacyMap = [
        'dng_payment_pushed' => DngPaymentPushedEmailContent::class,
        'dng_payment_received' => DngPaymentReceivedEmailContent::class,
        'parent_payment_reminder' => ParentPaymentReminderEmailContent::class,
        'payment_reminder' => PaymentReminderEmailContent::class,
    ];

    /** @var array<string, EmailContentProvider> */
    private array $resolved = [];

    public function has(string $typeKey): bool
    {
        // Accept any valid enum case; legacy fallback is checked separately
        // in build() so DB-only keys (e.g. installment_payment_reminder) still
        // resolve when use_db_templates=true even without a legacy class.
        if (NotificationTemplateTypeKey::tryFrom($typeKey) !== null) {
            return true;
        }

        return isset($this->legacyMap[$typeKey]);
    }

    public function resolve(string $typeKey): EmailContentProvider
    {
        if (! $this->has($typeKey)) {
            throw new InvalidArgumentException("No EmailContentProvider registered for type_key: {$typeKey}");
        }

        $cacheKey = $typeKey.':'.$this->flagState();

        if (isset($this->resolved[$cacheKey])) {
            return $this->resolved[$cacheKey];
        }

        return $this->resolved[$cacheKey] = $this->build($typeKey);
    }

    public function isConfiguredForCampus(string $typeKey, int $campusId): bool
    {
        return NotificationEmailTemplate::query()
            ->where('campus_id', $campusId)
            ->where('type_key', $typeKey)
            ->exists();
    }

    /**
     * Clear the resolved-provider memo and forward the reset to any cached
     * provider that implements its own `reset()` (e.g. DbEmailContentProvider's
     * per-(typeKey, campusId) row cache). Wired to `RequestHandled` and
     * `JobProcessed` listeners in NotificationServiceProvider so long-running
     * runtimes (FrankenPHP / Octane / queue workers) do not leak cached state
     * across request/job boundaries.
     */
    public function reset(): void
    {
        foreach ($this->resolved as $provider) {
            if (method_exists($provider, 'reset')) {
                $provider->reset();
            }
        }

        $this->resolved = [];
    }

    private function build(string $typeKey): EmailContentProvider
    {
        if ((bool) config('notifications.use_db_templates', true)) {
            return new DbEmailContentProvider(NotificationTemplateTypeKey::from($typeKey));
        }

        if (! isset($this->legacyMap[$typeKey])) {
            throw new InvalidArgumentException(sprintf(
                'No legacy EmailContentProvider class registered for type_key=%s. Enable notifications.use_db_templates or add a legacy class.',
                $typeKey,
            ));
        }

        return app($this->legacyMap[$typeKey]);
    }

    private function flagState(): string
    {
        return (bool) config('notifications.use_db_templates', true) ? 'db' : 'legacy';
    }
}
