<script setup lang="ts">
import FilterPanel from '@/components/filters/FilterPanel.vue';
import FilterSearchInput from '@/components/filters/FilterSearchInput.vue';
import FilterSelect from '@/components/filters/FilterSelect.vue';
import ServerPaginatedDataTable from '@/components/tables/ServerPaginatedDataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useServerTableQuery } from '@/composables/useServerTableQuery';
import { createColumns } from '@/lib/table-utils';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { Check, Clock, RefreshCw } from 'lucide-vue-next';
import { computed, h } from 'vue';
import { route } from 'ziggy-js';

interface DeliveryInfo {
    id: number;
    channel: 'email' | 'realtime';
    status: string;
}

interface MessageEntry {
    id: number;
    event_id: string;
    type_key: string;
    recipient_user_id: number;
    campus_id: number;
    title: string;
    body: string;
    data: Record<string, unknown> | null;
    read_at: string | null;
    status: 'active' | 'archived';
    created_at: string;
    updated_at: string;
    recipient?: {
        id: number;
        name: string;
        email: string;
    };
    deliveries?: DeliveryInfo[];
}

interface Filters {
    search?: string;
    status?: string;
    type_key?: string;
    read_status?: string;
    per_page?: number;
    page?: number;
    sort?: string;
    direction?: 'asc' | 'desc';
}

interface Props {
    messages: PaginatedResponse<MessageEntry>;
    filters: Filters;
    statuses: Record<string, string>;
    typeKeys: string[];
}

const props = defineProps<Props>();

const { filters, hasActiveFilters, clearFilters, apply, applySearch, handleSortChange, handlePageChange, handlePageSizeChange, currentSort, currentDirection } =
    useServerTableQuery<Filters>({
        baseUrl: route('admin.notifications.ops.messages'),
        initialFilters: {
            search: props.filters.search ?? '',
            status: props.filters.status ?? '',
            type_key: props.filters.type_key ?? '',
            read_status: props.filters.read_status ?? '',
            sort: props.filters.sort ?? 'created_at',
            direction: props.filters.direction ?? 'desc',
            per_page: props.filters.per_page ?? 15,
            page: 1,
        },
        emptyFilters: {
            search: '',
            status: '',
            type_key: '',
            read_status: '',
            sort: 'created_at',
            direction: 'desc',
            per_page: 15,
            page: 1,
        },
        defaultValues: { sort: 'created_at', direction: 'desc', per_page: 15, page: 1 },
        only: ['messages', 'filters'],
    });

const statusOptions = computed(() => Object.entries(props.statuses).map(([value, label]) => ({ value, label })));
const typeKeyOptions = computed(() => props.typeKeys.map((key) => ({ value: key, label: key })));
const readStatusOptions = [
    { value: 'read', label: 'Read' },
    { value: 'unread', label: 'Unread' },
];

const getStatusVariant = (status: string) => {
    return status === 'active' ? 'default' : 'secondary';
};

const getReadIcon = (readAt: string | null) => {
    if (readAt) {
        return h(
            TooltipProvider,
            { delayDuration: 0 },
            () =>
                h(Tooltip, {}, () => [
                    h(TooltipTrigger, { asChild: true }, () => h(Check, { class: 'h-4 w-4 text-green-600' })),
                    h(TooltipContent, {}, () => `Read ${formatDistanceToNow(new Date(readAt), { addSuffix: true })}`),
                ]),
        );
    }
    return h(Clock, { class: 'h-4 w-4 text-muted-foreground' });
};

const getDeliveryStatus = (deliveries: DeliveryInfo[] | undefined) => {
    if (!deliveries || deliveries.length === 0) return '-';

    const badges = deliveries.map((d) => {
        const variant = d.status === 'sent' ? 'default' : d.status === 'failed' ? 'destructive' : 'secondary';
        return h(Badge, { variant, class: 'mr-1 text-xs' }, () => `${d.channel}:${d.status}`);
    });

    return h('div', { class: 'flex flex-wrap gap-1' }, badges);
};

