<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDataTable } from '@/composables/useDataTable';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResponse } from '@/types';
import { formatCurrency } from '@/types/finance';
import { formatDateTime } from '@/utils/date';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Clock, History, UserRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface FilterOption {
    value: string | number;
    label: string;
}

interface HistoryEvent {
    id: number;
    event_type: string;
    event_type_label: string;
    performed_at: string;
    actor: { id: number; name: string } | null;
    from_status: string | null;
    to_status: string | null;
    resolution_action: string | null;
    resolution_action_label: string | null;
    resolution_reason: string | null;
    dng_status_before: string | null;
    dng_status_after: string | null;
    failure_summary: string | null;
    impact_preview: Record<string, unknown> | null;
    is_legacy_backfill: boolean;
    dng_request: {
        id: number;
        item_id: string;
        status: string;
        amount: number;
        fee_type: string;
        due_date: string | null;
    } | null;
    student: {
        id: number;
        student_code: string;
        full_name: string;
        status: string;
        status_label: string;
        status_color: string;
    } | null;
    current_review: {
        status: string;
        status_label: string;
        exception_reason: string;
        exception_reason_label: string;
    } | null;
    links: {
        dng_payment_request: string | null;
        student_charges: string | null;
        settlement: string | null;
        lifecycle_exceptions: string;
        history: string | null;
    };
}

interface Summary {
    total_events: number;
    failed_actions: number;
    destructive_attempts: number;
    resolved_actions: number;
}

interface Filters {
    dng_payment_request_id: number | null;
    dng_item_id: string | null;
    student_id: number | null;
    search: string;
    event_type: string | null;
    review_status: string | null;
    exception_reason: string | null;
    performed_by_user_id: number | null;
    performed_from: string | null;
    performed_to: string | null;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
}

interface Props {
    history_events: PaginatedResponse<HistoryEvent>;
    summary: Summary;
    filters?: Partial<Filters>;
    filter_options: {
        event_types: FilterOption[];
        review_statuses: FilterOption[];
        exception_reasons: FilterOption[];
        actors: FilterOption[];
    };
    selected_timeline: HistoryEvent[];
    links: {
        lifecycle_exceptions: string;
    };
}

const props = defineProps<Props>();

const filterProps = props.filters && !Array.isArray(props.filters) ? props.filters : {};
const stringFilter = (value: unknown): string | null => (typeof value === 'string' && value !== '' ? value : null);
const numberFilter = (value: unknown): number | null => {
    if (typeof value === 'number') return value;
    if (typeof value === 'string' && value !== '' && !Number.isNaN(Number(value))) return Number(value);

    return null;
};
const directionFilter = (value: unknown): 'asc' | 'desc' | null => (value === 'asc' || value === 'desc' ? value : null);

const {
    filters: tableFilters,
    hasActiveFilters,
    isLoading,
    handleSearch,
    handlePaginationNavigate,
    handlePageSizeChange,
    setFilter,
    clearAllFilters,
} = useDataTable<Filters>({
    baseUrl: route('finance.operations.lifecycle-exception-history'),
    initialFilters: {
        dng_payment_request_id: numberFilter(filterProps.dng_payment_request_id),
        dng_item_id: stringFilter(filterProps.dng_item_id),
        student_id: numberFilter(filterProps.student_id),
        search: stringFilter(filterProps.search) ?? '',
        event_type: stringFilter(filterProps.event_type),
        review_status: stringFilter(filterProps.review_status),
        exception_reason: stringFilter(filterProps.exception_reason),
        performed_by_user_id: numberFilter(filterProps.performed_by_user_id),
        performed_from: stringFilter(filterProps.performed_from),
        performed_to: stringFilter(filterProps.performed_to),
        per_page: numberFilter(filterProps.per_page) ?? 20,
        sort: stringFilter(filterProps.sort),
        direction: directionFilter(filterProps.direction),
    },
    defaultValues: {
        per_page: 20,
        search: '',
        dng_payment_request_id: null,
        dng_item_id: null,
        student_id: null,
        event_type: null,
        review_status: null,
        exception_reason: null,
        performed_by_user_id: null,
        performed_from: null,
        performed_to: null,
        sort: 'performed_at',
        direction: 'desc',
    },
    only: ['history_events', 'filters', 'summary', 'selected_timeline'],
    immediateFields: ['event_type', 'review_status', 'exception_reason', 'performed_by_user_id', 'per_page'],
});

const selectedEvent = ref<HistoryEvent | null>(null);

const showTimelinePanel = computed(() => props.selected_timeline.length > 0 && tableFilters.dng_payment_request_id !== null);

const getStudentStatusClass = (color: string) => {
    const map: Record<string, string> = {
        red: 'bg-red-50 text-red-700 border-red-200',
        blue: 'bg-blue-50 text-blue-700 border-blue-200',
        yellow: 'bg-yellow-50 text-yellow-700 border-yellow-200',
        indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        green: 'bg-green-50 text-green-700 border-green-200',
        orange: 'bg-orange-50 text-orange-700 border-orange-200',
        gray: 'bg-slate-50 text-slate-700 border-slate-200',
    };

    return map[color] ?? map.gray;
};

