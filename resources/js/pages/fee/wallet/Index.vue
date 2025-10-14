<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { debounce } from 'lodash-es';
import { Eye, Upload, Wallet } from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface Campus {
    id: number;
    name: string;
}

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    campus: Campus;
}

interface WalletItem {
    id: number;
    student_id: number;
    balance: number;
    currency: string;
    created_at: string;
    updated_at: string;
    last_transaction_date: string | null;
    transactions_count: number;
    student: Student;
}

interface Props {
    wallets: PaginatedResponse<WalletItem>;
    campuses: Campus[];
    filters: {
        search?: string;
        campus_id?: number | null;
        min_balance?: number | null;
        max_balance?: number | null;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const currentSearch = computed(() => props.filters?.search ?? '');
const currentCampusId = computed(() => (props.filters?.campus_id ? String(props.filters.campus_id) : 'all'));
const currentMinBalance = computed(() => props.filters?.min_balance ?? '');
const currentMaxBalance = computed(() => props.filters?.max_balance ?? '');
const currentPerPage = computed(() => props.filters?.per_page ?? 20);

const searchInput = ref(currentSearch.value);
const minBalanceInput = ref(currentMinBalance.value);
const maxBalanceInput = ref(currentMaxBalance.value);

watch(currentSearch, (newValue) => {
    if (searchInput.value !== newValue) {
        searchInput.value = newValue;
    }
});

watch(currentMinBalance, (newValue) => {
    if (minBalanceInput.value !== newValue) {
        minBalanceInput.value = newValue;
    }
});

watch(currentMaxBalance, (newValue) => {
    if (maxBalanceInput.value !== newValue) {
        maxBalanceInput.value = newValue;
    }
});

const campusOptions = computed(() => {
    return [{ value: 'all', label: 'All Campuses' }, ...props.campuses.map((campus) => ({ value: campus.id.toString(), label: campus.name }))];
});

const hasWallets = computed(() => (props.wallets?.data?.length ?? 0) > 0);
const totalWallets = computed(() => props.wallets?.total ?? 0);

const buildFilterPayload = (search: string, campusId: string, minBalance: string | number, maxBalance: string | number, perPage: number) => {
    const payload: Record<string, unknown> = {
        per_page: perPage,
    };

    if (search.trim().length > 0) {
        payload.search = search.trim();
    }

    if (campusId !== 'all') {
        payload.campus_id = Number(campusId);
    }

    if (minBalance !== '' && minBalance !== null) {
        payload.min_balance = Number(minBalance);
    }

    if (maxBalance !== '' && maxBalance !== null) {
        payload.max_balance = Number(maxBalance);
    }

    return payload;
};

const applyFilters = (search: string, campusId: string, minBalance: string | number, maxBalance: string | number, perPage: number) => {
    router.get(route('wallets.index'), buildFilterPayload(search, campusId, minBalance, maxBalance, perPage) as any, {
        preserveState: true,
        preserveScroll: true,
        only: ['wallets', 'filters'],
        replace: true,
    });
};

const handleCampusFilterChange = (value: any) => {
    const campusValue = String(value ?? 'all');
    applyFilters(searchInput.value, campusValue, minBalanceInput.value, maxBalanceInput.value, currentPerPage.value);
};

const debouncedSearch = debounce(() => {
    applyFilters(searchInput.value, currentCampusId.value, minBalanceInput.value, maxBalanceInput.value, currentPerPage.value);
}, 400);

watch(searchInput, () => {
    debouncedSearch();
});

watch(minBalanceInput, () => {
    debouncedSearch();
});

watch(maxBalanceInput, () => {
    debouncedSearch();
});

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['wallets', 'filters'],
        replace: true,
    });
};

const handlePageSizeChange = (pageSize: number) => {
    applyFilters(searchInput.value, currentCampusId.value, minBalanceInput.value, maxBalanceInput.value, pageSize);
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount);
};

const formatDate = (date: string | null) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const walletColumns: ColumnDef<WalletItem>[] = [
    {
        accessorKey: 'student.student_id',
        header: 'Student ID',
    },
    {
        accessorKey: 'student.full_name',
        header: 'Student Name',
    },
    {
        accessorKey: 'student.campus.name',
        header: 'Campus',
    },
    {
        accessorKey: 'balance',
        header: 'Balance',
        cell: ({ row }) => formatCurrency(row.original.balance),
    },
    {
        accessorKey: 'last_transaction_date',
        header: 'Last Transaction',
        cell: ({ row }) => formatDate(row.original.last_transaction_date),
    },
    {
        accessorKey: 'transactions_count',
        header: 'Transactions',
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            return h(
                Button,
                {
                    size: 'sm',
                    variant: 'outline',
                    onClick: () => router.visit(route('wallets.show', row.original.id)),
                },
                () => [h(Eye, { class: 'mr-2 h-4 w-4' }), 'View Details']
            );
        },
    },
];
</script>

<template>
    <Head title="Student Wallets" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Student Wallets</h1>
                <p class="text-muted-foreground">Manage student cash wallet balances and transactions</p>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="route('wallets.import')">
                    <Button>
                        <Upload class="mr-2 h-4 w-4" />
                        Bulk Import
                    </Button>
                </Link>
                <Wallet class="h-8 w-8 text-green-600" />
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
                <CardDescription>Filter wallets by student, campus, or balance range</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div class="space-y-2">
                        <Label for="search">Search</Label>
                        <Input id="search" v-model="searchInput" placeholder="Search by student ID or name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="campus-filter">Campus</Label>
                        <Select :model-value="currentCampusId" @update:model-value="handleCampusFilterChange">
                            <SelectTrigger id="campus-filter">
                                <SelectValue placeholder="All Campuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in campusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="min-balance">Min Balance</Label>
                        <Input id="min-balance" v-model="minBalanceInput" type="number" placeholder="0" min="0" step="1000" />
                    </div>

                    <div class="space-y-2">
                        <Label for="max-balance">Max Balance</Label>
                        <Input id="max-balance" v-model="maxBalanceInput" type="number" placeholder="No limit" min="0" step="1000" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Wallets ({{ totalWallets }})</CardTitle>
                <CardDescription>List of all student cash wallets</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <DataTable v-if="hasWallets" :columns="walletColumns" :data="wallets.data" />
                <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                    <Wallet class="text-muted-foreground h-12 w-12" />
                    <h3 class="mt-4 text-lg font-semibold">No wallets found</h3>
                    <p class="text-muted-foreground mt-2 text-sm">Try adjusting your search or filter criteria.</p>
                </div>
                <DataPagination v-if="hasWallets" :pagination-data="wallets" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
