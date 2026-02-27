<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import type { PaginatedResponse } from '@/types';
import type { ColumnDef } from '@tanstack/vue-table';

interface ServerPaginatedDataTableProps {
    data: any[];
    columns: ColumnDef<any>[];
    paginationData: Pick<PaginatedResponse<any>, 'from' | 'to' | 'total' | 'current_page' | 'last_page' | 'prev_page_url' | 'next_page_url' | 'links' | 'per_page'>;
    loading?: boolean;
    initialSort?: string;
    initialDirection?: 'asc' | 'desc';
    emptyMessage?: string;
    itemName?: string;
}

const props = withDefaults(defineProps<ServerPaginatedDataTableProps>(), {
    loading: false,
    emptyMessage: 'No results found.',
    itemName: 'items',
});

const emit = defineEmits<{
    'sort-change': [sort: string | null, direction: 'asc' | 'desc' | null];
    'page-change': [page: number];
    'page-size-change': [pageSize: number];
}>();

const handleNavigate = (url: string) => {
    const parsedUrl = new URL(url, window.location.origin);
    const pageParam = parsedUrl.searchParams.get('page');
    const pageNumber = pageParam ? parseInt(pageParam, 10) : 1;
    emit('page-change', Number.isNaN(pageNumber) ? 1 : pageNumber);
};

const handlePageSizeChange = (pageSize: number) => {
    emit('page-size-change', pageSize);
};
</script>

<template>
    <div class="space-y-4">
        <DataTable
            :data="props.data"
            :columns="props.columns"
            :loading="props.loading"
            :initial-sort="props.initialSort"
            :initial-direction="props.initialDirection"
            :empty-message="props.emptyMessage"
            @sort-change="(sort, direction) => emit('sort-change', sort, direction)"
        >
            <template v-for="(_, slotName) in $slots" :key="slotName" #[slotName]="slotProps">
                <slot :name="slotName" v-bind="slotProps" />
            </template>
        </DataTable>

        <DataPagination :pagination-data="props.paginationData" :item-name="props.itemName" @navigate="handleNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
