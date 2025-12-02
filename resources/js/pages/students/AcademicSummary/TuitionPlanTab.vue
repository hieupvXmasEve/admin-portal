<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { AlertTriangle, Calendar, CheckCircle, Clock, DollarSign, FileText, GraduationCap, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface TuitionPlanData {
    id: number;
    total_amount: number;
    currency: string;
    is_active: boolean;
    curriculum_version: {
        id: number;
        version_code: string;
        program: { id: number; name: string; code: string } | null;
        specialization: { id: number; name: string; code: string } | null;
    } | null;
    intake_semester: {
        id: number;
        code: string;
        name: string;
        start_date: string;
        end_date: string;
    } | null;
    terms: Array<{
        id: number;
        term_number: number;
        amount: number;
        discount_amount: number;
        amount_after_discount: number;
        due_date: string | null;
        formatted_due_date: string | null;
        semester: { id: number; code: string; name: string } | null;
        paid_amount: number;
        remaining_amount: number;
        payment_status: 'paid' | 'partial' | 'unpaid' | 'overdue';
        invoices: Array<{
            invoice_number: string;
            semester_id: number;
            due_date: string | null;
            status: string;
            item_total: number;
            item_discount: number;
            item_amount_after_discount: number;
            item_paid: number;
            voucher_discounts: Array<{
                code: string;
                name: string;
                amount: number;
                voucher: {
                    code: string;
                    name: string;
                    discount_type: string;
                    discount_value: number;
                } | null;
            }>;
        }>;
    }>;
    summary: {
        total_amount: number;
        total_discount: number;
        total_after_discount: number;
        total_paid: number;
        total_remaining: number;
        paid_count: number;
        partial_count: number;
        unpaid_count: number;
        overdue_count: number;
    };
}

interface ScholarshipAward {
    id: number;
    scholarship_code: string;
    awarded_at: string | null;
    formatted_awarded_at: string | null;
    notes: string | null;
    scholarship: {
        id: number;
        code: string;
        name: string;
        description: string | null;
        type: 'percentage' | 'fixed_amount';
        amount: number;
        valid_from: string | null;
        valid_until: string | null;
        formatted_valid_from: string | null;
        formatted_valid_until: string | null;
        is_active: boolean;
        is_valid: boolean;
    } | null;
}

interface Props {
    tuitionPlan: TuitionPlanData | null;
    scholarshipAward: ScholarshipAward | null;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const formatCurrency = (amount: number, currency: string = 'VND'): string => {
    return new Intl.NumberFormat('vi-VN').format(amount) + ' ' + currency;
};

const getPaymentStatusBadge = (status: string) => {
    const variants: Record<string, { variant: string; label: string; icon: any }> = {
        paid: { variant: 'default', label: 'Paid', icon: CheckCircle },
        partial: { variant: 'secondary', label: 'Partial', icon: Clock },
        unpaid: { variant: 'outline', label: 'Unpaid', icon: XCircle },
        overdue: { variant: 'destructive', label: 'Overdue', icon: AlertTriangle },
    };
    return variants[status] || variants.unpaid;
};

const getPaymentProgress = (paid: number, total: number): number => {
    if (!paid || !total || total === 0 || isNaN(paid) || isNaN(total)) return 0;
    const percentage = (paid / total) * 100;
    return Math.min(100, Math.max(0, percentage));
};

const paymentPercentage = computed(() => {
    if (!props.tuitionPlan || !props.tuitionPlan.summary) return 0;
    const { total_after_discount, total_paid } = props.tuitionPlan.summary;
    // Use amount after discount for progress calculation
    if (!total_after_discount || total_after_discount === 0 || isNaN(total_after_discount) || isNaN(total_paid)) return 0;
    const percentage = (total_paid / total_after_discount) * 100;
    return Math.min(100, Math.max(0, percentage));
});
</script>

<template>
    <div class="space-y-6">
        <!-- No Plan Alert -->
        <Alert v-if="!tuitionPlan" variant="default">
            <AlertTriangle class="h-4 w-4" />
            <AlertDescription>No active tuition plan found for this student.</AlertDescription>
        </Alert>

        <template v-else>
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-muted-foreground text-sm font-medium">Total Amount</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <DollarSign class="text-muted-foreground h-5 w-5" />
                            <p class="text-2xl font-bold">{{ formatCurrency(tuitionPlan.summary.total_amount, tuitionPlan.currency) }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-muted-foreground text-sm font-medium">Total Paid</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <CheckCircle class="h-5 w-5 text-green-600" />
                            <p class="text-2xl font-bold text-green-600">
                                {{ formatCurrency(tuitionPlan.summary.total_paid, tuitionPlan.currency) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-muted-foreground text-sm font-medium">Total Discount</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <DollarSign class="h-5 w-5 text-green-600" />
                            <p class="text-2xl font-bold text-green-600">
                                {{ formatCurrency(tuitionPlan.summary.total_discount, tuitionPlan.currency) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-muted-foreground text-sm font-medium">Remaining</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="h-5 w-5 text-orange-600" />
                            <p class="text-2xl font-bold text-orange-600">
                                {{ formatCurrency(tuitionPlan.summary.total_remaining, tuitionPlan.currency) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-muted-foreground text-sm font-medium">Payment Progress</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <p class="text-2xl font-bold">{{ Math.round(paymentPercentage) || 0 }}%</p>
                            <Progress :model-value="paymentPercentage || 0" class="h-2" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Scholarship Award Card -->
            <Card v-if="scholarshipAward && scholarshipAward.scholarship">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <GraduationCap class="text-primary h-5 w-5" />
                            Scholarship Award
                        </CardTitle>
                        <Badge :variant="scholarshipAward.scholarship.is_valid ? 'default' : 'outline'">
                            {{ scholarshipAward.scholarship.is_valid ? 'Active' : 'Expired' }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Scholarship Code</p>
                            <p class="text-lg font-semibold">{{ scholarshipAward.scholarship.code }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Scholarship Name</p>
                            <p class="text-lg font-semibold">{{ scholarshipAward.scholarship.name }}</p>
                        </div>
                    </div>
                    <div v-if="scholarshipAward.scholarship.description">
                        <p class="text-muted-foreground text-sm font-medium">Description</p>
                        <p class="text-sm">{{ scholarshipAward.scholarship.description }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Type</p>
                            <Badge variant="secondary">
                                {{ scholarshipAward.scholarship.type === 'percentage' ? 'Percentage' : 'Fixed Amount' }}
                            </Badge>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Discount Value</p>
                            <p class="text-lg font-semibold">
                                {{ scholarshipAward.scholarship.type === 'percentage' ? `${scholarshipAward.scholarship.amount}%` : formatCurrency(scholarshipAward.scholarship.amount, tuitionPlan.currency) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Awarded Date</p>
                            <p class="text-sm font-semibold">
                                {{ scholarshipAward.formatted_awarded_at || '-' }}
                            </p>
                        </div>
                    </div>
                    <!-- <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div v-if="scholarshipAward.scholarship.formatted_valid_from" class="bg-muted flex items-center gap-2 rounded-lg p-3">
                            <Calendar class="text-muted-foreground h-4 w-4" />
                            <div>
                                <p class="text-sm font-medium">Valid From</p>
                                <p class="text-muted-foreground text-sm">{{ scholarshipAward.scholarship.formatted_valid_from }}</p>
                            </div>
                        </div>
                        <div v-if="scholarshipAward.scholarship.formatted_valid_until" class="bg-muted flex items-center gap-2 rounded-lg p-3">
                            <Calendar class="text-muted-foreground h-4 w-4" />
                            <div>
                                <p class="text-sm font-medium">Valid Until</p>
                                <p class="text-muted-foreground text-sm">{{ scholarshipAward.scholarship.formatted_valid_until }}</p>
                            </div>
                        </div>
                    </div> -->
                    <div v-if="scholarshipAward.notes">
                        <p class="text-muted-foreground text-sm font-medium">Notes</p>
                        <p class="text-sm">{{ scholarshipAward.notes }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Plan Information -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <GraduationCap class="text-primary h-5 w-5" />
                            Tuition Plan Details
                        </CardTitle>
                        <Badge v-if="tuitionPlan.is_active" variant="default">Active</Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div v-if="tuitionPlan.curriculum_version">
                            <p class="text-muted-foreground text-sm font-medium">Curriculum Version</p>
                            <p class="text-lg font-semibold">{{ tuitionPlan.curriculum_version.version_code }}</p>
                            <p v-if="tuitionPlan.curriculum_version.program" class="text-muted-foreground text-sm">
                                {{ tuitionPlan.curriculum_version.program.name }}
                            </p>
                            <p v-if="tuitionPlan.curriculum_version.specialization" class="text-muted-foreground text-sm">
                                {{ tuitionPlan.curriculum_version.specialization.name }}
                            </p>
                        </div>
                        <div v-if="tuitionPlan.intake_semester" class="bg-muted flex items-center gap-2 rounded-lg p-3">
                            <Calendar class="text-muted-foreground h-4 w-4" />
                            <div>
                                <p class="text-sm font-medium">Intake Semester</p>
                                <p class="text-muted-foreground text-sm">{{ tuitionPlan.intake_semester.name }} ({{ tuitionPlan.intake_semester.code }})</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Payment Status Summary -->
            <Card>
                <CardHeader>
                    <CardTitle>Payment Status</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div class="flex items-center gap-2">
                            <CheckCircle class="h-5 w-5 text-green-600" />
                            <div>
                                <p class="text-2xl font-bold">{{ tuitionPlan.summary.paid_count }}</p>
                                <p class="text-muted-foreground text-sm">Paid</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Clock class="h-5 w-5 text-blue-600" />
                            <div>
                                <p class="text-2xl font-bold">{{ tuitionPlan.summary.partial_count }}</p>
                                <p class="text-muted-foreground text-sm">Partial</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <XCircle class="h-5 w-5 text-gray-600" />
                            <div>
                                <p class="text-2xl font-bold">{{ tuitionPlan.summary.unpaid_count }}</p>
                                <p class="text-muted-foreground text-sm">Unpaid</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="h-5 w-5 text-red-600" />
                            <div>
                                <p class="text-2xl font-bold">{{ tuitionPlan.summary.overdue_count }}</p>
                                <p class="text-muted-foreground text-sm">Overdue</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Terms Table -->
            <Card>
                <CardHeader>
                    <CardTitle>Payment Terms</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Term</TableHead>
                                    <TableHead>Semester</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Discount</TableHead>
                                    <TableHead>After Discount</TableHead>
                                    <TableHead>Due Date</TableHead>
                                    <TableHead>Paid</TableHead>
                                    <TableHead>Remaining</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Progress</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="term in tuitionPlan.terms" :key="term.id">
                                    <TableCell class="font-medium">Term {{ term.term_number }}</TableCell>
                                    <TableCell>
                                        <span v-if="term.semester">{{ term.semester.name }}</span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>
                                    <TableCell>{{ formatCurrency(term.amount, tuitionPlan.currency) }}</TableCell>
                                    <TableCell>
                                        <span v-if="term.discount_amount > 0" class="font-semibold text-green-600"> -{{ formatCurrency(term.discount_amount, tuitionPlan.currency) }} </span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>
                                    <TableCell class="font-semibold">
                                        {{ formatCurrency(term.amount_after_discount, tuitionPlan.currency) }}
                                    </TableCell>
                                    <TableCell>
                                        <span v-if="term.formatted_due_date" class="flex items-center gap-1">
                                            <Calendar class="h-3 w-3" />
                                            {{ term.formatted_due_date }}
                                        </span>
                                        <span v-else class="text-muted-foreground">-</span>
                                    </TableCell>
                                    <TableCell class="font-semibold text-green-600">
                                        {{ formatCurrency(term.paid_amount, tuitionPlan.currency) }}
                                    </TableCell>
                                    <TableCell class="font-semibold text-orange-600">
                                        {{ formatCurrency(term.remaining_amount, tuitionPlan.currency) }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getPaymentStatusBadge(term.payment_status).variant as any" class="flex items-center gap-1">
                                            <component :is="getPaymentStatusBadge(term.payment_status).icon" class="h-3 w-3" />
                                            {{ getPaymentStatusBadge(term.payment_status).label }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Progress :model-value="getPaymentProgress(term.paid_amount || 0, term.amount_after_discount || 0)" class="h-2 w-20" />
                                            <span class="text-muted-foreground text-xs"> {{ Math.round(getPaymentProgress(term.paid_amount || 0, term.amount_after_discount || 0)) || 0 }}% </span>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <!-- Invoice Details -->
            <template v-for="term in tuitionPlan.terms" :key="`invoice-${term.id}`">
                <Card v-if="term.invoices.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-4 w-4" />
                            Term {{ term.term_number }} - Invoice Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="invoice in term.invoices" :key="invoice.invoice_number" class="rounded-lg border p-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <p class="font-medium">{{ invoice.invoice_number }}</p>
                                        <div class="mt-1 space-y-1">
                                            <p class="text-muted-foreground text-sm">
                                                Amount: {{ formatCurrency(invoice.item_total, tuitionPlan.currency) }}
                                                <span v-if="invoice.item_discount > 0" class="text-green-600"> - Discount: {{ formatCurrency(invoice.item_discount, tuitionPlan.currency) }} </span>
                                            </p>
                                            <p class="text-sm font-semibold">
                                                After Discount: {{ formatCurrency(invoice.item_amount_after_discount, tuitionPlan.currency) }} | Paid:
                                                {{ formatCurrency(invoice.item_paid, tuitionPlan.currency) }}
                                            </p>
                                            <div v-if="invoice.voucher_discounts && invoice.voucher_discounts.length > 0" class="mt-2 space-y-1">
                                                <p class="text-muted-foreground text-xs font-medium">Vouchers Applied:</p>
                                                <div v-for="voucher in invoice.voucher_discounts" :key="voucher.code" class="flex items-center gap-2 text-xs">
                                                    <Badge variant="outline" class="text-xs">
                                                        {{ voucher.code }}
                                                    </Badge>
                                                    <span class="text-muted-foreground">
                                                        {{ voucher.name }} - {{ formatCurrency(voucher.amount, tuitionPlan.currency) }}
                                                        <span v-if="voucher.voucher && voucher.voucher.discount_type === 'percentage'"> ({{ voucher.voucher.discount_value }}%) </span>
                                                    </span>
                                                </div>
                                            </div>
                                            <p v-if="invoice.due_date" class="text-muted-foreground text-xs">Due: {{ new Date(invoice.due_date).toLocaleDateString('vi-VN') }}</p>
                                        </div>
                                    </div>
                                    <Badge :variant="invoice.status === 'paid' ? 'default' : invoice.status === 'partial' ? 'secondary' : 'outline'" class="ml-4">
                                        {{ invoice.status }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </template>
        </template>
    </div>
</template>
