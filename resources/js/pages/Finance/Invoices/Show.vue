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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatCurrency, formatDate } from '@/utils/format';
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertCircle,
    ChevronLeft,
    CreditCard,
    DollarSign,
    Receipt,
    User as UserIcon
} from 'lucide-vue-next';
import { route } from 'ziggy-js';

interface Charge {
    id: number;
    charge_type: string;
    description: string;
    amount: number;
    paid_amount: number;
    balance: number;
    effective_at: string;
    source_type: string;
    status: string;
    allocations: Array<{
        id: number;
        allocated_amount: number;
        payment: {
            id: number;
            amount: number;
            paid_at: string;
            method: string;
        }
    }>;
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
        }
    };
    semester: {
        id: number;
        name: string;
    };
    billing_cycle: {
        name: string;
    } | null;
    due_date: string;
    created_at: string;

    // Status metrics
    real_time_status: string;
    total_amount: number;
    paid_amount: number;
    outstanding_balance: number;

    // Relations
    charges: Charge[];
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
                    <span class="text-muted-foreground">Total Charges</span>
                    <span class="font-bold text-lg">{{ formatCurrency(invoice.total_amount) }}</span>
                </div>
                <div class="flex justify-between items-center text-green-600">
                    <span class="text-muted-foreground">Paid Amount</span>
                    <span class="font-bold text-lg">- {{ formatCurrency(invoice.paid_amount) }}</span>
                </div>
                <Separator />
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

    <!-- Details Tabs -->
    <Tabs default-value="charges" class="w-full">
        <TabsList>
            <TabsTrigger value="charges" class="flex items-center gap-2">
                <Receipt class="h-4 w-4" /> Charge Details
            </TabsTrigger>
            <TabsTrigger value="payments" class="flex items-center gap-2">
                <CreditCard class="h-4 w-4" /> Payments & Allocations
            </TabsTrigger>
        </TabsList>

        <!-- Charges Tab -->
        <TabsContent value="charges" class="mt-6">
            <Card>
                <CardHeader>
                    <CardTitle>Charge Breakdown</CardTitle>
                    <CardDescription>Detailed list of all charges included in this invoice.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Charge Type</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Effective Date</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                                <TableHead class="text-right">Paid</TableHead>
                                <TableHead class="text-right">Balance</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="charge in invoice.charges" :key="charge.id">
                                <TableCell class="font-medium">
                                    {{ getChargeTypeLabel(charge.charge_type) }}
                                </TableCell>
                                <TableCell>
                                    {{ charge.description }}
                                    <div v-if="charge.source_type === 'Manual'"
                                        class="text-xs text-muted-foreground mt-1">
                                        Manually added
                                    </div>
                                </TableCell>
                                <TableCell>{{ formatDate(charge.effective_at) }}</TableCell>
                                <TableCell class="text-right font-medium"
                                    :class="charge.amount < 0 ? 'text-green-600' : ''">
                                    {{ formatCurrency(charge.amount) }}
                                </TableCell>
                                <TableCell class="text-right text-muted-foreground">
                                    {{ formatCurrency(charge.paid_amount) }}
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
        </TabsContent>

        <!-- Payments Tab -->
        <TabsContent value="payments" class="mt-6">
            <Card>
                <CardHeader>
                    <CardTitle>Payment History</CardTitle>
                    <CardDescription>Payments allocated to charges on this invoice.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Payment #</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead>Allocated To</TableHead>
                                <TableHead class="text-right">Allocated Amount</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-for="charge in invoice.charges" :key="charge.id">
                                <TableRow v-for="allocation in charge.allocations" :key="allocation.id">
                                    <TableCell class="font-medium">
                                        <Link :href="route('finance.payments.show', allocation.payment.id)"
                                            class="hover:underline text-primary">
                                            {{ allocation.payment.id }}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{{ formatDate(allocation.payment.paid_at) }}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{{ allocation.payment.method }}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <span class="text-sm text-muted-foreground">Charge:</span> {{
                                            charge.description }}
                                    </TableCell>
                                    <TableCell class="text-right font-medium text-green-600">
                                        {{ formatCurrency(allocation.allocated_amount) }}
                                    </TableCell>
                                </TableRow>
                            </template>
                            <TableRow v-if="!invoice.charges.some(c => c.allocations.length > 0)">
                                <TableCell colspan="5" class="h-24 text-center text-muted-foreground">
                                    No payments allocated yet.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </TabsContent>
    </Tabs>
</template>
