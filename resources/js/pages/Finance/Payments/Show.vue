<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency, formatDate } from '@/utils/format';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

interface PaymentApplication {
    id: number;
    entry_type: string;
    amount: number;
    applied_at: string | null;
    invoice_line: {
        id: number;
        description_snapshot: string;
        amount_snapshot: number;
        invoice?: {
            id: number;
            invoice_number: string;
            status: string;
        } | null;
        charge?: {
            id: number;
            description: string;
            amount: number;
            semester?: {
                id: number;
                name: string;
            } | null;
        } | null;
    } | null;
}

interface Payment {
    id: number;
    amount: number;
    paid_at: string | null;
    source: string | null;
    external_ref: string | null;
    status: string;
    method: string;
    notes: string | null;
    student: {
        id: number;
        full_name: string;
        student_id: string;
    } | null;
    unapplied_amount: number;
    allocated_amount: number;
    applications: PaymentApplication[];
    received_by?: {
        name: string;
    } | null;
}

const props = defineProps<{
    payment: Payment;
}>();

const form = useForm({
    charge_id: '',
    amount: '',
});

const submitAllocation = () => {
    if (!form.charge_id || !form.amount) return;

    form.post(route('finance.payments.allocate', props.payment.id), {
        onSuccess: () => {
            toast.success('Allocation successful');
            form.reset();
        },
        onError: () => {
            toast.error('Allocation failed');
        },
    });
};

const getApplicationDescription = (application: PaymentApplication) => application.invoice_line?.charge?.description || application.invoice_line?.description_snapshot || '-';

const getApplicationSemester = (application: PaymentApplication) => application.invoice_line?.charge?.semester?.name || '-';

const getApplicationInvoice = (application: PaymentApplication) => application.invoice_line?.invoice?.invoice_number || '-';

const getApplicationAmountClass = (application: PaymentApplication) => (application.amount < 0 ? 'text-red-600' : 'text-foreground');
</script>

<template>
    <Head :title="`Payment #${payment.id}`" />

    <div class="flex items-center justify-between">
        <h2 class="text-xl leading-tight font-semibold text-gray-800">Payment #{{ payment.id }}</h2>
        <Link :href="route('finance.payments.index')">
            <Button variant="outline">Back to List</Button>
        </Link>
    </div>

    <!-- Payment Info -->
    <div class="grid gap-6 md:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle>Payment Information</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Amount</span>
                    <span class="text-lg font-bold">{{ formatCurrency(payment.amount) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Date</span>
                    <span>{{ formatDate(payment.paid_at) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Method/Source</span>
                    <div class="flex gap-2">
                        <Badge variant="outline">{{ payment.method }}</Badge>
                        <Badge variant="secondary">{{ payment.source }}</Badge>
                    </div>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">External Ref</span>
                    <span class="font-mono">{{ payment.external_ref || '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Status</span>
                    <Badge variant="outline">{{ payment.status }}</Badge>
                </div>
                <Separator />
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Unallocated Balance</span>
                    <span class="font-bold text-green-600">{{ formatCurrency(payment.unapplied_amount) }}</span>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Student Information</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4">
                <div v-if="payment.student">
                    <div class="text-lg font-bold">{{ payment.student.full_name }}</div>
                    <div class="text-muted-foreground">{{ payment.student.student_id }}</div>
                </div>
                <div v-else class="text-muted-foreground">No student linked.</div>

                <div v-if="payment.notes" class="mt-4">
                    <Label>Notes</Label>
                    <p class="mt-1 text-sm text-gray-600">{{ payment.notes }}</p>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Applications -->
    <Card>
        <CardHeader>
            <CardTitle>Payment Applications</CardTitle>
            <CardDescription> Settlement entries linked to invoice lines. </CardDescription>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Invoice</TableHead>
                        <TableHead>Charge</TableHead>
                        <TableHead>Semester</TableHead>
                        <TableHead>Entry</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="application in payment.applications" :key="application.id">
                        <TableCell>{{ application.applied_at ? formatDate(application.applied_at) : '-' }}</TableCell>
                        <TableCell>{{ getApplicationInvoice(application) }}</TableCell>
                        <TableCell>{{ getApplicationDescription(application) }}</TableCell>
                        <TableCell>{{ getApplicationSemester(application) }}</TableCell>
                        <TableCell>
                            <Badge variant="outline">{{ application.entry_type }}</Badge>
                        </TableCell>
                        <TableCell class="text-right" :class="getApplicationAmountClass(application)">{{ formatCurrency(application.amount) }}</TableCell>
                    </TableRow>
                    <TableRow v-if="payment.applications.length === 0">
                        <TableCell colspan="6" class="text-muted-foreground h-24 text-center"> No payment applications yet. </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>

    <!-- Manual Allocation Form (Future Work: Dropdown of charges) -->
    <Card v-if="payment.unapplied_amount > 0">
        <CardHeader>
            <CardTitle>Manual Allocation</CardTitle>
            <CardDescription>Allocate remaining balance to a charge ID (Temporary UI).</CardDescription>
        </CardHeader>
        <CardContent>
            <form @submit.prevent="submitAllocation" class="flex items-end gap-4">
                <div class="grid w-full max-w-sm items-center gap-1.5">
                    <Label for="charge_id">Charge ID</Label>
                    <Input id="charge_id" v-model="form.charge_id" placeholder="Enter Charge ID" />
                </div>
                <div class="grid w-full max-w-sm items-center gap-1.5">
                    <Label for="amount">Amount</Label>
                    <Input id="amount" v-model="form.amount" type="number" step="0.01" />
                </div>
                <Button type="submit" :disabled="form.processing"> Allocate </Button>
            </form>
        </CardContent>
    </Card>
</template>
