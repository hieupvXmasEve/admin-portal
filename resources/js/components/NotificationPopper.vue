<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { useApi } from '@/composables';
import { useRealtimeNotifications } from '@/composables/useRealtimeNotifications';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, Check, Loader2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import NotificationItem from './NotificationItem.vue';

const page = usePage<SharedData>();
// safely access user.id
const userId = computed(() => page.props.auth?.user?.id);
const campusId = computed(() => page.props.auth?.current_campus_id ?? 0);
console.log('campusId', campusId.value)
const api = useApi();

// Use the realtime composable to get new notifications and show toasts
const { notifications: realtimeNotifications } = useRealtimeNotifications(userId.value, campusId.value);

const notifications = ref<any[]>([]);
const isLoading = ref(false);
const isMarkingAll = ref(false);

const unreadCount = computed(() => notifications.value.filter((n) => !n.read_at).length);
const hasUnread = computed(() => unreadCount.value > 0);

const fetchNotifications = async () => {
    if (!userId.value) return;

    isLoading.value = true;
    try {
        const { data: apiData } = await api.get<any[]>(route('api.admin.notifications.index'));
        if (apiData.value?.success) {
            console.log('apiData.value.data', apiData.value.data)
            notifications.value = apiData.value.data;
        }
    } catch (error) {
        console.error('Failed to load notifications:', error);
    } finally {
        isLoading.value = false;
    }
};

const handleMarkAsRead = async (id: string) => {
    const notification = notifications.value.find((n) => n.id === id);
    if (!notification || notification.read_at) return;

    // Optimistic update
    notification.read_at = new Date().toISOString();
    // Decrease unread count handled by computed

    try {
        await api.post(route('api.admin.notifications.mark-as-read', { notification: id }), {});
    } catch (error) {
        console.error('Failed to mark as read:', error);
        // Revert? simpler to just log
    }
};

const handleMarkAllAsRead = async () => {
    if (!hasUnread.value || isMarkingAll.value) return;

    isMarkingAll.value = true;
    const previousState = JSON.parse(JSON.stringify(notifications.value));

    // Optimistic
    notifications.value.forEach((n) => {
        if (!n.read_at) n.read_at = new Date().toISOString();
    });

    try {
        const { data: apiData } = await api.post(route('api.admin.notifications.mark-all-read'), {});
        if (apiData.value?.success) {
            toast.success(apiData.value.message || 'All notifications marked as read');
        } else {
            throw new Error(apiData.value?.message || 'Failed to mark all as read');
        }
    } catch (error: any) {
        toast.error(error.message || 'Failed to mark all as read');
        notifications.value = previousState;
    } finally {
        isMarkingAll.value = false;
    }
};

// Sync realtime notifications into the list
watch(
    realtimeNotifications,
    (newVal) => {
        if (newVal && newVal.length > 0) {
            const latest = newVal[0];
            // Avoid duplicates if already fetched
            if (!notifications.value.some((n) => n.id === latest.id)) {
                // Ensure required fields
                const newNotification = {
                    ...latest,
                    read_at: null, // New is unread
                    created_at: new Date().toISOString(), // Fallback
                    data: latest.data || {},
                };
                notifications.value.unshift(newNotification);
            }
        }
    },
    { deep: true },
);

onMounted(() => {
    fetchNotifications();
});
</script>

<template>
    <Popover>
        <PopoverTrigger as-child>
            <div class="relative cursor-pointer">
                <slot>
                    <Button variant="outline" size="icon">
                        <Bell class="text-muted-foreground hover:text-foreground h-5 w-5 transition-colors" />
                    </Button>
                </slot>
                <Badge v-if="hasUnread" variant="destructive"
                    class="absolute -top-1 -right-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full px-1 text-[10px]">
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </Badge>
            </div>
        </PopoverTrigger>
        <PopoverContent class="w-80 p-0" align="end">
            <div class="flex items-center justify-between p-4">
                <div class="flex items-center gap-2">
                    <h3 class="font-semibold">Notifications</h3>
                    <Badge variant="secondary" class="rounded-full px-2 py-0.5 text-xs"> {{ unreadCount }} New </Badge>
                </div>
                <Button v-if="hasUnread" variant="ghost" size="sm" class="h-auto px-2 text-xs" :disabled="isMarkingAll"
                    @click="handleMarkAllAsRead">
                    <Loader2 v-if="isMarkingAll" class="mr-1 h-3 w-3 animate-spin" />
                    <Check v-else class="mr-1 h-3 w-3" />
                    Mark all read
                </Button>
            </div>
            <Separator />

            <ScrollArea class="h-80">
                <div v-if="isLoading" class="text-muted-foreground flex flex-col items-center justify-center py-8">
                    <Loader2 class="h-6 w-6 animate-spin" />
                    <span class="mt-2 text-sm">Loading...</span>
                </div>
                <div v-else-if="notifications.length === 0"
                    class="text-muted-foreground flex flex-col items-center justify-center py-8">
                    <Bell class="h-8 w-8 opacity-20" />
                    <p class="mt-2 text-sm">No notifications yet</p>
                </div>
                <div v-else class="flex flex-col divide-y">
                    <NotificationItem v-for="notification in notifications" :key="notification.id"
                        :notification="notification" @mark-as-read="handleMarkAsRead" />
                </div>
            </ScrollArea>

            <Separator />
            <div class="p-2">
                <Button variant="ghost" size="sm" class="w-full justify-center text-xs" as-child>
                    <Link href="/notifications"> View all notifications </Link>
                </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
