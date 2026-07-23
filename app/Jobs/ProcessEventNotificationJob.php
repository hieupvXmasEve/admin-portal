<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * Temporary compatibility alias for queued payloads created before the
 * Engagement module owned event notification delivery.
 */
class ProcessEventNotificationJob extends \App\Modules\Engagement\Jobs\ProcessEventNotificationJob {}