const isFailureEvent = (eventType: string) => eventType.endsWith('_failed');

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="Lifecycle Exception History" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <History class="text-muted-foreground h-8 w-8" />
                    <h1 class="text-3xl font-bold tracking-tight">Lifecycle History</h1>
                </div>
                <p class="text-muted-foreground mt-1">Nhật ký quyết định xử lý ngoại lệ lifecycle trên toàn bộ DNG requests.</p>
            </div>
            <Link :href="links.lifecycle_exceptions">
                <Button variant="outline">Về Lifecycle Exceptions</Button>
            </Link>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Tổng sự kiện</CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-bold">{{ summary.total_events }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Hành động thất bại</CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-bold text-red-600">{{ summary.failed_actions }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Thử hủy / void</CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-bold">{{ summary.destructive_attempts }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Đã xử lý / giải quyết</CardTitle>
                </CardHeader>
                <CardContent class="text-2xl font-bold text-green-700">{{ summary.resolved_actions }}</CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Bộ lọc</CardTitle>
                <CardDescription>Tìm theo sinh viên, DNG request, loại hành động, trạng thái review và khoảng thời gian.</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <DebouncedInput v-model="tableFilters.search" placeholder="Mã SV, tên, DNG id, item id..." @update:model-value="handleSearch" />
                <Select :model-value="tableFilters.event_type ?? 'all'" @update:model-value="(value) => setFilter('event_type', value === 'all' ? null : String(value))">
                    <SelectTrigger>
                        <SelectValue placeholder="Loại hành động" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Tất cả hành động</SelectItem>
                        <SelectItem v-for="option in filter_options.event_types" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select :model-value="tableFilters.review_status ?? 'all'" @update:model-value="(value) => setFilter('review_status', value === 'all' ? null : String(value))">
                    <SelectTrigger>
                        <SelectValue placeholder="Trạng thái review" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Tất cả trạng thái</SelectItem>
                        <SelectItem v-for="option in filter_options.review_statuses" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select :model-value="tableFilters.exception_reason ?? 'all'" @update:model-value="(value) => setFilter('exception_reason', value === 'all' ? null : String(value))">
                    <SelectTrigger>
                        <SelectValue placeholder="Lý do ngoại lệ" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Tất cả lý do</SelectItem>
                        <SelectItem v-for="option in filter_options.exception_reasons" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select :model-value="tableFilters.performed_by_user_id ? String(tableFilters.performed_by_user_id) : 'all'" @update:model-value="(value) => setFilter('performed_by_user_id', value === 'all' ? null : Number(value))">
                    <SelectTrigger>
                        <SelectValue placeholder="Người thực hiện" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Tất cả người thực hiện</SelectItem>
                        <SelectItem v-for="option in filter_options.actors" :key="option.value" :value="String(option.value)">{{ option.label }}</SelectItem>
                    </SelectContent>
                </Select>
                <DatePicker :model-value="tableFilters.performed_from" placeholder="Từ ngày" @update:model-value="(value) => setFilter('performed_from', value || null)" />
                <DatePicker :model-value="tableFilters.performed_to" placeholder="Đến ngày" @update:model-value="(value) => setFilter('performed_to', value || null)" />
                <div class="flex items-end gap-2">
                    <Button v-if="hasActiveFilters" variant="outline" :disabled="isLoading" @click="clearAllFilters">Xóa bộ lọc</Button>
                </div>
            </CardContent>
        </Card>

        <Card v-if="showTimelinePanel">
            <CardHeader>
                <CardTitle>Timeline DNG-{{ tableFilters.dng_payment_request_id }}</CardTitle>
                <CardDescription>Toàn bộ sự kiện cho DNG request đang được lọc.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div v-for="event in selected_timeline" :key="`timeline-${event.id}`" class="rounded-lg border p-3 text-sm" :class="isFailureEvent(event.event_type) ? 'border-red-200 bg-red-50/40' : ''">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-medium">{{ event.event_type_label }}</span>
                        <span class="text-muted-foreground text-xs">{{ formatDateTime(event.performed_at) }}</span>
                    </div>
                    <p v-if="event.resolution_reason" class="mt-2 break-words whitespace-pre-wrap">{{ event.resolution_reason }}</p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Lịch sử hành động</CardTitle>
                <CardDescription>Mới nhất trước. Mỗi hành động là một bản ghi bất biến.</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="history_events.data.length === 0" class="flex flex-col items-center gap-2 py-12 text-center">
                    <CheckCircle2 class="text-muted-foreground h-12 w-12" />
                    <p class="text-muted-foreground">Không có sự kiện nào khớp bộ lọc hiện tại.</p>
                    <p class="text-muted-foreground text-sm">Các review trước khi bật event capture có thể không có lịch sử chi tiết.</p>
                </div>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Thời gian</TableHead>
                            <TableHead>Hành động</TableHead>
                            <TableHead>Người thực hiện</TableHead>
                            <TableHead>Sinh viên</TableHead>
                            <TableHead>DNG</TableHead>
                            <TableHead>Review</TableHead>
                            <TableHead>Lý do</TableHead>
                            <TableHead class="text-right">Chi tiết</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="event in history_events.data" :key="event.id" :class="isFailureEvent(event.event_type) ? 'bg-red-50/30' : ''">
                            <TableCell class="text-sm whitespace-nowrap">{{ formatDateTime(event.performed_at) }}</TableCell>
                            <TableCell>
                                <div class="font-medium">{{ event.event_type_label }}</div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <Badge v-if="event.is_legacy_backfill" variant="outline">Legacy</Badge>
                                    <Badge v-if="isFailureEvent(event.event_type)" variant="destructive">Failed</Badge>
                                </div>
                            </TableCell>
                            <TableCell>{{ event.actor?.name ?? '—' }}</TableCell>
                            <TableCell>
                                <template v-if="event.student">
                                    <div class="font-medium">{{ event.student.full_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ event.student.student_code }}</div>
                                </template>
                                <span v-else class="text-muted-foreground text-xs">—</span>
                            </TableCell>
                            <TableCell>
                                <template v-if="event.dng_request">
                                    <div class="font-medium">DNG-{{ event.dng_request.id }}</div>
                                    <div class="text-muted-foreground text-xs">{{ event.dng_request.item_id }}</div>
                                </template>
                            </TableCell>
                            <TableCell>
                                <div v-if="event.from_status || event.to_status" class="text-xs">{{ event.from_status || '—' }} → {{ event.to_status || '—' }}</div>
                                <div v-if="event.current_review" class="text-muted-foreground text-xs">{{ event.current_review.exception_reason_label }}</div>
                            </TableCell>
                            <TableCell class="max-w-xs">
                                <p class="line-clamp-2 text-sm break-words">{{ event.resolution_reason || '—' }}</p>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button variant="ghost" size="sm" @click="selectedEvent = event">Chi tiết</Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <DataPagination :pagination-data="history_events" :page-size="tableFilters.per_page" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>

    <Sheet
        :open="selectedEvent !== null"
        @update:open="
            (open) => {
                if (!open) selectedEvent = null;
            }
        "
    >
        <SheetContent class="w-full overflow-y-auto px-4 sm:max-w-xl">
            <SheetHeader v-if="selectedEvent">
                <SheetTitle>{{ selectedEvent.event_type_label }}</SheetTitle>
                <SheetDescription>{{ formatDateTime(selectedEvent.performed_at) }}</SheetDescription>
            </SheetHeader>

            <div v-if="selectedEvent" class="mt-6 space-y-6 text-sm">
                <div class="space-y-2">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Người thực hiện</span>
                        <span class="font-medium">{{ selectedEvent.actor?.name ?? '—' }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Review transition</span>
                        <span>{{ selectedEvent.from_status || '—' }} → {{ selectedEvent.to_status || '—' }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">DNG transition</span>
                        <span>{{ selectedEvent.dng_status_before || '—' }} → {{ selectedEvent.dng_status_after || '—' }}</span>
                    </div>
                </div>

                <div v-if="selectedEvent.student" class="space-y-2">
                    <h3 class="flex items-center gap-2 font-medium">
                        <UserRound class="h-4 w-4" />
                        Student
                    </h3>
                    <div class="rounded-md border p-3">
                        <div class="font-medium">{{ selectedEvent.student.full_name }}</div>
                        <div class="text-muted-foreground">{{ selectedEvent.student.student_code }}</div>
                        <Badge variant="outline" :class="getStudentStatusClass(selectedEvent.student.status_color)">
                            {{ selectedEvent.student.status_label }}
                        </Badge>
                    </div>
                </div>

                <div v-if="selectedEvent.dng_request" class="space-y-2">
                    <h3 class="font-medium">DNG request</h3>
                    <div class="rounded-md border p-3">
                        <div class="font-medium">DNG-{{ selectedEvent.dng_request.id }} · {{ selectedEvent.dng_request.item_id }}</div>
                        <div class="text-muted-foreground">{{ selectedEvent.dng_request.status }} · {{ formatCurrency(selectedEvent.dng_request.amount) }}</div>
                    </div>
                </div>

                <p v-if="selectedEvent.resolution_reason" class="break-words whitespace-pre-wrap">{{ selectedEvent.resolution_reason }}</p>

                <div v-if="selectedEvent.failure_summary" class="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-red-800">
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    <span class="break-words">{{ selectedEvent.failure_summary }}</span>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link v-if="selectedEvent.links.dng_payment_request" :href="selectedEvent.links.dng_payment_request">
                        <Button variant="outline" size="sm">DNG detail</Button>
                    </Link>
                    <Link v-if="selectedEvent.links.student_charges" :href="selectedEvent.links.student_charges">
                        <Button variant="outline" size="sm">Student charges</Button>
                    </Link>
                    <Link v-if="selectedEvent.links.settlement" :href="selectedEvent.links.settlement">
                        <Button variant="outline" size="sm">Settlement</Button>
                    </Link>
                    <Link v-if="selectedEvent.links.history" :href="selectedEvent.links.history">
                        <Button variant="secondary" size="sm">
                            <Clock class="mr-1 h-4 w-4" />
                            Full timeline
                        </Button>
                    </Link>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>
