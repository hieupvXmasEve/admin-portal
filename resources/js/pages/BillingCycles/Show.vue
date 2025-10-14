<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { usePermission } from '@/composables/usePermission';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { debounce } from 'lodash-es';
import { AlertCircle, Download, Edit, Eye, Loader2, Wallet } from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Semester {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
}

interface Campus {
    id: number;
    name: string;
}

interface CashWallet {
    id: number;
    student_id: number;
    balance: number;
    currency: string;
}

interface Student {
    id: number;
    student_code?: string | null;
    student_id?: string | null;
    full_name?: string | null;
    campus?: Campus | null;
    cash_wallet?: CashWallet | null;
}

interface InvoiceItem {
    id: number;
    invoice_id: number;
    item_type: 'tuition' | 'egc' | 'retake' | 'miscellaneous';
    description: string;
    total_price: number;
    paid_amount: number;
}

interface Invoice {
    id: number;
    invoice_number: string;
    student_id: number;
    total_amount: number;
    paid_amount: number;
    status: string;
    due_date: string;
    student: Student | null;
    items?: InvoiceItem[];
}

interface BillingCycle {
    id: number;
    semester_id: number;
    name: string;
    start_date: string;
    end_date: string;
    due_date: string;
    status: 'draft' | 'active' | 'closed';
    semester: Semester;
}

interface StatusOption {
    value: string;
    label: string;
}

interface Props {
    billingCycle: BillingCycle;
    invoices: PaginatedResponse<Invoice>;
    campuses: Campus[];
    filters: {
        status?: string | null;
        campus_id?: number | null;
        per_page?: number | null;
        search?: string | null;
    };
    statusOptions: StatusOption[];
}

const props = defineProps<Props>();
const { can } = usePermission();
const confirmDialog = useGlobalConfirmDialog();

const isActivating = ref(false);
const isClosing = ref(false);
const isDeleting = ref(false);
const isExporting = ref(false);
const isBulkPaying = ref(false);
const payingInvoices = ref<Record<number, boolean>>({});

// Computed filter values from props (synced automatically on page load)
const currentStatus = computed(() => props.filters?.status ?? 'all');
const currentCampusId = computed(() => (props.filters?.campus_id ? String(props.filters.campus_id) : 'all'));
const currentPerPage = computed(() => props.filters?.per_page ?? props.invoices?.per_page ?? 10);
const currentSearch = computed(() => props.filters?.search ?? '');

// Local ref for search input (needed for v-model)
const searchInput = ref(currentSearch.value);

// Sync searchInput when props.filters.search changes
watch(currentSearch, (newValue) => {
    if (searchInput.value !== newValue) {
        searchInput.value = newValue;
    }
});

const statusFilterOptions = computed(() => {
    return [{ value: 'all', label: 'All Statuses' }, ...props.statusOptions];
});

const campusFilterOptions = computed(() => {
    return [{ value: 'all', label: 'All Campuses' }, ...props.campuses.map((campus) => ({ value: campus.id.toString(), label: campus.name }))];
});

const hasInvoices = computed(() => (props.invoices?.data?.length ?? 0) > 0);
const totalInvoices = computed(() => props.invoices?.total ?? 0);

const baseShowRoute = computed(() => route('billing-cycles.show', props.billingCycle.id));

const buildFilterPayload = (status: string, campusId: string, perPage: number, search: string) => {
    const payload: Record<string, unknown> = {
        per_page: perPage,
    };

    if (status !== 'all') {
        payload.status = status;
    }

    if (campusId !== 'all') {
        payload.campus_id = Number(campusId);
    }

    if (search.trim().length > 0) {
        payload.search = search.trim();
    }

    return payload;
};

const applyFilters = (status: string, campusId: string, perPage: number, search: string, preserveScroll = true) => {
    router.get(baseShowRoute.value, buildFilterPayload(status, campusId, perPage, search) as any, {
        preserveState: true,
        preserveScroll,
        only: ['invoices', 'filters'],
        replace: true,
    });
};

