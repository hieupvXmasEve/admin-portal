<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Info, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const FAILURE_REASON_LABELS: Record<string, string> = {
    grade_failed: 'Fail điểm',
    attendance_failed: 'Fail chuyên cần',
    both_failed: 'Fail điểm + chuyên cần',
    manual_failed: 'Fail thủ công',
};

const failureReasonLabel = (reason: string | null): string => (reason ? (FAILURE_REASON_LABELS[reason] ?? reason) : '—');

interface AcademicRecordEntry {
    id: number;
    unit_id: number;
    final_percentage: string | null;
    failure_reason: string | null;
    failure_reason_snapshot: { attendance_pct: number | null } | null;
}

interface EligibleEntry {
    student: { id: number; full_name: string; student_id: string; campus_id: number };
    failed_record: AcademicRecordEntry;
    unit: { id: number; code: string; name: string };
}

interface BlockedEntry {
    student: { id: number; full_name: string; student_id: string; campus_id: number };
    failed_record: AcademicRecordEntry;
    unit: { id: number; code: string; name: string };
    reason_code: string;
    reason_label: string;
}

interface Props {
    eligible_students: EligibleEntry[];
    total_eligible: number;
    blocked_students: BlockedEntry[];
    total_blocked: number;
    filters?: Record<string, unknown>;
    semesters: { id: number; name: string; code: string }[];
    current_semester_id: number | null;
    campuses: { id: number; name: string; code: string }[];
}

const props = defineProps<Props>();

const entryKey = (entry: { student: { id: number }; failed_record: { id: number } }): string => `${entry.student.id}-${entry.failed_record.id}`;

const listFilterSemesterId = computed<number | null>(() => {
    const filterSemesterId = Number(props.filters?.semester_id);

    return Number.isInteger(filterSemesterId) ? filterSemesterId : (props.current_semester_id ?? null);
});

const changeListFilterSemester = (value: string | undefined): void => {
    router.get(
        route('academic.exam-resit.create'),
        { ...props.filters, semester_id: value ? Number(value) : undefined },
        { preserveState: false, preserveScroll: true },
    );
};

const defaultSemesterId = (): number | null => {
    const filterSemesterId = Number(props.filters?.semester_id);
    if (Number.isInteger(filterSemesterId) && props.semesters.some((s) => s.id === filterSemesterId)) {
        return filterSemesterId;
    }

    return props.current_semester_id ?? props.semesters[0]?.id ?? null;
};

const form = useForm({
    selected_keys: [] as string[],
    items: [] as { student_id: number; academic_record_id: number; campus_id: number }[],
    operation_semester_id: defaultSemesterId() as number | null,
    charge_semester_id: defaultSemesterId() as number | null,
    notes: '',
});

const selectedEntries = computed(() => props.eligible_students.filter((entry) => form.selected_keys.includes(entryKey(entry))));

const isSelected = (entry: EligibleEntry): boolean => form.selected_keys.includes(entryKey(entry));

const toggleEntry = (entry: EligibleEntry): void => {
    const key = entryKey(entry);
    form.selected_keys = isSelected(entry) ? form.selected_keys.filter((k) => k !== key) : [...form.selected_keys, key];
};

const allSelected = computed(() => props.eligible_students.length > 0 && props.eligible_students.every((entry) => isSelected(entry)));

const toggleSelectAll = (): void => {
    form.selected_keys = allSelected.value ? [] : props.eligible_students.map(entryKey);
};

const canSubmit = computed(() => selectedEntries.value.length > 0 && !!form.operation_semester_id && !!form.charge_semester_id);

