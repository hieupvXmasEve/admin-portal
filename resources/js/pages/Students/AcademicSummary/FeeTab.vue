<script setup lang="ts">
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Student } from '@/types/models';
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle, CircleDollarSign, Clock, CreditCard, FileText, ListChecks, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

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
        active_due: number;
        total_paid: number;
        total_allocated: number;
        outstanding: number;
        unapplied_balance: number;
        remaining: number;
        net_amount_to_collect: number;
        payment_count: number;
        progress: number;
    };
    payments: Array<{
        id: number;
        paid_at: string | null;
        method: string;
        source: string | null;
        amount: number;
        allocated_amount: number;
        unapplied_amount: number;
        ref: string | null;
        allocations: Array<{
            allocation_id: number;
            allocated_amount: number;
            semester_name: string | null;
            invoice_id: number | null;
            invoice_number: string | null;
            charge_id: number | null;
            charge_description: string | null;
        }>;
    }>;
    statement_events: Array<{
        event_key: string;
        event_at: string | null;
        kind: 'payment' | 'application' | 'release' | 'discount';
        label: string;
        reference: string;
        details: string;
        money_in: number;
        money_out: number;
        unapplied_balance: number;
    }>;
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
        scholarship_breakdown: {
            original_amount: number;
            deduction_amount: number;
            effective_amount: number;
            adjustment_status: string;
            label: string;
        } | null;
        invoices: Array<{
            id: number;
            invoice_number: string;
            created_at: string;
            due_date: string | null;
            status: string;
            total: number;
            paid: number;
            remaining: number;
            final_payable: number;
            cash_applied: number;
            settled_amount: number;
            snapshot_total: number;
            discount_total: number;
            discount_allocated: number;
            lines: Array<{
                id: number | string;
                item: string;
                category: string;
                type: string;
                amount: number;
                status: string;
                affects_payable: boolean;
            }>;
            payments: Array<{
                id: number;
                paid_at: string | null;
                method: string;
                source: string | null;
                amount: number;
                allocated_amount: number;
                ref: string | null;
                charges: Array<{
                    invoice_line_id: number;
                    charge_id: number | null;
                    charge_description: string;
                    allocated_amount: number;
                }>;
            }>;
            discounts: Array<{
                id: number;
                created_at: string | null;
                description: string;
                discount_type: string;
                amount: number;
                allocated_amount: number;
                targets: Array<{
                    invoice_line_id: number;
                    charge_id: number | null;
                    charge_description: string;
                    allocated_amount: number;
                }>;
            }>;
        }>;
    }>;
    tuition_plan_checklist: {
        plan_id: number;
        plan_name: string;
        terms: Array<{
            term_number: number;
            semester_id: number | null;
            semester_name: string | null;
            is_projected_semester?: boolean;
            required_amount: number;
            discount_amount: number;
            is_estimated_discount?: boolean;
            amount_due: number;
            paid_amount: number;
            generated: boolean;
            charge_id: number | null;
            payment_status: string;
            invoices: Array<{
                id: number;
                invoice_number: string;
                status: string;
                due_date: string | null;
            }>;
        }>;
    } | null;
}

interface Props {
    feeSummary: FeeSummary;
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status' | 'email' | 'intake'>;
    loading?: boolean;
}

type BillingInvoice = FeeSummary['billing_by_semester'][number]['invoices'][number];
type SettlementChargeSummary = {
    key: string;
    label: string;
    lineAmount: number | null;
    cashApplied: number;
    discountApplied: number;
    totalApplied: number;
    paymentCount: number;
    discountCount: number;
};

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
};

const formatEnumLabel = (value: string | null | undefined): string => {
    if (!value) {
        return 'N/A';
    }

    return value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
};

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
    const normalized = status.toLowerCase();

    if (normalized === 'paid') return { variant: 'default', class: 'bg-green-600' };
    if (normalized === 'partial') return { variant: 'secondary', class: 'bg-amber-100 text-amber-800' };
    if (normalized === 'void') return { variant: 'destructive', class: 'bg-gray-500' };

    return { variant: 'outline', class: 'text-gray-600' };
};

const getInvoiceStatusClass = (status: string) => {
    const normalized = status?.toLowerCase() || '';

    if (normalized === 'paid') return 'bg-green-100 text-green-700 border-green-200';
    if (normalized === 'partial') return 'bg-amber-100 text-amber-700 border-amber-200';
    if (normalized === 'pending') return 'bg-blue-100 text-blue-700 border-blue-200';
    if (normalized === 'overdue') return 'bg-red-100 text-red-700 border-red-200';
    if (normalized === 'draft') return 'bg-gray-100 text-gray-600 border-gray-200';
    if (normalized === 'cancelled' || normalized === 'void') return 'bg-gray-100 text-gray-500 border-gray-200';

    return 'bg-gray-100 text-gray-600 border-gray-200';
};

