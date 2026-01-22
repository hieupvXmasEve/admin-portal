<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AutoAllocateDialog from './Components/AutoAllocateDialog.vue';
import { Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import DataPagination from '@/components/DataPagination.vue';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { formatCurrency, formatDate } from '@/utils/format';
import { debounce } from 'lodash-es';
import { PaginatedResponse } from '@/types';

interface Payment {
    id: number;
    amount: number;
    paid_at: string;
    source: string;
    external_ref: string;
    status: string;
    student?: {
        id: number;
        full_name: string;
        student_id: string;
    };
    unapplied_amount: number;
}

interface PaymentFilters {
    search: string;
    source: string;
    status: string;
    date_range: string | null;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
}

const props = defineProps<{
    items: PaginatedResponse<Payment>;
    filters?: Partial<PaymentFilters>;
}>();

const {
    filters,
    handleSearch,
    handleSelectFilter,
    handlePageSizeChange,
    handlePaginationNavigate,
} = useInertiaFilters<PaymentFilters>({
    baseUrl: route('finance.payments.index'),
    initialFilters: {
        search: (typeof props.filters?.search === 'string' ? props.filters.search : '') || '',
        source: (typeof props.filters?.source === 'string' ? props.filters.source : 'all') || 'all',
        status: (typeof props.filters?.status === 'string' ? props.filters.status : 'all') || 'all',
        date_range: props.filters?.date_range || null,
        per_page: props.filters?.per_page || 15,
        sort: (typeof props.filters?.sort === 'string' ? props.filters.sort : 'paid_at') || 'paid_at',
        direction: (props.filters?.direction as 'asc' | 'desc') || 'desc',
    },
    defaultValues: {
        source: 'all',
        status: 'all',
        per_page: 15,
        sort: 'paid_at',
        direction: 'desc',
    },
    only: ['items', 'filters'],
});

const onSearch = debounce((val) => handleSearch(val), 500);

const getStatusColor = (status: string) => {
    switch (status) {
        case 'completed': return 'success'; // Assuming badges support 'success' variant or use class
        case 'pending': return 'warning';
        case 'cancelled': return 'destructive';
        default: return 'secondary';
    }
};

const getSourceLabel = (source: string) => {
    switch (source) {
        case 'import': return 'Excel Import';
        case 'manual': return 'Manual';
        default: return source;
    }
};
const showAutoAllocateDialog = ref(false);
</script>

<template>

    <Head title="Payments" />

    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Payments
        </h2>
        <div class="flex gap-2">
            <Button variant="outline" @click="showAutoAllocateDialog = true">
                <Sparkles class="mr-2 h-4 w-4" />
                Auto Allocate
            </Button>
            <Link :href="route('finance.payments.import')">
                <Button>
                    Import Payments
                </Button>
            </Link>
        </div>
    </div>

    <AutoAllocateDialog v-model:open="showAutoAllocateDialog" />

    <div>
        <!-- Filters -->
        <div class="mb-6 grid gap-4 md:grid-cols-4">
            <div>
                <Input v-model="filters.search" placeholder="Search student, ref..." @update:model-value="onSearch" />
            </div>
            <div>
                <Select :model-value="filters.source" @update:model-value="(val) => handleSelectFilter('source', val)">
                    <SelectTrigger>
                        <SelectValue placeholder="Source" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Sources</SelectItem>
                        <SelectItem value="import">Excel Import</SelectItem>
                        <SelectItem value="manual">Manual</SelectItem>
                        <SelectItem value="gateway">Gateway</SelectItem>
                        <SelectItem value="bank_transfer">Bank Transfer</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div>
                <Select :model-value="filters.status" @update:model-value="(val) => handleSelectFilter('status', val)">
                    <SelectTrigger>
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem value="completed">Completed</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="cancelled">Cancelled</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <!-- Date Range (Simplified) -->
        </div>

        <!-- Table -->
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="cursor-pointer">
                            Date
                        </TableHead>
                        <TableHead>Student</TableHead>
                        <TableHead>Ref</TableHead>
                        <TableHead class="cursor-pointer">
                            Amount
                        </TableHead>
                        <TableHead>Unallocated</TableHead>
                        <TableHead>Source</TableHead>
                        <TableHead class="cursor-pointer">
                            Status
                        </TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="payment in items.data" :key="payment.id">
                        <TableCell>{{ formatDate(payment.paid_at) }}</TableCell>
                        <TableCell>
                            <div v-if="payment.student">
                                <div class="font-medium">{{ payment.student.full_name }}</div>
                                <div class="text-xs text-muted-foreground">{{ payment.student.student_id
                                }}
                                </div>
                            </div>
                            <span v-else class="text-muted-foreground">-</span>
                        </TableCell>
                        <TableCell class="font-mono text-xs">{{ payment.external_ref }}</TableCell>
                        <TableCell>{{ formatCurrency(payment.amount) }}</TableCell>
                        <TableCell>
                            <span :class="{ 'text-green-600 font-bold': payment.unapplied_amount > 0 }">
                                {{ formatCurrency(payment.unapplied_amount) }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <Badge variant="outline">{{ getSourceLabel(payment.source) }}</Badge>
                        </TableCell>
                        <TableCell>
                            <Badge :variant="getStatusColor(payment.status) as any">
                                {{ payment.status }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-right">
                            <Link :href="route('finance.payments.show', payment.id)">
                                <Button variant="ghost" size="sm">View</Button>
                            </Link>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="items.data.length === 0">
                        <TableCell colspan="8" class="h-24 text-center">
                            No payments found.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div class="mt-4">
            <DataPagination :pagination-data="items"
                @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
        </div>
    </div>
</template>
