<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { BatchDiffBucket, BatchPreviewLineClient, BatchPreviewMajorContext } from '@/types/finance';
import { formatCurrency } from '@/types/finance';
import { BATCH_BUCKET_META, batchReasonLabel } from '@/utils/batchStudioDisplay';
import { ChevronDown, ChevronsUpDown, ChevronUp, Download, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    lines: BatchPreviewLineClient[];
    selected: Set<string>;
    counts: Record<string, number>;
    exportable?: boolean;
    blockCountControls?: boolean;
    blockCounts?: Record<string, number>;
    majorDetails?: boolean;
    majorContext?: BatchPreviewMajorContext | null;
    creditOffsetColumn?: boolean;
}>();

const emit = defineEmits<{
    (e: 'toggle', key: string): void;
    (e: 'select-all', keys: string[]): void;
    (e: 'deselect-all', keys: string[]): void;
    (e: 'export'): void;
    (e: 'update-block-count', key: string, count: number): void;
}>();

const filter = ref<BatchDiffBucket | 'all'>('all');
const search = ref('');

type SortKey = 'label' | 'program_name' | 'term_number' | 'net' | 'unapplied_credit';
const sortKey = ref<SortKey | null>(null);
const sortDir = ref<'asc' | 'desc'>('asc');

function toggleSort(key: SortKey): void {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
}

function sortValue(line: BatchPreviewLineClient, key: SortKey): string | number {
    switch (key) {
        case 'label':
            return line.display.label.toLowerCase();
        case 'program_name':
            return (line.display.program_name ?? '').toLowerCase();
        case 'term_number':
            return line.display.term_number ?? -Infinity;
        case 'net':
            // Sort on the stable list amount, not the block-count-adjustable
            // displayAmount() — otherwise editing a block-count dropdown
            // re-sorts the table under the user's cursor mid-edit.
            return Number(line.display.net ?? 0);
        case 'unapplied_credit':
            return line.display.unapplied_credit ?? -Infinity;
    }
}

const filteredRows = computed(() =>
    props.lines.filter((l) => {
        const okBucket = filter.value === 'all' || l.display.diff === filter.value;
        const q = search.value.trim().toLowerCase();
        const okSearch = !q || l.display.label.toLowerCase().includes(q) || l.display.student_id.toLowerCase().includes(q);
        return okBucket && okSearch;
    }),
);

const rows = computed(() => {
    if (!sortKey.value) return filteredRows.value;

    const key = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;

    return [...filteredRows.value].sort((a, b) => {
        const av = sortValue(a, key);
        const bv = sortValue(b, key);
        if (av < bv) return -1 * dir;
        if (av > bv) return 1 * dir;
        return 0;
    });
});

const selectedInView = computed(() => rows.value.filter((l) => props.selected.has(l.key)).length);

// Rows with an enabled checkbox in the current filter/search view — "skip"
// rows have a disabled checkbox (see the Checkbox binding below) and must
// never be force-selected by "Chọn tất cả".
const selectableRowKeys = computed(() => rows.value.filter((l) => l.display.diff !== 'skip').map((l) => l.key));

const allSelectedInView = computed(() => selectableRowKeys.value.length > 0 && selectableRowKeys.value.every((key) => props.selected.has(key)));

// true/false/'indeterminate' drives the reka-ui Checkbox tri-state directly.
const headerCheckboxState = computed<boolean | 'indeterminate'>(() => {
    if (selectedInView.value === 0) return false;
    return allSelectedInView.value ? true : 'indeterminate';
});

function onHeaderCheckboxChange(value: boolean | 'indeterminate'): void {
    if (value === true) {
        emit('select-all', selectableRowKeys.value);
    } else {
        emit('deselect-all', selectableRowKeys.value);
    }
}

const pageSizeOptions = [10, 25, 50, 100];
const pageSize = ref(25);
const page = ref(1);

const totalPages = computed(() => Math.max(1, Math.ceil(rows.value.length / pageSize.value)));
const pagedRows = computed(() => {
    const start = (page.value - 1) * pageSize.value;
    return rows.value.slice(start, start + pageSize.value);
});

