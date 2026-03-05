<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use Illuminate\Support\Facades\Log;

class NotificationMetrics
{
    /**
     * @param  array<string, scalar|null>  $tags
     */
    public function increment(string $metric, array $tags = []): void
    {
        Log::info('notification.metric', [
            'metric' => $metric,
            'tags' => $tags,
            'at' => now()->toDateTimeString(),
        ]);
    }
}
