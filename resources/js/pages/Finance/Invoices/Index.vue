<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTableFilters } from '@/composables/useFilters';
import { PaginatedResponse } from '@/types';
import { formatCurrency } from '@/types/finance';
import { formatDate } from '@/utils/date';
import { Head, Link } from '@inertiajs/vue3';
import { Eye, FileDown, Search } from 'lucide-vue-next';
interface Invoice {
    id: number;
    invoice_number: string;
    student: {
        id: number;
        full_name: string;
        student_id: string;
    };
    semester: {
        id: number;
        name: string;
    };
    real_time_status: string;
    total_amount: number;
    paid_amount: number;
    outstanding_balance: number;
    due_date: string;
    created_at: string;
}

interface InvoiceFilters {
    search?: string;
    status?: string;
    semester_id?: string;
    per_page?: number;
}

interface Props {
    invoices: PaginatedResponse<Invoice>;
    filters: InvoiceFilters;
}

const props = defineProps<Props>();

const {
    filters,
    hasActiveFilters,
    updateFieldDebounced,
    applyFilters,
    clearFilters,
    handlePaginationNavigate,
    handlePageSizeChange,
} = useTableFilters<InvoiceFilters>(
    route('finance.invoices.index'),
    props.filters,
    ['invoices', 'filters']
);

const updateSearchFilter = (value: string | number) => {
    updateFieldDebounced('search', String(value));
};

const handleExport = () => {
    // Construct query string manually or reuse filters
    const params = new URLSearchParams();
    if (filters.value.search) params.append('search', filters.value.search);
    if (filters.value.status) params.append('status', filters.value.status);
    if (filters.value.semester_id) params.append('semester_id', filters.value.semester_id);

    window.location.href = route('finance.invoices.export') + '?' + params.toString();
};

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'paid':
            return 'success'; // Assuming project has custom variant or mapped to default/secondary
        case 'overdue':
            return 'destructive';
        case 'open':
            return 'default'; // Primary color often used for Open
        case 'zero_amount':
            return 'secondary';
        default:
            return 'outline';
    }
};
</script>

<template>

    <Head title="Invoices" />

    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-3xl font-bold tracking-tight">Invoices</h2>
            <p class="text-muted-foreground">
                Manage and view student invoices.
            </p>
        </div>
        <Button variant="outline" @click="handleExport">
            <FileDown class="mr-2 h-4 w-4" />
            Export Excel
        </Button>
    </div>

    <Card>
        <CardHeader class="pb-3">
            <CardTitle>Invoice List</CardTitle>
            <CardDescription>
                Overview of all student invoices containing charge and payment details.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <!-- Filters -->
            <div class="flex flex-col md:flex-row gap-4 mb-6">
                <div class="w-full md:w-1/3 relative">
                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input placeholder="Search invoice #, student name/ID..." class="pl-8" :model-value="filters.search"
                        @update:model-value="updateSearchFilter" />
                </div>

                <div class="w-full md:w-1/4">
                    <Select :model-value="filters.status" @update:model-value="(val) => applyFilters({ status: val })">
                        <SelectTrigger>
                            <SelectValue placeholder="Filter by Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem value="open">Open</SelectItem>
                            <SelectItem value="paid">Paid</SelectItem>
                            <SelectItem value="overdue">Overdue</SelectItem>
                            <SelectItem value="zero_amount">Zero Amount</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Add Semester Filter if needed, usually dynamic from props, skipping for now layout-wise or assuming handled if passed in props -->

                <div class="flex items-center ml-auto">
                    <Button v-if="hasActiveFilters" variant="ghost" class="h-8 px-2 lg:px-3" @click="clearFilters">
                        Reset
                    </Button>
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Invoice #</TableHead>
                            <TableHead>Student</TableHead>
                            <TableHead>Semester</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                            <TableHead class="text-right">Paid</TableHead>
                            <TableHead class="text-right">Balance</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Due Date</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="invoice in invoices.data" :key="invoice.id">
                            <TableCell class="font-medium">
                                <Link :href="route('finance.invoices.show', invoice.id)"
                                    class="hover:underline text-primary">
                                    {{ invoice.invoice_number }}
                                </Link>
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ invoice.student.full_name }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ invoice.student.student_id }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell>{{ invoice.semester.name }}</TableCell>
                            <TableCell class="text-right">{{ formatCurrency(invoice.total_amount) }}</TableCell>
                            <TableCell class="text-right text-green-600">{{ formatCurrency(invoice.paid_amount) }}
                            </TableCell>
                            <TableCell class="text-right font-bold">
                                {{ formatCurrency(invoice.outstanding_balance) }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="getStatusBadgeVariant(invoice.real_time_status)">
                                    {{ invoice.real_time_status.toUpperCase() }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                {{ invoice.due_date ? formatDate(invoice.due_date) : '-' }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button variant="ghost" size="icon" as-child>
                                    <Link :href="route('finance.invoices.show', invoice.id)">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="invoices.data.length === 0">
                            <TableCell colspan="9" class="h-24 text-center">
                                No invoices found.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div class="mt-4">
                <DataPagination :pagination-data="invoices" @navigate="handlePaginationNavigate"
                    @page-size-change="handlePageSizeChange" />
            </div>
        </CardContent>
    </Card>
</template>
