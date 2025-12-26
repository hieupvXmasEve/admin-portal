import { configureEcho } from '@laravel/echo-vue';
import Pusher from 'pusher-js';

/**
 * Configure Laravel Echo (Singleton via @laravel/echo-vue)
 *
 * Rules:
 * - No vendor lock-in (Ably, Pusher, etc. are interchangeable)
 * - Broadcaster is ENV-driven (VITE_BROADCASTER)
 * - Supports Ably via Pusher compatibility mode
 */
export function setupEcho(): void {
    const broadcaster = import.meta.env.VITE_BROADCASTER || 'null';
    const key = import.meta.env.VITE_BROADCAST_KEY;

    if (broadcaster === 'null' || !key) {
        return;
    }

    const config: any = {
        broadcaster: broadcaster === 'ably' ? 'pusher' : broadcaster,
        key: broadcaster === 'ably' && key.includes(':') ? key.split(':')[0] : key,
        cluster: import.meta.env.VITE_PUSH_CLUSTER || 'mt1',
        forceTLS: true,
    };

    try {
        // Driver-specific initialization
        if (broadcaster === 'ably' || broadcaster === 'pusher') {
            (window as any).Pusher = Pusher;

            if (broadcaster === 'ably') {
                config.wsHost = 'realtime-pusher.ably.io';
                config.wsPort = 443;
                config.disableStats = true;
                config.encrypted = true;
            } else if (import.meta.env.VITE_SOCKET_HOST) {
                config.wsHost = import.meta.env.VITE_SOCKET_HOST;
                config.wsPort = import.meta.env.VITE_SOCKET_PORT || 443;
                config.wssPort = import.meta.env.VITE_SOCKET_PORT || 443;
                config.disableStats = true;
                config.enabledTransports = ['ws', 'wss'];
            }
        }

        configureEcho(config);
    } catch (error) {
        console.error('Failed to initialize Laravel Echo:', error);
    }
}
