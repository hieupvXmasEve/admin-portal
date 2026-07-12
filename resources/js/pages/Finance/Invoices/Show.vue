<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency, formatDate } from '@/utils/format';
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, ChevronLeft, CreditCard, DollarSign, Percent, Receipt, User as UserIcon } from 'lucide-vue-next';
import { route } from 'ziggy-js';

interface Money {
    amount: string;
    currency: string;
    scale: number;
    minor_amount: number;
}

interface SettlementAmounts {
    gross: Money;
    discount: Money;
    cash: Money;
    credit: Money;
    remaining: Money;
}

interface SettlementIssue {
    code: string;
    severity: string;
    blocking: boolean;
    evidence: Record<string, string | number>;
    finance_invariant_code: string | null;
}

interface SettlementPosition {
    scope_type: string;
    scope_id: number;
    payable_line_id: number | null;
    finance_obligation_id: number | null;
    invoice_id: number | null;
    billing_account_id: number | null;
    fee_type: string | null;
    position_mode: 'current' | 'as_of';
    captured_at: string;
    snapshot_version: string;
    settlement_state: string;
    valid: boolean;
    amounts: SettlementAmounts | null;
    raw_evidence: Record<string, Money>;
    issues: SettlementIssue[];
    payable_line_breakdown: SettlementPosition[];
    breakdown_reconciliation: {
        status: string;
        rounding_remainder: Money;
    };
}

interface Charge {
    id: number | null;
    invoice_line_id: number;
    charge_type: string | null;
    description: string | null;
    amounts: SettlementAmounts | null;
    valid: boolean;
    settlement_state: string | null;
    issues: SettlementIssue[];
    effective_at: string | null;
    source_type: string | null;
    status: string;
}

interface SettlementEntry {
    id: string;
    entry_group: 'payment' | 'discount';
    entry_type: string;
    applied_at: string | null;
    amount: string;
    charge: {
        id: number | null;
        description: string | null;
        charge_type: string | null;
    };
    payment: {
        id: number;
        amount: string;
        paid_at: string | null;
        method: string;
        status: string;
        external_ref: string | null;
        surplus_amount: number;
        is_fully_allocated: boolean;
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
        program: { name: string } | null;
    };
    semester: { id: number; name: string };
    billing_cycle: { name: string } | null;
    due_date: string | null;
    created_at: string | null;
    status: string;
    settlement_position: SettlementPosition;
    charges: Charge[];
    settlement_entries: SettlementEntry[];
}

const props = defineProps<{ invoice: Invoice }>();

const statusLabel = (state: string): string =>
    ({
        unpaid: 'Chưa thu',
        partially_settled: 'Đang thu',
        settled_by_cash: 'Đã thu',
        settled_by_reduction: 'Đã giảm trừ',
        surplus: 'Còn dư',
        invalid: 'Cần kiểm tra',
        missing: 'Cần kiểm tra',
    })[state] ?? state;

const getStatusBadgeVariant = (state: string) => {
    if (state === 'invalid' || state === 'missing') return 'destructive';
    if (state === 'settled_by_cash' || state === 'settled_by_reduction') return 'success';
    return 'outline';
};

