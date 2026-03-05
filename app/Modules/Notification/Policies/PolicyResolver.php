<?php

declare(strict_types=1);

namespace App\Modules\Notification\Policies;

use App\Modules\Notification\Domain\Contracts\NotificationIntent;
use Illuminate\Contracts\Config\Repository;

class PolicyResolver
{
    /**
     * @param  array<int, string>|null  $allowedChannels
     * @param  array<int, string>|null  $globalAllowlist
     */
    public function __construct(
        private readonly ?array $allowedChannels = null,
        private readonly ?bool $strictIsolation = null,
        private readonly ?array $globalAllowlist = null,
    ) {}

    /**
     * @return array{allow_channels:array<int, string>,deny_channels:array<int, string>,reason:string}
     */
    public function decide(NotificationIntent $intent, string $eventName, ?int $eventCampusId): array
    {
        $config = app()->bound('config') ? app('config') : null;
        $allowedChannels = $this->allowedChannels ?? $this->configArray($config, 'notification.channels.allowed', ['email', 'realtime']);
        $strictIsolation = $this->strictIsolation ?? $this->configBool($config, 'notification.campus.strict_isolation', true);
        $globalAllowlist = $this->globalAllowlist ?? $this->configArray($config, 'notification.campus.global_event_allowlist', []);

        if ($strictIsolation && ! $eventCampusId && ! $this->isGlobalAllowed($eventName, $globalAllowlist)) {
            return [
                'allow_channels' => [],
                'deny_channels' => $intent->channels,
                'reason' => 'strict_campus_isolation',
            ];
        }

        $allowChannels = array_values(array_intersect($intent->channels, $allowedChannels));
        $denyChannels = array_values(array_diff($intent->channels, $allowChannels));

        return [
            'allow_channels' => $allowChannels,
            'deny_channels' => $denyChannels,
            'reason' => $denyChannels ? 'channel_not_allowed' : 'allow_all',
        ];
    }

    /**
     * @param  array<int, string>  $globalAllowlist
     */
    private function isGlobalAllowed(string $eventName, array $globalAllowlist): bool
    {
        foreach ($globalAllowlist as $prefix) {
            if (str_starts_with($eventName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $default
     * @return array<int, string>
     */
    private function configArray(?Repository $config, string $key, array $default): array
    {
        if (! $config) {
            return $default;
        }

        $value = $config->get($key, $default);

        return is_array($value) ? array_values(array_map('strval', $value)) : $default;
    }

    private function configBool(?Repository $config, string $key, bool $default): bool
    {
        if (! $config) {
            return $default;
        }

        return (bool) $config->get($key, $default);
    }
}
