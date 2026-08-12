<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import InputError from '@/components/InputError.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { useTableFilters } from '@/composables/useFilters';
import type { PaginatedResponse } from '@/types';
import { studentRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Download, RefreshCw, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

type Verdict = 'clean' | 'still_failing' | 'not_finalized';
type ProposalState = 'none' | 'pending' | 'rejected' | 'approved_partial';
type BadgeColor = 'default' | 'secondary' | 'destructive' | 'outline' | 'success' | 'warning' | 'info' | 'purple' | 'indigo';

interface SemesterRef {
    id: number;
    code: string | null;
    name: string | null;
}

interface CourseAttendance {
    unit_code: string | null;
    unit_name: string | null;
    attendance_percentage: number;
    absent_count: number;
}

interface CourseRegistration {
    course_code: string | null;
    course_title: string | null;
}

interface WatchlistRow {
    adjustment_id: number;
    student: { id: number; student_code: string; full_name: string };
    target_semester: SemesterRef;
    target_semester_id: number;
    original_type: 'percentage' | 'fixed_amount';
    original_amount: number;
    adjusted_amount: number;
    effective_amount: number;
    proposal_state: ProposalState;
    latest_proposal_id: number | null;
    latest_restored_amount: number | null;
    academic_dossier_id: number | null;
    verdict: Verdict;
    evaluated_semester: SemesterRef;
    semester_gpa: number | null;
    cumulative_gpa: number | null;
    attendance_min_percentage: number | null;
    courses: CourseAttendance[];
    current_registrations: CourseRegistration[];
}

interface WatchlistFilters {
    search: string | null;
    semester_id: number | null;
    verdict: Verdict | null;
    proposal_state: ProposalState | null;
    per_page: number;
}

interface Props {
    rows: PaginatedResponse<WatchlistRow>;
    can: { propose: boolean; approve: boolean };
    filters: WatchlistFilters;
    options: {
        semesters: SemesterRef[];
        verdicts: Verdict[];
        proposal_states: ProposalState[];
    };
}

const props = defineProps<Props>();

const { filters, clearFilters, handlePaginationNavigate, handlePageSizeChange, updateField, updateFieldDebounced } = useTableFilters<WatchlistFilters>(
    studentRoutes.scholarshipRestorationWatchlist(),
    props.filters,
    ['rows', 'filters'],
);

const setOption = (field: keyof WatchlistFilters, value: string) => updateField(field, value === 'all' ? null : value);

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    if (filters.value.search) params.set('search', filters.value.search);
    if (filters.value.semester_id) params.set('semester_id', String(filters.value.semester_id));
    if (filters.value.verdict) params.set('verdict', filters.value.verdict);
    if (filters.value.proposal_state) params.set('proposal_state', filters.value.proposal_state);

    const query = params.toString();
    return `${studentRoutes.scholarshipRestorationWatchlistExport()}${query ? `?${query}` : ''}`;
});

const verdictLabel = (verdict: Verdict): string =>
    ({ clean: 'Sạch (Clean)', still_failing: 'Vẫn còn nợ môn', not_finalized: 'Chưa có kết quả' })[verdict];

const verdictBadgeVariant = (verdict: Verdict): BadgeColor =>
    ({ clean: 'success', still_failing: 'destructive', not_finalized: 'secondary' })[verdict] as BadgeColor;

const proposalStateLabel = (state: ProposalState): string =>
    ({ none: 'Chưa đề xuất', pending: 'Chờ duyệt', rejected: 'Đã từ chối', approved_partial: 'Đã khôi phục 1 phần' })[state];

const proposalStateBadgeVariant = (state: ProposalState): BadgeColor =>
    ({ none: 'outline', pending: 'warning', rejected: 'destructive', approved_partial: 'info' })[state] as BadgeColor;

const amountLabel = (row: WatchlistRow, amount: number): string => (row.original_type === 'percentage' ? `${amount}%` : `${amount.toLocaleString('vi-VN')}đ`);

// null = no attendance data at all (never render as 0%); a real 0% (all
// absent) is rendered as-is.
const attendanceLabel = (value: number | null): string => (value === null ? '—' : `${value.toFixed(0)}%`);

const expandedRowId = ref<number | null>(null);
const toggleExpanded = (adjustmentId: number) => {
    expandedRowId.value = expandedRowId.value === adjustmentId ? null : adjustmentId;
};

const goToStudentLifecycle = (studentId: number) => router.visit(studentRoutes.hub.lifecycle(studentId));
const openDossier = (dossierId: number) => router.visit(route('scholarship-adjustments.show', dossierId));