const getChargeTypeLabel = (type: string | null): string => {
    if (!type) return 'Payable Line';

    return type
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const getSettlementBadgeVariant = (entryGroup: SettlementEntry['entry_group']) => (entryGroup === 'payment' ? 'default' : 'secondary');

const getSettlementLabel = (entry: SettlementEntry): string => (entry.entry_group === 'payment' ? 'Đã thu' : 'Giảm giá');

const getSettlementReference = (entry: SettlementEntry): string => {
    if (entry.payment) return `#${entry.payment.id}`;
    if (entry.discount) return `#${entry.discount.id}`;

    return '-';
};

const getSettlementSource = (entry: SettlementEntry): string => entry.payment?.method ?? entry.discount?.discount_type ?? '-';

const getSettlementDescription = (entry: SettlementEntry): string => {
    if (entry.payment) return entry.payment.external_ref || 'Cash application';

    return entry.discount?.description || entry.discount?.discount_source || 'Discount allocation';
};

const formatIssueEvidence = (issue: SettlementIssue): string => {
    const invariant = issue.finance_invariant_code ? ` (${issue.finance_invariant_code})` : '';

    return `${issue.code}${invariant}`;
};

const formatEvidence = (evidence: SettlementIssue['evidence']): string =>
    Object.entries(evidence)
        .map(([key, value]) => `${key}: ${value}`)
        .join(', ');
</script>

<template>
    <div class="space-y-6">
        <Head :title="`Invoice ${props.invoice.invoice_number}`" />

        <div class="flex items-center gap-4">
            <Button variant="outline" size="icon" as-child>
                <Link :href="route('finance.invoices.index')">
                    <ChevronLeft class="h-4 w-4" />
                </Link>
            </Button>
            <div>
                <h2 class="flex items-center gap-2 text-3xl font-bold tracking-tight">
                    Invoice {{ props.invoice.invoice_number }}
                    <Badge :variant="getStatusBadgeVariant(props.invoice.settlement_position.settlement_state)">
                        {{ statusLabel(props.invoice.settlement_position.settlement_state) }}
                    </Badge>
                    <span v-if="props.invoice.status === 'overdue'" class="text-destructive flex items-center gap-1 text-xs"> <AlertCircle class="h-3 w-3" /> Overdue </span>
                </h2>
                <p class="text-muted-foreground">Created on {{ formatDate(props.invoice.created_at) }} • Due on {{ formatDate(props.invoice.due_date) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <Card class="md:col-span-2">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"> <UserIcon class="h-5 w-5" /> Student Information </CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Full Name</p>
                        <p class="text-lg">{{ props.invoice.student.full_name }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Student ID</p>
                        <p class="text-lg">{{ props.invoice.student.student_id }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Program</p>
                        <p>{{ props.invoice.student.program?.name || 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Email</p>
                        <p>{{ props.invoice.student.email }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"><DollarSign class="h-5 w-5" /> Settlement Position</CardTitle>
                    <CardDescription>{{ props.invoice.semester.name }} · {{ props.invoice.settlement_position.position_mode }}</CardDescription>
                </CardHeader>
                <CardContent v-if="props.invoice.settlement_position.valid && props.invoice.settlement_position.amounts" class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Phí gốc</span><span class="font-medium">{{ formatCurrency(props.invoice.settlement_position.amounts.gross.amount) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Giảm giá</span><span class="font-medium text-amber-600">{{ formatCurrency(props.invoice.settlement_position.amounts.discount.amount) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Đã thu</span><span class="font-medium text-emerald-600">{{ formatCurrency(props.invoice.settlement_position.amounts.cash.amount) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Credit đã áp dụng</span><span class="font-medium text-sky-600">{{ formatCurrency(props.invoice.settlement_position.amounts.credit.amount) }}</span>
                    </div>
                    <Separator />
                    <div class="flex items-center justify-between">
                        <span class="font-bold">Còn phải thu</span><span class="text-primary text-2xl font-bold">{{ formatCurrency(props.invoice.settlement_position.amounts.remaining.amount) }}</span>
                    </div>
                    <p class="text-muted-foreground text-xs">
                        Breakdown reconcile: {{ props.invoice.settlement_position.breakdown_reconciliation.status }} · Rounding remainder: {{ formatCurrency(props.invoice.settlement_position.breakdown_reconciliation.rounding_remainder.amount) }}
                    </p>
                    <p class="text-muted-foreground text-xs">Còn dư là tiền đã thu nhưng chưa khớp nghĩa vụ; không được gộp vào Đã thu hoặc Còn phải thu.</p>
                </CardContent>
                <CardContent v-else class="space-y-3">
                    <div class="text-destructive flex items-center gap-2 font-semibold"><AlertCircle class="h-4 w-4" /> Cần kiểm tra</div>
                    <p class="text-muted-foreground text-sm">Không hiển thị số tiền còn phải thu hoặc thao tác thu tiền khi Settlement Position không hợp lệ.</p>
                    <ul class="text-muted-foreground space-y-1 text-xs">
                        <li v-for="issue in props.invoice.settlement_position.issues" :key="issue.code">
                            {{ formatIssueEvidence(issue) }}<span v-if="Object.keys(issue.evidence).length"> — {{ formatEvidence(issue.evidence) }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2"><Receipt class="h-4 w-4" /> Payable Line Breakdown</CardTitle>
                <CardDescription>Each line repeats the same canonical components as the invoice position.</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader
                        ><TableRow
                            ><TableHead>Khoản phí</TableHead><TableHead>Mô tả</TableHead><TableHead class="text-right">Phí gốc</TableHead><TableHead class="text-right">Giảm giá</TableHead><TableHead class="text-right">Đã thu</TableHead
                            ><TableHead class="text-right">Credit đã áp dụng</TableHead><TableHead class="text-right">Còn phải thu</TableHead><TableHead>Trạng thái</TableHead></TableRow
                        ></TableHeader
                    >
                    <TableBody>
                        <TableRow v-for="charge in props.invoice.charges" :key="charge.invoice_line_id">
                            <TableCell class="font-medium">{{ getChargeTypeLabel(charge.charge_type) }}</TableCell>
                            <TableCell
                                >{{ charge.description || '-' }}
                                <div v-if="charge.source_type === 'Manual'" class="text-muted-foreground mt-1 text-xs">Manually added</div></TableCell
                            >
                            <template v-if="charge.valid && charge.amounts">
                                <TableCell class="text-right">{{ formatCurrency(charge.amounts.gross.amount) }}</TableCell>
                                <TableCell class="text-right text-amber-600">{{ formatCurrency(charge.amounts.discount.amount) }}</TableCell>
                                <TableCell class="text-right text-emerald-600">{{ formatCurrency(charge.amounts.cash.amount) }}</TableCell>
                                <TableCell class="text-right text-sky-600">{{ formatCurrency(charge.amounts.credit.amount) }}</TableCell>
                                <TableCell class="text-right font-medium">{{ formatCurrency(charge.amounts.remaining.amount) }}</TableCell>
                                <TableCell
                                    ><Badge variant="outline">{{ statusLabel(charge.settlement_state || 'invalid') }}</Badge></TableCell
                                >
                            </template>
                            <template v-else>
                                <TableCell colspan="5" class="text-destructive text-center text-sm">Cần kiểm tra</TableCell>
                                <TableCell><Badge variant="destructive">Cần kiểm tra</Badge></TableCell>
                            </template>
                        </TableRow>
                        <TableRow v-if="props.invoice.charges.length === 0"><TableCell colspan="8" class="text-muted-foreground h-24 text-center">No payable lines.</TableCell></TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2"><CreditCard class="h-4 w-4" /> Settlement Ledger</CardTitle>
                <CardDescription>Raw payment and discount evidence linked to the visible payable lines.</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader
                        ><TableRow
                            ><TableHead>Type</TableHead><TableHead>Reference</TableHead><TableHead>Date</TableHead><TableHead>Method / Source</TableHead><TableHead>Details</TableHead><TableHead>Allocated To</TableHead><TableHead>Entry</TableHead
                            ><TableHead class="text-right">Amount</TableHead></TableRow
                        ></TableHeader
                    >
                    <TableBody>
                        <TableRow v-for="entry in props.invoice.settlement_entries" :key="entry.id">
                            <TableCell
                                ><Badge :variant="getSettlementBadgeVariant(entry.entry_group)"
                                    ><span class="flex items-center gap-1"><Percent v-if="entry.entry_group === 'discount'" class="h-3 w-3" /><CreditCard v-else class="h-3 w-3" />{{ getSettlementLabel(entry) }}</span></Badge
                                ></TableCell
                            >
                            <TableCell class="font-medium"
                                ><Link v-if="entry.payment" :href="route('finance.payments.show', entry.payment.id)" class="text-primary hover:underline">{{ getSettlementReference(entry) }}</Link
                                ><span v-else>{{ getSettlementReference(entry) }}</span></TableCell
                            >
                            <TableCell>{{ formatDate(entry.applied_at) }}</TableCell>
                            <TableCell
                                ><Badge variant="outline">{{ getSettlementSource(entry) }}</Badge></TableCell
                            >
                            <TableCell class="text-muted-foreground text-sm">
                                {{ getSettlementDescription(entry) }}
                                <div v-if="entry.payment && !entry.payment.is_fully_allocated" class="mt-1 font-medium text-amber-600">Còn dư: {{ formatCurrency(entry.payment.surplus_amount) }}</div>
                            </TableCell>
                            <TableCell>{{ entry.charge.description || '-' }}</TableCell>
                            <TableCell
                                ><Badge variant="outline">{{ entry.entry_type }}</Badge></TableCell
                            >
                            <TableCell class="text-right font-medium">{{ formatCurrency(entry.amount) }}</TableCell>
                        </TableRow>
                        <TableRow v-if="props.invoice.settlement_entries.length === 0"><TableCell colspan="8" class="text-muted-foreground h-24 text-center">No settlement evidence yet.</TableCell></TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