const getInvoiceStatusLabel = (status: string) => {
    const labels: Record<string, string> = {
        paid: 'Đã thanh toán',
        partial: 'Thanh toán 1 phần',
        pending: 'Chờ thanh toán',
        overdue: 'Quá hạn',
        draft: 'Nháp',
        cancelled: 'Đã hủy',
        void: 'Đã hủy',
    };

    return labels[status?.toLowerCase()] || status;
};

const getChecklistPaymentBadge = (status: string) => {
    const variants: Record<string, { label: string; class: string; icon: any }> = {
        paid: { label: 'Paid', class: 'text-green-600 bg-green-50 border-green-200', icon: CheckCircle },
        partial: { label: 'Partial', class: 'text-amber-600 bg-amber-50 border-amber-200', icon: Clock },
        unpaid: { label: 'Unpaid', class: 'text-red-600 bg-red-50 border-red-200', icon: XCircle },
        not_generated: { label: 'Pending', class: 'text-gray-400 bg-gray-50 border-gray-200', icon: Clock },
        waived: { label: 'Không cần tạo phí', class: 'text-slate-500 bg-slate-50 border-slate-200', icon: CheckCircle },
    };

    return variants[status] || variants.not_generated;
};

const getChargeLineStatusBadge = (status: string) => {
    const normalized = status?.toLowerCase() || 'missing';

    if (normalized === 'active') {
        return {
            label: 'Active',
            class: 'bg-green-50 text-green-700 border-green-200',
        };
    }

    if (normalized === 'void') {
        return {
            label: 'Void',
            class: 'bg-gray-100 text-gray-500 border-gray-200',
        };
    }

    return {
        label: 'Unknown',
        class: 'bg-gray-100 text-gray-500 border-gray-200',
    };
};

const getStatementKindBadge = (kind: 'payment' | 'application' | 'release' | 'discount') => {
    if (kind === 'payment') {
        return {
            label: 'Money In',
            class: 'bg-blue-50 text-blue-700 border-blue-200',
        };
    }

    if (kind === 'release') {
        return {
            label: 'Released',
            class: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        };
    }

    if (kind === 'discount') {
        return {
            label: 'Discount',
            class: 'bg-violet-50 text-violet-700 border-violet-200',
        };
    }

    return {
        label: 'Applied',
        class: 'bg-amber-50 text-amber-700 border-amber-200',
    };
};

const getInvoiceSettlementSummary = (invoice: BillingInvoice): SettlementChargeSummary[] => {
    const summaryMap = new Map<string, SettlementChargeSummary>();

    const ensureSummary = (key: string, label: string, lineAmount: number | null) => {
        const existing = summaryMap.get(key);

        if (existing) {
            return existing;
        }

        const summary: SettlementChargeSummary = {
            key,
            label,
            lineAmount,
            cashApplied: 0,
            discountApplied: 0,
            totalApplied: 0,
            paymentCount: 0,
            discountCount: 0,
        };

        summaryMap.set(key, summary);

        return summary;
    };

    invoice.lines.forEach((line) => {
        ensureSummary(`line-${line.id}`, line.item, line.affects_payable ? Math.abs(line.amount) : null);
    });

    invoice.payments.forEach((payment) => {
        const visitedChargeKeys = new Set<string>();

        payment.charges.forEach((charge) => {
            const key = charge.invoice_line_id ? `line-${charge.invoice_line_id}` : `charge-${charge.charge_id ?? charge.charge_description}`;
            const summary = ensureSummary(key, charge.charge_description, null);

            summary.cashApplied += charge.allocated_amount;
            summary.totalApplied += charge.allocated_amount;

            if (!visitedChargeKeys.has(key)) {
                summary.paymentCount += 1;
                visitedChargeKeys.add(key);
            }
        });
    });

    invoice.discounts.forEach((discount) => {
        const visitedChargeKeys = new Set<string>();

        discount.targets.forEach((target) => {
            const key = target.invoice_line_id ? `line-${target.invoice_line_id}` : `charge-${target.charge_id ?? target.charge_description}`;
            const summary = ensureSummary(key, target.charge_description, null);

            summary.discountApplied += target.allocated_amount;
            summary.totalApplied += target.allocated_amount;

            if (!visitedChargeKeys.has(key)) {
                summary.discountCount += 1;
                visitedChargeKeys.add(key);
            }
        });
    });

    return Array.from(summaryMap.values())
        .filter((summary) => summary.totalApplied > 0 || summary.lineAmount !== null)
        .sort((left, right) => right.totalApplied - left.totalApplied);
};