watch([filter, search, sortKey, sortDir, pageSize, () => props.lines], () => {
    page.value = 1;
});
watch(totalPages, (next) => {
    if (page.value > next) page.value = next;
});

function setFilter(value: BatchDiffBucket | 'all') {
    filter.value = value;
}

function maxBlockCount(line: BatchPreviewLineClient): number {
    return Math.max(0, Number(line.display.block_count ?? 0));
}

function blockOptions(line: BatchPreviewLineClient): number[] {
    return Array.from({ length: maxBlockCount(line) }, (_, index) => index + 1);
}

function selectedBlockCount(line: BatchPreviewLineClient): number {
    return props.blockCounts?.[line.key] ?? maxBlockCount(line);
}

function displayAmount(line: BatchPreviewLineClient): number {
    const blocks = selectedBlockCount(line);
    const amounts = line.display.block_amounts ?? [];

    if (props.blockCountControls && amounts.length > 0 && blocks > 0) {
        return amounts.slice(0, blocks).reduce((total, amount) => total + Number(amount), 0);
    }

    return Number(line.display.net ?? 0);
}

function hasDiscountBreakdown(line: BatchPreviewLineClient): boolean {
    return Number(line.display.scholarship_amount ?? 0) > 0 || Number(line.display.voucher_amount ?? 0) > 0;
}

function scholarshipLabel(line: BatchPreviewLineClient): string {
    const { scholarship_type: type, scholarship_raw_value: raw } = line.display;
    if (raw === null || raw === undefined) return '';
    return type === 'percentage' ? `${raw}%` : formatCurrency(raw);
}

function hasReduction(line: BatchPreviewLineClient): boolean {
    return Number(line.display.scholarship_reduction_amount ?? 0) > 0;
}

function adjustedLabel(line: BatchPreviewLineClient): string {
    const { scholarship_type: type, scholarship_adjusted_raw_value: raw } = line.display;
    if (raw === null || raw === undefined) return '';
    return type === 'percentage' ? `còn ${raw}%` : `còn ${formatCurrency(raw)}`;
}

function hasUnappliedCredit(line: BatchPreviewLineClient): boolean {
    return Number(line.display.unapplied_credit ?? 0) > 0;
}

function hasProjectedOffset(line: BatchPreviewLineClient): boolean {
    return Number(line.display.credit_offset_projected ?? 0) > 0;
}

function noOffsetReason(): string {
    // Distinguishes "config is on but this row didn't clear the threshold"
    // from the globally-disabled case (already stated once in the banner).
    return props.majorContext?.credit_offset_enabled ? 'Dưới ngưỡng áp dụng' : 'Chưa áp dụng';
}