// The dossier list has no query-string prefill contract today (its "add
// manually" flow is a POST, not a prefillable create page) — this lands
// staff on the right screen with the student id in view, not a fully
// prefilled form.
// ponytail: no prefill support exists yet; add student_id query prefill
// to ScholarshipAdjustmentDossierController::index / its add-manually
// dialog if this becomes a real friction point.
const goToCreateAdjustment = (studentId: number) => router.visit(route('scholarship-adjustments.index', { student_id: studentId }));

// -----------------------------------------------------------------------
// Propose restore
// -----------------------------------------------------------------------

const proposeDialogRow = ref<WatchlistRow | null>(null);
const proposeForm = useForm({ reason: '', restored_amount: '' as string | number });

const openProposeDialog = (row: WatchlistRow) => {
    proposeDialogRow.value = row;
    proposeForm.reset();
    proposeForm.clearErrors();
};

// Client hint only — server FormRequest is the authority, never mirror the
// clamp math here beyond a display hint.
const proposeFloor = computed(() => proposeDialogRow.value?.effective_amount ?? 0);
const proposeCeiling = computed(() => proposeDialogRow.value?.original_amount ?? 0);

const submitPropose = () => {
    if (!proposeDialogRow.value) return;

    proposeForm
        .transform((data) => ({
            reason: data.reason,
            restored_amount: data.restored_amount === '' ? null : Number(data.restored_amount),
        }))
        .post(route('finance.scholarship-restorations.propose', proposeDialogRow.value.adjustment_id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Đã gửi đề xuất khôi phục.');
                proposeDialogRow.value = null;
            },
            onError: () => toast.error('Không thể gửi đề xuất — kiểm tra lỗi bên dưới.'),
        });
};

// -----------------------------------------------------------------------
// Approve / Reject
// -----------------------------------------------------------------------

const approveDialogRow = ref<WatchlistRow | null>(null);
const approveForm = useForm({});

const openApproveDialog = (row: WatchlistRow) => {
    approveDialogRow.value = row;
};

const submitApprove = () => {
    if (!approveDialogRow.value?.latest_proposal_id) return;

    approveForm.post(route('finance.scholarship-restorations.approve', approveDialogRow.value.latest_proposal_id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã duyệt khôi phục học bổng.');
            approveDialogRow.value = null;
        },
        onError: () => toast.error('Duyệt thất bại.'),
    });
};

const rejectDialogRow = ref<WatchlistRow | null>(null);
const rejectForm = useForm({ reason: '' });

const openRejectDialog = (row: WatchlistRow) => {
    rejectDialogRow.value = row;
    rejectForm.reset();
    rejectForm.clearErrors();
};

const submitReject = () => {
    if (!rejectDialogRow.value?.latest_proposal_id) return;

    rejectForm.post(route('finance.scholarship-restorations.reject', rejectDialogRow.value.latest_proposal_id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã từ chối đề xuất khôi phục.');
            rejectDialogRow.value = null;
        },
        onError: () => toast.error('Không thể từ chối đề xuất.'),
    });
};
</script>

