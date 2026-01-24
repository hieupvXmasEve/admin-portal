<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    AlertTriangle,
    CheckCircle,
    Clock,
    CreditCard,
    FileText,
    ListChecks,
    XCircle,
    DollarSign
} from 'lucide-vue-next';
import { computed } from 'vue';

interface FeeSummary {
    student_info: {
        full_name: string;
        student_id: string;
        program: string;
        intake: string;
    };
    summary: {
        total_charged: number;
        total_discount: number;
        total_paid: number;
        remaining: number;
        progress: number;
    };
    billing_by_semester: Array<{
        semester_id: number;
        semester_name: string;
        semester_code: string;
        status: string;
        totals: {
            total: number;
            paid: number;
            remaining: number;
        };
        invoices: Array<{
            id: number;
            invoice_number: string;
            created_at: string;
            due_date: string;
            status: string;
            total: number;
            paid: number;
            remaining: number;
            lines: Array<{
                id: number;
                item: string;
                category: string;
                type: string;
                amount: number;
            }>;
            payments: Array<{
                id: number;
                paid_at: string;
                method: string;
                amount: number;
                ref: string | null;
            }>;
        }>;
    }>;
    tuition_plan_checklist: {
        plan_name: string;
        terms: Array<{
            term_number: number;
            semester_name: string;
            required_amount: number;
            generated: boolean;
            charge_id: number | null;
            payment_status: string;
            linked_invoices: string[];
        }>;
    } | null;
}

interface Props {
    feeSummary: FeeSummary;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
};

// --- Badge Helpers ---

const getSemesterStatusBadge = (status: string) => {
    const variants: Record<string, { variant: 'default' | 'secondary' | 'outline' | 'destructive'; label: string; icon: any; class: string }> = {
        paid: { variant: 'default', label: 'Settled', icon: CheckCircle, class: 'bg-green-600 hover:bg-green-700' },
        partial: { variant: 'secondary', label: 'Partial', icon: Clock, class: 'bg-amber-100 text-amber-800 hover:bg-amber-200' },
        unpaid: { variant: 'outline', label: 'Unpaid', icon: XCircle, class: 'text-gray-600 border-gray-300' },
        overdue: { variant: 'destructive', label: 'Overdue', icon: AlertTriangle, class: '' },
        no_invoices: { variant: 'secondary', label: 'No Invoices', icon: XCircle, class: 'bg-gray-100 text-gray-500' },
    };
    return variants[status] || variants.unpaid;
};

const getInvoiceStatusBadge = (status: string) => {
    // Mapping DB status to UI
    const s = status.toLowerCase();
    if (s === 'paid') return { variant: 'default', class: 'bg-green-600' };
    if (s === 'partial') return { variant: 'secondary', class: 'bg-amber-100 text-amber-800' };
    if (s === 'void') return { variant: 'destructive', class: 'bg-gray-500' };
    return { variant: 'outline', class: 'text-gray-600' };
};

const getChecklistPaymentBadge = (status: string) => {
    const variants: Record<string, { label: string; class: string; icon: any }> = {
        paid: { label: 'Paid', class: 'text-green-600 bg-green-50 border-green-200', icon: CheckCircle },
        partial: { label: 'Partial', class: 'text-amber-600 bg-amber-50 border-amber-200', icon: Clock },
        unpaid: { label: 'Unpaid', class: 'text-red-600 bg-red-50 border-red-200', icon: XCircle },
        not_generated: { label: 'Pending', class: 'text-gray-400 bg-gray-50 border-gray-200', icon: Clock },
    };
    return variants[status] || variants.not_generated;
};

// --- Computeds ---

// Expand overdue or partial semesters by default
const defaultOpenSemesters = computed(() => {
    return props.feeSummary.billing_by_semester
        .filter(s => s.status === 'overdue' || s.status === 'partial' || s.status === 'unpaid')
        .map(s => `sem-${s.semester_id}`);
});

</script>

