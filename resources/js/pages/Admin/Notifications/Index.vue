<script setup lang="ts">
import { computed, h } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Search, X, Send, User as UserIcon } from 'lucide-vue-next';
import { format } from 'date-fns';
import { route } from 'ziggy-js';
import type { ColumnDef } from '@tanstack/vue-table';

import DataTable from '@/components/DataTable.vue';
import DataPagination from '@/components/DataPagination.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { NOTIFICATION_ROUTE_NAMES } from '@/constants/notification-routes';
import type { PaginatedResponse } from '@/types';

interface Notification {
    id: number;
    category: string;
    title: string;
    message: string;
    is_important: boolean;
    notifiable_type: string;
    notifiable_id: number;
    notifiable?: {
        id: number;
        name?: string;
        full_name?: string;
        student_code?: string;
        email: string;
    };
    created_at: string;
}

interface NotificationFilters {
    search: string;
    category: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
    page: number;
}

interface Props {
    notifications: PaginatedResponse<Notification>;
    filters: Partial<NotificationFilters>;
    categories: Array<{ value: string; label: string; description: string }>;
}

const props = defineProps<Props>();

const {
    filters,
    hasActiveFilters,
    clearFilters,
    handleSearch,
    handleSelectFilter,
    handleSortChange,
    handlePageSizeChange,
    handlePaginationNavigate,
    currentSort,
    currentDirection
} = useInertiaFilters<NotificationFilters>({
    baseUrl: route(NOTIFICATION_ROUTE_NAMES.INDEX),
    initialFilters: {
        search: (typeof props.filters.search === 'string' ? props.filters.search : '') || '',
        category: (typeof props.filters.category === 'string' ? props.filters.category : 'all') || 'all',
        sort: (typeof props.filters.sort === 'string' ? props.filters.sort : 'created_at') || 'created_at',
        direction: (props.filters.direction as 'asc' | 'desc') || 'desc',
        per_page: props.filters.per_page || 15,
        page: props.notifications.current_page || 1,
    },
    defaultValues: {
        category: 'all',
        per_page: 15,
        direction: 'desc',
        sort: 'created_at',
    },
    only: ['notifications', 'filters'],
});

const columns: ColumnDef<Notification>[] = [
    {
        accessorKey: 'created_at',
        header: 'Sent At',
        enableSorting: true,
        cell: ({ row }) => format(new Date(row.original.created_at), 'MMM dd, yyyy HH:mm'),
    },
    {
        accessorKey: 'notifiable',
        header: 'Recipient',
        enableSorting: false,
        cell: ({ row }) => {
            const notifiable = row.original.notifiable;
            if (!notifiable) return h('span', { class: 'text-gray-400' }, 'Unknown');
            return h('div', { class: 'flex items-center gap-2' }, [
                h(UserIcon, { class: 'h-4 w-4 text-gray-400' }),
                h('div', [
                    h('div', { class: 'font-medium' }, notifiable.name || notifiable.full_name),
                    h('div', { class: 'text-xs text-gray-500' }, notifiable.student_code || notifiable.email)
                ])
            ]);
        }
    },
    {
        accessorKey: 'category',
        header: 'Category',
        enableSorting: true,
        cell: ({ row }) => h(Badge, { variant: 'outline', class: 'capitalize' }, () => row.original.category),
    },
    {
        accessorKey: 'title',
        header: 'Title',
        enableSorting: true,
        cell: ({ row }) => h('div', { class: 'max-w-[200px] truncate font-medium' }, row.original.title),
    },
    {
        accessorKey: 'message',
        header: 'Message',
        enableSorting: false,
        cell: ({ row }) => h('div', { class: 'max-w-[300px] truncate text-sm text-gray-600' }, row.original.message),
    },
    {
        accessorKey: 'is_important',
        header: 'Priority',
        enableSorting: true,
        cell: ({ row }) => row.original.is_important
            ? h(Badge, { variant: 'destructive' }, () => 'Important')
            : h(Badge, { variant: 'secondary' }, () => 'Normal'),
    }
];

const data = computed(() => props.notifications.data);
</script>

<template>

    <Head title="Notification History" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Notification History</h1>
                <p class="text-muted-foreground text-sm">View all notifications sent throughout the system.</p>
            </div>
            <Button @click="router.visit(route(NOTIFICATION_ROUTE_NAMES.SEND))">
                <Send class="mr-2 h-4 w-4" />
                Send Notification
            </Button>
        </div>

        <Card>
            <CardHeader class="pb-3">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-1 items-center gap-2">
                        <DebouncedInput :model-value="filters.search" placeholder="Search by title or message..."
                            class="max-w-sm" @update:model-value="handleSearch" />
                        <Select v-model="filters.category"
                            @update:model-value="(val) => handleSelectFilter('category', val)">
                            <SelectTrigger class="w-[180px]">
                                <SelectValue placeholder="Category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Categories</SelectItem>
                                <SelectItem v-for="cat in categories" :key="cat.value" :value="cat.value">
                                    {{ cat.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
                            <X class="mr-2 h-4 w-4" />
                            Reset
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <DataTable :columns="columns" :data="data" :initial-sort="currentSort"
                    :initial-direction="currentDirection" enable-server-sorting @sort-change="handleSortChange" />

                <div class="mt-4">
                    <DataPagination :pagination-data="notifications" item-name="notifications"
                        @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
