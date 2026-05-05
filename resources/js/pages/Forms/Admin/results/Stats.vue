<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Semester } from '@/types/models';
import { Head, router } from '@inertiajs/vue3';

interface ProgramRow {
    program_code: string;
    program_name: string;
    submissions: number;
    high_rated_count: number;
    percent: number | null;
}

interface StatsData {
    rows: ProgramRow[];
    totals: {
        submissions: number;
        high_rated_count: number;
        percent: number | null;
    };
}

defineProps<{
    stats: StatsData;
    semesters: Semester[];
    filters: {
        semester_id: string;
    };
}>();

function formatPercent(value: number | null): string {
    if (value === null) return '\u2014';
    return `${value.toFixed(1)}%`;
}

function handleSemesterChange(value: string) {
    router.visit('/forms/admin/results/stats', {
        data: { semester_id: value === 'all' ? undefined : value },
        preserveState: true,
        only: ['stats', 'filters'],
    });
}
</script>

<template>
    <Head title="Course Survey Stats by Program" />

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Course Survey Stats by Program</h1>
    </div>

    <div class="space-y-4 rounded-lg border bg-card p-4">
        <div class="flex items-center gap-4">
            <div class="flex flex-col gap-1">
                <Label class="text-xs font-semibold text-muted-foreground">Semester</Label>
                <Select :model-value="filters.semester_id" @update:model-value="handleSemesterChange">
                    <SelectTrigger class="w-56">
                        <SelectValue placeholder="All Semesters" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Semesters</SelectItem>
                        <SelectItem v-for="s in semesters" :key="s.id" :value="s.id.toString()">
                            {{ s.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
    </div>

    <div v-if="stats.rows.length === 0" class="rounded-lg border bg-card p-8 text-center text-muted-foreground">
        No course survey data available for the selected filter.
    </div>

    <div v-else class="rounded-lg border bg-card">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Program</TableHead>
                    <TableHead class="text-right">Submissions</TableHead>
                    <TableHead class="text-right">KQ TBC &ge; 4</TableHead>
                    <TableHead class="text-right">%</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="row in stats.rows" :key="row.program_code">
                    <TableCell>
                        <div class="flex flex-col">
                            <span class="font-medium">{{ row.program_code }}</span>
                            <span class="text-xs text-muted-foreground">{{ row.program_name }}</span>
                        </div>
                    </TableCell>
                    <TableCell class="text-right tabular-nums">{{ row.submissions }}</TableCell>
                    <TableCell class="text-right tabular-nums">{{ row.high_rated_count }}</TableCell>
                    <TableCell class="text-right tabular-nums">{{ formatPercent(row.percent) }}</TableCell>
                </TableRow>

                <TableRow class="border-t-2 font-semibold">
                    <TableCell>TOTAL</TableCell>
                    <TableCell class="text-right tabular-nums">{{ stats.totals.submissions }}</TableCell>
                    <TableCell class="text-right tabular-nums">{{ stats.totals.high_rated_count }}</TableCell>
                    <TableCell class="text-right tabular-nums">{{ formatPercent(stats.totals.percent) }}</TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
