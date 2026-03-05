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
import { AlertCircle, Eye, Loader2, RefreshCw, RotateCcw } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface OutboxEntry {
    id: number;
    event_id: string;
    event_name: string;
    event_version: number;
    occurred_at: string;
    aggregate_type: string;
    aggregate_id: string;
    campus_id: number;
    actor_user_id: number | null;
    payload: Record<string, unknown>;
    status: 'pending' | 'processing' | 'dispatched' | 'failed';
    attempts: number;
    last_error: string | null;
    last_attempt_at: string | null;
    created_at: string;
    updated_at: string;
}

interface Filters {
    search?: string;
    status?: string;
    event_name?: string;
    has_error?: string;
    per_page?: number;
    page?: number;
    sort?: string;
    direction?: 'asc' | 'desc';
}

interface Props {
    outbox: PaginatedResponse<OutboxEntry>;
    filters: Filters;
    statuses: Record<string, string>;
    eventNames: string[];
}

const props = defineProps<Props>();
const api = useApi();

const retryingIds = ref<Set<number>>(new Set());

const { filters, hasActiveFilters, clearFilters, apply, applySearch, handleSortChange, handlePageChange, handlePageSizeChange, currentSort, currentDirection } =
    useServerTableQuery<Filters>({
        baseUrl: route('admin.notifications.ops.outbox'),
        initialFilters: {
            search: props.filters.search ?? '',
            status: props.filters.status ?? '',
            event_name: props.filters.event_name ?? '',
            has_error: props.filters.has_error ?? '',
            sort: props.filters.sort ?? 'occurred_at',
            direction: props.filters.direction ?? 'desc',
            per_page: props.filters.per_page ?? 15,
            page: 1,
        },
        emptyFilters: {
            search: '',
            status: '',
            event_name: '',
            has_error: '',
            sort: 'occurred_at',
            direction: 'desc',
            per_page: 15,
            page: 1,
        },
        defaultValues: { sort: 'occurred_at', direction: 'desc', per_page: 15, page: 1 },
        only: ['outbox', 'filters'],
    });

const statusOptions = computed(() => Object.entries(props.statuses).map(([value, label]) => ({ value, label })));
const eventNameOptions = computed(() => props.eventNames.map((name) => ({ value: name, label: name })));
const hasErrorOptions = [
    { value: 'yes', label: 'Yes' },
    { value: 'no', label: 'No' },
];

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'dispatched':
            return 'default';
        case 'failed':
            return 'destructive';
        case 'pending':
        case 'processing':
            return 'secondary';
        default:
            return 'outline';
    }
};

const canRetry = (entry: OutboxEntry) => {
    return entry.status === 'failed' || entry.status === 'pending';
};

const retryOutbox = async (entry: OutboxEntry) => {
    if (!canRetry(entry) || retryingIds.value.has(entry.id)) return;

    try {
        retryingIds.value = new Set([...retryingIds.value, entry.id]);
        const response = await api.post(route('admin.notifications.ops.outbox.retry', entry.id), {});

        if (response.data.value?.success) {
            toast.success('Outbox entry queued for retry');
            router.reload({ only: ['outbox'] });
        } else {
            throw new Error(response.data.value?.message || 'Failed to retry');
        }
    } catch (error: unknown) {
        const message = error instanceof Error ? error.message : 'Failed to retry outbox entry';
        toast.error(message);
    } finally {
        const next = new Set(retryingIds.value);
        next.delete(entry.id);
        retryingIds.value = next;
    }
};

const columns = createColumns<OutboxEntry>([
    {
        accessorKey: 'event_id',
        header: 'Event ID',
        cell: ({ row }) => {
            const id = row.getValue('event_id') as string;
            return id.length > 12 ? id.substring(0, 12) + '...' : id;
        },
    },
    {
        accessorKey: 'event_name',
        header: 'Event Name',
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
        accessorKey: 'occurred_at',
        header: 'Occurred',
        enableSorting: true,
        cell: ({ row }) => {
            const date = new Date(row.getValue('occurred_at') as string);
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
    const data = props.outbox.data;
    return {
        pending: data.filter((e) => e.status === 'pending').length,
        processing: data.filter((e) => e.status === 'processing').length,
        dispatched: data.filter((e) => e.status === 'dispatched').length,
        failed: data.filter((e) => e.status === 'failed').length,
    };
});
</script>

<template>
    <div class="space-y-6">
        <Head title="Notification Ops - Outbox" />

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Notification Outbox</h1>
                <p class="text-muted-foreground mt-2">Monitor domain events waiting for processing</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('admin.notifications.ops.deliveries')" as="button">
                    <Button variant="outline">Deliveries</Button>
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
                    <CardDescription>Processing</CardDescription>
                    <CardTitle class="text-2xl">{{ statsData.processing }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Dispatched</CardDescription>
                    <CardTitle class="text-2xl text-green-600">{{ statsData.dispatched }}</CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Failed</CardDescription>
                    <CardTitle class="text-2xl text-destructive">{{ statsData.failed }}</CardTitle>
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
                        placeholder="Event ID, name..."
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
                        :model-value="filters.event_name ?? ''"
                        :options="eventNameOptions"
                        placeholder="All events"
                        all-label="All events"
                        @change="(v) => apply({ event_name: v, page: 1 })"
                    />
                    <FilterSelect
                        :model-value="filters.has_error ?? ''"
                        :options="hasErrorOptions"
                        placeholder="Has error"
                        all-label="Any"
                        @change="(v) => apply({ has_error: v, page: 1 })"
                    />
                    <Button variant="outline" @click="() => router.reload({ only: ['outbox'] })">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </FilterPanel>
            </CardContent>
        </Card>

        <!-- Table -->
        <Card>
            <CardHeader>
                <CardTitle>Outbox Events</CardTitle>
                <CardDescription>Showing {{ outbox.from ?? 0 }} to {{ outbox.to ?? 0 }} of {{ outbox.total }} entries</CardDescription>
            </CardHeader>
            <CardContent>
                <ServerPaginatedDataTable
                    :data="outbox.data"
                    :columns="columns"
                    :pagination-data="outbox"
                    :initial-sort="currentSort"
                    :initial-direction="currentDirection"
                    item-name="entries"
                    empty-message="No outbox entries found"
                    @sort-change="handleSortChange"
                    @page-change="handlePageChange"
                    @page-size-change="handlePageSizeChange"
                >
                    <template #cell-actions="{ row }">
                        <div class="flex items-center gap-2">
                            <Button v-if="canRetry(row.original)" size="sm" variant="outline" :disabled="retryingIds.has(row.original.id)" @click="retryOutbox(row.original)">
                                <Loader2 v-if="retryingIds.has(row.original.id)" class="mr-2 h-4 w-4 animate-spin" />
                                <RotateCcw v-else class="mr-2 h-4 w-4" />
                                Retry
                            </Button>
                            <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Link :href="route('admin.notifications.ops.outbox.detail', row.original.id)" as="button">
                                            <Button size="sm" variant="ghost">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>View details</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </template>
                </ServerPaginatedDataTable>
            </CardContent>
        </Card>
    </div>
</template>