const submit = (): void => {
    form.transform((data) => ({
        items: selectedEntries.value.map((entry) => ({
            student_id: entry.student.id,
            academic_record_id: entry.failed_record.id,
            campus_id: entry.student.campus_id,
        })),
        operation_semester_id: data.operation_semester_id,
        charge_semester_id: data.charge_semester_id,
        notes: data.notes,
    })).post(route('academic.exam-resit.store-bulk'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Đăng ký thi lại - Tạo mới" />
    <div class="mb-6 flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="router.visit(route('academic.exam-resit.index'))">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Đăng ký thi lại</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Chọn sinh viên trượt (mọi lý do) để đăng ký thi lại — có thể chọn nhiều để đăng ký cùng lúc.</p>
        </div>
    </div>

    <div class="bg-card mb-6 flex items-center gap-3 rounded-lg border p-4">
        <label class="text-sm leading-none font-medium whitespace-nowrap">Học kỳ trượt</label>
        <Select :model-value="listFilterSemesterId?.toString()" @update:model-value="(v) => changeListFilterSemester(v as string | undefined)">
            <SelectTrigger class="w-64"><SelectValue placeholder="Chọn học kỳ..." /></SelectTrigger>
            <SelectContent>
                <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">{{ semester.name }}</SelectItem>
            </SelectContent>
        </Select>
        <span class="text-muted-foreground text-xs">Danh sách chỉ lấy bản ghi trượt phát sinh trong học kỳ này.</span>
    </div>

    <Alert class="mb-6">
        <Info class="h-4 w-4" />
        <AlertTitle>Logic hiển thị hiện tại</AlertTitle>
        <AlertDescription>
            Danh sách bên dưới gồm mọi sinh viên có bản ghi trượt đã chốt điểm trong học kỳ đã chọn ở trên (fail điểm, chuyên cần, cả hai, hoặc thủ công) — không giới hạn theo lý
            do trượt. Staff tự xem lý do trượt và điểm chuyên cần để quyết định đăng ký. Chỉ loại các bản ghi trùng lặp thật sự: đã pass môn ở bản ghi khác, hoặc đã có đăng ký thi
            lại đang xử lý/hoàn tất — các bản ghi này nằm ở bảng "Bản ghi trùng lặp / đã xử lý" phía dưới.
        </AlertDescription>
    </Alert>

    <div class="bg-card mb-6 flex items-center gap-3 rounded-lg border p-4">
        <Users class="text-muted-foreground h-4 w-4" />
        <span class="text-muted-foreground text-sm">Tổng bản ghi đủ điều kiện thi lại:</span>
        <span class="text-lg font-semibold">{{ total_eligible.toLocaleString('vi-VN') }}</span>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between gap-4 space-y-0">
                <div>
                    <CardTitle>Bản ghi đủ điều kiện</CardTitle>
                    <CardDescription>Mọi sinh viên trượt đã chốt điểm — trừ bản ghi trùng lặp.</CardDescription>
                </div>
                <Button v-if="eligible_students.length > 0" type="button" variant="outline" size="sm" @click="toggleSelectAll">
                    {{ allSelected ? 'Bỏ chọn tất cả' : 'Chọn tất cả' }}
                </Button>
            </CardHeader>
            <CardContent>
                <div v-if="eligible_students.length === 0" class="text-muted-foreground py-8 text-center">Không tìm thấy bản ghi đủ điều kiện.</div>
                <div v-else class="max-h-[500px] space-y-2 overflow-y-auto">
                    <div
                        v-for="(entry, index) in eligible_students"
                        :key="entryKey(entry)"
                        class="hover:bg-muted/50 flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors"
                        :class="{ 'border-primary bg-primary/5 ring-1 ring-primary/30': isSelected(entry) }"
                        @click="toggleEntry(entry)"
                    >
                        <span class="text-muted-foreground mt-1 w-5 shrink-0 text-right font-mono text-xs">{{ index + 1 }}</span>
                        <Checkbox :model-value="isSelected(entry)" class="mt-1" @click.stop="toggleEntry(entry)" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-medium">{{ entry.student.full_name }}</div>
                                    <div class="text-muted-foreground font-mono text-xs">{{ entry.student.student_id }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-mono text-sm font-medium">{{ entry.unit.code }}</div>
                                    <div v-if="entry.failed_record.final_percentage !== null" class="text-muted-foreground text-xs">Điểm: {{ Number(entry.failed_record.final_percentage).toFixed(1) }}</div>
                                    <div v-if="entry.failed_record.failure_reason_snapshot?.attendance_pct !== null && entry.failed_record.failure_reason_snapshot?.attendance_pct !== undefined" class="text-muted-foreground text-xs">
                                        Chuyên cần: {{ Number(entry.failed_record.failure_reason_snapshot.attendance_pct).toFixed(1) }}%
                                    </div>
                                </div>
                            </div>
                            <div class="mt-1 flex items-center justify-between gap-2">
                                <div class="text-muted-foreground truncate text-xs">{{ entry.unit.name }}</div>
                                <span class="bg-muted shrink-0 rounded px-1.5 py-0.5 text-xs whitespace-nowrap">{{ failureReasonLabel(entry.failed_record.failure_reason) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Thông tin đăng ký</CardTitle>
                <CardDescription v-if="selectedEntries.length === 0">Chọn một hoặc nhiều bản ghi từ danh sách bên trái.</CardDescription>
                <CardDescription v-else>Đăng ký thi lại cho {{ selectedEntries.length }} sinh viên đã chọn.</CardDescription>
            </CardHeader>
            <CardContent>
                <form v-if="selectedEntries.length > 0" @submit.prevent="submit" class="space-y-4">
                    <div class="max-h-[180px] space-y-1 overflow-y-auto rounded-lg border p-2">
                        <div v-for="entry in selectedEntries" :key="entryKey(entry)" class="flex items-center justify-between text-sm">
                            <span class="truncate">{{ entry.student.full_name }} <span class="text-muted-foreground font-mono text-xs">({{ entry.student.student_id }})</span></span>
                            <span class="text-muted-foreground font-mono text-xs">{{ entry.unit.code }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="text-sm leading-none font-medium">Học kỳ vận hành <span class="text-destructive">*</span></label>
                        <Select :model-value="form.operation_semester_id?.toString() ?? undefined" @update:model-value="(v) => (form.operation_semester_id = v ? Number(v) : null)">
                            <SelectTrigger><SelectValue placeholder="Chọn học kỳ..." /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">{{ semester.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.operation_semester_id" class="text-destructive mt-1 text-xs">{{ form.errors.operation_semester_id }}</p>
                    </div>

                    <div>
                        <label class="text-sm leading-none font-medium">Học kỳ thu phí <span class="text-destructive">*</span></label>
                        <Select :model-value="form.charge_semester_id?.toString() ?? undefined" @update:model-value="(v) => (form.charge_semester_id = v ? Number(v) : null)">
                            <SelectTrigger><SelectValue placeholder="Chọn học kỳ..." /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">{{ semester.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.charge_semester_id" class="text-destructive mt-1 text-xs">{{ form.errors.charge_semester_id }}</p>
                    </div>

                    <div>
                        <label class="text-sm leading-none font-medium">Ghi chú</label>
                        <Textarea v-model="form.notes" placeholder="Ghi chú thêm (không bắt buộc, áp dụng cho tất cả)" rows="3" />
                    </div>

                    <p v-if="form.errors.items" class="text-destructive text-xs">{{ form.errors.items }}</p>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing || !canSubmit">
                            {{ form.processing ? 'Đang xử lý...' : `Đăng ký thi lại (${selectedEntries.length})` }}
                        </Button>
                    </div>
                </form>

                <div v-else class="text-muted-foreground flex items-center justify-center py-16">Chọn bản ghi từ danh sách bên trái để bắt đầu.</div>
            </CardContent>
        </Card>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Bản ghi trùng lặp / đã xử lý</CardTitle>
            <CardDescription>Đã pass môn ở bản ghi khác, hoặc đã có đăng ký thi lại đang xử lý/hoàn tất — không hiển thị ở danh sách chọn.</CardDescription>
        </CardHeader>
        <CardContent>
            <div v-if="blocked_students.length === 0" class="text-muted-foreground py-8 text-center">Không có bản ghi trùng lặp.</div>
            <div v-else class="max-h-[400px] space-y-2 overflow-y-auto">
                <div v-for="(entry, index) in blocked_students" :key="entryKey(entry)" class="flex items-start gap-3 rounded-lg border p-3">
                    <span class="text-muted-foreground mt-1 w-5 shrink-0 text-right font-mono text-xs">{{ index + 1 }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-medium">{{ entry.student.full_name }}</div>
                                <div class="text-muted-foreground font-mono text-xs">{{ entry.student.student_id }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono text-sm font-medium">{{ entry.unit.code }}</div>
                                <div v-if="entry.failed_record.final_percentage !== null" class="text-muted-foreground text-xs">Điểm: {{ Number(entry.failed_record.final_percentage).toFixed(1) }}</div>
                            </div>
                        </div>
                        <div class="mt-1 flex items-center justify-between gap-2">
                            <div class="text-muted-foreground truncate text-xs">{{ entry.unit.name }}</div>
                            <span class="bg-destructive/10 text-destructive shrink-0 rounded px-1.5 py-0.5 text-xs whitespace-nowrap">{{ entry.reason_label }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