<template>
    <Head title="Scholarship Restoration Watchlist" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Scholarship Restoration Watchlist</h1>
                <p class="text-muted-foreground">Students carrying a scholarship adjustment — evidence and restoration decisions in one place</p>
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

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-full space-y-2 sm:w-64">
                        <Label>Search Student</Label>
                        <div class="relative">
                            <Search class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                            <Input
                                :model-value="filters.search ?? ''"
                                @update:model-value="(val) => updateFieldDebounced('search', String(val))"
                                placeholder="Search by name or code..."
                                class="pl-8"
                            />
                        </div>
                    </div>

                    <div class="w-full space-y-2 sm:w-56">
                        <Label>Target Semester</Label>
                        <Select :model-value="filters.semester_id ? String(filters.semester_id) : 'all'" @update:model-value="(v) => setOption('semester_id', String(v))">
                            <SelectTrigger>
                                <SelectValue placeholder="All semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All semesters</SelectItem>
                                <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="String(sem.id)">
                                    {{ sem.code ?? sem.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="w-full space-y-2 sm:w-48">
                        <Label>Verdict</Label>
                        <Select :model-value="filters.verdict ?? 'all'" @update:model-value="(v) => setOption('verdict', String(v))">
                            <SelectTrigger>
                                <SelectValue placeholder="All verdicts" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All verdicts</SelectItem>
                                <SelectItem v-for="v in options.verdicts" :key="v" :value="v">{{ verdictLabel(v) }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="w-full space-y-2 sm:w-56">
                        <Label>Proposal Status</Label>
                        <Select :model-value="filters.proposal_state ?? 'all'" @update:model-value="(v) => setOption('proposal_state', String(v))">
                            <SelectTrigger>
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All statuses</SelectItem>
                                <SelectItem v-for="s in options.proposal_states" :key="s" :value="s">{{ proposalStateLabel(s) }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Button variant="outline" @click="clearFilters">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Reset
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div v-if="rows.data.length === 0" class="text-muted-foreground py-12 text-center">No carried adjustments for this campus.</div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-3 text-left font-medium">Student</th>
                                <th class="px-4 py-3 text-left font-medium">Target semester</th>
                                <th class="px-4 py-3 text-left font-medium">Original → Adjusted → Effective</th>
                                <th class="px-4 py-3 text-left font-medium">Verdict</th>
                                <th class="px-4 py-3 text-left font-medium">GPA (sem / cum)</th>
                                <th class="px-4 py-3 text-left font-medium">Attendance (min)</th>
                                <th class="px-4 py-3 text-left font-medium">Proposal</th>
                                <th class="px-4 py-3 text-left font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in rows.data" :key="row.adjustment_id">
                                <tr class="border-b align-top">
                                    <td class="px-4 py-3">
                                        <p class="font-medium">{{ row.student.full_name }}</p>
                                        <p class="text-muted-foreground text-xs">{{ row.student.student_code }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <p>{{ row.target_semester.code ?? row.target_semester.name }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ amountLabel(row, row.original_amount) }} → {{ amountLabel(row, row.adjusted_amount) }} → <span class="font-medium">{{ amountLabel(row, row.effective_amount) }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge :variant="verdictBadgeVariant(row.verdict)">{{ verdictLabel(row.verdict) }}</Badge>
                                        <p class="text-muted-foreground mt-1 text-xs">đánh giá tại kỳ {{ row.evaluated_semester.code ?? row.evaluated_semester.name }}</p>
                                    </td>
                                    <td class="px-4 py-3">{{ row.semester_gpa ?? '—' }} / {{ row.cumulative_gpa ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span>{{ attendanceLabel(row.attendance_min_percentage) }}</span>
                                            <Button
                                                v-if="row.courses.length > 0 || row.current_registrations.length > 0"
                                                variant="ghost"
                                                size="sm"
                                                class="h-6 w-6 p-0"
                                                @click="toggleExpanded(row.adjustment_id)"
                                            >
                                                <ChevronUp v-if="expandedRowId === row.adjustment_id" class="h-4 w-4" />
                                                <ChevronDown v-else class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge :variant="proposalStateBadgeVariant(row.proposal_state)">{{ proposalStateLabel(row.proposal_state) }}</Badge>
                                        <p v-if="row.latest_restored_amount !== null" class="text-muted-foreground mt-1 text-xs">
                                            {{ amountLabel(row, row.latest_restored_amount) }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1.5">
                                            <template v-if="row.proposal_state === 'pending'">
                                                <Button v-if="can.approve" size="sm" @click="openApproveDialog(row)">Duyệt</Button>
                                                <Button v-if="can.approve" size="sm" variant="outline" @click="openRejectDialog(row)">Từ chối</Button>
                                            </template>
                                            <Button v-else-if="can.propose" size="sm" variant="outline" @click="openProposeDialog(row)">Đề xuất khôi phục</Button>
                                            <Button size="sm" variant="ghost" @click="goToStudentLifecycle(row.student.id)">Hồ sơ SV</Button>
                                            <Button v-if="row.academic_dossier_id" size="sm" variant="ghost" @click="openDossier(row.academic_dossier_id)">Mở hồ sơ giảm</Button>
                                            <Button size="sm" variant="ghost" @click="goToCreateAdjustment(row.student.id)">Giảm tiếp</Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="expandedRowId === row.adjustment_id" class="bg-muted/30 border-b">
                                    <td colspan="8" class="space-y-4 px-4 py-3">
                                        <div v-if="row.courses.length > 0">
                                            <p class="mb-2 text-xs font-medium">Chi tiết điểm danh (kỳ {{ row.evaluated_semester.code ?? row.evaluated_semester.name }})</p>
                                            <table class="w-full text-xs">
                                                <thead>
                                                    <tr class="text-muted-foreground">
                                                        <th class="px-2 py-1 text-left">Môn học</th>
                                                        <th class="px-2 py-1 text-left">Tỷ lệ điểm danh</th>
                                                        <th class="px-2 py-1 text-left">Số buổi vắng</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(course, idx) in row.courses" :key="`${course.unit_code}-${idx}`">
                                                        <td class="px-2 py-1">{{ course.unit_code }} — {{ course.unit_name }}</td>
                                                        <td class="px-2 py-1">{{ attendanceLabel(course.attendance_percentage) }}</td>
                                                        <td class="px-2 py-1">{{ course.absent_count }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div v-if="row.current_registrations.length > 0">
                                            <p class="mb-2 text-xs font-medium">Môn đang đăng ký (kỳ {{ row.evaluated_semester.code ?? row.evaluated_semester.name }})</p>
                                            <ul class="grid grid-cols-2 gap-1 text-xs sm:grid-cols-3">
                                                <li v-for="(registration, idx) in row.current_registrations" :key="idx" class="text-muted-foreground">
                                                    {{ registration.course_code ?? '—' }} — {{ registration.course_title ?? '—' }}
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <Separator class="my-4" />

                <DataPagination :pagination-data="rows" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>

        <!-- Propose restore dialog -->
        <Dialog :open="proposeDialogRow !== null" @update:open="(open) => !open && (proposeDialogRow = null)">
            <DialogContent v-if="proposeDialogRow">
                <DialogHeader>
                    <DialogTitle>Đề xuất khôi phục học bổng</DialogTitle>
                    <DialogDescription>
                        {{ proposeDialogRow.student.full_name }} — mức hiện tại {{ amountLabel(proposeDialogRow, proposeDialogRow.effective_amount) }}, mức gốc
                        {{ amountLabel(proposeDialogRow, proposeDialogRow.original_amount) }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label>Lý do</Label>
                        <Textarea v-model="proposeForm.reason" rows="3" placeholder="Kết quả kỳ đánh giá đã sạch..." />
                        <InputError :message="proposeForm.errors.reason" />
                    </div>

                    <div class="grid gap-2">
                        <Label>Mức khôi phục (bỏ trống = khôi phục toàn bộ)</Label>
                        <Input v-model="proposeForm.restored_amount" type="number" step="0.01" :placeholder="`Giữa ${proposeFloor} và ${proposeCeiling}`" />
                        <p class="text-muted-foreground text-xs">Phải lớn hơn {{ proposeFloor }} và nhỏ hơn {{ proposeCeiling }} (giá trị hiển thị mang tính tham khảo — hệ thống xác nhận cuối cùng).</p>
                        <InputError :message="proposeForm.errors.restored_amount" />
                    </div>

                    <InputError :message="(proposeForm.errors as Record<string, string>).error" />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="proposeDialogRow = null">Hủy</Button>
                    <Button :disabled="proposeForm.processing" @click="submitPropose">Gửi đề xuất</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Approve confirm dialog -->
        <AlertDialog :open="approveDialogRow !== null" @update:open="(open) => !open && (approveDialogRow = null)">
            <AlertDialogContent v-if="approveDialogRow">
                <AlertDialogHeader>
                    <AlertDialogTitle>Duyệt khôi phục học bổng</AlertDialogTitle>
                    <AlertDialogDescription>
                        Duyệt đề xuất khôi phục cho {{ approveDialogRow.student.full_name }} lên mức
                        {{ amountLabel(approveDialogRow, approveDialogRow.latest_restored_amount ?? approveDialogRow.original_amount) }}? Hồ sơ giảm liên quan sẽ được đóng.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Hủy</AlertDialogCancel>
                    <AlertDialogAction :disabled="approveForm.processing" @click="submitApprove">Duyệt</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Reject dialog -->
        <Dialog :open="rejectDialogRow !== null" @update:open="(open) => !open && (rejectDialogRow = null)">
            <DialogContent v-if="rejectDialogRow">
                <DialogHeader>
                    <DialogTitle>Từ chối đề xuất khôi phục</DialogTitle>
                    <DialogDescription>{{ rejectDialogRow.student.full_name }}</DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label>Lý do từ chối</Label>
                        <Textarea v-model="rejectForm.reason" rows="3" />
                        <InputError :message="rejectForm.errors.reason" />
                    </div>
                    <InputError :message="(rejectForm.errors as Record<string, string>).error" />
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="rejectDialogRow = null">Hủy</Button>
                    <Button variant="destructive" :disabled="rejectForm.processing" @click="submitReject">Từ chối</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
