<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import LookupRowActions from '@/components/finance/lookup/LookupRowActions.vue';
import SendToBatchBar from '@/components/finance/lookup/SendToBatchBar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableEmpty, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDataTable } from '@/composables/useDataTable';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import { useLookupSelection } from '@/composables/useLookupSelection';
import { usePermission } from '@/composables/usePermission';
import type { PaginatedResponse } from '@/types';
import { formatCurrency } from '@/types/finance';
import { formatDate } from '@/utils/date';
import { financeRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { ChevronsUpDown, FileDown, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface InvoiceRow {
    id: number;
    invoice_number: string;
    student_id: number;
    student: { id: number; full_name: string; student_id: string };
    semester: { id: number; name: string };
    real_time_status: string;
    total_amount: number | null;
    paid_amount: number | null;
    outstanding_balance: number | null;
    gross_amount: number | null;
    discount_amount: number | null;
    credit_amount: number | null;
    settlement_valid: boolean;
    settlement_issue_codes: string[];
    due_date: string | null;
}

interface InvoiceFilters {
    search: string;
    status: string;
    semester_id?: number | null;
    sort?: string | null;
    direction?: 'asc' | 'desc' | null;
    per_page: number;
}

const props = defineProps<{
    invoices: PaginatedResponse<InvoiceRow>;
    filters: InvoiceFilters;
}>();

const permission = usePermission();
const sel = useLookupSelection();
const { selectedLabel } = useFinanceSemester();

const { filters, setFilter, handleSearch, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useDataTable<InvoiceFilters>({
    baseUrl: financeRoutes.lookup.invoices(),
    initialFilters: {
        search: props.filters.search ?? '',
        status: props.filters.status ?? 'all',
        sort: props.filters.sort ?? null,
        direction: props.filters.direction ?? null,
        per_page: props.filters.per_page ?? 50,
    },
    defaultValues: {
        status: 'all',
        per_page: 50,
        sort: null,
        direction: null,
    },
    only: ['invoices', 'filters'],
});

const canDng = computed(() => permission.can('create_finance_payments'));
const canRemind = computed(() => permission.can('view_finance_operations_due_calendar'));

const allRows = computed(() => props.invoices.data.map((invoice) => ({ key: invoice.id, studentId: invoice.student_id })));

function sortBy(column: string): void {
    handleSortChange(column, currentSort.value === column && currentDirection.value === 'asc' ? 'desc' : 'asc');
}

function openStudent(row: InvoiceRow): void {
    router.visit(financeRoutes.students.overview(row.student_id, `invoice:${row.id}`));
}

function handleExport(): void {
    const params = new URLSearchParams();
    if (filters.search) params.append('search', filters.search);
    if (filters.status && filters.status !== 'all') params.append('status', filters.status);
    window.location.href = `${route('finance.invoices.export')}?${params.toString()}`;
}

function getStatusBadgeVariant(status: string): 'default' | 'destructive' | 'outline' | 'secondary' {
    switch (status) {
        case 'paid':
            return 'default';
        case 'overdue':
            return 'destructive';
        case 'open':
            return 'default';
        case 'zero_amount':
            return 'secondary';
        case 'invalid':
            return 'destructive';
        default:
            return 'outline';
    }
}
</script>

<template>
    <Head title="Invoices" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold tracking-tight">Invoices</h2>
                <p class="text-muted-foreground">Tra cứu invoice — sort, filter, chọn nhiều dòng để Batch Studio. · {{ selectedLabel }}</p>
            </div>
            <Button variant="outline" @click="handleExport">
                <FileDown class="mr-2 h-4 w-4" />
                Export Excel
            </Button>
        </div>

        <Card>
            <CardHeader class="pb-3">
                <CardTitle>Invoice List</CardTitle>
                <CardDescription>Overview of all student invoices containing charge and payment details.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="mb-6 flex flex-col gap-4 md:flex-row">
                    <div class="relative w-full md:w-1/3">
                        <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                        <Input :model-value="filters.search" placeholder="Search invoice #, student name/ID…" class="pl-8" @update:model-value="handleSearch" />
                    </div>
                    <div class="w-full md:w-1/4">
                        <Select :model-value="filters.status" @update:model-value="(value) => setFilter('status', String(value))">
                            <SelectTrigger>
                                <SelectValue placeholder="Filter by Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="open">Open</SelectItem>
                                <SelectItem value="paid">Paid</SelectItem>
                                <SelectItem value="overdue">Overdue</SelectItem>
                                <SelectItem value="zero_amount">Zero Amount</SelectItem>
                                <SelectItem value="invalid">Cần kiểm tra</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="bg-background sticky top-0 z-10 w-8">
                                    <Checkbox :model-value="allRows.length > 0 && sel.count.value === allRows.length" @update:model-value="(value) => sel.toggleAll(allRows, !!value)" />
                                </TableHead>
                                <TableHead class="bg-background sticky top-0 z-10 cursor-pointer" @click="sortBy('invoice_number')">
                                    Invoice #
                                    <ChevronsUpDown class="inline h-3 w-3" />
                                </TableHead>
                                <TableHead class="bg-background sticky top-0 z-10">Student</TableHead>
                                <TableHead class="bg-background sticky top-0 z-10">Semester</TableHead>
                                <TableHead class="bg-background sticky top-0 z-10 text-right">
                                    Total
                                    <ChevronsUpDown class="inline h-3 w-3" />
                                </TableHead>
                                <TableHead class="bg-background sticky top-0 z-10 text-right">
                                    Paid
                                    <ChevronsUpDown class="inline h-3 w-3" />
                                </TableHead>
                                <TableHead class="bg-background sticky top-0 z-10 text-right">Balance</TableHead>
                                <TableHead class="bg-background sticky top-0 z-10">Status</TableHead>
                                <TableHead class="bg-background sticky top-0 z-10 cursor-pointer" @click="sortBy('due_date')">
                                    Due Date
                                    <ChevronsUpDown class="inline h-3 w-3" />
                                </TableHead>
                                <TableHead class="bg-background sticky top-0 z-10 text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-muted/50 cursor-pointer" @click="openStudent(invoice)">
                                <TableCell @click.stop>
                                    <Checkbox :model-value="sel.isSelected(invoice.id)" @update:model-value="() => sel.toggle({ key: invoice.id, studentId: invoice.student_id })" />
                                </TableCell>
                                <TableCell class="font-medium tabular-nums">{{ invoice.invoice_number }}</TableCell>
                                <TableCell>
                                    <div class="font-medium">{{ invoice.student.full_name }}</div>
                                    <div class="text-muted-foreground text-xs tabular-nums">{{ invoice.student.student_id }}</div>
                                </TableCell>
                                <TableCell>{{ invoice.semester.name }}</TableCell>
                                <TableCell class="text-right tabular-nums">{{ invoice.settlement_valid ? formatCurrency(invoice.total_amount ?? 0) : '—' }}</TableCell>
                                <TableCell class="text-right text-emerald-600 tabular-nums">{{ invoice.settlement_valid ? formatCurrency(invoice.paid_amount ?? 0) : '—' }}</TableCell>
                                <TableCell class="text-right font-medium tabular-nums">
                                    <span v-if="invoice.settlement_valid">{{ formatCurrency(invoice.outstanding_balance ?? 0) }}</span>
                                    <span v-else class="text-destructive text-xs">Cần kiểm tra</span>
                                    <div v-if="!invoice.settlement_valid" class="text-destructive text-xs">{{ invoice.settlement_issue_codes.join(', ') }}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusBadgeVariant(invoice.real_time_status)">
                                        {{ invoice.real_time_status.toUpperCase() }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ invoice.due_date ? formatDate(invoice.due_date) : '-' }}</TableCell>
                                <TableCell @click.stop>
                                    <LookupRowActions :student-id="invoice.student_id" :focus="`invoice:${invoice.id}`" :detail-url="financeRoutes.lookup.invoiceDetail(invoice.id)" />
                                </TableCell>
                            </TableRow>
                            <TableEmpty v-if="invoices.data.length === 0" :colspan="10"> No invoices found. </TableEmpty>
                        </TableBody>
                    </Table>
                </div>

                <div class="mt-4">
                    <DataPagination :pagination-data="invoices" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>

        <SendToBatchBar :count="sel.count.value" :can-dng="canDng" :can-remind="canRemind" @dng="sel.sendToBatch('dng')" @reminders="sel.sendToBatch('reminders')" @clear="sel.clear" />
    </div>
</template>
