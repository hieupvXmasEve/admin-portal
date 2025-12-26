import { onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { createEcho } from '../lib/echo';

/**
 * Composable for Realtime Notifications
 *
 * Rules:
 * - Uses Laravel Echo (Rule 4.1)
 * - Uses standard Listening Syntax (Rule 4.3)
 * - Abstracted initialization (Rule 4.2)
 */
export function useRealtimeNotifications(notifiableId: number | string | undefined) {
    const echo = ref<any>(null);
    const notifications = ref<any[]>([]);

    const connect = async () => {
        if (!notifiableId) return;

        echo.value = await createEcho();

        if (!echo.value) return;

        // Listen for user-specific notifications
        // Rule 4.3: Standard Echo API only
        echo.value.private(`notifications.${notifiableId}`).listen('.NotificationCreated', (data: any) => {
            notifications.value.unshift(data);

            // Visual feedback (Rule 3 in Design Aesthetics)
            toast(data.title, {
                description: data.message,
                action: data.data?.action_url
                    ? {
                          label: data.data?.action_text || 'View',
                          onClick: () => (window.location.href = data.data.action_url),
                      }
                    : undefined,
            });
        });
    };

    const disconnect = () => {
        if (echo.value && notifiableId) {
            echo.value.leave(`notifications.${notifiableId}`);
        }
    };

    onMounted(connect);
    onUnmounted(disconnect);

    return {
        notifications,
        echo,
    };
}
