<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Support\CampusLogContext;
use Illuminate\Database\Eloquent\Model;

final class SystemConfigurationAuditLogger
{
    /**
     * @param  list<string>  $changedKeys
     */
    public function record(string $event, array $changedKeys): void
    {
        $activity = activity('system_configuration_system')
            ->event($event)
            ->withProperties([
                ...CampusLogContext::enhanceLogProperties(),
                'scope' => 'global',
                'changed_keys' => $changedKeys,
            ]);

        $actor = auth()->user();
        if ($actor instanceof Model) {
            $activity->causedBy($actor);
        }

        $activity->log(match ($event) {
            'uploaded' => 'System configuration file uploaded',
            default => 'System configuration updated',
        });
    }
}
