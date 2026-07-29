<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useDataTable } from '@/composables/useDataTable';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatCurrency, getChargeTypeLabel } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, HelpCircle, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface RevenueMoneySet {
    gross: number;
    discount: number;
    net_billed: number;
    cash: number;
    credit: number;
    outstanding: number;
    collection_rate: number | null;
    invalid_count: number;
    invalid_gross: number;
}

interface RevenueRow extends RevenueMoneySet {
    semester_id: number;
    semester_name: string;
    start_date: string | null;
    growth_pct: number | null;
    by_campus: BreakdownItem[];
    by_fee_type: BreakdownItem[];
}

interface BreakdownItem extends RevenueMoneySet {
    key: string;
    label: string;
}

interface FilterOption {
    value: string | number;
    label: string;
}

interface RevenueFilters {
    semester_ids: number[];
    campus_id: string;
    fee_type: string;
}

const props = defineProps<{
    rows: RevenueRow[];
    totals: RevenueMoneySet;
    breakdowns: { by_campus: BreakdownItem[]; by_fee_type: BreakdownItem[] };
    unattributed: { unapplied: number; refund: number; retain_forfeit: number };
    filters: Partial<RevenueFilters>;
    filter_options: { semesters: FilterOption[]; campuses: FilterOption[]; fee_types: FilterOption[] };
    computed_at: string;
}>();

const { filters: tableFilters, setFilter, hasActiveFilters, clearAllFilters } = useDataTable<RevenueFilters>({
    baseUrl: financeRoutes.revenue.index(),
    initialFilters: {
        semester_ids: props.filters.semester_ids ?? [],
        campus_id: props.filters.campus_id ? String(props.filters.campus_id) : 'all',
        fee_type: props.filters.fee_type ?? 'all',
    },
    defaultValues: { semester_ids: [], campus_id: 'all', fee_type: 'all' },
    only: ['rows', 'totals', 'breakdowns', 'unattributed', 'filters', 'computed_at'],
    immediateFields: ['semester_ids', 'campus_id', 'fee_type'],
});

const toggleSemester = (semesterId: number): void => {
    const current = tableFilters.semester_ids ?? [];
    const next = current.includes(semesterId) ? current.filter((id) => id !== semesterId) : [...current, semesterId];
    setFilter('semester_ids', next);
};

const activeBreakdown = ref<'by_campus' | 'by_fee_type'>('by_campus');
const breakdownRows = computed(() => props.breakdowns[activeBreakdown.value]);
const breakdownLabel = (item: BreakdownItem): string => (activeBreakdown.value === 'by_fee_type' ? getChargeTypeLabel(item.label) : item.label);

const growthLabel = (value: number | null): string => {
    if (value === null) return '—';
    const pct = Math.round(value * 1000) / 10;

    return `${pct > 0 ? '+' : ''}${pct}%`;
};

const growthClass = (value: number | null): string => {
    if (value === null) return 'text-muted-foreground';

    return value > 0 ? 'text-emerald-700' : value < 0 ? 'text-red-600' : 'text-muted-foreground';
};