const handleStatusFilterChange = (value: any) => {
    const statusValue = String(value ?? 'all');
    applyFilters(statusValue, currentCampusId.value, currentPerPage.value, searchInput.value);
};

const handleCampusFilterChange = (value: any) => {
    const campusValue = String(value ?? 'all');
    applyFilters(currentStatus.value, campusValue, currentPerPage.value, searchInput.value);
};

const debouncedSearch = debounce(() => {
    applyFilters(currentStatus.value, currentCampusId.value, currentPerPage.value, searchInput.value);
}, 400);

// Watch search input changes only
watch(searchInput, () => {
    debouncedSearch();
});

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['invoices', 'filters'],
        replace: true,
    });
};

const handlePageSizeChange = (pageSize: number) => {
    applyFilters(currentStatus.value, currentCampusId.value, pageSize, searchInput.value);
};

const extractErrorMessage = (errors: Record<string, unknown> | undefined, fallback: string) => {
    if (!errors) {
        return fallback;
    }

    const directError = errors.error;
    if (typeof directError === 'string' && directError.trim().length > 0) {
        return directError;
    }

    const firstKey = Object.keys(errors)[0];
    if (!firstKey) {
        return fallback;
    }

    const value = errors[firstKey];
    if (typeof value === 'string' && value.trim().length > 0) {
        return value;
    }

    if (Array.isArray(value) && value.length > 0) {
        const firstEntry = value[0];
        if (typeof firstEntry === 'string' && firstEntry.trim().length > 0) {
            return firstEntry;
        }
    }

    return fallback;
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount);
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'closed':
            return 'secondary';
        case 'draft':
            return 'outline';
        default:
            return 'outline';
    }
};

const getInvoiceStatusVariant = (status: string) => {
    switch (status) {
        case 'paid':
            return 'default';
        case 'partial':
            return 'secondary';
        case 'pending':
            return 'outline';
        case 'overdue':
            return 'destructive';
        default:
            return 'outline';
    }
};

const handleActivate = () => {
    confirmDialog.showConfirmDialog(
        {
            title: 'Activate Billing Cycle',
            message: 'Activate this billing cycle? Once activated, its configuration becomes read-only.',
            confirmText: 'Activate',
        },
        {
            onConfirm: () => {
                isActivating.value = true;

                return new Promise<void>((resolve, reject) => {
                    router.post(
                        route('billing-cycles.activate', props.billingCycle.id),
                        {},
                        {
                            onSuccess: () => {
                                toast.success('Billing cycle activated successfully');
                                resolve();
                            },
                            onError: (errors: Record<string, unknown>) => {
                                const message = extractErrorMessage(errors, 'Failed to activate billing cycle');
                                toast.error(message);
                                reject(new Error(message));
                            },
                            onFinish: () => {
                                isActivating.value = false;
                            },
                        },
                    );
                });
            },
        },
    );
};

const handleClose = () => {
    confirmDialog.showConfirmDialog(
        {
            title: 'Close Billing Cycle',
            message: 'Close this billing cycle? Closed cycles cannot be reopened or modified.',
            confirmText: 'Close',
        },
        {
            onConfirm: () => {
                isClosing.value = true;

                return new Promise<void>((resolve, reject) => {
                    router.post(
                        route('billing-cycles.close', props.billingCycle.id),
                        {},
                        {
                            onSuccess: () => {
                                toast.success('Billing cycle closed successfully');
                                resolve();
                            },
                            onError: (errors: Record<string, unknown>) => {
                                const message = extractErrorMessage(errors, 'Failed to close billing cycle');
                                toast.error(message);
                                reject(new Error(message));
                            },
                            onFinish: () => {
                                isClosing.value = false;
                            },
                        },
                    );
                });
            },
        },
    );
};

const handleDelete = () => {
    confirmDialog.confirmDelete(props.billingCycle.name, 'billing cycle', () => {
        isDeleting.value = true;

        return new Promise<void>((resolve, reject) => {
            router.delete(route('billing-cycles.destroy', props.billingCycle.id), {
                onSuccess: () => {
                    toast.success('Billing cycle deleted successfully');
                    resolve();
                },
                onError: (errors: Record<string, unknown>) => {
                    const message = extractErrorMessage(errors, 'Failed to delete billing cycle');
                    toast.error(message);
                    reject(new Error(message));
                },
                onFinish: () => {
                    isDeleting.value = false;
                },
            });
        });
    });
};