const getInvoiceSettlementHint = (invoice: BillingInvoice): string | null => {
    const summaries = getInvoiceSettlementSummary(invoice);

    if (summaries.length !== 1) {
        return null;
    }

    const [summary] = summaries;

    if (summary.paymentCount <= 1 && summary.discountCount === 0) {
        return null;
    }

    const parts: string[] = [];

    if (summary.paymentCount > 0) {
        parts.push(`${summary.paymentCount} lần thu tiền`);
    }

    if (summary.discountCount > 0) {
        parts.push(`${summary.discountCount} lần giảm trừ`);
    }

    return `${summary.label} là một khoản phí duy nhất. Các dòng bên dưới chỉ là ${parts.join(' + ')} được áp vào cùng khoản phí này, không phải fee bị tạo trùng.`;
};

const defaultOpenSemesters = computed(() => {
    return props.feeSummary.billing_by_semester.filter((semester) => semester.status === 'overdue' || semester.status === 'partial' || semester.status === 'unpaid').map((semester) => `sem-${semester.semester_id}`);
});

const checklistHelperText = computed(() => {
    if (props.student.status === 'intake_course') {
        return 'Checklist này theo dõi học phí chuyên ngành theo kỳ. Nếu chưa đóng hoặc chưa phát sinh charge tương ứng thì vẫn có thể chưa map vào actual billing.';
    }

    return 'Checklist này là kế hoạch học phí chuyên ngành để staff đối chiếu. Với student chưa ở trạng thái intake_course, quyết định thu tiền nên đọc theo Lịch sử nộp tiền và Khoản phí theo kỳ bên trái.';
});

const staffSummary = computed(() => {
    const summary = props.feeSummary.summary;

    if (summary.payment_count === 0) {
        return `Student chưa có khoản thu nào. Nghĩa vụ active hiện tại là ${formatCurrency(summary.active_due)}.`;
    }

    if (summary.net_amount_to_collect > 0) {
        return `Student đã nộp ${formatCurrency(summary.total_paid)} qua ${summary.payment_count} lần. ${formatCurrency(summary.total_allocated)} đã được phân bổ, ${formatCurrency(summary.unapplied_balance)} còn dư chưa phân bổ. Nghĩa vụ active hiện tại là ${formatCurrency(summary.active_due)}, nên staff còn cần thu thêm ${formatCurrency(summary.net_amount_to_collect)}.`;
    }

    if (summary.unapplied_balance > 0) {
        return `Student đã nộp đủ cho toàn bộ nghĩa vụ active. Hiện còn ${formatCurrency(summary.unapplied_balance)} dư chưa phân bổ để dùng cho các khoản phí tiếp theo.`;
    }

    return `Student đã nộp đủ cho toàn bộ nghĩa vụ active. Không còn khoản cần thu thêm ở thời điểm hiện tại.`;
});

const summaryToneClass = computed(() => {
    if (props.feeSummary.summary.net_amount_to_collect > 0) {
        return 'border-amber-200 bg-amber-50/60';
    }

    if (props.feeSummary.summary.unapplied_balance > 0) {
        return 'border-blue-200 bg-blue-50/60';
    }

    return 'border-green-200 bg-green-50/60';
});
</script>

