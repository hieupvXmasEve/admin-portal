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

interface ChargeableLevel {
    level_number: number;
    is_retake: boolean;
    amount: number;
}

interface DeferredBlock {
    block_number: number;
    level_number: number;
}

interface EligibleStudent {
    student_id: number;
    student_name: string;
    student_code: string;
    student_email: string | null;
    eligibility_status: 'eligible';
    current_level: number;
    total_levels: number;
    is_studying: boolean;
    already_charged_blocks: number;
    has_deferred_blocks: boolean;
    max_chargeable_blocks: number;
    deferred_blocks: DeferredBlock[];
    chargeable_levels: ChargeableLevel[];
}

interface NonEligibleStudent {
    student_id: number;
    student_name: string;
    student_code: string;
    student_email: string | null;
    eligibility_status: 'ineligible' | 'warning';
    eligibility_reason: string;
    current_level: number | null;
    total_levels: number | null;
    already_charged_blocks: number;
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
    baseUrl: route('finance.egc.charges.index'),
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

const blockCounts = ref<Record<number, number>>({});

const eligibleStudents = computed(() => props.preview.eligible_students.data ?? []);

function getBlockCount(student: EligibleStudent): number {
    return blockCounts.value[student.student_id] ?? student.max_chargeable_blocks;
}

function setBlockCount(studentId: number, count: number) {
    blockCounts.value[studentId] = count;
}

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
    students: [] as { student_id: number; block_count: number; current_level: number }[],
});

function confirmGeneration() {
    form.semester_id = filters.semester_id;
    form.due_date = dueDate.value;
    form.search = filters.search;
    form.ignore_student_ids = filters.ignore_student_ids;
    form.students = eligibleStudents.value.map((student) => ({
        student_id: student.student_id,
        block_count: getBlockCount(student),
        current_level: student.current_level,
    }));

    form.post(route('finance.egc.charges.store'));
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

function reasonLabel(reason: string): string {
    const labels: Record<string, string> = {
        already_fully_charged: 'Đã tạo đủ charge trong kỳ này',
        exceeded_max_level: 'Đã tới total level, không được tạo charge mới',
        studying_last_level: 'Đang học level cuối, không còn level tiếp theo để tạo phí dự kiến',
        no_chargeable_blocks_remaining: 'Không còn block hợp lệ để tạo',
        missing_current_level: 'Thiếu dữ liệu current level',
        missing_total_levels: 'Thiếu dữ liệu total levels',
    };

    return labels[reason] ?? reason;
}

function rowNumber(index: number): number {
    return (props.preview.eligible_students.from ?? 1) + index;
}
</script>

<template>
    <Head title="EGC — Generate Charges" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">EGC Generate Charges</h1>
                <p class="text-muted-foreground text-sm">Filter trên backend, preview theo điều kiện hiện tại, rồi tạo charge theo đúng tập lọc.</p>
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
                        <p class="text-muted-foreground text-sm">Ineligible</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold text-amber-500">{{ preview.summary.warning_count }}</div>
                        <p class="text-muted-foreground text-sm">Warning</p>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="preview.warning_students.length > 0" class="border-amber-200 bg-amber-50">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-amber-700">
                        <AlertTriangle class="h-4 w-4" />
                        Warning — Không cho tạo charge
                    </CardTitle>
                    <CardDescription class="text-amber-600">Các student này bị loại khỏi batch tạo charge. Có hiển thị lý do để xử lý trước.</CardDescription>
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
                    <CardTitle>Eligible — Sẵn sàng tạo charge</CardTitle>
                    <CardDescription>
                        {{ preview.summary.eligible_count }} student hợp lệ theo filter hiện tại. Nút xác nhận sẽ tạo cho toàn bộ tập lọc hợp lệ, không chỉ trang đang xem.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>No</TableHead>
                                <TableHead>Student</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Level</TableHead>
                                <TableHead>Deferred</TableHead>
                                <TableHead>Block mới</TableHead>
                                <TableHead>Levels</TableHead>
                                <TableHead class="text-right">Tổng phí</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(student, index) in eligibleStudents" :key="student.student_id">
                                <TableCell class="w-16 text-sm text-slate-500">{{ rowNumber(index) }}</TableCell>
                                <TableCell>
                                    <div class="font-medium">{{ student.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ student.student_code }}</div>
                                </TableCell>
                                <TableCell class="text-sm">{{ student.student_email || '—' }}</TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-sm">{{ student.current_level }} / {{ student.total_levels }}</span>
                                        <Badge v-if="student.is_studying" variant="outline" class="border-blue-300 text-blue-600 text-xs">
                                            Dự kiến
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div v-if="student.has_deferred_blocks" class="flex flex-wrap gap-1">
                                        <Badge v-for="block in student.deferred_blocks" :key="block.block_number" variant="outline">
                                            L{{ block.level_number }} (deferred)
                                        </Badge>
                                    </div>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </TableCell>
                                <TableCell>
                                    <Select
                                        :model-value="String(getBlockCount(student))"
                                        @update:model-value="(value) => setBlockCount(student.student_id, Number(value))"
                                    >
                                        <SelectTrigger class="w-20">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="count in student.max_chargeable_blocks" :key="count" :value="String(count)">
                                                {{ count }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="level in student.chargeable_levels.slice(0, getBlockCount(student))"
                                            :key="level.level_number"
                                            :variant="level.is_retake ? 'destructive' : 'secondary'"
                                        >
                                            L{{ level.level_number }}
                                            <span v-if="level.is_retake" class="ml-1 text-xs">(Retake)</span>
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right font-mono text-sm">
                                    {{ formatCurrency(15_000_000 * getBlockCount(student)) }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="eligibleStudents.length === 0">
                                <TableCell colspan="8" class="text-muted-foreground py-8 text-center text-sm">
                                    Không có student eligible theo filter hiện tại.
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
                        Ineligible — Không tạo được charge
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Level</TableHead>
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
                                <TableCell>
                                    <span v-if="student.current_level !== null" class="text-sm">{{ student.current_level }} / {{ student.total_levels }}</span>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="secondary" class="text-slate-600">
                                        {{ reasonLabel(student.eligibility_reason) }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <div v-if="preview.summary.total_count === 0" class="text-muted-foreground py-8 text-center text-sm">
                Không có EGC students theo điều kiện hiện tại.
            </div>

            <div v-if="preview.summary.eligible_count > 0" class="flex items-end justify-end gap-4">
                <div class="space-y-1.5">
                    <Label class="text-sm font-medium">Hạn thanh toán <span class="text-red-500">*</span></Label>
                    <DatePicker v-model="dueDate" placeholder="Chọn hạn thanh toán" class="w-52" />
                    <p v-if="form.errors.due_date" class="text-destructive text-xs">{{ form.errors.due_date }}</p>
                </div>
                <Button :disabled="form.processing || !dueDate" @click="confirmGeneration">
                    <Play v-if="!form.processing" class="mr-2 h-4 w-4" />
                    <RefreshCw v-else class="mr-2 h-4 w-4 animate-spin" />
                    {{ form.processing ? 'Đang tạo...' : `Xác nhận tạo charge (${preview.summary.eligible_count} students)` }}
                </Button>
            </div>
        </template>
    </div>
</template>
