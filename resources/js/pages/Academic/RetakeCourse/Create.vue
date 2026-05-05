<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
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
    failed_record: { id: number; unit_id: number; attempt_number: number };
    unit: { id: number; code: string; name: string; retake_fee: string };
    available_offerings: {
        id: number;
        section_code: string | null;
        course_code: string | null;
        semester: { id: number; name: string };
        campus: { id: number; name: string } | null;
        lecture: { display_name: string } | null;
        current_enrollment: number;
        max_capacity: number;
    }[];
}

interface Props {
    eligible_students: EligibleEntry[];
    total_eligible_students: number;
    filters?: Record<string, any>;
    semesters: { id: number; name: string; code: string }[];
    campuses: { id: number; name: string; code: string }[];
}

defineProps<Props>();

const selectedEntry = ref<EligibleEntry | null>(null);
const selectedOfferingId = ref<number | null>(null);

const form = useForm({
    student_id: null as number | null,
    unit_id: null as number | null,
    original_academic_record_id: null as number | null,
    course_offering_id: null as number | null,
    semester_id: null as number | null,
    campus_id: null as number | null,
    registration_start_date: '',
    registration_end_date: '',
    notes: '',
});

const selectEntry = (entry: EligibleEntry) => {
    selectedEntry.value = entry;
    selectedOfferingId.value = null;
    form.student_id = entry.student.id;
    form.unit_id = entry.unit.id;
    form.original_academic_record_id = entry.failed_record.id;
    form.campus_id = entry.student.campus_id;
    form.course_offering_id = null;
    form.semester_id = null;
};

const selectOffering = (offeringId: string) => {
    const id = Number(offeringId);
    selectedOfferingId.value = id;
    form.course_offering_id = id;
    const offering = selectedEntry.value?.available_offerings.find((o) => o.id === id);
    if (offering) {
        form.semester_id = offering.semester.id;
    }
};

const selectedOffering = computed(() => {
    if (!selectedEntry.value || !selectedOfferingId.value) return null;
    return selectedEntry.value.available_offerings.find((o) => o.id === selectedOfferingId.value);
});

const hasZeroRetakeFee = computed(() => {
    if (!selectedEntry.value) return false;
    return !selectedEntry.value.unit.retake_fee || parseFloat(selectedEntry.value.unit.retake_fee) <= 0;
});

