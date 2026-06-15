<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { BatchDiffBucket, BatchPreviewLineClient } from '@/types/finance';
import { formatCurrency } from '@/types/finance';
import { BATCH_BUCKET_META, batchReasonLabel } from '@/utils/batchStudioDisplay';
import { Download, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    lines: BatchPreviewLineClient[];
    selected: Set<string>;
    counts: Record<string, number>;
    exportable?: boolean;
}>();

const emit = defineEmits<{ (e: 'toggle', key: string): void; (e: 'export'): void }>();

const filter = ref<BatchDiffBucket | 'all'>('all');
const search = ref('');

const rows = computed(() =>
    props.lines.filter((l) => {
        const okBucket = filter.value === 'all' || l.display.diff === filter.value;
        const q = search.value.trim().toLowerCase();
        const okSearch =
            !q || l.display.label.toLowerCase().includes(q) || l.display.student_id.toLowerCase().includes(q);
        return okBucket && okSearch;
    }),
);

const selectedInView = computed(() => rows.value.filter((l) => props.selected.has(l.key)).length);

function setFilter(value: BatchDiffBucket | 'all') {
    filter.value = value;
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
                :class="[meta.cardClass, filter === key ? 'ring-2 ring-primary/30' : '']"
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
                    <CardDescription>
                        {{ rows.length }} dòng hiển thị · {{ selectedInView }} đã chọn trong bộ lọc
                    </CardDescription>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button :variant="filter === 'all' ? 'default' : 'outline'" size="sm" @click="setFilter('all')">
                        Tất cả
                    </Button>
                    <Button
                        v-for="(meta, key) in BATCH_BUCKET_META"
                        :key="`btn-${key}`"
                        size="sm"
                        :variant="filter === key ? 'default' : 'outline'"
                        @click="setFilter(key as BatchDiffBucket)"
                    >
                        {{ meta.short }} ({{ counts[key] ?? 0 }})
                    </Button>
                </div>
            </CardHeader>

            <CardContent class="space-y-4 pt-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="relative w-full sm:max-w-sm">
                        <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                        <Input v-model="search" placeholder="Tìm theo tên hoặc mã SV…" class="pl-8" />
                    </div>
                    <Button v-if="exportable" variant="outline" size="sm" class="shrink-0" @click="emit('export')">
                        <Download class="mr-2 h-4 w-4" />
                        Xuất Excel
                    </Button>
                </div>

                <div class="overflow-hidden rounded-lg border">
                    <div class="max-h-[min(28rem,60vh)] overflow-auto">
                        <Table>
                            <TableHeader class="bg-muted/40 sticky top-0 z-10">
                                <TableRow>
                                    <TableHead class="w-12 px-4" />
                                    <TableHead class="min-w-[14rem] px-4">Sinh viên</TableHead>
                                    <TableHead class="w-36 px-4">Phân loại</TableHead>
                                    <TableHead class="w-36 px-4 text-right">Số tiền</TableHead>
                                    <TableHead class="min-w-[12rem] px-4">Lý do</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="l in rows"
                                    :key="l.key"
                                    class="hover:bg-muted/30"
                                    :class="selected.has(l.key) ? 'bg-primary/5' : ''"
                                >
                                    <TableCell class="px-4 py-3 align-middle">
                                        <Checkbox
                                            :model-value="selected.has(l.key)"
                                            :disabled="l.display.diff === 'skip'"
                                            @update:model-value="emit('toggle', l.key)"
                                        />
                                    </TableCell>
                                    <TableCell class="px-4 py-3 align-middle">
                                        <div class="font-medium leading-snug">{{ l.display.label }}</div>
                                        <div class="text-muted-foreground mt-0.5 font-mono text-xs">
                                            {{ l.display.student_id || '—' }}
                                        </div>
                                    </TableCell>
                                    <TableCell class="px-4 py-3 align-middle">
                                        <Badge variant="outline" :class="BATCH_BUCKET_META[l.display.diff].badgeClass">
                                            {{ BATCH_BUCKET_META[l.display.diff].label }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="px-4 py-3 text-right align-middle font-mono text-sm tabular-nums">
                                        {{ formatCurrency(l.display.net) }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground px-4 py-3 align-middle text-sm leading-relaxed">
                                        {{ batchReasonLabel(l.display.reason) }}
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="rows.length === 0">
                                    <TableCell colspan="5" class="text-muted-foreground px-4 py-10 text-center">
                                        Không có dòng nào khớp bộ lọc.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>