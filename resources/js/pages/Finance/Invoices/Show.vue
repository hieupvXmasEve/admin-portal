<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCurrency, formatDate } from '@/utils/format';
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    ChevronLeft,
    CreditCard,
    DollarSign,
    Percent,
    Receipt,
    User as UserIcon
} from 'lucide-vue-next';
import { route } from 'ziggy-js';

interface Charge {
    id: number;
    invoice_line_id: number;
    charge_type: string;
    description: string;
    amount: number;
    paid_amount: number;
    discount_amount: number;
    settled_amount: number;
    balance: number;
    effective_at: string | null;
    source_type: string | null;
    status: string;
}

interface SettlementEntry {
    id: string;
    entry_group: 'payment' | 'discount';
    entry_type: string;
    applied_at: string | null;
    amount: number;
    charge: {
        id: number | null;
        description: string;
        charge_type: string | null;
    };
    payment: {
        id: number;
        amount: number;
        paid_at: string | null;
        method: string;
        status: string;
        external_ref: string | null;
    } | null;
    discount: {
        id: number;
        description: string;
        discount_type: string;
        discount_source: string | null;
    } | null;
}

interface Invoice {
    id: number;
    invoice_number: string;
    student: {
        id: number;
        full_name: string;
        student_id: string;
        email: string;
        program: {
            name: string;
        } | null
    };
    semester: {
        id: number;
        name: string;
    };
    billing_cycle: {
        name: string;
    } | null;
    due_date: string | null;
    created_at: string | null;

    // Status metrics
    real_time_status: string;
    subtotal: number;
    discount_total: number;
    total_amount: number;
    paid_amount: number;
    outstanding_balance: number;

    // Relations
    charges: Charge[];
    settlement_entries: SettlementEntry[];
}

defineProps<{
    invoice: Invoice;
}>();

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'paid': return 'success';
        case 'overdue': return 'destructive';
        case 'open': return 'default';
        case 'zero_amount': return 'secondary';
        default: return 'outline';
    }
};

