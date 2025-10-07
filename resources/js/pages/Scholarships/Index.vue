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
import { Edit, Eye } from 'lucide-vue-next';

interface Scholarship {
    id: number;
    code: string;
    name: string;
    type: 'percentage' | 'fixed_amount';
    amount: number;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
    student_count: number;
}

interface Props {
    scholarships: PaginatedResponse<Scholarship>;
    filters: {
        search?: string;
        status?: string;
        type?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const searchForm = reactive({
    search: props.filters.search || '',
    status: props.filters.status || 'all',
    type: props.filters.type || 'all',
    per_page: props.filters.per_page || 20,
});

const applyFilters = () => {
    const params = new URLSearchParams();

    // Only add params if they have meaningful values
    if (searchForm.search) params.set('search', searchForm.search);
    if (searchForm.status && searchForm.status !== 'all') params.set('status', searchForm.status);
    if (searchForm.type && searchForm.type !== 'all') params.set('type', searchForm.type);
    if (searchForm.per_page) params.set('per_page', searchForm.per_page.toString());

    const url = `/scholarships${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['scholarships', 'filters'],
    });
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString();
};

const formatAmount = (amount: number, type: string) => {
    if (type === 'percentage') {
        return `${amount}%`;
    }
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount);
};

const getStatusVariant = (scholarship: Scholarship): 'default' | 'destructive' | 'outline' | 'secondary' => {
    const now = new Date();
    const validFrom = new Date(scholarship.valid_from);
    const validUntil = new Date(scholarship.valid_until);

    if (!scholarship.is_active) return 'secondary';
    if (now > validUntil) return 'destructive';
    if (now >= validFrom && now <= validUntil) return 'default';
    return 'outline';
};

const getStatusLabel = (scholarship: Scholarship): string => {
    const now = new Date();
    const validFrom = new Date(scholarship.valid_from);
    const validUntil = new Date(scholarship.valid_until);

    if (!scholarship.is_active) return 'Inactive';
    if (now > validUntil) return 'Expired';
    if (now >= validFrom && now <= validUntil) return 'Active';
    return 'Upcoming';
};

// Table columns definition
const columns: ColumnDef<Scholarship>[] = [
    {
        accessorKey: 'code',
        header: 'Code',
    },
    {
        accessorKey: 'name',
        header: 'Name',
    },
    {
        accessorKey: 'type',
        header: 'Type',
    },
    {
        accessorKey: 'amount',
        header: 'Amount',
    },
    {
        accessorKey: 'valid_from',
        header: 'Valid Period',
    },
    {
        accessorKey: 'student_count',
        header: 'Students',
    },
    {
        accessorKey: 'status',
        header: 'Status',
    },
    {
        accessorKey: 'actions',
        header: 'Actions',
    },
];
const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['scholarships'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    searchForm.per_page = pageSize;
    applyFilters();
};
</script>

<template>
    <Head title="Scholarships" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Scholarships</h2>
            <p class="text-muted-foreground mt-1 text-sm">Manage scholarship definitions and assignments</p>
        </div>
        <div class="flex gap-2">
<!--            <Button variant="outline" as-child>-->
<!--                <Link :href="route('scholarships.import')">Import Scholarships</Link>-->
<!--            </Button>-->
            <Button as-child>
                <Link :href="route('scholarships.create')">Create Scholarship</Link>
            </Button>
        </div>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Filter Scholarships</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="space-y-2">
                    <Label for="search">Search</Label>
                    <Input id="search" v-model="searchForm.search" type="text" placeholder="Search by code or name..." @input="debouncedSearch" />
                </div>

                <div class="space-y-2">
                    <Label for="status">Status</Label>
                    <Select v-model="searchForm.status" @update:model-value="applyFilters">
                        <SelectTrigger id="status">
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                            <SelectItem value="expired">Expired</SelectItem>
                            <SelectItem value="valid">Currently Valid</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="type">Type</Label>
                    <Select v-model="searchForm.type" @update:model-value="applyFilters">
                        <SelectTrigger id="type">
                            <SelectValue placeholder="All Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem value="percentage">Percentage</SelectItem>
                            <SelectItem value="fixed_amount">Fixed Amount</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </CardContent>
    </Card>

    <Card class="mt-6">
        <CardContent class="pt-6">
            <DataTable :columns="columns" :data="scholarships.data">
                <template #cell-type="{ row }">
                    <Badge variant="outline">
                        {{ row.original.type === 'percentage' ? 'Percentage' : 'Fixed Amount' }}
                    </Badge>
                </template>

                <template #cell-amount="{ row }">
                    <span class="font-medium">{{ formatAmount(row.original.amount, row.original.type) }}</span>
                </template>

                <template #cell-valid_from="{ row }">
                    <div class="text-sm">
                        <div>{{ formatDate(row.original.valid_from) }}</div>
                        <div class="text-muted-foreground">to {{ formatDate(row.original.valid_until) }}</div>
                    </div>
                </template>

                <template #cell-student_count="{ row }">
                    <span class="text-muted-foreground">{{ row.original.student_count }}</span>
                </template>

                <template #cell-status="{ row }">
                    <Badge :variant="getStatusVariant(row.original)">
                        {{ getStatusLabel(row.original) }}
                    </Badge>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex gap-2">
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="route('scholarships.show', row.original.id)">
                                <Eye class="size-4"/>
                            </Link>
                        </Button>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="route('scholarships.edit', row.original.id)"><Edit class="h-4 w-4" /></Link>
                        </Button>
                    </div>
                </template>
            </DataTable>

            <DataPagination v-if="scholarships.data.length > 0" :pagination-data="scholarships" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" class="mt-4" />

            <div v-if="scholarships.data.length === 0" class="text-muted-foreground py-8 text-center">
                <p>No scholarships found.</p>
                <Button class="mt-4" as-child>
                    <Link :href="route('scholarships.create')">Create Your First Scholarship</Link>
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
