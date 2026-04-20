<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DataPagination from '@/components/DataPagination.vue';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { Head, useForm } from '@inertiajs/vue3';
import { RefreshCw, Search } from 'lucide-vue-next';

interface EgcBlock {
    id: number;
    student: { id: number; full_name: string; student_id: string };
    block_number: number;
    level_number: number;
    result: 'pending' | 'pass' | 'fail';
    attendance_rate: string | null;
    is_retake: boolean;
    synced_at: string | null;
}

interface Semester {
    id: number;
    name: string;
}

interface EgcBlockPagination {
    data: EgcBlock[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
    prev_page_url?: string | null;
    next_page_url?: string | null;
    links?: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

const props = defineProps<{
    blocks: EgcBlockPagination | EgcBlock[];
    semesters: Semester[];
    currentSemester: Semester | null;
    filters: { semester_id: string | null; search: string; result: string; per_page?: number; page?: number };
}>();

const { filters, handleSearch, handlePaginationNavigate, handlePageSizeChange } = useInertiaFilters({
    baseUrl: route('finance.egc.block-results.index'),
    initialFilters: {
        semester_id: props.filters.semester_id ?? String(props.currentSemester?.id ?? ''),
        search: props.filters.search ?? '',
        result: props.filters.result ?? 'all',
        per_page: props.filters.per_page ?? 50,
        page: props.filters.page ?? 1,
    },
    defaultValues: {
        semester_id: String(props.currentSemester?.id ?? ''),
        search: '',
        result: 'all',
        per_page: 50,
        page: 1,
    },
    only: ['blocks', 'filters'],
});

const syncForm = useForm({ semester_id: '' });

function handleSemesterChange(value: string) {
    filters.semester_id = value;
    filters.page = 1;
}

function handleResultChange(value: string) {
    filters.result = value;
    filters.page = 1;
}

function handleSearchChange(value: string | number) {
    handleSearch(value);
    filters.page = 1;
}

function syncResults() {
    syncForm.semester_id = filters.semester_id;
    syncForm.post(route('finance.egc.block-results.sync'));
}

function resultBadgeVariant(result: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (result === 'pass') return 'default';
    if (result === 'fail') return 'destructive';
    return 'secondary';
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('vi-VN');
}
</script>

<template>
    <Head title="EGC — Block Results" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">EGC Block Results</h1>
                <p class="text-muted-foreground text-sm">View and sync block results from academic records</p>
            </div>
            <Button
                :disabled="!filters.semester_id || syncForm.processing"
                variant="outline"
                @click="syncResults"
            >
                <RefreshCw :class="['mr-2 h-4 w-4', syncForm.processing && 'animate-spin']" />
                {{ syncForm.processing ? 'Syncing...' : 'Sync Results' }}
            </Button>
        </div>

        <!-- Filters -->
        <Card>
            <CardContent class="flex flex-wrap gap-4 pt-4">
                <Select :model-value="filters.semester_id" @update:model-value="handleSemesterChange">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Select semester" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="sem in semesters" :key="sem.id" :value="String(sem.id)">
                            {{ sem.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="filters.result" @update:model-value="handleResultChange">
                    <SelectTrigger class="w-36">
                        <SelectValue placeholder="Result" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Results</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="pass">Pass</SelectItem>
                        <SelectItem value="fail">Fail</SelectItem>
                    </SelectContent>
                </Select>

                <div class="relative flex-1">
                    <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                    <Input :model-value="filters.search" class="pl-8" placeholder="Search student..." @update:model-value="handleSearchChange" />
                </div>
            </CardContent>
        </Card>

        <!-- Results Table -->
        <Card>
            <CardHeader>
                <CardTitle>Block Results</CardTitle>
                <CardDescription v-if="'total' in blocks">{{ blocks.total }} blocks</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Student</TableHead>
                            <TableHead>Block</TableHead>
                            <TableHead>Level</TableHead>
                            <TableHead>Result</TableHead>
                            <TableHead>Attendance</TableHead>
                            <TableHead>Retake</TableHead>
                            <TableHead>Synced At</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="block in ('data' in blocks ? blocks.data : blocks)"
                            :key="block.id"
                        >
                            <TableCell>
                                <div class="font-medium">{{ block.student?.full_name }}</div>
                                <div class="text-muted-foreground text-xs">{{ block.student?.student_id }}</div>
                            </TableCell>
                            <TableCell>Block {{ block.block_number }}</TableCell>
                            <TableCell>Level {{ block.level_number }}</TableCell>
                            <TableCell>
                                <Badge :variant="resultBadgeVariant(block.result)">
                                    {{ block.result.charAt(0).toUpperCase() + block.result.slice(1) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                {{ block.attendance_rate != null ? `${block.attendance_rate}%` : '—' }}
                            </TableCell>
                            <TableCell>
                                <Badge v-if="block.is_retake" variant="outline">Retake</Badge>
                                <span v-else class="text-muted-foreground">—</span>
                            </TableCell>
                            <TableCell class="text-muted-foreground text-sm">{{ formatDate(block.synced_at) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <DataPagination
                    v-if="'last_page' in blocks && blocks.last_page > 1"
                    :pagination-data="blocks"
                    :page-size-options="[20, 50, 100]"
                    item-name="blocks"
                    class="mt-4"
                    @navigate="handlePaginationNavigate"
                    @page-size-change="handlePageSizeChange"
                />
            </CardContent>
        </Card>
    </div>
</template>
