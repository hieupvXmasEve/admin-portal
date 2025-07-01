<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User | null;
    showEmail?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: true,
    class: '',
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(() => Boolean(props.user?.avatar && props.user?.avatar !== ''));
</script>

<template>
    <div :class="`flex items-center gap-3 ${props.class}`">
        <Avatar class="h-9 w-9 overflow-hidden rounded-lg border-2 border-white shadow-sm dark:border-gray-700">
            <AvatarImage v-if="showAvatar" :src="user?.avatar || ''" :alt="user?.name || ''" />
            <AvatarFallback class="rounded-lg bg-blue-600 font-semibold text-white dark:bg-blue-500">
                {{ getInitials(user?.name || '') }}
            </AvatarFallback>
        </Avatar>

        <div class="grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden">
            <span class="truncate font-semibold text-gray-900 dark:text-gray-100">{{ user?.name || '' }}</span>
            <span v-if="showEmail" class="truncate text-xs text-gray-500 dark:text-gray-400">{{ user?.email || '' }}</span>
        </div>
    </div>
</template>
