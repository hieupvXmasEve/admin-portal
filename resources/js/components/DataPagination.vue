<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { PaginatedResponse } from '@/types';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

interface DataPaginationProps {
    paginationData: Pick<PaginatedResponse<any>, 'from' | 'to' | 'total' | 'current_page' | 'last_page' | 'prev_page_url' | 'next_page_url' | 'links' | 'per_page'>;
    itemName?: string;
    pageSizeOptions?: number[];
    showPageSizeSelector?: boolean;
}

const props = withDefaults(defineProps<DataPaginationProps>(), {
    itemName: 'items',
    pageSizeOptions: () => [10, 25, 50, 100],
    showPageSizeSelector: true,
});

const emit = defineEmits<{
    navigate: [url: string];
    pageSizeChange: [pageSize: number];
}>();

const goToPage = (url: string | null) => {
    if (url) {
        emit('navigate', url);
    }
};

const goToPreviousPage = () => {
    goToPage(props.paginationData.prev_page_url);
};

const goToNextPage = () => {
    goToPage(props.paginationData.next_page_url);
};

const handlePageSizeChange = (pageSize: number) => {
    emit('pageSizeChange', pageSize);
};

// Generate smart pagination with ellipsis
const paginationItems = computed(() => {
    const current = props.paginationData.current_page;
    const last = props.paginationData.last_page;
    const items: Array<{ type: 'page' | 'ellipsis'; value: number; label: string; active?: boolean }> = [];

    if (last <= 7) {
        // Show all pages if 7 or fewer
        for (let i = 1; i <= last; i++) {
            items.push({
                type: 'page',
                value: i,
                label: i.toString(),
                active: i === current,
            });
        }
    } else {
        // Always show first page
        items.push({
            type: 'page',
            value: 1,
            label: '1',
            active: current === 1,
        });

        // Show ellipsis and pages around current page
        if (current > 4) {
            items.push({
                type: 'ellipsis',
                value: Math.max(1, current - 5),
                label: '...',
            });
        }

        // Show pages around current page
        const start = Math.max(2, current - 1);
        const end = Math.min(last - 1, current + 1);

        for (let i = start; i <= end; i++) {
            if (i !== 1 && i !== last) {
                items.push({
                    type: 'page',
                    value: i,
                    label: i.toString(),
                    active: i === current,
                });
            }
        }

        // Show ellipsis before last page if needed
        if (current < last - 3) {
            items.push({
                type: 'ellipsis',
                value: Math.min(last, current + 5),
                label: '...',
            });
        }

        // Always show last page if more than 1 page
        if (last > 1) {
            items.push({
                type: 'page',
                value: last,
                label: last.toString(),
                active: current === last,
            });
        }
    }

    return items;
});

const goToPageNumber = (pageNumber: number) => {
    // Construct URL for specific page number
    const baseUrl = window.location.pathname;
    const params = new URLSearchParams(window.location.search);
    params.set('page', pageNumber.toString());
    const url = `${baseUrl}?${params.toString()}`;
    emit('navigate', url);
};
</script>

<template>
    <div class="flex items-center justify-between space-x-2 py-4">
        <div class="flex items-center space-x-4">
            <!-- Page Size Selector -->
            <div v-if="showPageSizeSelector" class="flex items-center space-x-2">
                <span class="text-sm text-muted-foreground">Show</span>
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="w-20 justify-between">
                            {{ paginationData.per_page }}
                            <ChevronDown class="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem
                            v-for="size in pageSizeOptions"
                            :key="size"
                            @click="handlePageSizeChange(size)"
                            :class="{ 'bg-accent': size === paginationData.per_page }"
                        >
                            {{ size }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <!-- Items Info -->
            <div class="text-muted-foreground text-sm">
                {{ paginationData.from || 0 }} - {{ paginationData.to || 0 }} of {{ paginationData.total }}
            </div>
        </div>

        <!-- Pagination Controls -->
        <div class="flex space-x-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!paginationData.prev_page_url"
                @click="goToPreviousPage"
            >
                Previous
            </Button>

            <div class="flex space-x-1">
                <template v-for="item in paginationItems" :key="`${item.type}-${item.value}`">
                    <Button
                        :variant="item.active ? 'default' : 'outline'"
                        size="sm"
                        @click="item.type === 'ellipsis' ? goToPageNumber(item.value) : goToPageNumber(item.value)"
                        class="min-w-[2.5rem]"
                        :class="{ 'cursor-pointer': item.type === 'ellipsis' }"
                    >
                        {{ item.label }}
                    </Button>
                </template>
            </div>

            <Button
                variant="outline"
                size="sm"
                :disabled="!paginationData.next_page_url"
                @click="goToNextPage"
            >
                Next
            </Button>
        </div>
    </div>
</template>
