<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useTableFilters } from '@/composables/useFilters';
import type { PaginatedResponse } from '@/types';
import { studentRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { CalendarClock, CheckCircle2, Download, RefreshCw, Search } from 'lucide-vue-next';
import { computed } from 'vue';

type Bucket = 'overdue' | 'upcoming' | 'waiting';
type Severity = 'in_semester' | 'semester_ended' | null;

interface Campus {
    id: number;
    name: string | null;
    code: string | null;
}

interface AnchorSemester {
    id: number;
    code: string | null;
    name: string | null;
}

interface DeferReturnRow {
    bucket: Bucket;
    severity: Severity;
    action_id: number;
    action_type: string;
    student_pk: number;
    student_code: string;
    student_name: string;
    student_status: string;
    campus: Campus | null;
    anchor_semester: AnchorSemester | null;
    anchor_start_date: string | null;
    anchor_end_date: string | null;
    days_elapsed: number | null;
}

interface DeferReturnCounts {
    overdue: number;
    upcoming: number;
    waiting: number;
}

interface DeferReturnFilters {
    search: string | null;
    bucket: Bucket | '' | null;
    per_page: number;
}

interface Props {
    rows: PaginatedResponse<DeferReturnRow>;
    counts: DeferReturnCounts;
    filters: DeferReturnFilters;
}

const props = defineProps<Props>();

const { filters, clearFilters, handlePaginationNavigate, handlePageSizeChange, updateField, updateFieldDebounced } = useTableFilters<DeferReturnFilters>(
    studentRoutes.academicProgressionDeferReturns(),
    props.filters,
    ['rows', 'counts', 'filters'],
);

const tabs: Array<{ value: Bucket | ''; label: string }> = [
    { value: '', label: 'All' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'waiting', label: 'Waiting' },
];

const setBucket = (value: Bucket | '') => updateField('bucket', value);

const goToStudentLifecycle = (studentId: number) => {
    router.visit(studentRoutes.hub.lifecycle(studentId));
};

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (filters.value.search) params.set('search', filters.value.search);
    if (filters.value.bucket) params.set('bucket', filters.value.bucket);

    const query = params.toString();
    return `${studentRoutes.academicProgressionDeferReturnsExport()}${query ? `?${query}` : ''}`;
});

const formatDate = (value: string | null): string => (value ? value.slice(0, 10) : '—');

const daysLabel = (row: DeferReturnRow): string => {
    if (row.days_elapsed === null) return '—';

    if (row.bucket === 'upcoming') return `còn ${Math.abs(row.days_elapsed)} ngày`;
    if (row.bucket === 'waiting') return `đang chờ ${row.days_elapsed} ngày`;

    return `quá hạn ${row.days_elapsed} ngày`;
};

const severityBadgeVariant = (severity: Severity): 'destructive' | 'outline' => (severity === 'semester_ended' ? 'destructive' : 'outline');

const bucketLabel = (bucket: Bucket): string => (bucket === 'overdue' ? 'Overdue' : bucket === 'upcoming' ? 'Upcoming' : 'Waiting');
</script>

