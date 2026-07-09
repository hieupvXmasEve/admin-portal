<?php

declare(strict_types=1);

namespace App\Modules\Finance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when PushNextInstallmentJob exhausts all 3 retries. Listeners should
 * notify admins so they can investigate (DNG outage, payload error, etc.)
 * and use the manual retry button once the underlying issue is resolved.
 *
 * The installment stays `pending` with `push_attempt_count >= 3` and
 * `last_push_error` populated for admin UI display.
 *
 * Email template wiring deferred to follow-up story (see harness backlog).
 */
class InstallmentPushFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $financeChargeId,
        public readonly string $errorMessage,
    ) {}
}
