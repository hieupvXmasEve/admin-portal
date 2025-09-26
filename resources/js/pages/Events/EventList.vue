<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { debounce } from 'lodash-es';
import { reactive } from 'vue';
import { route } from 'ziggy-js';

interface Event {
    id: number;
    title: string;
    description: string;
    start_time: string;
    end_time: string;
    location: string;
    status: 'draft' | 'published' | 'cancelled' | 'completed';
    gold_reward_amount: number;
    max_participants: number | null;
    registered_count: number;
}

interface Props {
    events: PaginatedResponse<Event>;
    filters: {
        search?: string;
        status?: string;
        date_from?: string;
        date_to?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();
console.log(props.events);
const searchForm = reactive({
    search: props.filters.search || '',
    status: props.filters.status || 'all',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    per_page: props.filters.per_page || 10,
});

const applyFilters = () => {
    router.get(route('events.index'), searchForm, {
        preserveState: true,
        replace: true,
    });
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

const formatDateTime = (dateTime: string) => {
    return new Date(dateTime).toLocaleString();
};

const getStatusVariant = (status: string): 'default' | 'destructive' | 'outline' | 'secondary' => {
    const variants = {
        draft: 'secondary' as const,
        published: 'default' as const,
        cancelled: 'destructive' as const,
        completed: 'outline' as const,
    };
    return variants[status as keyof typeof variants] || 'secondary';
};

// Table columns definition
const columns: ColumnDef<Event>[] = [
    {
        accessorKey: 'title',
        header: 'Event',
    },
    {
        accessorKey: 'start_time',
        header: 'Date & Time',
    },
    {
        accessorKey: 'location',
        header: 'Location',
    },
    {
        accessorKey: 'status',
        header: 'Status',
    },
    {
        accessorKey: 'registered_count',
        header: 'Participants',
    },
    {
        accessorKey: 'gold_reward_amount',
        header: 'Gold Reward',
    },
    {
        id: 'actions',
        header: 'Actions',
    },
];

const handlePaginationNavigate = (url: string) => {
    router.get(url, {}, { preserveState: true, replace: true });
};
const handlePageSizeChange = (pageSize: number) => {
    console.log('Page size changed to:', pageSize);
    searchForm.per_page = pageSize;
    applyFilters();
};
</script>

<template>
    <Head title="Events" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h2 class="text-xl leading-tight font-semibold text-gray-800">Events</h2>
            <Link :href="route('events.create')">
                <Button>Create Event</Button>
            </Link>
        </div>

        <!-- Filters Card -->
        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="space-y-2">
                        <Label for="search">Search</Label>
                        <Input id="search" v-model="searchForm.search" type="text" placeholder="Search events..." @input="debouncedSearch" />
                    </div>

                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Select v-model="searchForm.status" @update:model-value="applyFilters">
                            <SelectTrigger>
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="draft">Draft</SelectItem>
                                <SelectItem value="published">Published</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="date_from">From Date</Label>
                        <Input id="date_from" v-model="searchForm.date_from" type="date" @change="applyFilters" />
                    </div>

                    <div class="space-y-2">
                        <Label for="date_to">To Date</Label>
                        <Input id="date_to" v-model="searchForm.date_to" type="date" @change="applyFilters" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Events Table -->
        <DataTable :data="events.data" :columns="columns" :empty-message="'No events found.'">
            <!-- Event Title Slot -->
            <template #cell-title="{ row }">
                <div>
                    <div class="text-sm font-medium text-gray-900">{{ row.original.title }}</div>
                    <div class="max-w-xs truncate text-sm text-gray-500">{{ row.original.description }}</div>
                </div>
            </template>

            <!-- Date & Time Slot -->
            <template #cell-start_time="{ row }">
                <div>
                    <div>{{ formatDateTime(row.original.start_time) }}</div>
                    <div class="text-xs text-gray-500">to {{ formatDateTime(row.original.end_time) }}</div>
                </div>
            </template>

            <!-- Status Slot -->
            <template #cell-status="{ row }">
                <Badge :variant="getStatusVariant(row.original.status)">
                    {{ row.original.status }}
                </Badge>
            </template>

            <!-- Participants Slot -->
            <template #cell-registered_count="{ row }">
                <div>
                    <div>{{ row.original.registered_count || 0 }} registered</div>
                    <div v-if="row.original.max_participants" class="text-xs text-gray-500">/ {{ row.original.max_participants }} max</div>
                </div>
            </template>

            <!-- Gold Reward Slot -->
            <template #cell-gold_reward_amount="{ row }"> {{ row.original.gold_reward_amount }} gold </template>

            <!-- Actions Slot -->
            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end space-x-2">
                    <Link :href="route('events.show', row.original.id)" class="text-indigo-600 hover:text-indigo-900"> View </Link>
                    <Link v-if="row.original.status === 'draft'" :href="route('events.edit', row.original.id)" class="text-blue-600 hover:text-blue-900"> Edit </Link>
                </div>
            </template>
        </DataTable>

        <!-- Pagination -->
        <DataPagination :pagination-data="events" @page-size-change="handlePageSizeChange" @navigate="handlePaginationNavigate" />
    </div>
</template>
