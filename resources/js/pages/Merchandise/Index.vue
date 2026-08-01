<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useApi, type ApiResponse } from '@/composables/useApiRequest';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { usePermissions } from '@/composables/usePermissions';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { debounce } from 'lodash-es';
import { Eye, Package, Pencil, PlusCircle } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface MerchandiseListItem {
    id: number;
    name: string;
    status: 'active' | 'coming_soon' | 'hidden' | 'archived';
    gold_price: number;
    variants_count: number;
    primary_image: string | null;
}

interface Props {
    merchandise: PaginatedResponse<MerchandiseListItem>;
    filters: {
        search?: string;
        status?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();
const { can } = usePermissions();
const { confirmDelete } = useGlobalConfirmDialog();
const api = useApi();

const searchForm = reactive({
    search: props.filters.search || '',
    status: props.filters.status || 'all',
    per_page: props.filters.per_page || 15,
});

const applyFilters = () => {
    router.get(route('merchandise.index'), searchForm, {
        preserveState: true,
        replace: true,
    });
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

const getStatusVariant = (status: string): 'default' | 'destructive' | 'outline' | 'secondary' | 'success' => {
    const variants = {
        active: 'success' as const,
        coming_soon: 'secondary' as const,
        hidden: 'outline' as const,
        archived: 'destructive' as const,
    };
    return variants[status as keyof typeof variants] || 'outline';
};

const archivingId = ref<number | null>(null);

const handleArchive = (item: MerchandiseListItem) => {
    confirmDelete(item.name, 'merchandise item', async () => {
        archivingId.value = item.id;

        try {
            const response = await api.post(route('merchandise.archive', item.id), {});
            const body = response.data.value as ApiResponse | null;

            if (body?.success) {
                toast.success(body.message || 'Merchandise archived successfully');
                router.reload({ only: ['merchandise'] });
                return;
            }

            toast.error(body?.message || 'Failed to archive merchandise');
        } catch {
            toast.error('Failed to archive merchandise');
        } finally {
            archivingId.value = null;
        }
    });
};

const columns: ColumnDef<MerchandiseListItem>[] = [
    { id: 'image', header: '' },
    { accessorKey: 'name', header: 'Name' },
    { accessorKey: 'status', header: 'Status' },
    { accessorKey: 'gold_price', header: 'Gold Price' },
    { accessorKey: 'variants_count', header: 'Variants' },
    { id: 'actions', header: 'Actions' },
];

const handlePaginationNavigate = (url: string) => {
    router.get(url, {}, { preserveState: true, replace: true });
};
const handlePageSizeChange = (pageSize: number) => {
    searchForm.per_page = pageSize;
    applyFilters();
};
</script>

<template>
    <Head title="Merchandise Store" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-xl leading-tight font-semibold text-gray-800">Merchandise Store</h2>
            <Link v-if="can('create_merchandise')" :href="route('merchandise.create')">
                <Button>
                    <PlusCircle class="mr-2 h-4 w-4" />
                    Add Merchandise
                </Button>
            </Link>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="search">Search</Label>
                        <Input id="search" v-model="searchForm.search" type="text" placeholder="Search by name..." @input="debouncedSearch" />
                    </div>

                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Select v-model="searchForm.status" @update:model-value="applyFilters">
                            <SelectTrigger>
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="coming_soon">Coming Soon</SelectItem>
                                <SelectItem value="hidden">Hidden</SelectItem>
                                <SelectItem value="archived">Archived</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <DataTable :data="merchandise.data" :columns="columns" empty-message="No merchandise found.">
            <template #cell-image="{ row }">
                <img
                    v-if="row.original.primary_image"
                    :src="row.original.primary_image"
                    :alt="row.original.name"
                    width="40"
                    height="40"
                    loading="lazy"
                    class="h-10 w-10 rounded-md border object-cover"
                />
                <div v-else class="bg-muted flex h-10 w-10 items-center justify-center rounded-md border">
                    <Package class="text-muted-foreground h-4 w-4" />
                </div>
            </template>

            <template #cell-name="{ row }">
                <span class="text-sm font-medium text-gray-900">{{ row.original.name }}</span>
            </template>

            <template #cell-status="{ row }">
                <Badge :variant="getStatusVariant(row.original.status)">
                    {{ row.original.status }}
                </Badge>
            </template>

            <template #cell-gold_price="{ row }"> {{ row.original.gold_price }} gold </template>

            <template #cell-variants_count="{ row }"> {{ row.original.variants_count }} </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-start gap-2">
                    <Button variant="ghost" size="icon" as-child>
                        <Link :href="route('merchandise.show', row.original.id)" title="View merchandise">
                            <Eye class="h-4 w-4" />
                        </Link>
                    </Button>
                    <Button v-if="can('edit_merchandise')" variant="ghost" size="icon" as-child>
                        <Link :href="route('merchandise.edit', row.original.id)" title="Edit merchandise">
                            <Pencil class="h-4 w-4" />
                        </Link>
                    </Button>
                    <Button
                        v-if="can('archive_merchandise') && row.original.status !== 'archived'"
                        variant="ghost"
                        size="icon"
                        title="Archive merchandise"
                        :disabled="archivingId === row.original.id"
                        @click="handleArchive(row.original)"
                    >
                        <Package class="text-destructive h-4 w-4" />
                    </Button>
                </div>
            </template>
        </DataTable>

        <DataPagination :pagination-data="merchandise" item-name="merchandise" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>
</template>
