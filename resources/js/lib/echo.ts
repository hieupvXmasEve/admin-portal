import Echo from 'laravel-echo';

/**
 * Realtime Echo Instance Factory
 *
 * Rules:
 * - No vendor lock-in (Ably, Pusher, etc. are interchangeable)
 * - Broadcaster is ENV-driven (VITE_BROADCASTER)
 * - Abstraction layer ensures components don't care about the driver
 */
export async function createEcho() {
    const broadcaster = import.meta.env.VITE_BROADCASTER || 'null';

    // Handle dummy/null broadcaster
    if (broadcaster === 'null' || !import.meta.env.VITE_BROADCAST_KEY) {
        return null;
    }

    const config: any = {
        broadcaster: broadcaster,
        key: import.meta.env.VITE_BROADCAST_KEY,
        forceTLS: true,
    };

    // Driver-specific initialization (Infra only)
    if (broadcaster === 'ably') {
        const Ably = await import('ably');
        (window as any).Ably = Ably;
    } else if (broadcaster === 'pusher') {
        const Pusher = await import('pusher-js');
        (window as any).Pusher = Pusher.default;

        if (import.meta.env.VITE_SOCKET_HOST) {
            config.wsHost = import.meta.env.VITE_SOCKET_HOST;
            config.wsPort = import.meta.env.VITE_SOCKET_PORT || 443;
            config.wssPort = import.meta.env.VITE_SOCKET_PORT || 443;
            config.disableStats = true;
            config.enabledTransports = ['ws', 'wss'];
        }
    }

    return new Echo(config);
}