const totalUnattributed = computed(() => props.unattributed.unapplied + props.unattributed.refund + props.unattributed.retain_forfeit);

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="Doanh thu" />

    <div class="space-y-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-semibold tracking-tight">Doanh thu</h1>
                    <Badge variant="secondary" class="border-primary/30 bg-primary/10 text-primary">Toàn trường</Badge>
                </div>
                <p class="text-muted-foreground max-w-2xl text-sm">
                    Doanh thu phát sinh và thực thu theo học kỳ, gộp mọi campus. Computed at {{ new Date(computed_at).toLocaleString() }}.
                </p>
            </div>
        </div>

        <Card>
            <CardHeader class="gap-3 space-y-0">
                <CardTitle class="text-base">Bộ lọc</CardTitle>
                <CardDescription>Chọn học kỳ để so sánh (bỏ trống = mọi kỳ), campus, và loại phí.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="option in filter_options.semesters"
                        :key="option.value"
                        type="button"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                        :class="
                            (tableFilters.semester_ids ?? []).includes(Number(option.value))
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-input bg-background hover:bg-muted'
                        "
                        @click="toggleSemester(Number(option.value))"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Select :model-value="tableFilters.campus_id" @update:model-value="(value) => setFilter('campus_id', String(value))">
                        <SelectTrigger><SelectValue placeholder="Campus" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả campus</SelectItem>
                            <SelectItem v-for="option in filter_options.campuses" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.fee_type" @update:model-value="(value) => setFilter('fee_type', String(value))">
                        <SelectTrigger><SelectValue placeholder="Loại phí" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả loại phí</SelectItem>
                            <SelectItem v-for="option in filter_options.fee_types" :key="option.value" :value="String(option.value)">{{ getChargeTypeLabel(String(option.value)) }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <div class="flex items-center">
                        <Button v-if="hasActiveFilters" variant="outline" size="sm" @click="clearAllFilters">Xóa bộ lọc</Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Doanh thu theo học kỳ</CardTitle>
                <CardDescription>1 dòng = 1 học kỳ, sắp xếp mới nhất trước. Số tính lại từ ledger tại thời điểm xem.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Học kỳ</TableHead>
                                <TableHead class="text-right">Phát sinh</TableHead>
                                <TableHead class="text-right">Đã thu</TableHead>
                                <TableHead class="text-right">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <span class="inline-flex items-center gap-1">Miễn giảm <HelpCircle class="size-3" /></span>
                                            </TooltipTrigger>
                                            <TooltipContent>Giảm còn phải thu. Không phải doanh thu, không phải tiền đã thu.</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </TableHead>
                                <TableHead class="text-right">Còn phải thu</TableHead>
                                <TableHead class="text-right">Tỷ lệ thu</TableHead>
                                <TableHead class="text-right">Tăng trưởng</TableHead>
                                <TableHead class="text-right">
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <span class="inline-flex items-center gap-1">Cần kiểm tra <HelpCircle class="size-3" /></span>
                                            </TooltipTrigger>
                                            <TooltipContent>Dòng phí có bất thường settlement, đã loại khỏi mọi cột tiền.</TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="rows.length === 0">
                                <TableCell colspan="8" class="text-muted-foreground py-8 text-center text-sm">Không có dữ liệu trong phạm vi lọc hiện tại.</TableCell>
                            </TableRow>
                            <TableRow v-for="row in rows" :key="row.semester_id">
                                <TableCell class="font-medium">{{ row.semester_name }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(row.net_billed) }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(row.cash) }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(row.credit) }}</TableCell>
                                <TableCell class="text-right" :class="row.outstanding > 0 ? 'font-medium' : 'text-muted-foreground'">{{ formatCurrency(row.outstanding) }}</TableCell>
                                <TableCell class="text-right">{{ row.collection_rate !== null ? `${Math.round(row.collection_rate * 100)}%` : '—' }}</TableCell>
                                <TableCell class="text-right" :class="growthClass(row.growth_pct)">{{ growthLabel(row.growth_pct) }}</TableCell>
                                <TableCell class="text-right">
                                    <Badge v-if="row.invalid_count > 0" variant="destructive" class="gap-1">
                                        <AlertTriangle class="size-3" />
                                        {{ row.invalid_count }}
                                    </Badge>
                                    <span v-else class="text-muted-foreground">0</span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                        <tfoot v-if="rows.length > 0">
                            <TableRow class="bg-muted/50 font-semibold">
                                <TableCell>Tổng cộng</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(totals.net_billed) }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(totals.cash) }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(totals.credit) }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(totals.outstanding) }}</TableCell>
                                <TableCell class="text-right">{{ totals.collection_rate !== null ? `${Math.round(totals.collection_rate * 100)}%` : '—' }}</TableCell>
                                <TableCell class="text-right">—</TableCell>
                                <TableCell class="text-right">
                                    <Badge v-if="totals.invalid_count > 0" variant="destructive">{{ totals.invalid_count }}</Badge>
                                    <span v-else class="text-muted-foreground">0</span>
                                </TableCell>
                            </TableRow>
                        </tfoot>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <Card class="border-amber-300 bg-amber-50/50">
            <CardHeader class="gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Wallet class="size-4" />
                        Chưa phân bổ kỳ
                    </CardTitle>
                    <CardDescription>Tiền đã thu nhưng chưa gán hoá đơn nên không quy được về học kỳ nào.</CardDescription>
                </div>
                <div class="text-lg font-semibold">{{ formatCurrency(totalUnattributed) }}</div>
            </CardHeader>
            <CardContent>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <p class="text-muted-foreground text-xs">Chưa gán</p>
                        <p class="font-medium">{{ formatCurrency(unattributed.unapplied) }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs">Hoàn tiền</p>
                        <p class="font-medium">{{ formatCurrency(unattributed.refund) }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs">Giữ lại (forfeit)</p>
                        <p class="font-medium">{{ formatCurrency(unattributed.retain_forfeit) }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="gap-3 space-y-0 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <CardTitle>Breakdown</CardTitle>
                    <CardDescription>Tổng theo campus hoặc loại phí, trong phạm vi bộ lọc hiện tại.</CardDescription>
                </div>
                <Select :model-value="activeBreakdown" @update:model-value="(value) => (activeBreakdown = value as 'by_campus' | 'by_fee_type')">
                    <SelectTrigger class="w-full sm:w-56"><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="by_campus">Theo campus</SelectItem>
                        <SelectItem value="by_fee_type">Theo loại phí</SelectItem>
                    </SelectContent>
                </Select>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nhóm</TableHead>
                                <TableHead class="text-right">Phát sinh</TableHead>
                                <TableHead class="text-right">Đã thu</TableHead>
                                <TableHead class="text-right">Còn phải thu</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="breakdownRows.length === 0">
                                <TableCell colspan="4" class="text-muted-foreground py-6 text-center text-sm">Không có dữ liệu.</TableCell>
                            </TableRow>
                            <TableRow v-for="item in breakdownRows" :key="item.key">
                                <TableCell class="font-medium">{{ breakdownLabel(item) }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(item.net_billed) }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(item.cash) }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(item.outstanding) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <p class="text-muted-foreground text-xs">Số tính lại từ ledger tại thời điểm xem, chưa có khoá sổ kỳ.</p>
    </div>
</template>
