<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Circle, ExternalLink } from 'lucide-vue-next';
import { formatDistanceToNow } from 'date-fns';
import { router } from '@inertiajs/vue3';

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

const handleClick = () => {
    emit('click', props.notification);
    if (!props.notification.read_at) {
        emit('mark-as-read', props.notification.id);
    }

    // Handle navigation if action_url exists
    if (props.notification.data?.action_url) {
        if (props.notification.data.action_url.startsWith('http')) {
            // open new tab
            window.open(props.notification.data.action_url, '_blank');
        } else {
            // using intertiajs'router
            router.visit(props.notification.data.action_url);
        }
    } else {
        // TODO: Redirect to notification detail
    }
};

const timeAgo = formatDistanceToNow(new Date(props.notification.created_at), { addSuffix: true });
</script>

<template>
    <div :class="cn(
        'flex cursor-pointer items-start gap-3 p-3 transition-colors hover:bg-muted/50',
        !notification.read_at && 'bg-primary/5'
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

                <Badge v-if="notification.data?.category" variant="outline" class="h-4 px-1 text-[10px] font-normal">
                    {{ notification.data.category }}
                </Badge>
            </div>
        </div>
    </div>
</template>