<template>
    <div class="space-y-8">
        <!-- 1) HEADER: Overall Totals -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground uppercase">Total Charged</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ formatCurrency(feeSummary.summary.total_charged) }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground uppercase">Total Discount</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-green-600">{{ formatCurrency(feeSummary.summary.total_discount)
                        }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground uppercase">Total Paid</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-blue-600">{{ formatCurrency(feeSummary.summary.total_paid) }}
                    </div>
                </CardContent>
            </Card>
            <Card class="lg:col-span-2">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-muted-foreground uppercase">Outstanding Balance
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between gap-4">
                        <div class="text-3xl font-bold"
                            :class="feeSummary.summary.remaining > 0 ? 'text-red-600' : 'text-gray-600'">
                            {{ formatCurrency(feeSummary.summary.remaining) }}
                        </div>
                        <div class="flex-1 max-w-[150px] flex flex-col items-end gap-1">
                            <span class=" text-muted-foreground">{{ Math.round(feeSummary.summary.progress) }}%
                                Paid</span>
                            <Progress :model-value="feeSummary.summary.progress" class="h-2 w-full" />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- 2) SECTION A: Billing by Semester (Primary, 2/3 width) -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center gap-2 mb-2">
                    <FileText class="h-5 w-5 text-primary" />
                    <h2 class="text-xl font-bold tracking-tight">Billing by Semester (Actual)</h2>
                </div>

                <div v-if="feeSummary.billing_by_semester.length === 0"
                    class="text-center p-8 border border-dashed rounded-lg text-muted-foreground">
                    No billing history available.
                </div>

                <Accordion v-else type="multiple" :default-value="defaultOpenSemesters" class="space-y-4">
                    <AccordionItem v-for="semester in feeSummary.billing_by_semester" :key="semester.semester_id"
                        :value="`sem-${semester.semester_id}`" class="border rounded-lg px-4 bg-card shadow-sm">
                        <AccordionTrigger class="hover:no-underline py-4">
                            <div class="flex flex-1 items-center justify-between mr-4">
                                <div class="flex items-center gap-4">
                                    <div class="flex flex-col items-start text-left">
                                        <h3 class="font-bold text-lg">{{ semester.semester_name }}</h3>
                                        <span class=" text-muted-foreground">Invoices: {{ semester.invoices.length
                                            }}</span>
                                    </div>
                                    <Badge :variant="getSemesterStatusBadge(semester.status).variant as any"
                                        :class="getSemesterStatusBadge(semester.status).class">
                                        <component :is="getSemesterStatusBadge(semester.status).icon"
                                            class="w-3 h-3 mr-1" />
                                        {{ getSemesterStatusBadge(semester.status).label }}
                                    </Badge>
                                </div>

                                <div class="hidden sm:flex gap-6 text-sm text-right">
                                    <div>
                                        <p class="text-[10px] text-muted-foreground uppercase">Total</p>
                                        <p class="font-medium">{{ formatCurrency(semester.totals.total) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-muted-foreground uppercase">Paid</p>
                                        <p class="font-medium text-green-600">{{ formatCurrency(semester.totals.paid) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-muted-foreground uppercase">Remaining</p>
                                        <p class="font-bold"
                                            :class="semester.totals.remaining > 0 ? 'text-red-600' : 'text-gray-400'">
                                            {{ formatCurrency(semester.totals.remaining) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </AccordionTrigger>
                        <AccordionContent class="pb-4">
                            <div v-if="semester.invoices.length === 0"
                                class="text-sm text-muted-foreground italic pl-2">
                                No invoices generated in this semester.
                            </div>

                            <!-- Invoice List -->
                            <div v-else class="space-y-4">
                                <Card v-for="invoice in semester.invoices" :key="invoice.id"
                                    class="border bg-muted/10 overflow-hidden">
                                    <div
                                        class="p-3 border-b flex flex-wrap items-center justify-between gap-4 bg-muted/20">
                                        <div class="flex items-center gap-3">
                                            <div class="p-1.5 bg-background rounded border">
                                                <FileText class="h-4 w-4 text-muted-foreground" />
                                            </div>
                                            <div>
                                                <div class="font-mono font-bold text-sm">{{ invoice.invoice_number }}
                                                </div>
                                                <div class=" text-muted-foreground">
                                                    {{ invoice.due_date ? `Due: ${invoice.due_date}` : `Created:
                                                    ${invoice.created_at}` }}
                                                </div>
                                            </div>
                                            <Badge :variant="getInvoiceStatusBadge(invoice.status).variant as any"
                                                :class="getInvoiceStatusBadge(invoice.status).class"
                                                class="text-[10px] h-5">
                                                {{ invoice.status }}
                                            </Badge>
                                        </div>
                                        <div class="flex items-center gap-4 text-sm">
                                            <div class="text-right">
                                                <span class=" text-muted-foreground block">Total</span>
                                                <span class="font-medium">{{ formatCurrency(invoice.total) }}</span>
                                            </div>
                                            <div class="text-right">
                                                <span class=" text-muted-foreground block">Paid</span>
                                                <span class="font-medium text-green-600">{{ formatCurrency(invoice.paid)
                                                    }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-4 grid md:grid-cols-2 gap-6 text-sm">
                                        <!-- Charge Lines -->
                                        <div>
                                            <h5 class=" font-bold uppercase text-muted-foreground mb-2">Details</h5>
                                            <ul class="space-y-1">
                                                <li v-for="line in invoice.lines" :key="line.id"
                                                    class="flex justify-between items-start py-1 border-b border-dashed last:border-0">
                                                    <div class="flex-1 pr-2">
                                                        <span class="block  font-medium">{{ line.item }}</span>
                                                        <span
                                                            class="text-[10px] text-muted-foreground bg-muted px-1 rounded">{{
                                                            line.category }}</span>
                                                    </div>
                                                    <span class="font-mono "
                                                        :class="line.amount < 0 ? 'text-green-600' : ''">
                                                        {{ formatCurrency(line.amount) }}
                                                    </span>
                                                </li>
                                            </ul>
                                        </div>

                                        <!-- Payments -->
                                        <div class="md:border-l md:pl-6">
                                            <h5 class=" font-bold uppercase text-muted-foreground mb-2">Payments</h5>
                                            <div v-if="invoice.payments.length > 0">
                                                <ul class="space-y-2">
                                                    <li v-for="payment in invoice.payments" :key="payment.id"
                                                        class="flex justify-between items-center ">
                                                        <div>
                                                            <div class="font-medium">{{ payment.paid_at }}</div>
                                                            <div class="text-muted-foreground">{{ payment.method }}
                                                                <span v-if="payment.ref">({{ payment.ref }})</span>
                                                            </div>
                                                        </div>
                                                        <span class="font-medium text-green-600">+ {{
                                                            formatCurrency(payment.amount) }}</span>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div v-else class=" text-muted-foreground italic">
                                                No payments allocated.
                                            </div>
                                        </div>
                                    </div>
                                </Card>
                            </div>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>
            </div>

            <!-- 3) SECTION B: Tuition Plan Checklist (Secondary, 1/3 width) -->
            <div class="space-y-4">
                <div class="flex items-center gap-2 mb-2">
                    <ListChecks class="h-5 w-5 text-primary" />
                    <h2 class="text-xl font-bold tracking-tight">Tuition Plan Checklist</h2>
                </div>

                <Card class="h-fit">
                    <CardHeader class="pb-3 bg-muted/20">
                        <CardTitle class="text-sm font-medium">
                            <span class="block text-muted-foreground  uppercase mb-1">Active Plan</span>
                            {{ feeSummary.tuition_plan_checklist?.plan_name || 'No Active Plan' }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="!feeSummary.tuition_plan_checklist" class="p-4 text-sm text-muted-foreground italic">
                            No active tuition plan assigned.
                        </div>

                        <div v-else class="divide-y">
                            <div v-for="term in feeSummary.tuition_plan_checklist.terms" :key="term.term_number"
                                class="p-4 hover:bg-muted/50 transition-colors">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <div class="font-semibold text-sm">Term {{ term.term_number }}</div>
                                        <div class=" text-muted-foreground">{{ term.semester_name }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-mono text-sm">{{ formatCurrency(term.required_amount) }}</div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2 items-center mt-3">
                                    <!-- Generation Status -->
                                    <Badge v-if="term.generated" variant="outline"
                                        class="text-[10px] bg-blue-50 text-blue-700 border-blue-200">
                                        Generated
                                    </Badge>
                                    <Badge v-else variant="outline" class="text-[10px] bg-gray-100 text-gray-500">
                                        Not Generated
                                    </Badge>

                                    <!-- Payment Status -->
                                    <Badge v-if="term.generated"
                                        :class="`text-[10px] border ${getChecklistPaymentBadge(term.payment_status).class}`"
                                        variant="outline">
                                        <component :is="getChecklistPaymentBadge(term.payment_status).icon"
                                            class="w-3 h-3 mr-1 inline" />
                                        {{ getChecklistPaymentBadge(term.payment_status).label }}
                                    </Badge>
                                </div>

                                <!-- Linked Invoices -->
                                <div v-if="term.linked_invoices.length > 0" class="mt-2 pt-2 border-t border-dashed">
                                    <span class="text-[10px] text-muted-foreground uppercase mr-1">Invoices:</span>
                                    <div class="inline-flex gap-1 flex-wrap">
                                        <span v-for="inv in term.linked_invoices" :key="inv"
                                            class="text-[10px] font-mono bg-muted px-1 rounded text-foreground">
                                            {{ inv }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>