<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermission } from '@/composables/usePermission';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, FileText, RotateCcw, TrendingDown, TrendingUp, User, Wallet } from 'lucide-vue-next';
import { computed, h } from 'vue';
import { route } from 'ziggy-js';

interface Campus {
    id: number;
    name: string;
}

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    avatar_url?: string | null;
    campus: Campus;
}

interface StudentWallet {
    id: number;
    student_id: number;
    balance: number;
    currency: string;
    formatted_balance?: string;
    created_at: string;
    updated_at: string;
    student: Student;
}

interface WalletTransaction {
    id: number;
    wallet_id: number;
    transaction_type: 'deposit' | 'payment' | 'refund' | 'adjustment';
    amount: number;
    balance_before: number;
    balance_after: number;
    description: string | null;
    reference_type: string | null;
    reference_id: number | null;
    created_by: number | null;
    created_at: string;
    created_by_user?: {
        id: number;
        name: string;
    } | null;
}

interface WalletStats {
    current_balance: number;
    total_deposits: number;
    total_payments: number;
    total_refunds: number;
    transaction_count: number;
    last_transaction_date: string | null;
}

interface Props {
    wallet: StudentWallet;
    transactions: PaginatedResponse<WalletTransaction>;
    stats: WalletStats;
    lastTransaction: WalletTransaction | null;
    filters: {
        transaction_type?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();
const { can } = usePermission();

const currentTransactionType = computed(() => props.filters?.transaction_type ?? 'all');
const currentPerPage = computed(() => props.filters?.per_page ?? 15);

const transactionTypeOptions = [
    { value: 'all', label: 'All Types' },
    { value: 'deposit', label: 'Deposits' },
    { value: 'payment', label: 'Payments' },
    { value: 'refund', label: 'Refunds' },
    { value: 'adjustment', label: 'Adjustments' },
];

const hasTransactions = computed(() => (props.transactions?.data?.length ?? 0) > 0);
const totalTransactions = computed(() => props.transactions?.total ?? 0);

const applyFilters = (transactionType: string, perPage: number) => {
    const payload: Record<string, unknown> = {
        per_page: perPage,
    };

    if (transactionType !== 'all') {
        payload.transaction_type = transactionType;
    }

    router.get(route('wallets.show', props.wallet.id), payload as any, {
        preserveState: true,
        preserveScroll: true,
        only: ['transactions', 'filters'],
        replace: true,
    });
};

const handleTransactionTypeChange = (value: any) => {
    const typeValue = String(value ?? 'all');
    applyFilters(typeValue, currentPerPage.value);
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['transactions', 'filters'],
        replace: true,
    });
};

const handlePageSizeChange = (pageSize: number) => {
    applyFilters(currentTransactionType.value, pageSize);
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount);
};