<template>
    <Head title="Defer Return Watchlist" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Defer Return Watchlist</h1>
                <p class="text-muted-foreground">Students still on hold (bảo lưu / chờ mở môn) who need a return decision</p>
            </div>

            <div class="flex items-center gap-2">
                <Badge variant="secondary" class="text-lg">{{ rows.total }} students</Badge>
                <Button variant="outline" as-child>
                    <a :href="exportUrl">
                        <Download class="mr-2 h-4 w-4" />
                        Export
                    </a>
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Card :class="counts.overdue > 0 ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950' : ''">
                <CardContent class="flex items-center justify-between pt-6">
                    <div>
                        <p class="text-muted-foreground text-sm">Overdue</p>
                        <p class="text-2xl font-bold" :class="counts.overdue > 0 ? 'text-red-600 dark:text-red-400' : ''">{{ counts.overdue }}</p>
                    </div>
                    <CalendarClock class="h-8 w-8" :class="counts.overdue > 0 ? 'text-red-500' : 'text-muted-foreground'" />
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center justify-between pt-6">
                    <div>
                        <p class="text-muted-foreground text-sm">Upcoming</p>
                        <p class="text-2xl font-bold">{{ counts.upcoming }}</p>
                    </div>
                    <CalendarClock class="text-muted-foreground h-8 w-8" />
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center justify-between pt-6">
                    <div>
                        <p class="text-muted-foreground text-sm">Waiting for course opening</p>
                        <p class="text-2xl font-bold">{{ counts.waiting }}</p>
                    </div>
                    <CalendarClock class="text-muted-foreground h-8 w-8" />
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="tab in tabs"
                            :key="tab.value || 'all'"
                            :variant="(filters.bucket ?? '') === tab.value ? 'default' : 'outline'"
                            size="sm"
                            @click="setBucket(tab.value)"
                        >
                            {{ tab.label }}
                        </Button>
                    </div>

                    <div class="flex items-end gap-4">
                        <div class="w-full space-y-2 sm:w-64">
                            <Label>Search Student</Label>
                            <div class="relative">
                                <Search class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                                <Input
                                    :model-value="filters.search ?? ''"
                                    @update:model-value="(val) => updateFieldDebounced('search', String(val))"
                                    placeholder="Search by name or ID..."
                                    class="pl-8"
                                />
                            </div>
                        </div>

                        <Button variant="outline" @click="clearFilters">
                            <RefreshCw class="mr-2 h-4 w-4" />
                            Reset
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Students on hold</CardTitle>
                <CardDescription>Row self-clears once the student's status changes (resume, dropout, transfer)</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="rows.data.length === 0 && (filters.bucket ?? '') === 'overdue'" class="py-12 text-center">
                    <CheckCircle2 class="mx-auto h-12 w-12 text-green-500" />
                    <p class="mt-4 font-medium text-green-600">Nobody overdue.</p>
                </div>

                <div v-else-if="rows.data.length === 0" class="py-12 text-center">
                    <CheckCircle2 class="text-muted-foreground mx-auto h-12 w-12" />
                    <p class="text-muted-foreground mt-4 font-medium">No students in this bucket.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-3 text-left font-medium">Student</th>
                                <th class="px-4 py-3 text-left font-medium">Campus</th>
                                <th class="px-4 py-3 text-left font-medium">Action type</th>
                                <th class="px-4 py-3 text-left font-medium">Return / anchor semester</th>
                                <th class="px-4 py-3 text-left font-medium">Date</th>
                                <th class="px-4 py-3 text-left font-medium">Days</th>
                                <th class="px-4 py-3 text-left font-medium">Status</th>
                                <th class="px-4 py-3 text-left font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in rows.data" :key="`${row.bucket}-${row.action_id}`" class="border-b">
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium">{{ row.student_name }}</p>
                                        <p class="text-muted-foreground text-xs">{{ row.student_code }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ row.campus?.name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <Badge variant="outline">{{ bucketLabel(row.bucket) }}</Badge>
                                </td>
                                <td class="px-4 py-3">{{ row.anchor_semester?.code ?? '—' }}</td>
                                <td class="px-4 py-3">{{ formatDate(row.anchor_start_date) }}</td>
                                <td class="px-4 py-3">
                                    <Badge v-if="row.bucket === 'overdue'" :variant="severityBadgeVariant(row.severity)">{{ daysLabel(row) }}</Badge>
                                    <span v-else>{{ daysLabel(row) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge variant="secondary">{{ row.student_status }}</Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <Button size="sm" variant="outline" @click="goToStudentLifecycle(row.student_pk)"> View lifecycle </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Separator class="my-4" />

                <DataPagination :pagination-data="rows" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
