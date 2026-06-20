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

const props = defineProps<Props>();

const NO_OFFERING_VALUE = '__no_offering__';
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

const defaultSemesterId = (): number | null => {
    const filterSemesterId = Number(props.filters?.semester_id);
    if (Number.isInteger(filterSemesterId) && props.semesters.some((semester) => semester.id === filterSemesterId)) {
        return filterSemesterId;
    }

    return props.semesters[0]?.id ?? null;
};

const selectEntry = (entry: EligibleEntry) => {
    selectedEntry.value = entry;
    selectedOfferingId.value = null;
    form.student_id = entry.student.id;
    form.unit_id = entry.unit.id;
    form.original_academic_record_id = entry.failed_record.id;
    form.campus_id = entry.student.campus_id;
    form.course_offering_id = null;
    form.semester_id = defaultSemesterId();
};

const selectOffering = (offeringId: string) => {
    if (offeringId === NO_OFFERING_VALUE) {
        selectedOfferingId.value = null;
        form.course_offering_id = null;
        return;
    }

    const id = Number(offeringId);
    selectedOfferingId.value = id;
    form.course_offering_id = id;
    const offering = selectedEntry.value?.available_offerings.find((o) => o.id === id);
    if (offering) {
        form.semester_id = offering.semester.id;
    }
};

const selectSemester = (semesterId: string) => {
    form.semester_id = Number(semesterId);
};

const selectedOffering = computed(() => {
    if (!selectedEntry.value || !selectedOfferingId.value) return null;
    return selectedEntry.value.available_offerings.find((o) => o.id === selectedOfferingId.value);
});

const selectedOfferingValue = computed(() => selectedOfferingId.value?.toString() ?? NO_OFFERING_VALUE);

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
    <div class="mb-6 flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="router.visit(route('academic.retake-course.index'))">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Đăng ký học lại</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Chọn sinh viên đủ điều kiện để tạo nguồn học lại.</p>
        </div>
    </div>

    <div class="bg-card mb-6 flex items-center gap-3 rounded-lg border p-4">
        <Users class="text-muted-foreground h-4 w-4" />
        <span class="text-muted-foreground text-sm">Tổng SV đủ điều kiện học lại:</span>
        <span class="text-lg font-semibold">{{ total_eligible_students.toLocaleString('vi-VN') }}</span>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Left: Eligible Students List -->
        <Card>
            <CardHeader>
                <CardTitle>Sinh viên đủ điều kiện</CardTitle>
                <CardDescription>Danh sách SV fail có thể tạo nguồn học lại.</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="eligible_students.length === 0" class="text-muted-foreground py-8 text-center">Không tìm thấy sinh viên đủ điều kiện.</div>
                <div v-else class="max-h-[500px] space-y-2 overflow-y-auto">
                    <div
                        v-for="entry in eligible_students"
                        :key="`${entry.student.id}-${entry.unit.id}`"
                        class="hover:bg-muted/50 cursor-pointer rounded-lg border p-3 transition-colors"
                        :class="{ 'border-primary bg-primary/5 ring-1 ring-primary/30': selectedEntry?.student.id === entry.student.id && selectedEntry?.unit.id === entry.unit.id }"
                        @click="selectEntry(entry)"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-medium">{{ entry.student.full_name }}</div>
                                <div class="text-muted-foreground font-mono text-xs">{{ entry.student.student_id }}</div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono text-sm font-medium">{{ entry.unit.code }}</div>
                                <div class="text-muted-foreground text-xs">
                                    {{ entry.available_offerings.length > 0 ? `${entry.available_offerings.length} lớp mở` : 'Chờ xếp lớp' }}
                                </div>
                            </div>
                        </div>
                        <div class="text-muted-foreground mt-1 truncate text-xs">{{ entry.unit.name }}</div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Right: Registration Form -->
        <Card>
            <CardHeader>
                <CardTitle>Thông tin đăng ký</CardTitle>
                <CardDescription v-if="!selectedEntry">Chọn sinh viên từ danh sách bên trái.</CardDescription>
                <CardDescription v-else>Hoàn tất thông tin nguồn học lại cho {{ selectedEntry.student.full_name }}.</CardDescription>
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
                        <p v-if="hasZeroRetakeFee" class="text-destructive mt-1 text-xs">Môn {{ selectedEntry.unit.code }} chưa cấu hình phí học lại. Vui lòng cập nhật retake_fee trong quản lý Unit trước khi đăng ký.</p>
                    </div>

                    <!-- Operation Semester -->
                    <div>
                        <Label>Học kỳ vận hành <span class="text-destructive">*</span></Label>
                        <Select :model-value="form.semester_id?.toString() ?? undefined" @update:model-value="selectSemester">
                            <SelectTrigger>
                                <SelectValue placeholder="Chọn học kỳ..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">
                                    {{ semester.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.semester_id" class="text-destructive mt-1 text-xs">{{ form.errors.semester_id }}</p>
                    </div>

                    <!-- Course Offering Selection -->
                    <div>
                        <Label>Chọn lớp</Label>
                        <Select :model-value="selectedOfferingValue" @update:model-value="selectOffering">
                            <SelectTrigger>
                                <SelectValue placeholder="Chưa gán lớp" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NO_OFFERING_VALUE">Chưa gán lớp</SelectItem>
                                <SelectItem v-for="offering in selectedEntry.available_offerings" :key="offering.id" :value="offering.id.toString()">
                                    {{ offering.section_code || 'N/A' }} · {{ offering.semester.name }}
                                    <template v-if="offering.campus"> · {{ offering.campus.name }}</template>
                                    ({{ offering.current_enrollment }}/{{ offering.max_capacity }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="selectedOffering && selectedOffering.current_enrollment >= selectedOffering.max_capacity" class="mt-1 text-xs text-yellow-600">Lớp đã đầy. Đăng ký sẽ được xử lý dạng admin override.</p>
                        <p v-if="!selectedOfferingId" class="text-muted-foreground mt-1 text-xs">Có thể tạo nguồn trước và xếp lớp sau.</p>
                        <p v-if="form.errors.course_offering_id" class="text-destructive mt-1 text-xs">{{ form.errors.course_offering_id }}</p>
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
                    <p v-if="form.errors.student_id" class="text-destructive text-xs">{{ form.errors.student_id }}</p>
                    <p v-if="form.errors.unit_id" class="text-destructive text-xs">{{ form.errors.unit_id }}</p>

                    <!-- Submit -->
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="form.processing || !form.semester_id || hasZeroRetakeFee">
                            {{ form.processing ? 'Đang xử lý...' : 'Đăng ký học lại' }}
                        </Button>
                    </div>
                </form>

                <div v-else class="text-muted-foreground flex items-center justify-center py-16">Chọn sinh viên từ danh sách bên trái để bắt đầu.</div>
            </CardContent>
        </Card>
    </div>
</template>
