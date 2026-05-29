<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { Head, useForm } from '@inertiajs/vue3';
import { AlertCircle, AlertTriangle, Play, RefreshCw, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface EligibleStudent {
    student_id: number;
    student_name: string;
    student_code: string;
    student_email: string | null;
    eligibility_status: 'eligible';
    term_number: number;
    chargeable_term_index: number | null;
    amount: number;
    scholarship_name: string | null;
    scholarship_type: 'percentage' | 'fixed' | null;
    scholarship_raw_value: number | null;
    scholarship_amount: number;
    voucher_codes: string[];
    voucher_amount: number;
    net_amount: number;
}

interface NonEligibleStudent {
    student_id: number;
    student_name: string;
    student_code: string;
    student_email: string | null;
    eligibility_status: 'ineligible' | 'warning';
    eligibility_reason: string;
    term_number: number | null;
    amount: number | null;
    existing_charge_amount: number | null;
}

interface EligibleStudentPagination {
    data: EligibleStudent[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
    prev_page_url?: string | null;
    next_page_url?: string | null;
    links?: Array<{ url: string | null; label: string; active: boolean }>;
}

interface PreviewData {
    eligible_students: EligibleStudentPagination;
    ineligible_students: NonEligibleStudent[];
    warning_students: NonEligibleStudent[];
    summary: {
        eligible_count: number;
        ineligible_count: number;
        warning_count: number;
        total_count: number;
    };
}

interface Semester {
    id: number;
    name: string;
}

const props = defineProps<{
    preview: PreviewData;
    semesters: Semester[];
    currentSemester: Semester | null;
    filters: { semester_id: string | null; search: string; ignore_student_ids: string; per_page: number; page: number };
}>();

const { filters, handleSearch, handlePaginationNavigate, handlePageSizeChange } = useInertiaFilters({
    baseUrl: route('finance.major.charges.index'),
    initialFilters: {
        semester_id: props.filters.semester_id ?? String(props.currentSemester?.id ?? ''),
        search: props.filters.search ?? '',
        ignore_student_ids: props.filters.ignore_student_ids ?? '',
        per_page: props.filters.per_page ?? 20,
        page: props.filters.page ?? 1,
    },
    defaultValues: {
        search: '',
        ignore_student_ids: '',
        per_page: 20,
        page: 1,
    },
    only: ['preview', 'filters', 'currentSemester'],
});

const eligibleStudents = computed(() => props.preview.eligible_students.data ?? []);

const totalAmount = computed(() =>
    eligibleStudents.value.reduce((sum, student) => sum + (student.amount ?? 0), 0),
);

const totalScholarship = computed(() =>
    eligibleStudents.value.reduce((sum, student) => sum + (student.scholarship_amount ?? 0), 0),
);

const totalVoucher = computed(() =>
    eligibleStudents.value.reduce((sum, student) => sum + (student.voucher_amount ?? 0), 0),
);

const totalNet = computed(() =>
    eligibleStudents.value.reduce((sum, student) => sum + (student.net_amount ?? student.amount ?? 0), 0),
);

function handleSemesterChange(value: string) {
    filters.semester_id = value;
    filters.page = 1;
}

function handleSearchChange(value: string | number) {
    handleSearch(value);
    filters.page = 1;
}

function handleIgnoreStudentIdsChange(value: string | number) {
    filters.ignore_student_ids = String(value);
    filters.page = 1;
}

const dueDate = ref('');

const form = useForm({
    semester_id: '',
    due_date: '',
    search: '',
    ignore_student_ids: '',
});

function confirmGeneration() {
    form.semester_id = filters.semester_id;
    form.due_date = dueDate.value;
    form.search = filters.search;
    form.ignore_student_ids = filters.ignore_student_ids;

    form.post(route('finance.major.charges.store'));
}

function formatCurrency(amount: number | null): string {
    if (amount === null) return '—';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

function reasonLabel(reason: string): string {
    const labels: Record<string, string> = {
        already_charged: 'Kỳ này đã có học phí HP — không tạo lại',
        tuition_not_due_this_semester: 'Chưa tới kỳ phát sinh HP (trước intake_major)',
        zero_amount_term: 'Kỳ này không phát sinh HP (amount = 0)',
        missing_curriculum_version: 'Thiếu curriculum_version',
        missing_intake_semester: 'Thiếu intake_semester',
        missing_intake_major: 'Thiếu intake_major',
        missing_tuition_plan_term: 'Chưa cấu hình TuitionPlanTerm cho kỳ này',
        cannot_resolve_term_number: 'Không xác định được term number',
    };

    return labels[reason] ?? reason;
}

function rowNumber(index: number): number {
    return (props.preview.eligible_students.from ?? 1) + index;
}
</script>

<template>
    <Head title="Major — Generate HP" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Major — Generate Học phí HP</h1>
                <p class="text-muted-foreground text-sm">
                    Phát sinh HP theo kỳ cho sinh viên <code>intake_course</code>. Nếu kỳ đã có charge HP active thì không tạo lại.
                </p>
            </div>
        </div>

        <Card>
            <CardContent class="flex flex-wrap gap-4 pt-4">
                <Select :model-value="filters.semester_id" @update:model-value="handleSemesterChange">
                    <SelectTrigger class="w-52">
                        <SelectValue placeholder="Select semester" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="sem in semesters" :key="sem.id" :value="String(sem.id)">
                            {{ sem.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <div class="relative min-w-72 flex-1">
                    <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                    <Input
                        :model-value="filters.search"
                        class="pl-8"
                        placeholder="Lọc theo tên, student_id, email"
                        @update:model-value="handleSearchChange"
                    />
                </div>

                <Textarea
                    :model-value="filters.ignore_student_ids"
                    class="min-h-[108px] min-w-72 flex-1"
                    placeholder="Ignore student_id, mỗi dòng một mã&#10;SE000001&#10;SE000002"
                    rows="4"
                    @update:model-value="handleIgnoreStudentIdsChange"
                />
            </CardContent>
        </Card>

        <template v-if="filters.semester_id">
            <div class="grid grid-cols-3 gap-4">
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold text-green-600">{{ preview.summary.eligible_count }}</div>
                        <p class="text-muted-foreground text-sm">Eligible theo filter hiện tại</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold text-slate-400">{{ preview.summary.ineligible_count }}</div>
                        <p class="text-muted-foreground text-sm">Ineligible (bao gồm SV đã có HP kỳ này)</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold text-amber-500">{{ preview.summary.warning_count }}</div>
                        <p class="text-muted-foreground text-sm">Warning — thiếu dữ liệu</p>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="preview.summary.eligible_count > 0" class="border-emerald-200 bg-emerald-50/50">
                <CardContent class="flex flex-wrap items-end justify-between gap-4">
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-emerald-900">
                            Sẵn sàng tạo HP cho {{ preview.summary.eligible_count }} sinh viên
                        </p>
                        <div class="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 text-xs">
                            <span>HP gốc (trang này): <strong class="font-mono">{{ formatCurrency(totalAmount) }}</strong></span>
                            <span>Scholarship: <strong class="font-mono text-blue-700">-{{ formatCurrency(totalScholarship) }}</strong></span>
                            <span>Voucher: <strong class="font-mono text-fuchsia-700">-{{ formatCurrency(totalVoucher) }}</strong></span>
                            <span>Còn phải trả: <strong class="font-mono text-emerald-700">{{ formatCurrency(totalNet) }}</strong></span>
                        </div>
                    </div>
                    <div class="flex items-end gap-4">
                        <div class="space-y-1.5">
                            <Label class="text-sm font-medium">Hạn thanh toán <span class="text-red-500">*</span></Label>
                            <DatePicker v-model="dueDate" placeholder="Chọn hạn thanh toán" class="w-52" />
                            <p v-if="form.errors.due_date" class="text-destructive text-xs">{{ form.errors.due_date }}</p>
                        </div>
                        <Button :disabled="form.processing || !dueDate" @click="confirmGeneration">
                            <Play v-if="!form.processing" class="mr-2 h-4 w-4" />
                            <RefreshCw v-else class="mr-2 h-4 w-4 animate-spin" />
                            {{ form.processing ? 'Đang tạo...' : `Xác nhận tạo HP (${preview.summary.eligible_count} students)` }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="preview.warning_students.length > 0" class="border-amber-200 bg-amber-50">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-amber-700">
                        <AlertTriangle class="h-4 w-4" />
                        Warning — Không tạo được HP
                    </CardTitle>
                    <CardDescription class="text-amber-600">
                        Các SV này thiếu dữ liệu (curriculum/intake/tuition plan). Xử lý dữ liệu trước khi tạo.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Lý do</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="student in preview.warning_students" :key="student.student_id">
                                <TableCell>
                                    <div class="font-medium">{{ student.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ student.student_code }}</div>
                                </TableCell>
                                <TableCell class="text-sm">{{ student.student_email || '—' }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline" class="border-amber-300 text-amber-700">
                                        {{ reasonLabel(student.eligibility_reason) }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Eligible — Sẵn sàng tạo HP</CardTitle>
                    <CardDescription>
                        {{ preview.summary.eligible_count }} sinh viên sẽ được tạo charge HP cho kỳ này. Tổng dự kiến: {{ formatCurrency(totalAmount) }} (trang hiện tại).
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>No</TableHead>
                                <TableHead>Student</TableHead>
                                <TableHead>Term</TableHead>
                                <TableHead class="text-right">HP gốc</TableHead>
                                <TableHead>Scholarship</TableHead>
                                <TableHead class="text-right">Giảm</TableHead>
                                <TableHead>Voucher</TableHead>
                                <TableHead class="text-right">Giảm voucher</TableHead>
                                <TableHead class="text-right">Còn phải trả</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(student, index) in eligibleStudents" :key="student.student_id">
                                <TableCell class="w-16 text-sm text-slate-500">{{ rowNumber(index) }}</TableCell>
                                <TableCell>
                                    <div class="font-medium">{{ student.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ student.student_code }}</div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1.5">
                                        <Badge variant="secondary">Term {{ student.term_number }}</Badge>
                                        <Badge v-if="student.chargeable_term_index" variant="outline" class="text-xs">
                                            Installment {{ student.chargeable_term_index }}
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right font-mono text-sm">
                                    {{ formatCurrency(student.amount) }}
                                </TableCell>
                                <TableCell>
                                    <div v-if="student.scholarship_name" class="space-y-0.5">
                                        <Badge variant="outline" class="border-blue-300 text-blue-700">
                                            {{ student.scholarship_name }}
                                        </Badge>
                                        <div class="text-muted-foreground text-xs">
                                            {{
                                                student.scholarship_type === 'percentage'
                                                    ? `${student.scholarship_raw_value}%`
                                                    : 'Fixed'
                                            }}
                                        </div>
                                    </div>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </TableCell>
                                <TableCell class="text-right font-mono text-sm" :class="student.scholarship_amount > 0 ? 'text-blue-700' : 'text-muted-foreground'">
                                    {{ student.scholarship_amount > 0 ? `-${formatCurrency(student.scholarship_amount)}` : '—' }}
                                </TableCell>
                                <TableCell>
                                    <div v-if="student.voucher_codes.length > 0" class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="code in student.voucher_codes"
                                            :key="code"
                                            variant="outline"
                                            class="border-fuchsia-300 text-fuchsia-700"
                                        >
                                            {{ code }}
                                        </Badge>
                                    </div>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </TableCell>
                                <TableCell class="text-right font-mono text-sm" :class="student.voucher_amount > 0 ? 'text-fuchsia-700' : 'text-muted-foreground'">
                                    {{ student.voucher_amount > 0 ? `-${formatCurrency(student.voucher_amount)}` : '—' }}
                                </TableCell>
                                <TableCell class="text-right font-mono text-sm font-semibold text-emerald-700">
                                    {{ formatCurrency(student.net_amount) }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="eligibleStudents.length === 0">
                                <TableCell colspan="9" class="text-muted-foreground py-8 text-center text-sm">
                                    Không có sinh viên eligible theo filter hiện tại.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <DataPagination
                        v-if="preview.eligible_students.last_page > 1"
                        :pagination-data="preview.eligible_students"
                        :page-size-options="[20, 50, 100]"
                        item-name="students"
                        class="mt-4"
                        @navigate="handlePaginationNavigate"
                        @page-size-change="handlePageSizeChange"
                    />
                </CardContent>
            </Card>

            <Card v-if="preview.ineligible_students.length > 0" class="border-slate-200">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-slate-500">
                        <AlertCircle class="h-4 w-4" />
                        Ineligible — Không tạo được HP ({{ preview.ineligible_students.length }})
                    </CardTitle>
                    <CardDescription class="text-xs">Cuộn để xem toàn bộ danh sách.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="max-h-96 overflow-y-auto rounded-md border">
                        <Table>
                            <TableHeader class="bg-background sticky top-0 z-10">
                                <TableRow>
                                    <TableHead>Student</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>HP hiện có</TableHead>
                                    <TableHead>Lý do</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="student in preview.ineligible_students" :key="student.student_id">
                                    <TableCell>
                                        <div class="font-medium">{{ student.student_name }}</div>
                                        <div class="text-muted-foreground text-xs">{{ student.student_code }}</div>
                                    </TableCell>
                                    <TableCell class="text-sm">{{ student.student_email || '—' }}</TableCell>
                                    <TableCell class="font-mono text-sm">
                                        {{ formatCurrency(student.existing_charge_amount) }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary" class="text-slate-600">
                                            {{ reasonLabel(student.eligibility_reason) }}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <div v-if="preview.summary.total_count === 0" class="text-muted-foreground py-8 text-center text-sm">
                Không có sinh viên <code>intake_course</code> theo điều kiện hiện tại.
            </div>
        </template>
    </div>
</template>
