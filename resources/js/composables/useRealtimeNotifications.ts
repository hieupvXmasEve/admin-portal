import { useEcho } from '@laravel/echo-vue';
import { BellIcon } from 'lucide-vue-next';
import { h, ref } from 'vue';
import { toast } from 'vue-sonner';

/**
 * Composable for Realtime Notifications
 *
 * Rules:
 * - Uses Laravel Echo via @laravel/echo-vue (Rule 4.1)
 * - Uses standard Listening Syntax (Rule 4.3)
 */
export function useRealtimeNotifications(notifiableId: number | string | undefined) {
    const notifications = ref<any[]>([]);

    if (!notifiableId) {
        return { notifications };
    }

    // Automatically connects and listens
    useEcho(`notifications.${notifiableId}`, '.NotificationCreated', (data: any) => {
        notifications.value.unshift(data);

        // Visual feedback (Rule 3 in Design Aesthetics)
        toast('New Notification', {
            description: 'You have a new notification from the system.',
            position: 'top-right',
            duration: 5000,
            icon: h(BellIcon, { style: 'color: #3b82f6; width: 20px; height: 20px' }),
            classes: {
                toast: 'my-custom-toast',
                title: 'my-toast-title',
                description: 'my-toast-description',
            },
        });
    });

    return {
        notifications,
    };
}
