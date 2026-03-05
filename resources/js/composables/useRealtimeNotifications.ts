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
export function useRealtimeNotifications(notifiableId: number | string | undefined, campusId?: number | null) {
    const notifications = ref<any[]>([]);
    const seenNotificationIds = new Set<string>();

    if (!notifiableId) {
        return { notifications };
    }

    const resolvedCampusId = campusId == null ? null : Number(campusId);

    const onNotification = (data: any) => {
        const id = String(data?.id ?? `${Date.now()}-${Math.random()}`);
        if (seenNotificationIds.has(id)) {
            return;
        }

        seenNotificationIds.add(id);
        notifications.value.unshift(data);

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
    };

    // New canonical channel: notify.{campusId}.{recipient_user_id}
    // Allow campusId = 0 for global notifications (see broadcasting docs)
    if (resolvedCampusId !== null && Number.isFinite(resolvedCampusId) && resolvedCampusId >= 0) {
        useEcho(`notify.${resolvedCampusId}.${notifiableId}`, '.NotificationCreated', onNotification);
    }

    return {
        notifications,
    };
}
