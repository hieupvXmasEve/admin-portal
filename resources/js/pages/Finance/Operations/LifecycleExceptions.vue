<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useDataTable } from '@/composables/useDataTable';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PaginatedResponse } from '@/types';
import { formatCurrency, type Semester } from '@/types/finance';
import { formatDateTime } from '@/utils/date';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRight, CheckCircle2, History } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface LinkedCharge {
    id: number;
    charge_type: string;
    status: string;
    amount: number;
}

interface LifecycleExceptionRow {
    id: number;
    item_id: string;
    fee_type: string;
    amount: number;
    dng_status: string;
    due_date: string | null;
    days_overdue: number;
    due_status: string;
    last_reminder_at: string | null;
    student: {
        id: number;
        student_code: string;
        full_name: string;
        status: string;
        status_label: string;
        status_color: string;
    } | null;
    finance_charge_id: number | null;
    linked_charges: LinkedCharge[];
    payment_id: number | null;
    has_bridged_payment: boolean;
    exception_reason: string;
    exception_reason_label: string;
    recommended_action: string;
    review_status: string;
    review_status_label: string;
    resolution_action: string | null;
    resolution_reason: string | null;
    resolved_at: string | null;
    available_actions: string[];
    blocking_reasons: string[];
    latest_student_action: {
        id: number;
        action_type: string;
        created_at: string;
        notes: string | null;
    } | null;
    defer_case: {
        id: number;
        fee_policy: string | null;
        scope_type: string | null;
        created_at: string;
    } | null;
}

interface Summary {
    deferred_count: number;
    dropout_count: number;
    transfer_count: number;
    other_count: number;
    total_count: number;
    total_amount: number;
}

interface Filters {
    semester_id: number | null;
    lifecycle_status: string | null;
    review_status: string | null;
    due_status: string | null;
    fee_type: string | null;
    search: string;
    per_page: number;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
}

interface Props {
    exceptions: PaginatedResponse<LifecycleExceptionRow>;
    summary: Summary;
    semesters: Semester[];
    current_semester: Semester | null;
    filters?: Partial<Filters>;
    permissions: {
        can_cancel_dng: boolean;
        can_void_charges: boolean;
    };
}

const props = defineProps<Props>();
const { selectedLabel } = useFinanceSemester();

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
    baseUrl: route('finance.operations.lifecycle-exceptions'),
    initialFilters: {
        lifecycle_status: stringFilter(filterProps.lifecycle_status),
        review_status: stringFilter(filterProps.review_status) ?? 'open',
        due_status: stringFilter(filterProps.due_status) ?? 'all',
        fee_type: stringFilter(filterProps.fee_type),
        search: stringFilter(filterProps.search) ?? '',
        per_page: numberFilter(filterProps.per_page) ?? 20,
        sort: stringFilter(filterProps.sort),
        direction: directionFilter(filterProps.direction),
    },
    defaultValues: {
        per_page: 20,
        review_status: 'open',
        due_status: 'all',
        search: '',
        lifecycle_status: null,
        fee_type: null,
        sort: null,
        direction: null,
    },
    only: ['exceptions', 'filters', 'summary'],
    immediateFields: ['lifecycle_status', 'review_status', 'due_status'],
});

const selectedRow = ref<LifecycleExceptionRow | null>(null);
const resolveDialogOpen = ref(false);
const resolveAction = ref<string>('acknowledge');

const resolveForm = useForm({
    resolution_action: 'acknowledge',
    reason: '',
});

const actionLabels: Record<string, string> = {
    acknowledge: 'Ghi nhận',
    keep_as_debt: 'Giữ nợ',
    route_to_settlement: 'Chuyển quyết toán',
    cancel_dng: 'Hủy DNG',
    cancel_dng_and_void_linked_charge: 'Hủy DNG và void phí liên kết',
};

const destructiveActions = new Set(['cancel_dng', 'cancel_dng_and_void_linked_charge']);