const getChargeTypeLabel = (type: string) => {
    return type.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const getSettlementBadgeVariant = (entryGroup: SettlementEntry['entry_group']) => {
    return entryGroup === 'payment' ? 'default' : 'secondary';
};

const getSettlementLabel = (entry: SettlementEntry) => {
    return entry.entry_group === 'payment' ? 'Payment' : 'Discount';
};

const getSettlementReference = (entry: SettlementEntry) => {
    if (entry.payment) {
        return `#${entry.payment.id}`;
    }

    if (entry.discount) {
        return `#${entry.discount.id}`;
    }

    return '-';
};

const getSettlementSource = (entry: SettlementEntry) => {
    if (entry.payment) {
        return entry.payment.method;
    }

    return entry.discount?.discount_type ?? '-';
};

const getSettlementDescription = (entry: SettlementEntry) => {
    if (entry.payment) {
        return entry.payment.external_ref || 'Cash application';
    }

    return entry.discount?.description || entry.discount?.discount_source || 'Discount allocation';
};
</script>

<template>

    <Head :title="`Invoice ${invoice.invoice_number}`" />

    <!-- Header -->
    <div class="flex items-center gap-4">
        <Button variant="outline" size="icon" as-child>
            <Link :href="route('finance.invoices.index')">
                <ChevronLeft class="h-4 w-4" />
            </Link>
        </Button>
        <div>
            <h2 class="text-3xl font-bold tracking-tight flex items-center gap-2">
                Invoice {{ invoice.invoice_number }}
                <Badge :variant="getStatusBadgeVariant(invoice.real_time_status)" class="ml-2">
                    {{ invoice.real_time_status.toUpperCase() }}
                </Badge>
            </h2>
            <p class="text-muted-foreground">
                Created on {{ formatDate(invoice.created_at) }} • Due on {{ formatDate(invoice.due_date) }}
            </p>
        </div>
        <div class="ml-auto flex gap-2">
            <!-- <Button variant="outline">
                    <Download class="mr-2 h-4 w-4" /> Download PDF
                </Button> -->
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Student Info -->
        <Card class="md:col-span-2">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <UserIcon class="h-5 w-5" /> Student Information
                </CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm font-medium text-muted-foreground">Full Name</p>
                    <p class="text-lg">{{ invoice.student.full_name }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-muted-foreground">Student ID</p>
                    <p class="text-lg">{{ invoice.student.student_id }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-muted-foreground">Program</p>
                    <p>{{ invoice.student.program?.name || 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-muted-foreground">Email</p>
                    <p>{{ invoice.student.email }}</p>
                </div>
            </CardContent>
        </Card>

        <!-- Invoice Summary -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <DollarSign class="h-5 w-5" /> Summary
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex justify-between items-center">
                    <span class="text-muted-foreground">Semester</span>
                    <span class="font-medium">{{ invoice.semester.name }}</span>
                </div>
                <Separator />
                <div class="flex justify-between items-center">
                    <span class="text-muted-foreground">Gross Charges</span>
                    <span class="font-bold text-lg">{{ formatCurrency(invoice.subtotal) }}</span>
                </div>
                <div class="flex justify-between items-center text-amber-600">
                    <span class="text-muted-foreground">Discount Allocations</span>
                    <span class="font-bold text-lg">- {{ formatCurrency(invoice.discount_total) }}</span>
                </div>
                <div class="flex justify-between items-center text-green-600">
                    <span class="text-muted-foreground">Cash Applied</span>
                    <span class="font-bold text-lg">- {{ formatCurrency(invoice.paid_amount) }}</span>
                </div>
                <Separator />
                <div class="flex justify-between items-center">
                    <span class="text-muted-foreground">Net Invoice Amount</span>
                    <span class="font-medium">{{ formatCurrency(invoice.total_amount) }}</span>
                </div>
                <div class="flex justify-between items-center pt-2">
                    <div class="flex flex-col">
                        <span class="font-bold text-xl">Balance Due</span>
                        <span v-if="invoice.real_time_status === 'overdue'"
                            class="text-xs text-destructive flex items-center gap-1">
                            <AlertCircle class="h-3 w-3" /> Overdue
                        </span>
                    </div>
                    <span class="font-bold text-2xl text-primary">{{ formatCurrency(invoice.outstanding_balance)
                    }}</span>
                </div>
            </CardContent>
        </Card>
    </div>

    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Receipt class="h-4 w-4" /> Charge Breakdown
            </CardTitle>
            <CardDescription>Line-level charges with cash applied and discount allocations.</CardDescription>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Charge Type</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Effective Date</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead class="text-right">Cash Applied</TableHead>
                        <TableHead class="text-right">Discount Applied</TableHead>
                        <TableHead class="text-right">Balance</TableHead>
                        <TableHead>Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="charge in invoice.charges" :key="charge.invoice_line_id">
                        <TableCell class="font-medium">
                            {{ getChargeTypeLabel(charge.charge_type) }}
                        </TableCell>
                        <TableCell>
                            {{ charge.description }}
                            <div v-if="charge.source_type === 'Manual'" class="text-xs text-muted-foreground mt-1">
                                Manually added
                            </div>
                        </TableCell>
                        <TableCell>{{ formatDate(charge.effective_at) }}</TableCell>
                        <TableCell class="text-right font-medium" :class="charge.amount < 0 ? 'text-green-600' : ''">
                            {{ formatCurrency(charge.amount) }}
                        </TableCell>
                        <TableCell class="text-right text-green-600">
                            {{ formatCurrency(charge.paid_amount) }}
                        </TableCell>
                        <TableCell class="text-right text-amber-600">
                            {{ formatCurrency(charge.discount_amount) }}
                        </TableCell>
                        <TableCell class="text-right">
                            {{ formatCurrency(charge.balance) }}
                        </TableCell>
                        <TableCell>
                            <Badge variant="outline" v-if="charge.status === 'void'"
                                class="text-destructive border-destructive">Void</Badge>
                            <Badge variant="outline" v-else>Active</Badge>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>

    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <CreditCard class="h-4 w-4" /> Payments & Allocations
            </CardTitle>
            <CardDescription>Settlement ledger from `payment_applications` and `discount_allocations`.</CardDescription>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Type</TableHead>
                        <TableHead>Reference</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Method / Source</TableHead>
                        <TableHead>Details</TableHead>
                        <TableHead>Allocated To</TableHead>
                        <TableHead>Entry</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="entry in invoice.settlement_entries" :key="entry.id">
                        <TableCell>
                            <Badge :variant="getSettlementBadgeVariant(entry.entry_group)">
                                <span class="flex items-center gap-1">
                                    <Percent v-if="entry.entry_group === 'discount'" class="h-3 w-3" />
                                    <CreditCard v-else class="h-3 w-3" />
                                    {{ getSettlementLabel(entry) }}
                                </span>
                            </Badge>
                        </TableCell>
                        <TableCell class="font-medium">
                            <Link v-if="entry.payment" :href="route('finance.payments.show', entry.payment.id)"
                                class="hover:underline text-primary">
                                {{ getSettlementReference(entry) }}
                            </Link>
                            <span v-else>{{ getSettlementReference(entry) }}</span>
                        </TableCell>
                        <TableCell>{{ formatDate(entry.applied_at) }}</TableCell>
                        <TableCell>
                            <Badge variant="outline">{{ getSettlementSource(entry) }}</Badge>
                        </TableCell>
                        <TableCell class="text-sm text-muted-foreground">
                            {{ getSettlementDescription(entry) }}
                        </TableCell>
                        <TableCell>
                            {{ entry.charge.description }}
                        </TableCell>
                        <TableCell>
                            <Badge variant="outline">{{ entry.entry_type }}</Badge>
                        </TableCell>
                        <TableCell class="text-right font-medium"
                            :class="entry.entry_group === 'payment' ? 'text-green-600' : 'text-amber-600'">
                            {{ formatCurrency(entry.amount) }}
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="invoice.settlement_entries.length === 0">
                        <TableCell colspan="8" class="h-24 text-center text-muted-foreground">
                            No payment or discount allocations yet.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
