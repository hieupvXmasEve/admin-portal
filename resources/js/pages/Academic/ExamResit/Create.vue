<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface EligibleEntry {
    student: { id: number; full_name: string; student_id: string; campus_id: number };
    failed_record: { id: number; unit_id: number; final_percentage: string | null };
    unit: { id: number; code: string; name: string };
}

interface Props {
    eligible_students: EligibleEntry[];
    total_eligible: number;
    filters?: Record<string, unknown>;
    semesters: { id: number; name: string; code: string }[];
    campuses: { id: number; name: string; code: string }[];
}

const props = defineProps<Props>();

const selectedEntry = ref<EligibleEntry | null>(null);

const form = useForm({
    student_id: null as number | null,
    academic_record_id: null as number | null,
    operation_semester_id: null as number | null,
    charge_semester_id: null as number | null,
    campus_id: null as number | null,
    notes: '',
});

const defaultSemesterId = (): number | null => {
    const filterSemesterId = Number(props.filters?.semester_id);
    if (Number.isInteger(filterSemesterId) && props.semesters.some((s) => s.id === filterSemesterId)) {
        return filterSemesterId;
    }

    return props.semesters[0]?.id ?? null;
};

const selectEntry = (entry: EligibleEntry) => {
    selectedEntry.value = entry;
    const semesterId = defaultSemesterId();
    form.student_id = entry.student.id;
    form.academic_record_id = entry.failed_record.id;
    form.campus_id = entry.student.campus_id;
    form.operation_semester_id = semesterId;
    form.charge_semester_id = semesterId;
    form.clearErrors();
};

const isSelected = (entry: EligibleEntry): boolean => selectedEntry.value?.student.id === entry.student.id && selectedEntry.value?.failed_record.id === entry.failed_record.id;

const canSubmit = computed(() => !!form.operation_semester_id && !!form.charge_semester_id);

const submit = () => {
    form.post(route('academic.exam-resit.store'), { preserveScroll: true });
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
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Chọn sinh viên fail điểm (đủ điều kiện thi lại) để tạo nguồn.</p>
        </div>
    </div>

    <div class="bg-card mb-6 flex items-center gap-3 rounded-lg border p-4">
        <Users class="text-muted-foreground h-4 w-4" />
        <span class="text-muted-foreground text-sm">Tổng bản ghi đủ điều kiện thi lại:</span>
        <span class="text-lg font-semibold">{{ total_eligible.toLocaleString('vi-VN') }}</span>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <Card>
            <CardHeader>
                <CardTitle>Bản ghi đủ điều kiện</CardTitle>
                <CardDescription>Sinh viên fail do điểm (grade_failed), chuyên cần đã ghi nhận.</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="eligible_students.length === 0" class="text-muted-foreground py-8 text-center">Không tìm thấy bản ghi đủ điều kiện.</div>
                <div v-else class="max-h-[500px] space-y-2 overflow-y-auto">
                    <div
                        v-for="entry in eligible_students"
                        :key="`${entry.student.id}-${entry.failed_record.id}`"
                        class="hover:bg-muted/50 cursor-pointer rounded-lg border p-3 transition-colors"
                        :class="{ 'border-primary bg-primary/5 ring-1 ring-primary/30': isSelected(entry) }"
                        @click="selectEntry(entry)"
                    >
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
                        <div class="text-muted-foreground mt-1 truncate text-xs">{{ entry.unit.name }}</div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Thông tin đăng ký</CardTitle>
                <CardDescription v-if="!selectedEntry">Chọn bản ghi từ danh sách bên trái.</CardDescription>
                <CardDescription v-else>Tạo nguồn thi lại cho {{ selectedEntry.student.full_name }}.</CardDescription>
            </CardHeader>
            <CardContent>
                <form v-if="selectedEntry" @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Sinh viên</Label>
                            <Input :model-value="selectedEntry.student.full_name" disabled />
                        </div>
                        <div>
                            <Label>MSSV</Label>
                            <Input :model-value="selectedEntry.student.student_id" disabled />
                        </div>
                    </div>

                    <div>
                        <Label>Môn học</Label>
                        <Input :model-value="`${selectedEntry.unit.code} - ${selectedEntry.unit.name}`" disabled />
                    </div>

                    <div>
                        <Label>Học kỳ vận hành <span class="text-destructive">*</span></Label>
                        <Select :model-value="form.operation_semester_id?.toString() ?? undefined" @update:model-value="(v) => (form.operation_semester_id = v ? Number(v) : null)">
                            <SelectTrigger><SelectValue placeholder="Chọn học kỳ..." /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">{{ semester.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.operation_semester_id" class="text-destructive mt-1 text-xs">{{ form.errors.operation_semester_id }}</p>
                    </div>

                    <div>
                        <Label>Học kỳ thu phí <span class="text-destructive">*</span></Label>
                        <Select :model-value="form.charge_semester_id?.toString() ?? undefined" @update:model-value="(v) => (form.charge_semester_id = v ? Number(v) : null)">
                            <SelectTrigger><SelectValue placeholder="Chọn học kỳ..." /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">{{ semester.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.charge_semester_id" class="text-destructive mt-1 text-xs">{{ form.errors.charge_semester_id }}</p>
                    </div>

                    <div>
                        <Label>Ghi chú</Label>
                        <Textarea v-model="form.notes" placeholder="Ghi chú thêm (không bắt buộc)" rows="3" />
                    </div>

                    <p v-if="form.errors.academic_record_id" class="text-destructive text-xs">{{ form.errors.academic_record_id }}</p>
                    <p v-if="form.errors.failure_reason" class="text-destructive text-xs">{{ form.errors.failure_reason }}</p>
                    <p v-if="form.errors.policy" class="text-destructive text-xs">{{ form.errors.policy }}</p>

                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing || !canSubmit">
                            {{ form.processing ? 'Đang xử lý...' : 'Đăng ký thi lại' }}
                        </Button>
                    </div>
                </form>

                <div v-else class="text-muted-foreground flex items-center justify-center py-16">Chọn bản ghi từ danh sách bên trái để bắt đầu.</div>
            </CardContent>
        </Card>
    </div>
</template>
