<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { route } from 'ziggy-js';

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email?: string;
    phone?: string;
    status?: string;
    campus_id?: number;
}

interface BillingCycle {
    id: number;
    name: string;
    semester: {
        id: number;
        name: string;
    };
}

interface InvoiceItem {
    id: number;
    item_type: 'tuition' | 'egc' | 'retake' | 'miscellaneous';
    description: string;
    quantity: number;
    unit_price: number;
    total_price: number;
}

interface InvoiceDiscount {
    id: number;
    discount_type: 'scholarship' | 'voucher';
    discount_source: string;
    description: string;
    amount: number;
    reference_id?: number;
    voucher?: {
        discount_type?: 'percentage' | 'fixed_amount';
        discount_value?: number;
    };
    scholarship?: {
        type?: 'percentage' | 'fixed_amount';
        amount?: number;
    };
}

interface Invoice {
    id: number;
    invoice_number: string;
    student_id: number;
    billing_cycle_id: number;
    subtotal: number;
    discount_total: number;
    total_amount: number;
    paid_amount: number;
    status: 'draft' | 'pending' | 'paid' | 'overdue' | 'cancelled';
    due_date: string;
    paid_at: string | null;
    student: Student;
    billing_cycle: BillingCycle;
    items: InvoiceItem[];
    discounts: InvoiceDiscount[];
}

interface Props {
    invoice: Invoice;
}

const props = defineProps<Props>();

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
        case 'paid':
            return 'default';
        case 'pending':
            return 'secondary';
        case 'overdue':
            return 'destructive';
        case 'draft':
            return 'outline';
        case 'cancelled':
            return 'outline';
        default:
            return 'outline';
    }
};

const getItemTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        tuition: 'Tuition',
        egc: 'EGC Fee',
        retake: 'Retake Fee',
        miscellaneous: 'Miscellaneous',
    };
    return labels[type] || type;
};

const getDiscountTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
        scholarship: 'Scholarship',
        voucher: 'Voucher',
    };
    return labels[type] || type;
};

const formatDiscountDetails = (discount: InvoiceDiscount) => {
    if (discount.discount_type === 'voucher' && discount.voucher) {
        const { discount_type, discount_value } = discount.voucher;
        if (discount_type === 'percentage') {
            return `${discount_value}% discount`;
        } else if (discount_type === 'fixed_amount') {
            return `${formatCurrency(discount_value || 0)} fixed discount`;
        }
    } else if (discount.discount_type === 'scholarship' && discount.scholarship) {
        const { type, amount } = discount.scholarship;
        if (type === 'percentage') {
            return `${amount}% scholarship`;
        } else if (type === 'fixed_amount') {
            return `${formatCurrency(amount || 0)} fixed scholarship`;
        }
    }
    return '';
};

const outstandingBalance = props.invoice.total_amount - props.invoice.paid_amount;
</script>

<template>
    <Head :title="`Invoice ${invoice.invoice_number}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Link :href="route('invoices.index')">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Invoice {{ invoice.invoice_number }}</h1>
                    <p class="text-muted-foreground">
                        {{ invoice.student.student_id }} - {{ invoice.student.full_name }}
                    </p>
                </div>
            </div>
            <Badge :variant="getStatusVariant(invoice.status)">
                {{ invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1) }}
            </Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Student Information</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Student ID:</span>
                    <span class="font-medium">{{ invoice.student.student_id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Full Name:</span>
                    <span class="font-medium">{{ invoice.student.full_name }}</span>
                </div>
                <div v-if="invoice.student.email" class="flex justify-between">
                    <span class="text-muted-foreground">Email:</span>
                    <span class="font-medium">{{ invoice.student.email }}</span>
                </div>
                <div v-if="invoice.student.phone" class="flex justify-between">
                    <span class="text-muted-foreground">Phone:</span>
                    <span class="font-medium">{{ invoice.student.phone }}</span>
                </div>
                <div v-if="invoice.student.status" class="flex justify-between">
                    <span class="text-muted-foreground">Status:</span>
                    <Badge variant="outline">{{ invoice.student.status }}</Badge>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Invoice Details</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Invoice Number:</span>
                        <span class="font-medium">{{ invoice.invoice_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Billing Cycle:</span>
                        <span class="font-medium">{{ invoice.billing_cycle.name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Semester:</span>
                        <span class="font-medium">{{ invoice.billing_cycle.semester.name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Due Date:</span>
                        <span class="font-medium">{{ formatDate(invoice.due_date) }}</span>
                    </div>
                    <div v-if="invoice.paid_at" class="flex justify-between">
                        <span class="text-muted-foreground">Paid At:</span>
                        <span class="font-medium">{{ formatDate(invoice.paid_at) }}</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Payment Summary</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Subtotal:</span>
                        <span class="font-medium">{{ formatCurrency(invoice.subtotal) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Discounts:</span>
                        <span class="font-medium text-green-600">-{{ formatCurrency(invoice.discount_total) }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2">
                        <span class="font-semibold">Total Amount:</span>
                        <span class="font-semibold">{{ formatCurrency(invoice.total_amount) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Paid Amount:</span>
                        <span class="font-medium">{{ formatCurrency(invoice.paid_amount) }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2">
                        <span class="font-semibold">Outstanding Balance:</span>
                        <span class="font-semibold" :class="outstandingBalance > 0 ? 'text-red-600' : 'text-green-600'">
                            {{ formatCurrency(outstandingBalance) }}
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Invoice Items</CardTitle>
                <CardDescription>Charges and fees for this invoice</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Type</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead class="text-right">Quantity</TableHead>
                            <TableHead class="text-right">Unit Price</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in invoice.items" :key="item.id">
                            <TableCell>
                                <Badge variant="outline">{{ getItemTypeLabel(item.item_type) }}</Badge>
                            </TableCell>
                            <TableCell>{{ item.description }}</TableCell>
                            <TableCell class="text-right">{{ item.quantity }}</TableCell>
                            <TableCell class="text-right">{{ formatCurrency(item.unit_price) }}</TableCell>
                            <TableCell class="text-right font-medium">{{ formatCurrency(item.total_price) }}</TableCell>
                        </TableRow>
                        <TableRow v-if="invoice.items.length === 0">
                            <TableCell colspan="5" class="text-center text-muted-foreground">No items found</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card v-if="invoice.discounts.length > 0">
            <CardHeader>
                <CardTitle>Discounts Applied</CardTitle>
                <CardDescription>Scholarships and vouchers applied to this invoice</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Type</TableHead>
                            <TableHead>Source</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead>Details</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="discount in invoice.discounts" :key="discount.id">
                            <TableCell>
                                <Badge variant="outline">{{ getDiscountTypeLabel(discount.discount_type) }}</Badge>
                            </TableCell>
                            <TableCell>{{ discount.discount_source }}</TableCell>
                            <TableCell>{{ discount.description }}</TableCell>
                            <TableCell class="text-sm text-muted-foreground">
                                {{ formatDiscountDetails(discount) }}
                            </TableCell>
                            <TableCell class="text-right font-medium text-green-600">-{{ formatCurrency(discount.amount) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