const columns = createColumns<MessageEntry>([
    {
        accessorKey: 'title',
        header: 'Title',
        cell: ({ row }) => {
            const title = row.getValue('title') as string;
            return title.length > 35 ? title.substring(0, 35) + '...' : title;
        },
    },
    {
        id: 'recipient',
        header: 'Recipient',
        cell: ({ row }) => {
            const recipient = row.original.recipient;
            return recipient?.name || recipient?.email || '-';
        },
    },
    {
        accessorKey: 'type_key',
        header: 'Type',
        enableSorting: true,
    },
    {
        accessorKey: 'status',
        header: 'Status',
        enableSorting: true,
        cell: ({ row }) => {
            const status = row.getValue('status') as string;
            return h(Badge, { variant: getStatusVariant(status) }, () => status.charAt(0).toUpperCase() + status.slice(1));
        },
    },
    {
        accessorKey: 'read_at',
        header: 'Read',
        enableSorting: true,
        cell: ({ row }) => getReadIcon(row.getValue('read_at') as string | null),
    },
    {
        id: 'deliveries',
        header: 'Channels',
        cell: ({ row }) => getDeliveryStatus(row.original.deliveries),
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        enableSorting: true,
        cell: ({ row }) => {
            const date = new Date(row.getValue('created_at') as string);
            return formatDistanceToNow(date, { addSuffix: true });
        },
    },
]);

const statsData = computed(() => {
    const data = props.messages.data;
    return {
        total: data.length,
        read: data.filter((m) => m.read_at !== null).length,
        unread: data.filter((m) => m.read_at === null).length,
        active: data.filter((m) => m.status === 'active').length,
    };
});
</script>

<template>
    <div class="space-y-6">
        <Head title="Notification Ops - Messages" />

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Notification Messages</h1>
                <p class="text-muted-foreground mt-2">View all notification messages sent to users</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('admin.notifications.ops.outbox')" as="button">
                    <Button variant="outline">Outbox</Button>
                </Link>
                <Link :href="route('admin.notifications.ops.deliveries')" as="button">
                    <Button variant="outline">Deliveries</Button>
                </Link>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Total (page)</CardDescription>
                    <CardTitle class="text-2xl">{{ statsData.total }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Read</CardDescription>
                    <CardTitle class="text-2xl text-green-600">{{ statsData.read }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Unread</CardDescription>
                    <CardTitle class="text-2xl text-amber-600">{{ statsData.unread }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Active</CardDescription>
                    <CardTitle class="text-2xl">{{ statsData.active }}</CardTitle>
                </CardHeader>
            </Card>
        </div>

        <!-- Filters -->
        <Card>
            <CardHeader>
                <CardTitle>Filter</CardTitle>
            </CardHeader>
            <CardContent>
                <FilterPanel :has-active-filters="hasActiveFilters" :columns="6" @clear="clearFilters">
                    <FilterSearchInput
                        :model-value="filters.search ?? ''"
                        placeholder="Title, event ID..."
                        @update:model-value="(v) => (filters.search = v)"
                        @search="applySearch"
                    />
                    <FilterSelect
                        :model-value="filters.status ?? ''"
                        :options="statusOptions"
                        placeholder="All statuses"
                        all-label="All statuses"
                        @change="(v) => apply({ status: v, page: 1 })"
                    />
                    <FilterSelect
                        :model-value="filters.type_key ?? ''"
                        :options="typeKeyOptions"
                        placeholder="All types"
                        all-label="All types"
                        @change="(v) => apply({ type_key: v, page: 1 })"
                    />
                    <FilterSelect
                        :model-value="filters.read_status ?? ''"
                        :options="readStatusOptions"
                        placeholder="Read status"
                        all-label="Any"
                        @change="(v) => apply({ read_status: v, page: 1 })"
                    />
                    <Button variant="outline" @click="() => router.reload({ only: ['messages'] })">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </FilterPanel>
            </CardContent>
        </Card>

        <!-- Table -->
        <Card>
            <CardHeader>
                <CardTitle>Messages</CardTitle>
                <CardDescription>Showing {{ messages.from ?? 0 }} to {{ messages.to ?? 0 }} of {{ messages.total }} messages</CardDescription>
            </CardHeader>
            <CardContent>
                <ServerPaginatedDataTable
                    :data="messages.data"
                    :columns="columns"
                    :pagination-data="messages"
                    :initial-sort="currentSort"
                    :initial-direction="currentDirection"
                    item-name="messages"
                    empty-message="No messages found"
                    @sort-change="handleSortChange"
                    @page-change="handlePageChange"
                    @page-size-change="handlePageSizeChange"
                />
            </CardContent>
        </Card>
    </div>
</template>