const handleExport = async () => {
    isExporting.value = true;

    try {
        // Build export URL with current filters
        const exportParams = new URLSearchParams();

        if (currentStatus.value !== 'all') {
            exportParams.append('status', currentStatus.value);
        }

        if (currentCampusId.value !== 'all') {
            exportParams.append('campus_id', currentCampusId.value);
        }

        if (searchInput.value.trim().length > 0) {
            exportParams.append('search', searchInput.value.trim());
        }

        const exportUrl = route('billing-cycles.export', props.billingCycle.id);
        const urlWithParams = exportParams.toString() ? `${exportUrl}?${exportParams.toString()}` : exportUrl;

        // Fetch the file
        const response = await fetch(urlWithParams, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            // Try to parse error message from response
            let errorMessage = 'Failed to export invoices. Please try again.';
            try {
                const errorData = await response.json();
                if (errorData.message) {
                    errorMessage = errorData.message;
                } else if (errorData.error) {
                    errorMessage = errorData.error;
                }
            } catch {
                // If response is not JSON, use status text
                errorMessage = `Export failed: ${response.statusText}`;
            }
            toast.error(errorMessage);
            return;
        }

        // Get the blob and trigger download
        const blob = await response.blob();
        const contentDisposition = response.headers.get('content-disposition');
        let filename = 'billing_cycle_invoices.xlsx';

        // Try to extract filename from content-disposition header
        if (contentDisposition) {
            const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
            if (filenameMatch && filenameMatch[1]) {
                filename = filenameMatch[1].replace(/['"]/g, '');
            }
        }

        // Create download link
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        toast.success('Export completed successfully. Your download should begin shortly.');
    } catch (error) {
        console.error('Export error:', error);
        toast.error('An unexpected error occurred during export. Please try again.');
    } finally {
        isExporting.value = false;
    }
};

const handlePayInvoice = async (invoice: Invoice) => {
    if (!invoice.student?.cash_wallet) {
        toast.error('Student does not have a wallet');
        return;
    }

    const walletBalance = invoice.student.cash_wallet.balance;
    const outstandingAmount = invoice.total_amount - invoice.paid_amount;

    if (walletBalance <= 0) {
        toast.error('Insufficient wallet balance');
        return;
    }

    payingInvoices.value[invoice.id] = true;

    try {
        const response = await fetch(
            route('billing-cycles.invoices.pay', {
                billingCycle: props.billingCycle.id,
                invoice: invoice.id,
            }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            },
        );

        const result = await response.json();

        if (result.success) {
            toast.success(result.data.message);
            // Refresh only the invoices data
            router.reload({ only: ['invoices'] });
        } else {
            toast.error(result.message || 'Payment failed');
        }
    } catch (error) {
        console.error('Payment error:', error);
        toast.error('An unexpected error occurred during payment. Please try again.');
    } finally {
        payingInvoices.value[invoice.id] = false;
    }
};

const handleBulkPayInvoices = () => {
    confirmDialog.showConfirmDialog(
        {
            title: 'Bulk Pay All Invoices',
            message: 'Process payment for all eligible invoices using student wallet balances? This will pay all pending and partial invoices that have sufficient wallet balance.',
            confirmText: 'Confirm Bulk Payment',
        },
        {
            onConfirm: async () => {
                isBulkPaying.value = true;

                try {
                    const response = await fetch(
                        route('billing-cycles.invoices.bulk-pay', {
                            billingCycle: props.billingCycle.id,
                        }),
                        {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        },
                    );

                    const result = await response.json();

                    if (result.success) {
                        toast.success(result.message);
                        // Show detailed results if available
                        if (result.data) {
                            const { successful, failed, skipped } = result.data;
                            console.log('Bulk payment results:', result.data);

                            // Show summary in toast
                            if (failed > 0 || skipped > 0) {
                                toast.info(`Details: ${successful} paid, ${failed} failed, ${skipped} skipped`);
                            }
                        }
                        // Refresh the invoices data
                        router.reload({ only: ['invoices'] });
                    } else {
                        toast.error(result.message || 'Bulk payment failed');
                    }
                } catch (error) {
                    console.error('Bulk payment error:', error);
                    toast.error('An unexpected error occurred during bulk payment. Please try again.');
                } finally {
                    isBulkPaying.value = false;
                }
            },
        },
    );
};

const invoiceColumns: ColumnDef<Invoice>[] = [
    {
        accessorKey: 'invoice_number',
        header: 'Invoice Number',
    },
    {
        accessorKey: 'student',
        header: 'Student',
        cell: ({ row }) => {
            const student = row.original.student;

            if (!student) {
                return 'Unknown student';
            }

            const code = student.student_id || 'N/A';
            const name = student.full_name || 'Unknown';

            return `${code} - ${name}`;
        },
    },
    {
        id: 'campus',
        header: 'Campus',
        cell: ({ row }) => row.original.student?.campus?.name ?? 'N/A',
    },
    {
        accessorKey: 'total_amount',
        header: 'Total Amount',
        cell: ({ row }) => formatCurrency(row.original.total_amount),
    },
    {
        accessorKey: 'paid_amount',
        header: 'Paid Amount',
        cell: ({ row }) => formatCurrency(row.original.paid_amount),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.original.status;
            return h(
                Badge,
                {
                    variant: getInvoiceStatusVariant(status),
                },
                () => status.charAt(0).toUpperCase() + status.slice(1),
            );
        },
    },
    {
        accessorKey: 'due_date',
        header: 'Due Date',
        cell: ({ row }) => formatDate(row.original.due_date),
    },
    {
        id: 'wallet_balance',
        header: 'Wallet Balance',
        cell: ({ row }) => {
            const wallet = row.original.student?.cash_wallet;
            if (!wallet) return 'N/A';
            return formatCurrency(wallet.balance);
        },
    },
    {
        id: 'outstanding',
        header: 'Outstanding',
        cell: ({ row }) => {
            const invoice = row.original;
            // Only show outstanding for invoices that are not fully paid
            if (invoice.status === 'paid') {
                return h('span', { class: 'text-green-600 font-medium' }, 'Fully Paid');
            }
            const outstanding = invoice.total_amount - invoice.paid_amount;
            return formatCurrency(outstanding);
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const invoice = row.original;
            const wallet = invoice.student?.cash_wallet;
            const outstanding = invoice.total_amount - invoice.paid_amount;
            const canPay = can('pay_invoice') && outstanding > 0 && invoice.status !== 'cancelled' && wallet && wallet.balance > 0;

            const buttons = [];

            // View Details button (always visible)
            buttons.push(
                h(
                    Link,
                    {
                        href: route('invoices.show', invoice.id),
                    },
                    () =>
                        h(
                            Button,
                            {
                                size: 'sm',
                                variant: 'ghost',
                            },
                            () => [h(Eye, { class: 'mr-2 h-4 w-4' })],
                        ),
                ),
            );

            // Pay with Wallet button (conditional)
            if (canPay) {
                buttons.push(
                    h(
                        Button,
                        {
                            size: 'sm',
                            variant: 'outline',
                            disabled: payingInvoices.value[invoice.id],
                            onClick: () => handlePayInvoice(invoice),
                        },
                        () => [payingInvoices.value[invoice.id] ? h(Loader2, { class: 'mr-2 h-4 w-4 animate-spin' }) : h(Wallet, { class: 'mr-2 h-4 w-4' }), 'Pay'],
                    ),
                );
            }

            return h('div', { class: 'flex items-center gap-2' }, buttons);
        },
    },
];
</script>

<template>
    <Head :title="`Billing Cycle: ${billingCycle.name}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">{{ billingCycle.name }}</h1>
                <p class="text-muted-foreground">View billing cycle details and invoices</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('billing-cycles.index')">
                    <Button variant="outline">Back to List</Button>
                </Link>
                <Button v-if="can('view_billing_cycle')" variant="outline" :disabled="isExporting" @click="handleExport">
                    <Loader2 v-if="isExporting" class="mr-2 h-4 w-4 animate-spin" />
                    <Download v-else class="mr-2 h-4 w-4" />
                    Export to Excel
                </Button>
                <Link v-if="can('edit_billing_cycle') && billingCycle.status === 'draft'" :href="route('billing-cycles.edit', billingCycle.id)">
                    <Button variant="outline">
                        <Edit class="mr-2 h-4 w-4" />
                        Edit
                    </Button>
                </Link>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Billing Cycle Information</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Status</p>
                        <Badge :variant="getStatusVariant(billingCycle.status)" class="mt-1">
                            {{ billingCycle.status.charAt(0).toUpperCase() + billingCycle.status.slice(1) }}
                        </Badge>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Semester</p>
                        <p class="text-base">{{ billingCycle.semester.name }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Start Date</p>
                        <p class="text-base">{{ formatDate(billingCycle.start_date) }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">End Date</p>
                        <p class="text-base">{{ formatDate(billingCycle.end_date) }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Due Date</p>
                        <p class="text-base">{{ formatDate(billingCycle.due_date) }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Actions</CardTitle>
                    <CardDescription>Manage the billing cycle status</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <Button v-if="can('activate_billing_cycle') && billingCycle.status === 'draft'" class="w-full" :disabled="isActivating" @click="handleActivate">
                        <Loader2 v-if="isActivating" class="mr-2 h-4 w-4 animate-spin" />
                        Activate Billing Cycle
                    </Button>
                    <Button v-if="can('close_billing_cycle') && billingCycle.status === 'active'" class="w-full" variant="secondary" :disabled="isClosing" @click="handleClose">
                        <Loader2 v-if="isClosing" class="mr-2 h-4 w-4 animate-spin" />
                        Close Billing Cycle
                    </Button>
                    <Button v-if="can('delete_billing_cycle') && billingCycle.status === 'draft'" class="w-full" variant="destructive" :disabled="isDeleting" @click="handleDelete">
                        <Loader2 v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                        Delete Billing Cycle
                    </Button>
                </CardContent>
            </Card>
        </div>
        <Card>
            <CardHeader>
                <CardTitle>Invoice Filters</CardTitle>
                <CardDescription>Filter invoices by status or campus</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="invoice-search">Search</Label>
                        <Input id="invoice-search" v-model="searchInput" placeholder="Search by invoice number, student ID, or name" />
                        <p class="text-muted-foreground text-xs">Filters by invoice number, student ID, or full name.</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="invoice-status-filter">Invoice Status</Label>
                        <Select :model-value="currentStatus" @update:model-value="handleStatusFilterChange">
                            <SelectTrigger id="invoice-status-filter">
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in statusFilterOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="invoice-campus-filter">Campus</Label>
                        <Select :model-value="currentCampusId" @update:model-value="handleCampusFilterChange">
                            <SelectTrigger id="invoice-campus-filter">
                                <SelectValue placeholder="All Campuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in campusFilterOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Invoices ({{ totalInvoices }})</CardTitle>
                        <CardDescription>Invoices generated for this billing cycle</CardDescription>
                    </div>
                    <Button v-if="can('pay_invoice') && hasInvoices" :disabled="isBulkPaying" @click="handleBulkPayInvoices">
                        <Loader2 v-if="isBulkPaying" class="mr-2 h-4 w-4 animate-spin" />
                        <Wallet v-else class="mr-2 h-4 w-4" />
                        Bulk Pay All Invoices
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <DataTable v-if="hasInvoices" :columns="invoiceColumns" :data="invoices.data" />
                <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                    <AlertCircle class="text-muted-foreground h-12 w-12" />
                    <h3 class="mt-4 text-lg font-semibold">No invoices yet</h3>
                    <p class="text-muted-foreground mt-2 text-sm">Invoices will appear here once they are generated for this billing cycle.</p>
                </div>
                <DataPagination v-if="hasInvoices" :pagination-data="invoices" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
