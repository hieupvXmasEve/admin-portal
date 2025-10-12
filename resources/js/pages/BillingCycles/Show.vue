<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermission } from '@/composables/usePermission';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { AlertCircle, Edit, Loader2 } from 'lucide-vue-next';
import { h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

interface Semester {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
}

interface Student {
    id: number;
    student_code: string;
    first_name: string;
    last_name: string;
}

interface Invoice {
    id: number;
    invoice_number: string;
    student_id: number;
    total_amount: number;
    paid_amount: number;
    status: string;
    due_date: string;
    student: Student;
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
    invoices: Invoice[];
}

interface Props {
    billingCycle: BillingCycle;
}

const props = defineProps<Props>();
const { can } = usePermission();

const isActivating = ref(false);
const isClosing = ref(false);
const isDeleting = ref(false);
const showDeleteDialog = ref(false);
const showActivateDialog = ref(false);
const showCloseDialog = ref(false);

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
        case 'pending':
            return 'outline';
        case 'overdue':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const handleActivate = () => {
    isActivating.value = true;
    router.post(
        route('billing-cycles.activate', props.billingCycle.id),
        {},
        {
            onSuccess: () => {
                toast.success('Billing cycle activated successfully');
                showActivateDialog.value = false;
            },
            onError: (errors) => {
                if (errors.error) {
                    toast.error(errors.error as string);
                } else {
                    toast.error('Failed to activate billing cycle');
                }
            },
            onFinish: () => {
                isActivating.value = false;
            },
        },
    );
};

const handleClose = () => {
    isClosing.value = true;
    router.post(
        route('billing-cycles.close', props.billingCycle.id),
        {},
        {
            onSuccess: () => {
                toast.success('Billing cycle closed successfully');
                showCloseDialog.value = false;
            },
            onError: (errors) => {
                if (errors.error) {
                    toast.error(errors.error as string);
                } else {
                    toast.error('Failed to close billing cycle');
                }
            },
            onFinish: () => {
                isClosing.value = false;
            },
        },
    );
};

const handleDelete = () => {
    isDeleting.value = true;
    router.delete(route('billing-cycles.destroy', props.billingCycle.id), {
        onSuccess: () => {
            toast.success('Billing cycle deleted successfully');
        },
        onError: (errors) => {
            if (errors.error) {
                toast.error(errors.error as string);
            } else {
                toast.error('Failed to delete billing cycle');
            }
        },
        onFinish: () => {
            isDeleting.value = false;
            showDeleteDialog.value = false;
        },
    });
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
            return `${student.student_code} - ${student.first_name} ${student.last_name}`;
        },
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
                        <p class="text-sm font-medium text-muted-foreground">Status</p>
                        <Badge :variant="getStatusVariant(billingCycle.status)" class="mt-1">
                            {{ billingCycle.status.charAt(0).toUpperCase() + billingCycle.status.slice(1) }}
                        </Badge>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Semester</p>
                        <p class="text-base">{{ billingCycle.semester.name }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Start Date</p>
                        <p class="text-base">{{ formatDate(billingCycle.start_date) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">End Date</p>
                        <p class="text-base">{{ formatDate(billingCycle.end_date) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Due Date</p>
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
                    <Button
                        v-if="can('activate_billing_cycle') && billingCycle.status === 'draft'"
                        class="w-full"
                        @click="showActivateDialog = true"
                    >
                        Activate Billing Cycle
                    </Button>
                    <Button
                        v-if="can('close_billing_cycle') && billingCycle.status === 'active'"
                        class="w-full"
                        variant="secondary"
                        @click="showCloseDialog = true"
                    >
                        Close Billing Cycle
                    </Button>
                    <Button
                        v-if="can('delete_billing_cycle') && billingCycle.status === 'draft'"
                        class="w-full"
                        variant="destructive"
                        @click="showDeleteDialog = true"
                    >
                        Delete Billing Cycle
                    </Button>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Invoices ({{ billingCycle.invoices.length }})</CardTitle>
                <CardDescription>Invoices generated for this billing cycle</CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable v-if="billingCycle.invoices.length > 0" :columns="invoiceColumns" :data="billingCycle.invoices" />
                <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                    <AlertCircle class="h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-semibold">No invoices yet</h3>
                    <p class="mt-2 text-sm text-muted-foreground">Invoices will appear here once they are generated for this billing cycle.</p>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Activate Dialog -->
    <AlertDialog v-model:open="showActivateDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Activate Billing Cycle</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to activate this billing cycle? Once activated, you will not be able to edit it.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="handleActivate" :disabled="isActivating">
                    <Loader2 v-if="isActivating" class="mr-2 h-4 w-4 animate-spin" />
                    Activate
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Close Dialog -->
    <AlertDialog v-model:open="showCloseDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Close Billing Cycle</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to close this billing cycle? This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="handleClose" :disabled="isClosing">
                    <Loader2 v-if="isClosing" class="mr-2 h-4 w-4 animate-spin" />
                    Close
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Delete Dialog -->
    <AlertDialog v-model:open="showDeleteDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Billing Cycle</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete this billing cycle? This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="handleDelete" :disabled="isDeleting" class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                    <Loader2 v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
