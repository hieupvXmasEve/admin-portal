<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Circle, ExternalLink } from 'lucide-vue-next';
import { formatDistanceToNow } from 'date-fns';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface NotificationCategory {
    key: string;
    label: string;
    color: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
}

interface NotificationType {
    id: string;
    title: string;
    message: string;
    created_at: string;
    read_at: string | null;
    type: string;
    data: {
        action_url?: string;
        action_text?: string;
        icon?: string;
        category?: NotificationCategory;
        [key: string]: any;
    };
}

const props = defineProps<{
    notification: NotificationType;
}>();

const emit = defineEmits<{
    (e: 'mark-as-read', id: string): void;
    (e: 'click', notification: NotificationType): void;
}>();

const hasActionUrl = computed(() => !!props.notification.data?.action_url);
const actionText = computed(() => props.notification.data?.action_text || 'View Details');
const category = computed(() => props.notification.data?.category);

const categoryColorClasses = computed(() => {
    const color = category.value?.color;
    if (!color) return '';

    const colorMap: Record<string, string> = {
        blue: 'border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-400',
        green: 'border-green-500 text-green-600 dark:border-green-400 dark:text-green-400',
        purple: 'border-purple-500 text-purple-600 dark:border-purple-400 dark:text-purple-400',
        red: 'border-red-500 text-red-600 dark:border-red-400 dark:text-red-400',
        yellow: 'border-yellow-500 text-yellow-600 dark:border-yellow-400 dark:text-yellow-400',
        gray: 'border-gray-500 text-gray-600 dark:border-gray-400 dark:text-gray-400',
    };

    return colorMap[color] || '';
});

const handleClick = () => {
    emit('click', props.notification);
    if (!props.notification.read_at) {
        emit('mark-as-read', props.notification.id);
    }
};

const handleActionClick = (event: Event) => {
    event.stopPropagation();

    if (!props.notification.read_at) {
        emit('mark-as-read', props.notification.id);
    }

    const actionUrl = props.notification.data?.action_url;
    if (actionUrl) {
        router.visit(actionUrl);
    }
};

const timeAgo = formatDistanceToNow(new Date(props.notification.created_at), { addSuffix: true });
</script>

<template>
    <div :class="cn(
        'flex cursor-pointer items-start gap-3 p-3 transition-colors hover:bg-muted/50',
        !notification.read_at && 'bg-primary/5',
    )" @click="handleClick">
        <!-- Unread indicator -->
        <div class="pt-1">
            <Circle v-if="!notification.read_at" class="h-2 w-2 fill-current text-primary" />
            <div v-else class="h-2 w-2" />
        </div>

        <!-- Content -->
        <div class="flex-1 space-y-1">
            <div class="flex items-start justify-between gap-2">
                <h4 :class="cn('text-sm leading-none font-medium', !notification.read_at && 'font-semibold')">
                    {{ notification.title || notification.data?.title || 'Notification' }}
                </h4>
            </div>

            <p class="text-xs text-muted-foreground line-clamp-2">
                {{ notification.message || notification.data?.message }}
            </p>

            <div class="flex items-center justify-between pt-1">
                <span class="text-[10px] text-muted-foreground">
                    {{ timeAgo }}
                </span>

                <div class="flex items-center gap-1">
                    <Badge
                        v-if="category"
                        :variant="category.variant || 'outline'"
                        :class="cn('h-4 px-1 text-[10px] font-normal', categoryColorClasses)"
                    >
                        {{ category.label }}
                    </Badge>
                </div>
            </div>

            <!-- Action Button -->
            <div v-if="hasActionUrl" class="pt-1">
                <Button variant="outline" size="sm" class="h-6 gap-1 px-2 text-xs" @click="handleActionClick">
                    <ExternalLink class="h-3 w-3" />
                    {{ actionText }}
                </Button>
            </div>
        </div>
    </div>
</template>