<template>
    <div class="space-y-8">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium uppercase">Nghĩa vụ active</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ formatCurrency(feeSummary.summary.active_due) }}</div>
                    <div v-if="feeSummary.summary.total_discount > 0" class="mt-1 text-xs text-green-600">Giảm trừ active: {{ formatCurrency(feeSummary.summary.total_discount) }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium uppercase">Đã thu</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-blue-600">{{ formatCurrency(feeSummary.summary.total_paid) }}</div>
                    <div class="text-muted-foreground mt-1 text-xs">{{ feeSummary.summary.payment_count }} lần thu tiền</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium uppercase">Đã phân bổ</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-green-600">{{ formatCurrency(feeSummary.summary.total_allocated) }}</div>
                    <div class="text-muted-foreground mt-1 text-xs">Đã gán vào invoice / charge</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium uppercase">Tiền dư chưa phân bổ</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold" :class="feeSummary.summary.unapplied_balance > 0 ? 'text-amber-600' : 'text-muted-foreground'">
                        {{ formatCurrency(feeSummary.summary.unapplied_balance) }}
                    </div>
                    <div class="text-muted-foreground mt-1 text-xs">Có thể dùng để bù các khoản phí tiếp theo</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium uppercase">Cần nộp thêm</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold" :class="feeSummary.summary.net_amount_to_collect > 0 ? 'text-red-600' : 'text-green-600'">
                        {{ formatCurrency(feeSummary.summary.net_amount_to_collect) }}
                    </div>
                    <div class="text-muted-foreground mt-1 text-xs">Đã trừ số dư chưa phân bổ</div>
                </CardContent>
            </Card>
        </div>

        <Card :class="['border', summaryToneClass]">
            <CardContent class="flex items-start gap-3 p-4">
                <CircleDollarSign class="text-primary mt-0.5 h-5 w-5 shrink-0" />
                <div class="space-y-1">
                    <p class="text-sm font-semibold">Tóm tắt cho staff</p>
                    <p class="text-foreground/90 text-sm">{{ staffSummary }}</p>
                </div>
            </CardContent>
        </Card>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <CircleDollarSign class="text-primary h-5 w-5" />
                        <h2 class="text-xl font-bold tracking-tight">Transaction Statement</h2>
                    </div>

                    <Card>
                        <CardContent class="p-0">
                            <div v-if="feeSummary.statement_events.length === 0" class="text-muted-foreground p-6 text-sm italic">Chưa có giao dịch nào để dựng statement.</div>

                            <Table v-else>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Time</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Reference</TableHead>
                                        <TableHead class="text-right">Money In</TableHead>
                                        <TableHead class="text-right">Money Out</TableHead>
                                        <TableHead class="text-right">Unapplied Balance</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="event in feeSummary.statement_events" :key="event.event_key" class="align-top">
                                        <TableCell>
                                            <div class="font-medium">{{ event.event_at || 'N/A' }}</div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" :class="getStatementKindBadge(event.kind).class">
                                                {{ getStatementKindBadge(event.kind).label }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div class="font-medium">{{ event.reference }}</div>
                                            <div v-if="event.details" class="text-muted-foreground text-xs">{{ event.details }}</div>
                                        </TableCell>
                                        <TableCell class="text-right font-medium text-blue-600">
                                            {{ event.money_in > 0 ? formatCurrency(event.money_in) : '—' }}
                                        </TableCell>
                                        <TableCell class="text-right font-medium text-amber-600">
                                            {{ event.money_out > 0 ? formatCurrency(event.money_out) : '—' }}
                                        </TableCell>
                                        <TableCell class="text-right font-semibold" :class="event.unapplied_balance > 0 ? 'text-emerald-600' : 'text-muted-foreground'">
                                            {{ formatCurrency(event.unapplied_balance) }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <CreditCard class="text-primary h-5 w-5" />
                        <h2 class="text-xl font-bold tracking-tight">Lịch sử nộp tiền</h2>
                    </div>

                    <Card>
                        <CardContent class="p-0">
                            <div v-if="feeSummary.payments.length === 0" class="text-muted-foreground p-6 text-sm italic">Student chưa có giao dịch nộp tiền nào.</div>

                            <Table v-else>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Ngày nộp</TableHead>
                                        <TableHead>Phương thức</TableHead>
                                        <TableHead class="text-right">Tổng thu</TableHead>
                                        <TableHead class="text-right">Đã phân bổ</TableHead>
                                        <TableHead class="text-right">Current Unapplied</TableHead>
                                        <TableHead>Thanh toán vào đâu</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="payment in feeSummary.payments" :key="payment.id" class="align-top">
                                        <TableCell>
                                            <div class="font-medium">{{ payment.paid_at || 'N/A' }}</div>
                                            <div class="text-muted-foreground text-xs">Payment #{{ payment.id }}</div>
                                        </TableCell>
                                        <TableCell>
                                            <div class="font-medium">{{ formatEnumLabel(payment.method) }}</div>
                                            <div class="text-muted-foreground text-xs">
                                                {{ formatEnumLabel(payment.source) }}
                                                <span v-if="payment.ref"> • {{ payment.ref }}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-right font-medium">{{ formatCurrency(payment.amount) }}</TableCell>
                                        <TableCell class="text-right font-medium text-green-600">{{ formatCurrency(payment.allocated_amount) }}</TableCell>
                                        <TableCell class="text-right font-medium" :class="payment.unapplied_amount > 0 ? 'text-amber-600' : 'text-muted-foreground'">
                                            {{ formatCurrency(payment.unapplied_amount) }}
                                        </TableCell>
                                        <TableCell>
                                            <div v-if="payment.allocations.length > 0" class="space-y-2">
                                                <div v-for="allocation in payment.allocations" :key="allocation.allocation_id" class="bg-muted/30 rounded-md border px-3 py-2">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <div class="text-sm font-medium">
                                                                {{ allocation.invoice_number || 'Chưa gắn invoice' }}
                                                            </div>
                                                            <div class="text-muted-foreground text-xs">
                                                                {{ allocation.semester_name || 'Không có kỳ' }}
                                                                <span v-if="allocation.charge_description"> • {{ allocation.charge_description }}</span>
                                                            </div>
                                                        </div>
                                                        <div class="text-sm font-semibold text-green-600">
                                                            {{ formatCurrency(allocation.allocated_amount) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="text-muted-foreground text-sm italic">Chưa phân bổ vào khoản phí nào.</div>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <FileText class="text-primary h-5 w-5" />
                        <h2 class="text-xl font-bold tracking-tight">Khoản phí theo kỳ (thực tế)</h2>
                    </div>

                    <div v-if="feeSummary.billing_by_semester.length === 0" class="text-muted-foreground rounded-lg border border-dashed p-8 text-center">No billing history available.</div>

                    <Accordion v-else type="multiple" :default-value="defaultOpenSemesters" class="space-y-4">
                        <AccordionItem v-for="semester in feeSummary.billing_by_semester" :key="semester.semester_id" :value="`sem-${semester.semester_id}`" class="bg-card rounded-lg border px-4 shadow-sm">
                            <AccordionTrigger class="py-4 hover:no-underline">
                                <div class="mr-4 flex flex-1 items-center justify-between">
                                    <div class="flex items-center gap-4">
                                        <div class="flex flex-col items-start text-left">
                                            <h3 class="text-lg font-bold">{{ semester.semester_name }}</h3>
                                            <span class="text-muted-foreground">Invoices: {{ semester.invoices.length }}</span>
                                        </div>
                                        <Badge :variant="getSemesterStatusBadge(semester.status).variant as any" :class="getSemesterStatusBadge(semester.status).class">
                                            <component :is="getSemesterStatusBadge(semester.status).icon" class="mr-1 h-3 w-3" />
                                            {{ getSemesterStatusBadge(semester.status).label }}
                                        </Badge>
                                    </div>

                                    <div class="hidden gap-6 text-right text-sm sm:flex">
                                        <div>
                                            <p class="text-muted-foreground text-[10px] uppercase">Total</p>
                                            <p class="font-medium">{{ formatCurrency(semester.totals.total) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground text-[10px] uppercase">Applied</p>
                                            <p class="font-medium text-green-600">{{ formatCurrency(semester.totals.paid) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground text-[10px] uppercase">Outstanding</p>
                                            <p class="font-bold" :class="semester.totals.remaining > 0 ? 'text-red-600' : 'text-gray-400'">
                                                {{ formatCurrency(semester.totals.remaining) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </AccordionTrigger>
                            <AccordionContent class="pb-4">
                                <!-- Scholarship adjustment breakdown — presentational only, never part of the settlement lines -->
                                <div
                                    v-if="semester.scholarship_breakdown"
                                    class="mb-4 rounded-lg border border-amber-200 bg-amber-50/60 p-3 text-sm dark:border-amber-900 dark:bg-amber-950/30"
                                >
                                    <p class="mb-2 font-semibold text-amber-800 dark:text-amber-300">{{ semester.scholarship_breakdown.label }}</p>
                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <p class="text-muted-foreground text-[10px] uppercase">Học bổng gốc</p>
                                            <p class="font-medium">{{ formatCurrency(semester.scholarship_breakdown.original_amount) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground text-[10px] uppercase">Giảm trừ</p>
                                            <p class="font-medium text-red-600">-{{ formatCurrency(semester.scholarship_breakdown.deduction_amount) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-muted-foreground text-[10px] uppercase">Còn được hưởng</p>
                                            <p class="font-medium text-green-700">{{ formatCurrency(semester.scholarship_breakdown.effective_amount) }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="semester.invoices.length === 0" class="text-muted-foreground pl-2 text-sm italic">No invoices generated in this semester.</div>

                                <div v-else class="space-y-4">
                                    <Card v-for="invoice in semester.invoices" :key="invoice.id" class="bg-muted/10 overflow-hidden border">
                                        <div class="bg-muted/20 flex flex-wrap items-center justify-between gap-4 border-b p-3">
                                            <div class="flex items-center gap-3">
                                                <div class="bg-background rounded border p-1.5">
                                                    <FileText class="text-muted-foreground h-4 w-4" />
                                                </div>
                                                <div>
                                                    <div class="font-mono text-sm font-bold">{{ invoice.invoice_number }}</div>
                                                    <div class="text-muted-foreground">
                                                        {{ invoice.due_date ? `Due: ${invoice.due_date}` : `Created: ${invoice.created_at}` }}
                                                    </div>
                                                </div>
                                                <Badge :variant="getInvoiceStatusBadge(invoice.status).variant as any" :class="getInvoiceStatusBadge(invoice.status).class" class="h-5 text-[10px]">
                                                    {{ invoice.status }}
                                                </Badge>
                                            </div>
                                            <div class="flex items-center gap-4 text-sm">
                                                <div class="text-right">
                                                    <span class="text-muted-foreground block">Final Payable</span>
                                                    <span class="font-medium">{{ formatCurrency(invoice.final_payable) }}</span>
                                                </div>
                                                <div class="text-right">
                                                    <span class="text-muted-foreground block">Cash Applied</span>
                                                    <span class="font-medium text-green-600">{{ formatCurrency(invoice.cash_applied) }}</span>
                                                </div>
                                                <div class="text-right">
                                                    <span class="text-muted-foreground block">Discount Applied</span>
                                                    <span class="font-medium text-violet-600">{{ formatCurrency(invoice.discount_allocated) }}</span>
                                                </div>
                                                <div class="text-right">
                                                    <span class="text-muted-foreground block">Still Due</span>
                                                    <span class="font-medium" :class="invoice.remaining > 0 ? 'text-red-600' : 'text-gray-400'">
                                                        {{ formatCurrency(invoice.remaining) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid gap-6 p-4 text-sm md:grid-cols-2">
                                            <div>
                                                <h5 class="text-muted-foreground mb-2 font-bold uppercase">Details</h5>
                                                <div class="text-muted-foreground mb-3 text-xs">
                                                    Charges snapshot: {{ formatCurrency(invoice.snapshot_total) }} • Discounts: {{ formatCurrency(invoice.discount_total) }} • Final payable now:
                                                    {{ formatCurrency(invoice.final_payable) }}
                                                </div>
                                                <ul class="space-y-1">
                                                    <li v-for="line in invoice.lines" :key="line.id" class="flex items-start justify-between border-b border-dashed py-1 last:border-0">
                                                        <div class="flex-1 pr-2">
                                                            <span class="block font-medium">{{ line.item }}</span>
                                                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                                                <span class="bg-muted text-muted-foreground rounded px-1 text-[10px]">{{ line.category }}</span>
                                                                <Badge variant="outline" :class="getChargeLineStatusBadge(line.status).class" class="h-5 text-[10px]">
                                                                    {{ getChargeLineStatusBadge(line.status).label }}
                                                                </Badge>
                                                                <span v-if="!line.affects_payable" class="text-muted-foreground text-[10px]"> Không tính vào final payable </span>
                                                            </div>
                                                        </div>
                                                        <span class="font-mono" :class="[line.amount < 0 ? 'text-green-600' : '', !line.affects_payable ? 'text-muted-foreground line-through' : '']">
                                                            {{ formatCurrency(line.amount) }}
                                                        </span>
                                                    </li>
                                                </ul>
                                            </div>

                                            <div class="md:border-l md:pl-6">
                                                <h5 class="text-muted-foreground mb-2 font-bold uppercase">Settlements</h5>
                                                <div v-if="invoice.payments.length > 0 || invoice.discounts.length > 0" class="space-y-4">
                                                    <div v-if="getInvoiceSettlementSummary(invoice).length > 0" class="space-y-3">
                                                        <div class="grid gap-2">
                                                            <div v-for="summary in getInvoiceSettlementSummary(invoice)" :key="summary.key" class="bg-muted/35 rounded-lg border px-3 py-2">
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <div class="min-w-0">
                                                                        <div class="font-medium">{{ summary.label }}</div>
                                                                        <div class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                                                            <span v-if="summary.lineAmount !== null">Tổng khoản phí: {{ formatCurrency(summary.lineAmount) }}</span>
                                                                            <span v-if="summary.paymentCount > 0">{{ summary.paymentCount }} lần thu tiền</span>
                                                                            <span v-if="summary.discountCount > 0">{{ summary.discountCount }} lần giảm trừ</span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <div class="font-medium">{{ formatCurrency(summary.totalApplied) }}</div>
                                                                        <div class="text-muted-foreground text-xs">đã áp vào khoản này</div>
                                                                    </div>
                                                                </div>
                                                                <div v-if="summary.cashApplied > 0 || summary.discountApplied > 0" class="mt-2 flex flex-wrap gap-2 text-xs">
                                                                    <span v-if="summary.cashApplied > 0" class="rounded-full bg-green-50 px-2 py-1 text-green-700"> Thu tiền: {{ formatCurrency(summary.cashApplied) }} </span>
                                                                    <span v-if="summary.discountApplied > 0" class="rounded-full bg-violet-50 px-2 py-1 text-violet-700"> Giảm trừ: {{ formatCurrency(summary.discountApplied) }} </span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div v-if="getInvoiceSettlementHint(invoice)" class="rounded-lg border border-blue-200 bg-blue-50/70 px-3 py-2 text-xs text-blue-800">
                                                            {{ getInvoiceSettlementHint(invoice) }}
                                                        </div>
                                                    </div>

                                                    <ul v-if="invoice.payments.length > 0" class="space-y-3">
                                                        <li v-for="payment in invoice.payments" :key="payment.id" class="space-y-2 border-b border-dashed pb-3 last:border-0 last:pb-0">
                                                            <div class="flex items-center justify-between gap-3">
                                                                <div>
                                                                    <div class="font-medium">{{ payment.paid_at || 'N/A' }}</div>
                                                                    <div class="text-muted-foreground">
                                                                        {{ formatEnumLabel(payment.method) }}
                                                                        <span v-if="payment.source"> • {{ formatEnumLabel(payment.source) }}</span>
                                                                        <span v-if="payment.ref"> ({{ payment.ref }})</span>
                                                                    </div>
                                                                    <div class="text-muted-foreground text-xs">Tổng phiếu thu: {{ formatCurrency(payment.amount) }}</div>
                                                                </div>
                                                                <div class="text-right">
                                                                    <div class="font-medium text-green-600">
                                                                        {{ formatCurrency(payment.allocated_amount) }}
                                                                    </div>
                                                                    <div class="text-muted-foreground text-xs">áp vào invoice này</div>
                                                                </div>
                                                            </div>

                                                            <div v-if="payment.charges.length > 0" class="space-y-1">
                                                                <div v-for="charge in payment.charges" :key="`${payment.id}-${charge.invoice_line_id}`" class="bg-muted/40 flex items-center justify-between rounded px-2 py-1 text-xs">
                                                                    <div class="text-muted-foreground">
                                                                        <div>Áp vào {{ charge.charge_description }}</div>
                                                                    </div>
                                                                    <span class="font-medium text-green-600">{{ formatCurrency(charge.allocated_amount) }}</span>
                                                                </div>
                                                            </div>
                                                        </li>
                                                    </ul>
                                                    <ul v-if="invoice.discounts.length > 0" class="space-y-3">
                                                        <li v-for="discount in invoice.discounts" :key="discount.id" class="space-y-2 border-b border-dashed pb-3 last:border-0 last:pb-0">
                                                            <div class="flex items-center justify-between gap-3">
                                                                <div>
                                                                    <div class="font-medium">{{ discount.created_at || 'N/A' }}</div>
                                                                    <div class="text-muted-foreground">
                                                                        {{ discount.description }}
                                                                    </div>
                                                                    <div class="text-muted-foreground text-xs">Loại discount: {{ formatEnumLabel(discount.discount_type) }}</div>
                                                                </div>
                                                                <div class="text-right">
                                                                    <div class="font-medium text-violet-600">
                                                                        {{ formatCurrency(discount.allocated_amount || discount.amount) }}
                                                                    </div>
                                                                    <div class="text-muted-foreground text-xs">giảm vào invoice này</div>
                                                                </div>
                                                            </div>

                                                            <div v-if="discount.targets.length > 0" class="space-y-1">
                                                                <div v-for="target in discount.targets" :key="`${discount.id}-${target.invoice_line_id}`" class="flex items-center justify-between rounded bg-violet-50/60 px-2 py-1 text-xs">
                                                                    <div class="text-muted-foreground">
                                                                        <div>Giảm cho {{ target.charge_description }}</div>
                                                                    </div>
                                                                    <span class="font-medium text-violet-600">{{ formatCurrency(target.allocated_amount) }}</span>
                                                                </div>
                                                            </div>
                                                        </li>
                                                    </ul>
                                                </div>
                                                <div v-else class="text-muted-foreground italic">No payments allocated.</div>
                                            </div>
                                        </div>
                                    </Card>
                                </div>
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <ListChecks class="text-primary h-5 w-5" />
                    <h2 class="text-xl font-bold tracking-tight">Tuition Plan Checklist</h2>
                </div>

                <Card class="h-fit">
                    <CardHeader class="bg-muted/20 space-y-3 pb-3">
                        <CardTitle class="text-sm font-medium">
                            <span class="text-muted-foreground mb-1 block uppercase">Active Plan</span>
                            <Link v-if="feeSummary.tuition_plan_checklist" :href="route('tuition-plans.show', feeSummary.tuition_plan_checklist.plan_id)" class="text-blue-500 transition-colors hover:underline">
                                {{ feeSummary.tuition_plan_checklist.plan_name }}
                            </Link>
                            <span v-else>No Active Plan</span>
                        </CardTitle>
                        <div class="space-y-2">
                            <Badge variant="outline" class="bg-background w-fit text-[10px]">
                                {{ student.status === 'intake_course' ? 'Intake Course' : 'Reference for staff' }}
                            </Badge>
                            <p class="text-muted-foreground text-xs leading-relaxed">{{ checklistHelperText }}</p>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="!feeSummary.tuition_plan_checklist" class="text-muted-foreground p-4 text-sm italic">No active tuition plan assigned.</div>

                        <div v-else class="divide-y">
                            <div v-for="term in feeSummary.tuition_plan_checklist.terms" :key="term.charge_id ?? `proj-${term.term_number}`" class="hover:bg-muted/50 p-4 transition-colors">
                                <div class="mb-2 flex items-start justify-between">
                                    <div>
                                        <div class="text-sm font-semibold">Term {{ term.term_number ?? '—' }}</div>
                                        <div class="text-muted-foreground">
                                            {{ term.semester_name ?? 'Chưa xác định học kỳ' }}
                                            <span v-if="term.is_projected_semester" class="text-muted-foreground/70">(dự kiến)</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-primary font-mono text-sm font-semibold">
                                            {{ formatCurrency(term.amount_due) }}
                                        </div>
                                        <div v-if="term.discount_amount > 0" class="text-muted-foreground text-[10px]">
                                            <span class="line-through">{{ formatCurrency(term.required_amount) }}</span>
                                            <span class="ml-1 text-green-600">-{{ formatCurrency(term.discount_amount) }}</span>
                                            <span v-if="term.is_estimated_discount" class="text-muted-foreground/70 ml-0.5">(dự kiến)</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="term.generated && term.amount_due > 0" class="mb-2">
                                    <div class="text-muted-foreground mb-1 flex justify-between text-[10px]">
                                        <span>Đã đóng</span>
                                        <span>{{ formatCurrency(term.paid_amount) }} / {{ formatCurrency(term.amount_due) }}</span>
                                    </div>
                                    <Progress :model-value="term.amount_due > 0 ? (term.paid_amount / term.amount_due) * 100 : 0" class="h-1.5" />
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <Badge v-if="term.generated" variant="outline" class="border-blue-200 bg-blue-50 text-[10px] text-blue-700"> Generated </Badge>
                                    <Badge v-else-if="term.payment_status !== 'waived'" variant="outline" class="bg-gray-100 text-[10px] text-gray-500"> Not Generated </Badge>

                                    <Badge v-if="term.generated || term.payment_status === 'waived'" :class="`border text-[10px] ${getChecklistPaymentBadge(term.payment_status).class}`" variant="outline">
                                        <component :is="getChecklistPaymentBadge(term.payment_status).icon" class="mr-1 inline h-3 w-3" />
                                        {{ getChecklistPaymentBadge(term.payment_status).label }}
                                    </Badge>
                                </div>

                                <div v-if="term.invoices.length > 0" class="mt-2 border-t border-dashed pt-2">
                                    <span class="text-muted-foreground mr-1 text-[10px] uppercase">Invoices:</span>
                                    <div class="mt-1 flex flex-col gap-1">
                                        <div v-for="invoice in term.invoices" :key="invoice.id" class="bg-muted flex items-center justify-between rounded px-2 py-1 text-[10px]">
                                            <span class="text-foreground font-mono">{{ invoice.invoice_number }}</span>
                                            <Badge variant="outline" :class="getInvoiceStatusClass(invoice.status)">
                                                {{ getInvoiceStatusLabel(invoice.status) }}
                                            </Badge>
                                        </div>
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