const submit = () => {
    form.post(route('academic.retake-course.store'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Đăng ký học lại - Tạo mới" />
    <div class="flex items-center gap-4 mb-6">
        <Button variant="ghost" size="icon" @click="router.visit(route('academic.retake-course.index'))">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Đăng ký học lại</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Chọn sinh viên đủ điều kiện để đăng ký học lại.</p>
        </div>
    </div>

    <div class="flex items-center gap-3 mb-6 rounded-lg border bg-card p-4">
        <Users class="h-4 w-4 text-muted-foreground" />
        <span class="text-sm text-muted-foreground">Tổng SV đủ điều kiện học lại:</span>
        <span class="text-lg font-semibold">{{ total_eligible_students.toLocaleString('vi-VN') }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left: Eligible Students List -->
        <Card>
            <CardHeader>
                <CardTitle>Sinh viên đủ điều kiện</CardTitle>
                <CardDescription>Danh sách SV fail có lớp mở để đăng ký học lại.</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="eligible_students.length === 0" class="text-center py-8 text-muted-foreground">
                    Không tìm thấy sinh viên đủ điều kiện.
                </div>
                <div v-else class="space-y-2 max-h-[500px] overflow-y-auto">
                    <div
                        v-for="entry in eligible_students"
                        :key="`${entry.student.id}-${entry.unit.id}`"
                        class="p-3 border rounded-lg cursor-pointer transition-colors hover:bg-accent"
                        :class="{ 'border-primary bg-accent': selectedEntry?.student.id === entry.student.id && selectedEntry?.unit.id === entry.unit.id }"
                        @click="selectEntry(entry)"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-medium">{{ entry.student.full_name }}</div>
                                <div class="text-xs text-muted-foreground font-mono">{{ entry.student.student_id }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono text-sm font-medium">{{ entry.unit.code }}</div>
                                <div class="text-xs text-muted-foreground">{{ entry.available_offerings.length }} lớp mở</div>
                            </div>
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground truncate">{{ entry.unit.name }}</div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Right: Registration Form -->
        <Card>
            <CardHeader>
                <CardTitle>Thông tin đăng ký</CardTitle>
                <CardDescription v-if="!selectedEntry">Chọn sinh viên từ danh sách bên trái.</CardDescription>
                <CardDescription v-else>Hoàn tất thông tin đăng ký cho {{ selectedEntry.student.full_name }}.</CardDescription>
            </CardHeader>
            <CardContent>
                <form v-if="selectedEntry" @submit.prevent="submit" class="space-y-4">
                    <!-- Student Info (read-only) -->
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

                    <!-- Unit Info -->
                    <div>
                        <Label>Môn học</Label>
                        <Input :model-value="`${selectedEntry.unit.code} - ${selectedEntry.unit.name}`" disabled />
                    </div>

                    <!-- Retake Fee -->
                    <div>
                        <Label>Phí học lại (VNĐ)</Label>
                        <Input :model-value="parseFloat(selectedEntry.unit.retake_fee || '0').toLocaleString('vi-VN')" disabled />
                        <p v-if="hasZeroRetakeFee" class="mt-1 text-xs text-destructive">
                            Môn {{ selectedEntry.unit.code }} chưa cấu hình phí học lại. Vui lòng cập nhật retake_fee trong quản lý Unit trước khi đăng ký.
                        </p>
                    </div>

                    <!-- Course Offering Selection -->
                    <div>
                        <Label>Chọn lớp <span class="text-destructive">*</span></Label>
                        <Select :model-value="selectedOfferingId?.toString() ?? undefined" @update:model-value="selectOffering">
                            <SelectTrigger>
                                <SelectValue placeholder="Chọn lớp học mở..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="offering in selectedEntry.available_offerings"
                                    :key="offering.id"
                                    :value="offering.id.toString()"
                                >
                                    {{ offering.section_code || 'N/A' }} · {{ offering.semester.name }}
                                    <template v-if="offering.campus"> · {{ offering.campus.name }}</template>
                                    ({{ offering.current_enrollment }}/{{ offering.max_capacity }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="selectedOffering && selectedOffering.current_enrollment >= selectedOffering.max_capacity" class="text-xs text-yellow-600 mt-1">
                            Lớp đã đầy. Đăng ký sẽ được xử lý dạng admin override.
                        </p>
                        <p v-if="form.errors.course_offering_id" class="text-xs text-destructive mt-1">{{ form.errors.course_offering_id }}</p>
                    </div>

                    <!-- Dates -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Ngày bắt đầu ĐK</Label>
                            <DatePicker v-model="form.registration_start_date" placeholder="Chọn ngày bắt đầu" />
                        </div>
                        <div>
                            <Label>Ngày kết thúc ĐK</Label>
                            <DatePicker v-model="form.registration_end_date" placeholder="Chọn ngày kết thúc" />
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <Label>Ghi chú</Label>
                        <Textarea v-model="form.notes" placeholder="Ghi chú thêm (không bắt buộc)" rows="3" />
                    </div>

                    <!-- Errors -->
                    <p v-if="form.errors.student_id" class="text-xs text-destructive">{{ form.errors.student_id }}</p>
                    <p v-if="form.errors.unit_id" class="text-xs text-destructive">{{ form.errors.unit_id }}</p>

                    <!-- Submit -->
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing || !form.course_offering_id || hasZeroRetakeFee">
                            {{ form.processing ? 'Đang xử lý...' : 'Đăng ký học lại' }}
                        </Button>
                    </div>
                </form>

                <div v-else class="flex items-center justify-center py-16 text-muted-foreground">
                    Chọn sinh viên từ danh sách bên trái để bắt đầu.
                </div>
            </CardContent>
        </Card>
    </div>
</template>