const formatDate = (date: string | null) => {
    if (!date) return 'N/A';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDateOnly = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const getTransactionTypeVariant = (type: string) => {
    switch (type) {
        case 'deposit':
            return 'default';
        case 'payment':
            return 'destructive';
        case 'refund':
            return 'secondary';
        case 'adjustment':
            return 'outline';
        default:
            return 'outline';
    }
};

const getTransactionAmountClass = (type: string) => {
    switch (type) {
        case 'deposit':
        case 'refund':
            return 'text-green-600 font-semibold';
        case 'payment':
            return 'text-red-600 font-semibold';
        case 'adjustment':
            return 'text-orange-600 font-semibold';
        default:
            return '';
    }
};

const formatTransactionAmount = (transaction: WalletTransaction) => {
    const sign = ['deposit', 'refund'].includes(transaction.transaction_type) ? '+' : '-';
    return `${sign}${formatCurrency(transaction.amount)}`;
};

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const transactionColumns: ColumnDef<WalletTransaction>[] = [
    {
        accessorKey: 'id',
        header: 'ID',
    },
    {
        accessorKey: 'created_at',
        header: 'Date & Time',
        cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
        accessorKey: 'transaction_type',
        header: 'Type',
        cell: ({ row }) => {
            const type = row.original.transaction_type;
            return h(Badge, { variant: getTransactionTypeVariant(type) }, () => type.charAt(0).toUpperCase() + type.slice(1));
        },
    },
    {
        accessorKey: 'description',
        header: 'Description',
        cell: ({ row }) => row.original.description || 'N/A',
    },
    {
        accessorKey: 'amount',
        header: 'Amount',
        cell: ({ row }) => {
            const transaction = row.original;
            return h('span', { class: getTransactionAmountClass(transaction.transaction_type) }, formatTransactionAmount(transaction));
        },
    },
    {
        accessorKey: 'balance_before',
        header: 'Balance Before',
        cell: ({ row }) => formatCurrency(row.original.balance_before),
    },
    {
        accessorKey: 'balance_after',
        header: 'Balance After',
        cell: ({ row }) => formatCurrency(row.original.balance_after),
    },
    {
        id: 'created_by',
        header: 'Created By',
        cell: ({ row }) => row.original.created_by_user?.name || 'System',
    },
];
</script>

<template>
    <Head :title="`Wallet - ${wallet.student.full_name}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Link :href="route('wallets.index')">
                    <Button variant="outline" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Student Wallet</h1>
                    <p class="text-muted-foreground">{{ wallet.student.student_id }} - {{ wallet.student.full_name }}</p>
                </div>
            </div>
        </div>

        <!-- Student Info Card -->
        <Card>
            <CardContent class="p-6">
                <div class="flex items-center gap-4">
                    <Avatar class="h-16 w-16">
                        <AvatarImage v-if="wallet.student.avatar_url" :src="wallet.student.avatar_url" :alt="wallet.student.full_name" />
                        <AvatarFallback>{{ getInitials(wallet.student.full_name) }}</AvatarFallback>
                    </Avatar>
                    <div class="flex-1">
                        <h3 class="text-lg font-semibold">{{ wallet.student.full_name }}</h3>
                        <p class="text-muted-foreground text-sm">{{ wallet.student.student_id }}</p>
                        <p class="text-muted-foreground text-sm">{{ wallet.student.email }}</p>
                        <p class="text-muted-foreground text-sm">{{ wallet.student.campus.name }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Wallet Info Card -->
        <Card>
            <CardContent class="p-6">
                <div class="grid gap-6 md:grid-cols-3">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <Wallet class="h-8 w-8 text-green-600" />
                        </div>
                        <div class="ml-5">
                            <dt class="text-muted-foreground text-sm font-medium">Current Balance</dt>
                            <dd class="text-2xl font-bold text-gray-900">{{ formatCurrency(wallet.balance) }}</dd>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <User class="text-muted-foreground h-8 w-8" />
                        </div>
                        <div class="ml-5">
                            <dt class="text-muted-foreground text-sm font-medium">Wallet Created</dt>
                            <dd class="text-lg font-semibold text-gray-900">{{ formatDateOnly(wallet.created_at) }}</dd>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <FileText class="text-muted-foreground h-8 w-8" />
                        </div>
                        <div class="ml-5">
                            <dt class="text-muted-foreground text-sm font-medium">Last Transaction</dt>
                            <dd class="text-lg font-semibold text-gray-900">
                                {{ lastTransaction ? formatDate(lastTransaction.created_at) : 'No transactions' }}
                            </dd>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Statistics Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardContent class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <TrendingUp class="h-6 w-6 text-green-600" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-muted-foreground truncate text-sm font-medium">Total Deposits</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ formatCurrency(stats.total_deposits) }}</dd>
                            </dl>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <TrendingDown class="h-6 w-6 text-red-600" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-muted-foreground truncate text-sm font-medium">Total Payments</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ formatCurrency(stats.total_payments) }}</dd>
                            </dl>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <RotateCcw class="h-6 w-6 text-blue-600" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-muted-foreground truncate text-sm font-medium">Total Refunds</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ formatCurrency(stats.total_refunds) }}</dd>
                            </dl>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <FileText class="text-muted-foreground h-6 w-6" />
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-muted-foreground truncate text-sm font-medium">Transactions</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ stats.transaction_count }}</dd>
                            </dl>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Transaction History Card -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Transaction History ({{ totalTransactions }})</CardTitle>
                        <CardDescription>All wallet transactions for this student</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-center gap-4">
                    <div class="w-64">
                        <Label for="transaction-type-filter">Transaction Type</Label>
                        <Select :model-value="currentTransactionType" @update:model-value="handleTransactionTypeChange">
                            <SelectTrigger id="transaction-type-filter">
                                <SelectValue placeholder="All Types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in transactionTypeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <DataTable v-if="hasTransactions" :columns="transactionColumns" :data="transactions.data" />
                <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                    <FileText class="text-muted-foreground h-12 w-12" />
                    <h3 class="mt-4 text-lg font-semibold">No transactions yet</h3>
                    <p class="text-muted-foreground mt-2 text-sm">Transactions will appear here once they are created.</p>
                </div>
                <DataPagination v-if="hasTransactions" :pagination-data="transactions" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
