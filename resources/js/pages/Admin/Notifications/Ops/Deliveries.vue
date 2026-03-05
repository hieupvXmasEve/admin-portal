<script setup lang="ts">
import FilterPanel from '@/components/filters/FilterPanel.vue';
import FilterSearchInput from '@/components/filters/FilterSearchInput.vue';
import FilterSelect from '@/components/filters/FilterSelect.vue';
import ServerPaginatedDataTable from '@/components/tables/ServerPaginatedDataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useApi } from '@/composables/useApiRequest';
import { useServerTableQuery } from '@/composables/useServerTableQuery';
import { createColumns } from '@/lib/table-utils';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { AlertCircle, Loader2, RefreshCw, RotateCcw } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface DeliveryEntry {
    id: number;
    message_id: number;
    channel: 'email' | 'realtime';
    status: 'pending' | 'sent' | 'failed' | 'skipped';
    attempts: number;
    last_error: string | null;
    queued_at: string | null;
    sent_at: string | null;
    created_at: string;
    updated_at: string;
    message?: {
        id: number;
        event_id: string;
        type_key: string;
        title: string;
        recipient_user_id: number;
        campus_id: number;
        recipient?: {
            id: number;
            name: string;
            email: string;
        };
    };
}

interface Filters {
    search?: string;
    status?: string;
    channel?: string;
    has_error?: string;
    per_page?: number;
    page?: number;
    sort?: string;
    direction?: 'asc' | 'desc';
}

interface Props {
    deliveries: PaginatedResponse<DeliveryEntry>;
    filters: Filters;
    statuses: Record<string, string>;
    channels: Record<string, string>;
}

const props = defineProps<Props>();
const api = useApi();

const retryingIds = ref<Set<number>>(new Set());

const { filters, hasActiveFilters, clearFilters, apply, applySearch, handleSortChange, handlePageChange, handlePageSizeChange, currentSort, currentDirection } =
    useServerTableQuery<Filters>({
        baseUrl: route('admin.notifications.ops.deliveries'),
        initialFilters: {
            search: props.filters.search ?? '',
            status: props.filters.status ?? '',
            channel: props.filters.channel ?? '',
            has_error: props.filters.has_error ?? '',
            sort: props.filters.sort ?? 'created_at',
            direction: props.filters.direction ?? 'desc',
            per_page: props.filters.per_page ?? 15,
            page: 1,
        },
        emptyFilters: {
            search: '',
            status: '',
            channel: '',
            has_error: '',
            sort: 'created_at',
            direction: 'desc',
            per_page: 15,
            page: 1,
        },
        defaultValues: { sort: 'created_at', direction: 'desc', per_page: 15, page: 1 },
        only: ['deliveries', 'filters'],
    });

const statusOptions = computed(() => Object.entries(props.statuses).map(([value, label]) => ({ value, label })));
const channelOptions = computed(() => Object.entries(props.channels).map(([value, label]) => ({ value, label })));
const hasErrorOptions = [
    { value: 'yes', label: 'Yes' },
    { value: 'no', label: 'No' },
];

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'sent':
            return 'default';
        case 'failed':
            return 'destructive';
        case 'pending':
            return 'secondary';
        case 'skipped':
            return 'outline';
        default:
            return 'outline';
    }
};

const getChannelVariant = (channel: string) => {
    return channel === 'email' ? 'secondary' : 'outline';
};

const canRetry = (entry: DeliveryEntry) => {
    return entry.status === 'failed' || entry.status === 'pending';
};

const retryDelivery = async (entry: DeliveryEntry) => {
    if (!canRetry(entry) || retryingIds.value.has(entry.id)) return;

    try {
        retryingIds.value = new Set([...retryingIds.value, entry.id]);
        const response = await api.post(route('admin.notifications.ops.deliveries.retry', entry.id), {});

        if (response.data.value?.success) {
            toast.success('Delivery queued for retry');
            router.reload({ only: ['deliveries'] });
        } else {
            throw new Error(response.data.value?.message || 'Failed to retry');
        }
    } catch (error: unknown) {
        const message = error instanceof Error ? error.message : 'Failed to retry delivery';
        toast.error(message);
    } finally {
        const next = new Set(retryingIds.value);
        next.delete(entry.id);
        retryingIds.value = next;
    }
};