function sortIcon(key: SortKey) {
    if (sortKey.value !== key) return ChevronsUpDown;
    return sortDir.value === 'asc' ? ChevronUp : ChevronDown;
}
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <button
                v-for="(meta, key) in BATCH_BUCKET_META"
                :key="key"
                type="button"
                class="rounded-lg border p-3 text-left transition hover:shadow-sm"
                :class="[meta.cardClass, filter === key ? 'ring-primary/30 ring-2' : '']"
                @click="setFilter(key as BatchDiffBucket)"
            >
                <p class="text-muted-foreground text-xs font-medium">{{ meta.dot }} {{ meta.label }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ counts[key] ?? 0 }}</p>
            </button>
        </div>

        <Card>
            <CardHeader class="gap-4 border-b pb-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <CardTitle class="text-base">Danh sách xem trước</CardTitle>
                    <CardDescription> {{ rows.length }} dòng hiển thị · {{ selectedInView }} đã chọn trong bộ lọc </CardDescription>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button :variant="filter === 'all' ? 'default' : 'outline'" size="sm" @click="setFilter('all')"> Tất cả </Button>
                    <Button v-for="(meta, key) in BATCH_BUCKET_META" :key="`btn-${key}`" size="sm" :variant="filter === key ? 'default' : 'outline'" @click="setFilter(key as BatchDiffBucket)"> {{ meta.short }} ({{ counts[key] ?? 0 }}) </Button>
                </div>
            </CardHeader>

            <CardContent class="space-y-4 pt-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:max-w-sm">
                        <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                        <Input v-model="search" placeholder="Tìm theo tên hoặc mã SV…" class="pl-8" />
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Button v-if="exportable" variant="outline" size="sm" @click="emit('export')">
                            <Download class="mr-2 h-4 w-4" />
                            Xuất Excel
                        </Button>
                    </div>
                </div>

                <div class="rounded-lg border">
                    <Table container-class="max-h-[min(42rem,75vh)] overflow-auto rounded-lg">
                        <TableHeader class="bg-background sticky top-0 z-10">
                            <TableRow>
                                <TableHead class="w-12 px-4">
                                    <Checkbox :model-value="headerCheckboxState" :disabled="selectableRowKeys.length === 0" title="Chọn / bỏ chọn tất cả trong bộ lọc" @update:model-value="onHeaderCheckboxChange" />
                                </TableHead>
                                <TableHead class="min-w-[14rem] cursor-pointer px-4" @click="toggleSort('label')"> Sinh viên <component :is="sortIcon('label')" class="inline h-3 w-3" /> </TableHead>
                                <TableHead v-if="majorDetails" class="min-w-[10rem] cursor-pointer px-4" @click="toggleSort('program_name')"> Ngành <component :is="sortIcon('program_name')" class="inline h-3 w-3" /> </TableHead>
                                <TableHead v-if="majorDetails" class="w-20 cursor-pointer px-4" title="Số thứ tự kỳ đóng học phí trong lộ trình của sinh viên (không phải kỳ lịch)" @click="toggleSort('term_number')">
                                    Kỳ HP <component :is="sortIcon('term_number')" class="inline h-3 w-3" />
                                </TableHead>
                                <TableHead class="w-36 px-4">Phân loại</TableHead>
                                <TableHead v-if="blockCountControls" class="w-32 px-4">Số block</TableHead>
                                <TableHead class="w-36 cursor-pointer px-4 text-right" @click="toggleSort('net')"> Số tiền <component :is="sortIcon('net')" class="inline h-3 w-3" /> </TableHead>
                                <TableHead v-if="creditOffsetColumn" class="w-44 cursor-pointer px-4 text-right" @click="toggleSort('unapplied_credit')">
                                    Số dư / Dự kiến trừ <component :is="sortIcon('unapplied_credit')" class="inline h-3 w-3" />
                                </TableHead>
                                <TableHead class="min-w-[12rem] px-4">Lý do</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="l in pagedRows" :key="l.key" class="hover:bg-muted/30" :class="selected.has(l.key) ? 'bg-primary/5' : ''">
                                <TableCell class="px-4 py-3 align-middle">
                                    <Checkbox :model-value="selected.has(l.key)" :disabled="l.display.diff === 'skip'" @update:model-value="emit('toggle', l.key)" />
                                </TableCell>
                                <TableCell class="px-4 py-3 align-middle">
                                    <div class="leading-snug font-medium">{{ l.display.label }}</div>
                                    <div class="text-muted-foreground mt-0.5 font-mono text-xs">
                                        {{ l.display.student_id || '—' }}
                                    </div>
                                </TableCell>
                                <TableCell v-if="majorDetails" class="px-4 py-3 align-middle text-sm">
                                    <div>{{ l.display.program_name || '—' }}</div>
                                    <div v-if="l.display.specialization_name" class="text-muted-foreground text-xs">{{ l.display.specialization_name }}</div>
                                </TableCell>
                                <TableCell v-if="majorDetails" class="px-4 py-3 text-center align-middle text-sm tabular-nums">
                                    {{ l.display.term_number ?? '—' }}
                                </TableCell>
                                <TableCell class="px-4 py-3 align-middle">
                                    <Badge variant="outline" :class="BATCH_BUCKET_META[l.display.diff].badgeClass">
                                        {{ BATCH_BUCKET_META[l.display.diff].label }}
                                    </Badge>
                                </TableCell>
                                <TableCell v-if="blockCountControls" class="px-4 py-3 align-middle">
                                    <Select v-if="maxBlockCount(l) > 0 && l.display.diff !== 'skip'" :model-value="String(selectedBlockCount(l))" @update:model-value="(value) => emit('update-block-count', l.key, Number(value))">
                                        <SelectTrigger class="h-8 w-24">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="count in blockOptions(l)" :key="count" :value="String(count)">
                                                {{ count }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <span v-else class="text-muted-foreground text-sm">—</span>
                                </TableCell>
                                <TableCell class="px-4 py-3 text-right align-middle font-mono text-sm tabular-nums">
                                    <template v-if="hasDiscountBreakdown(l)">
                                        <div class="text-muted-foreground text-xs line-through">{{ formatCurrency(l.display.gross ?? 0) }}</div>
                                        <div class="font-semibold">{{ formatCurrency(displayAmount(l)) }}</div>
                                        <div class="text-muted-foreground mt-0.5 space-y-0.5 font-sans text-xs normal-case">
                                            <div v-if="Number(l.display.scholarship_amount ?? 0) > 0">
                                                🎓 {{ l.display.scholarship_name }}<template v-if="scholarshipLabel(l)"> ({{ scholarshipLabel(l) }})</template>: -{{ formatCurrency(l.display.scholarship_amount ?? 0) }}
                                            </div>
                                            <div v-if="hasReduction(l)" class="text-amber-600">
                                                📉 Bị giảm học bổng<template v-if="adjustedLabel(l)"> ({{ adjustedLabel(l) }})</template>: +{{ formatCurrency(l.display.scholarship_reduction_amount ?? 0) }}
                                            </div>
                                            <div v-if="Number(l.display.voucher_amount ?? 0) > 0">🎟️ {{ (l.display.voucher_codes ?? []).join(', ') }}: -{{ formatCurrency(l.display.voucher_amount ?? 0) }}</div>
                                        </div>
                                    </template>
                                    <template v-else>{{ formatCurrency(displayAmount(l)) }}</template>
                                </TableCell>
                                <TableCell v-if="creditOffsetColumn" class="px-4 py-3 text-right align-middle font-mono text-sm tabular-nums">
                                    <template v-if="hasUnappliedCredit(l)">
                                        <div class="text-muted-foreground text-xs">Dư: {{ formatCurrency(l.display.unapplied_credit ?? 0) }}</div>
                                        <div v-if="hasProjectedOffset(l)" class="font-medium text-amber-600">Dự kiến trừ: -{{ formatCurrency(l.display.credit_offset_projected ?? 0) }}</div>
                                        <div v-else class="text-muted-foreground text-xs italic">{{ noOffsetReason() }}</div>
                                    </template>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="text-muted-foreground px-4 py-3 align-middle text-sm leading-relaxed">
                                    {{ batchReasonLabel(l.display.reason) }}
                                    <div v-if="Number(l.display.uncovered_amount ?? 0) > 0" class="font-mono text-xs">
                                        Còn thiếu: {{ formatCurrency(l.display.uncovered_amount ?? 0) }}
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="rows.length === 0">
                                <TableCell :colspan="(blockCountControls ? 6 : 5) + (majorDetails ? 2 : 0) + (creditOffsetColumn ? 1 : 0)" class="text-muted-foreground px-4 py-10 text-center"> Không có dòng nào khớp bộ lọc. </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-muted-foreground text-sm">Hiển thị</span>
                        <Select :model-value="String(pageSize)" @update:model-value="(value) => (pageSize = Number(value) || 25)">
                            <SelectTrigger class="h-8 w-20">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="size in pageSizeOptions" :key="size" :value="String(size)">
                                    {{ size }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <span class="text-muted-foreground text-sm">dòng / trang</span>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="text-muted-foreground text-sm"> Trang {{ page }} / {{ totalPages }} ({{ rows.length }} dòng) </span>
                        <div class="flex gap-2">
                            <Button variant="outline" size="sm" :disabled="page <= 1" @click="page--"> Trước </Button>
                            <Button variant="outline" size="sm" :disabled="page >= totalPages" @click="page++"> Sau </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
