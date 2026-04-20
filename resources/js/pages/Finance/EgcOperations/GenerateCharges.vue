<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, AlertTriangle, Play, RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';

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
    eligibility_status: 'eligible';
    current_level: number;
    total_levels: number;
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
    eligibility_status: 'ineligible' | 'warning';
    eligibility_reason: string;
    current_level: number | null;
    total_levels: number | null;
    already_charged_blocks: number;
}

interface PreviewData {
    eligible_students: EligibleStudent[];
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
    filters: { semester_id: string | null };
}>();

const selectedSemesterId = ref(props.filters.semester_id ?? String(props.currentSemester?.id ?? ''));
const blockCounts = ref<Record<number, number>>({});

function getBlockCount(student: EligibleStudent): number {
    return blockCounts.value[student.student_id] ?? student.max_chargeable_blocks;
}

function setBlockCount(studentId: number, count: number) {
    blockCounts.value[studentId] = count;
}

function onSemesterChange(val: string) {
    selectedSemesterId.value = val;
    router.visit(route('finance.egc.charges.index'), {
        data: { semester_id: val },
        preserveState: false,
    });
}

const form = useForm({
    semester_id: '',
    students: [] as { student_id: number; block_count: number; current_level: number }[],
});

function confirmGeneration() {
    form.semester_id = selectedSemesterId.value;
    form.students = props.preview.eligible_students.map((s) => ({
        student_id: s.student_id,
        block_count: getBlockCount(s),
        current_level: s.current_level,
    }));

    form.post(route('finance.egc.charges.store'));
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

function reasonLabel(reason: string): string {
    const labels: Record<string, string> = {
        already_fully_charged: 'Đã tạo đủ charge trong kỳ này',
        exceeded_max_level: 'Đã hoàn tất level tính phí',
        no_chargeable_blocks_remaining: 'Không còn block hợp lệ để tạo',
        missing_current_level: 'Thiếu dữ liệu current level',
        missing_total_levels: 'Thiếu dữ liệu total levels',
        currently_studying: 'Đang học level hiện tại — chưa hoàn tất',
    };
    return labels[reason] ?? reason;
}
</script>

<template>
    <Head title="EGC — Generate Charges" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">EGC Generate Charges</h1>
                <p class="text-muted-foreground text-sm">Preview và xác nhận EGC block charges cho kỳ học</p>
            </div>
        </div>

        <!-- Semester Selector -->
        <Card>
            <CardHeader>
                <CardTitle>Chọn Học Kỳ</CardTitle>
            </CardHeader>
            <CardContent>
                <Select :model-value="selectedSemesterId" @update:model-value="onSemesterChange">
                    <SelectTrigger class="w-64">
                        <SelectValue placeholder="Select semester" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="sem in semesters" :key="sem.id" :value="String(sem.id)">
                            {{ sem.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </CardContent>
        </Card>

        <template v-if="selectedSemesterId">
            <!-- Summary -->
            <div class="grid grid-cols-3 gap-4">
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold text-green-600">{{ preview.summary.eligible_count }}</div>
                        <p class="text-muted-foreground text-sm">Eligible — có thể tạo charge</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold text-slate-400">{{ preview.summary.ineligible_count }}</div>
                        <p class="text-muted-foreground text-sm">Ineligible — không tạo được</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="text-2xl font-bold text-amber-500">{{ preview.summary.warning_count }}</div>
                        <p class="text-muted-foreground text-sm">Warning — cần xử lý trước</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Warning Students -->
            <Card v-if="preview.warning_students.length > 0" class="border-amber-200 bg-amber-50">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-amber-700">
                        <AlertTriangle class="h-4 w-4" />
                        Warning — Thiếu dữ liệu ({{ preview.warning_students.length }} students)
                    </CardTitle>
                    <CardDescription class="text-amber-600">Các student này cần được cập nhật dữ liệu trước khi tạo charge.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student</TableHead>
                                <TableHead>Vấn đề</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="s in preview.warning_students" :key="s.student_id">
                                <TableCell>
                                    <div class="font-medium">{{ s.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ s.student_code }}</div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline" class="border-amber-300 text-amber-700">
                                        {{ reasonLabel(s.eligibility_reason) }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Eligible Students -->
            <Card v-if="preview.eligible_students.length > 0">
                <CardHeader>
                    <CardTitle>Eligible — Sẵn sàng tạo charge ({{ preview.eligible_students.length }} students)</CardTitle>
                    <CardDescription>Chọn số block cho từng student, sau đó xác nhận.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student</TableHead>
                                <TableHead>Level</TableHead>
                                <TableHead>Deferred</TableHead>
                                <TableHead>Block mới</TableHead>
                                <TableHead>Levels</TableHead>
                                <TableHead class="text-right">Tổng phí</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="student in preview.eligible_students" :key="student.student_id">
                                <TableCell>
                                    <div class="font-medium">{{ student.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ student.student_code }}</div>
                                </TableCell>
                                <TableCell>
                                    <span class="text-sm">{{ student.current_level }} / {{ student.total_levels }}</span>
                                </TableCell>
                                <TableCell>
                                    <div v-if="student.has_deferred_blocks" class="flex flex-wrap gap-1">
                                        <Badge v-for="d in student.deferred_blocks" :key="d.block_number" variant="outline">
                                            L{{ d.level_number }} (deferred)
                                        </Badge>
                                    </div>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </TableCell>
                                <TableCell>
                                    <Select
                                        :model-value="String(getBlockCount(student))"
                                        @update:model-value="(v) => setBlockCount(student.student_id, Number(v))"
                                    >
                                        <SelectTrigger class="w-20">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="n in student.max_chargeable_blocks"
                                                :key="n"
                                                :value="String(n)"
                                            >
                                                {{ n }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="(lvl, i) in student.chargeable_levels.slice(0, getBlockCount(student))"
                                            :key="i"
                                            :variant="lvl.is_retake ? 'destructive' : 'secondary'"
                                        >
                                            L{{ lvl.level_number }}
                                            <span v-if="lvl.is_retake" class="ml-1 text-xs">(Retake)</span>
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right font-mono text-sm">
                                    {{ formatCurrency(15_000_000 * getBlockCount(student)) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Ineligible Students -->
            <Card v-if="preview.ineligible_students.length > 0" class="border-slate-200">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-slate-500">
                        <AlertCircle class="h-4 w-4" />
                        Ineligible — Không tạo được charge ({{ preview.ineligible_students.length }} students)
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student</TableHead>
                                <TableHead>Level</TableHead>
                                <TableHead>Lý do</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="s in preview.ineligible_students" :key="s.student_id">
                                <TableCell>
                                    <div class="font-medium">{{ s.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ s.student_code }}</div>
                                </TableCell>
                                <TableCell>
                                    <span v-if="s.current_level" class="text-sm">{{ s.current_level }} / {{ s.total_levels }}</span>
                                    <span v-else class="text-muted-foreground text-xs">—</span>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="secondary" class="text-slate-600">
                                        {{ reasonLabel(s.eligibility_reason) }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <div v-if="preview.summary.total_count === 0" class="text-muted-foreground py-8 text-center text-sm">
                Không có EGC students trong hệ thống.
            </div>

            <!-- Confirm Button -->
            <div v-if="preview.eligible_students.length > 0" class="flex justify-end">
                <Button :disabled="form.processing" @click="confirmGeneration">
                    <Play v-if="!form.processing" class="mr-2 h-4 w-4" />
                    <RefreshCw v-else class="mr-2 h-4 w-4 animate-spin" />
                    {{ form.processing ? 'Đang tạo...' : `Xác nhận tạo charge (${preview.eligible_students.length} students)` }}
                </Button>
            </div>
        </template>
    </div>
</template>