const columns = createColumns<DeliveryEntry>([
    {
        accessorKey: 'message',
        header: 'Title',
        cell: ({ row }) => {
            const message = row.getValue('message') as DeliveryEntry['message'];
            const title = message?.title || '-';
            return title.length > 30 ? title.substring(0, 30) + '...' : title;
        },
    },
    {
        id: 'recipient',
        header: 'Recipient',
        cell: ({ row }) => {
            const message = row.original.message;
            return message?.recipient?.name || message?.recipient?.email || '-';
        },
    },
    {
        accessorKey: 'channel',
        header: 'Channel',
        enableSorting: true,
        cell: ({ row }) => {
            const channel = row.getValue('channel') as string;
            return h(Badge, { variant: getChannelVariant(channel) }, () => channel.charAt(0).toUpperCase() + channel.slice(1));
        },
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
        accessorKey: 'attempts',
        header: 'Attempts',
        enableSorting: true,
    },
    {
        accessorKey: 'last_error',
        header: 'Error',
        cell: ({ row }) => {
            const error = row.getValue('last_error') as string | null;
            if (!error) return '-';
            return h(
                TooltipProvider,
                { delayDuration: 0, ignoreNonKeyboardFocus: true, disableHoverableContent: true },
                () =>
                    h(Tooltip, {}, () => [
                        h(TooltipTrigger, { asChild: true }, () => h(AlertCircle, { class: 'h-4 w-4 text-destructive' })),
                        h(TooltipContent, { class: 'max-w-xs' }, () => error.substring(0, 200)),
                    ]),
            );
        },
    },
    {
        accessorKey: 'sent_at',
        header: 'Sent At',
        enableSorting: true,
        cell: ({ row }) => {
            const date = row.getValue('sent_at') as string | null;
            if (!date) return '-';
            return formatDistanceToNow(new Date(date), { addSuffix: true });
        },
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
    {
        id: 'actions',
        header: 'Actions',
        enableSorting: false,
        enableHiding: false,
        cell: 'actions',
    },
]);

const statsData = computed(() => {
    const data = props.deliveries.data;
    return {
        pending: data.filter((e) => e.status === 'pending').length,
        sent: data.filter((e) => e.status === 'sent').length,
        failed: data.filter((e) => e.status === 'failed').length,
        skipped: data.filter((e) => e.status === 'skipped').length,
    };
});
</script>

<template>
    <div class="space-y-6">
        <Head title="Notification Ops - Deliveries" />

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Notification Deliveries</h1>
                <p class="text-muted-foreground mt-2">Monitor notification delivery status per channel</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('admin.notifications.ops.outbox')" as="button">
                    <Button variant="outline">Outbox</Button>
                </Link>
                <Link :href="route('admin.notifications.ops.messages')" as="button">
                    <Button variant="outline">Messages</Button>
                </Link>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Pending</CardDescription>
                    <CardTitle class="text-2xl">{{ statsData.pending }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Sent</CardDescription>
                    <CardTitle class="text-2xl text-green-600">{{ statsData.sent }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Failed</CardDescription>
                    <CardTitle class="text-2xl text-destructive">{{ statsData.failed }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Skipped</CardDescription>
                    <CardTitle class="text-2xl text-muted-foreground">{{ statsData.skipped }}</CardTitle>
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
                        :model-value="filters.channel ?? ''"
                        :options="channelOptions"
                        placeholder="All channels"
                        all-label="All channels"
                        @change="(v) => apply({ channel: v, page: 1 })"
                    />
                    <FilterSelect
                        :model-value="filters.has_error ?? ''"
                        :options="hasErrorOptions"
                        placeholder="Has error"
                        all-label="Any"
                        @change="(v) => apply({ has_error: v, page: 1 })"
                    />
                    <Button variant="outline" @click="() => router.reload({ only: ['deliveries'] })">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </FilterPanel>
            </CardContent>
        </Card>

        <!-- Table -->
        <Card>
            <CardHeader>
                <CardTitle>Deliveries</CardTitle>
                <CardDescription>Showing {{ deliveries.from ?? 0 }} to {{ deliveries.to ?? 0 }} of {{ deliveries.total }} deliveries</CardDescription>
            </CardHeader>
            <CardContent>
                <ServerPaginatedDataTable
                    :data="deliveries.data"
                    :columns="columns"
                    :pagination-data="deliveries"
                    :initial-sort="currentSort"
                    :initial-direction="currentDirection"
                    item-name="deliveries"
                    empty-message="No deliveries found"
                    @sort-change="handleSortChange"
                    @page-change="handlePageChange"
                    @page-size-change="handlePageSizeChange"
                >
                    <template #cell-actions="{ row }">
                        <div class="flex items-center gap-2">
                            <Button v-if="canRetry(row.original)" size="sm" variant="outline" :disabled="retryingIds.has(row.original.id)" @click="retryDelivery(row.original)">
                                <Loader2 v-if="retryingIds.has(row.original.id)" class="mr-2 h-4 w-4 animate-spin" />
                                <RotateCcw v-else class="mr-2 h-4 w-4" />
                                Retry
                            </Button>
                        </div>
                    </template>
                </ServerPaginatedDataTable>
            </CardContent>
        </Card>
    </div>
</template>