const selectedImpactPreview = computed(() => {
    if (!selectedRow.value) return null;

    return {
        linked_charges: selectedRow.value.linked_charges,
        blocking_reasons: selectedRow.value.blocking_reasons,
        has_bridged_payment: selectedRow.value.has_bridged_payment,
        amount: selectedRow.value.amount,
    };
});

const openResolveDialog = (row: LifecycleExceptionRow, action: string) => {
    selectedRow.value = row;
    resolveAction.value = action;
    resolveForm.resolution_action = action;
    resolveForm.reason = '';
    resolveDialogOpen.value = true;
};

const submitResolve = () => {
    if (!selectedRow.value) return;

    resolveForm.post(route('finance.operations.lifecycle-exceptions.resolve', selectedRow.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            resolveDialogOpen.value = false;
            selectedRow.value = null;
        },
    });
};

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

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="Lifecycle Exceptions" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Lifecycle Exceptions</h1>
                <p class="text-muted-foreground mt-1">Rà soát DNG đến hạn của sinh viên ngoài trạng thái thu phí bình thường · {{ selectedLabel }}</p>
            </div>
            <div class="flex items-center gap-3">
                <Link :href="route('finance.operations.due-calendar')">
                    <Button variant="outline">DNG Due Reminders</Button>
                </Link>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Bảo lưu</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ summary.deferred_count }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Thôi học</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ summary.dropout_count }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Chuyển trường</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ summary.transfer_count }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Khác</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ summary.other_count }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Tổng số tiền</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-orange-600">{{ formatCurrency(summary.total_amount) }}</div>
                    <p class="text-muted-foreground text-xs">{{ summary.total_count }} mục</p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardContent class="pt-6">
                <div class="flex flex-wrap items-center gap-3">
                    <DebouncedInput :model-value="tableFilters.search" placeholder="Tìm mã SV, tên, DNG id, item id..." class="w-[280px]" @update:model-value="handleSearch" />
                    <Select :model-value="tableFilters.lifecycle_status ?? 'all'" @update:model-value="(value) => setFilter('lifecycle_status', value === 'all' ? null : String(value))">
                        <SelectTrigger class="w-[220px]">
                            <SelectValue placeholder="Lý do lifecycle" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả lý do</SelectItem>
                            <SelectItem value="deferred">Bảo lưu</SelectItem>
                            <SelectItem value="dropout">Thôi học</SelectItem>
                            <SelectItem value="dropout_transfer">Chuyển trường</SelectItem>
                            <SelectItem value="inactive_or_non_financial">Không thu phí</SelectItem>
                            <SelectItem value="missing_student">Thiếu SV</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.review_status ?? 'open'" @update:model-value="(value) => setFilter('review_status', value === 'all' ? null : String(value))">
                        <SelectTrigger class="w-[200px]">
                            <SelectValue placeholder="Trạng thái xử lý" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="open">Chờ xử lý</SelectItem>
                            <SelectItem value="acknowledged">Đã xem xét</SelectItem>
                            <SelectItem value="kept_as_debt">Giữ nợ</SelectItem>
                            <SelectItem value="routed_to_settlement">Chuyển quyết toán</SelectItem>
                            <SelectItem value="resolved">Đã xử lý xong</SelectItem>
                            <SelectItem value="all">Tất cả</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select :model-value="tableFilters.due_status ?? 'all'" @update:model-value="(value) => setFilter('due_status', value === 'all' ? null : String(value))">
                        <SelectTrigger class="w-[180px]">
                            <SelectValue placeholder="Hạn thanh toán" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả hạn</SelectItem>
                            <SelectItem value="upcoming">Sắp đến hạn</SelectItem>
                            <SelectItem value="due_today">Đến hạn hôm nay</SelectItem>
                            <SelectItem value="overdue">Quá hạn</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button v-if="hasActiveFilters" variant="ghost" :disabled="isLoading" @click="clearAllFilters"> Xóa bộ lọc </Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>
                    Hàng đợi ngoại lệ lifecycle
                    <Badge variant="outline" class="ml-2">{{ exceptions.total || 0 }}</Badge>
                </CardTitle>
                <CardDescription> Các mục này bị loại khỏi DNG Due Reminders và không nhận nhắc nợ tự động. </CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>DNG</TableHead>
                            <TableHead>Sinh viên</TableHead>
                            <TableHead>Lifecycle</TableHead>
                            <TableHead class="text-right">Số tiền</TableHead>
                            <TableHead>Hạn</TableHead>
                            <TableHead>Lý do</TableHead>
                            <TableHead>Xử lý</TableHead>
                            <TableHead class="text-right">Thao tác</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-if="exceptions.data.length === 0">
                            <TableCell colspan="8" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <CheckCircle2 class="text-muted-foreground h-12 w-12" />
                                    <p class="text-muted-foreground">Không có ngoại lệ lifecycle nào phù hợp</p>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-for="row in exceptions.data" :key="row.id">
                            <TableCell>
                                <div class="font-medium">DNG-{{ row.id }}</div>
                                <div class="text-muted-foreground text-xs">{{ row.item_id }}</div>
                            </TableCell>
                            <TableCell>
                                <div v-if="row.student">
                                    <div class="font-medium">{{ row.student.full_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ row.student.student_code }}</div>
                                </div>
                                <span v-else class="text-muted-foreground text-xs">Thiếu sinh viên</span>
                            </TableCell>
                            <TableCell>
                                <Badge v-if="row.student" variant="outline" :class="getStudentStatusClass(row.student.status_color)">
                                    {{ row.student.status_label }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right font-medium">{{ formatCurrency(row.amount) }}</TableCell>
                            <TableCell>
                                <div class="text-sm">{{ row.due_date ? new Date(row.due_date).toLocaleDateString('vi-VN') : '—' }}</div>
                                <div v-if="row.days_overdue > 0" class="text-xs text-red-600">Quá hạn {{ row.days_overdue }} ngày</div>
                            </TableCell>
                            <TableCell>
                                <Badge variant="secondary">{{ row.exception_reason_label }}</Badge>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline">{{ row.review_status_label }}</Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button variant="ghost" size="sm" @click="selectedRow = row"> Chi tiết </Button>
                                    <Link :href="route('finance.operations.lifecycle-exception-history', { dng_payment_request_id: row.id })">
                                        <Button variant="ghost" size="sm">
                                            <History class="mr-1 h-4 w-4" />
                                            History
                                        </Button>
                                    </Link>
                                    <Link :href="route('finance.dng.payment-requests.show', row.id)">
                                        <Button variant="ghost" size="sm">
                                            <ArrowRight class="h-4 w-4" />
                                        </Button>
                                    </Link>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <DataPagination :pagination-data="exceptions" :page-size="tableFilters.per_page" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
    </div>

    <Sheet
        :open="selectedRow !== null"
        @update:open="
            (open) => {
                if (!open) selectedRow = null;
            }
        "
    >
        <SheetContent class="w-full overflow-y-auto px-4 sm:max-w-xl">
            <SheetHeader v-if="selectedRow">
                <SheetTitle>DNG-{{ selectedRow.id }}</SheetTitle>
                <SheetDescription>{{ selectedRow.recommended_action }}</SheetDescription>
            </SheetHeader>

            <div v-if="selectedRow" class="mt-6 space-y-6">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Lý do ngoại lệ</span>
                        <span class="font-medium">{{ selectedRow.exception_reason_label }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Trạng thái xử lý</span>
                        <span class="font-medium">{{ selectedRow.review_status_label }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Số tiền</span>
                        <span class="font-medium">{{ formatCurrency(selectedRow.amount) }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-muted-foreground">Nhắc nợ gần nhất</span>
                        <span>{{ selectedRow.last_reminder_at ? formatDateTime(selectedRow.last_reminder_at) : 'Chưa gửi' }}</span>
                    </div>
                </div>

                <div v-if="selectedRow.linked_charges.length > 0" class="space-y-2">
                    <h3 class="font-medium">Phí liên kết</h3>
                    <div v-for="charge in selectedRow.linked_charges" :key="charge.id" class="rounded-md border p-3 text-sm">
                        <div class="font-medium">#{{ charge.id }} · {{ charge.charge_type }}</div>
                        <div class="text-muted-foreground">{{ charge.status }} · {{ formatCurrency(charge.amount) }}</div>
                    </div>
                </div>

                <div v-if="selectedRow.defer_case" class="space-y-2">
                    <h3 class="font-medium">Defer case</h3>
                    <div class="rounded-md border p-3 text-sm">#{{ selectedRow.defer_case.id }} · {{ selectedRow.defer_case.fee_policy || selectedRow.defer_case.scope_type || '—' }}</div>
                </div>

                <div v-if="selectedRow.latest_student_action" class="space-y-2">
                    <h3 class="font-medium">Student action gần nhất</h3>
                    <div class="rounded-md border p-3 text-sm">
                        <div>{{ selectedRow.latest_student_action.action_type }}</div>
                        <div class="text-muted-foreground">{{ selectedRow.latest_student_action.notes || '—' }}</div>
                    </div>
                </div>

                <div v-if="selectedRow.blocking_reasons.length > 0" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <div class="mb-1 flex items-center gap-2 font-medium">
                        <AlertTriangle class="h-4 w-4" />
                        Không thể hủy
                    </div>
                    <ul class="list-disc pl-5">
                        <li v-for="reason in selectedRow.blocking_reasons" :key="reason">{{ reason }}</li>
                    </ul>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link :href="route('finance.operations.lifecycle-exception-history', { dng_payment_request_id: selectedRow.id })">
                        <Button variant="outline" size="sm">
                            <History class="mr-1 h-4 w-4" />
                            View history
                        </Button>
                    </Link>
                    <Button v-for="action in selectedRow.available_actions" :key="action" :variant="destructiveActions.has(action) ? 'destructive' : 'outline'" size="sm" @click="openResolveDialog(selectedRow, action)">
                        {{ actionLabels[action] ?? action }}
                    </Button>
                    <Link v-if="selectedRow.student" :href="route('finance.operations.settlement.index', { search: selectedRow.student.student_code })">
                        <Button variant="secondary" size="sm">Mở quyết toán</Button>
                    </Link>
                </div>
            </div>
        </SheetContent>
    </Sheet>

    <Dialog v-model:open="resolveDialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ actionLabels[resolveAction] ?? 'Xử lý ngoại lệ' }}</DialogTitle>
                <DialogDescription> Ghi lý do xử lý trước khi lưu quyết định. Các thao tác hủy DNG không thể hoàn tác tự động. </DialogDescription>
            </DialogHeader>

            <div v-if="destructiveActions.has(resolveAction) && selectedImpactPreview" class="space-y-3 rounded-md border p-3 text-sm">
                <div class="font-medium">Ảnh hưởng dự kiến</div>
                <div>Số tiền DNG: {{ formatCurrency(selectedImpactPreview.amount) }}</div>
                <div v-if="selectedImpactPreview.has_bridged_payment" class="text-amber-700">DNG đã có payment bridge.</div>
                <div v-if="selectedImpactPreview.linked_charges.length > 0">
                    <div class="mb-1 font-medium">Phí liên kết</div>
                    <ul class="list-disc pl-5">
                        <li v-for="charge in selectedImpactPreview.linked_charges" :key="charge.id">#{{ charge.id }} · {{ charge.charge_type }} · {{ charge.status }}</li>
                    </ul>
                </div>
                <div v-if="selectedImpactPreview.blocking_reasons.length > 0" class="text-red-700">
                    {{ selectedImpactPreview.blocking_reasons.join(' ') }}
                </div>
            </div>

            <div class="space-y-2">
                <Label for="resolution-reason">Lý do</Label>
                <Textarea id="resolution-reason" v-model="resolveForm.reason" rows="4" placeholder="Mô tả quyết định xử lý..." />
            </div>

            <DialogFooter>
                <Button variant="outline" @click="resolveDialogOpen = false">Hủy</Button>
                <Button :variant="destructiveActions.has(resolveAction) ? 'destructive' : 'default'" :disabled="resolveForm.processing || resolveForm.reason.trim().length < 3" @click="submitResolve"> Xác nhận </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
