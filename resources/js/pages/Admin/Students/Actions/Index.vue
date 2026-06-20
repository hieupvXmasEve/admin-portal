<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    getActionTypeBadgeClass,
    getActionTypeLabel,
    StudentActionType,
    type ActionTypeOption,
    type Campus,
    type CourseRegistrationPreview,
    type EgcChargePreview,
    type EgcDeferBlockOption,
    type Semester,
    type StoreStudentActionForm,
    type StudentActionLog,
} from '@/types/student-action';
import { studentRoutes } from '@/utils/routes';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowRight, Calendar, Eye, FileText, Paperclip, Plus, Upload, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface StudentBasic {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    status: string;
    campus_id: number;
    campus?: Campus;
}

interface Props {
    student: StudentBasic;
    actionLogs: {
        data: StudentActionLog[];
        meta: any;
        links: any;
    };
    filters: {
        action_type: string | null;
        per_page: number;
    };
    options: {
        actionTypes: ActionTypeOption[];
        semesters: Semester[];
        activeSemesterId?: number | null;
        campuses: Campus[];
        deferScopeTypes?: { value: string; label: string }[];
        deferFeePolicies?: { value: string; label: string }[];
        egcDeferBlocks?: EgcDeferBlockOption[];
        courseRegistrations?: CourseRegistrationPreview[];
        egcCharges?: EgcChargePreview[];
        studentDecisions?: Array<{
            id: number;
            decision_name: string;
            decision_number: string;
            issued_at?: string;
            expires_at?: string | null;
        }>;
    };
}

const props = defineProps<Props>();

const isDialogOpen = ref(false);

const form = useForm<StoreStudentActionForm>({
    student_id: props.student.id,
    action_type: '',
    reason: '',
    notes: '',
    decision_number: '',
    decision_signed_at: '',
    decision_signer: '',
    decision_id: null,
    missing_documents: true,
    attachment_ids: [],
    from_semester_id: props.options.activeSemesterId ?? null,
    return_semester_id: props.options.activeSemesterId ?? null,
    egc_defer_from_block_number: null,
    intended_intake_semester_id: null,
    dropout_semester_id: null,
    from_campus_id: props.student.campus_id,
    to_campus_id: null,
    effective_at: '',
    effective_semester_id: null,
    // Defer case fields
    defer_scope_type: 'FULL',
    defer_fee_policy: 'FORFEIT',
    defer_preserve_amount: null,
    defer_course_registration_ids: [],
    defer_egc_charge_ids: [],
});

const selectedActionType = computed(() => {
    return form.action_type ? (form.action_type as StudentActionType) : null;
});

const activeSemester = computed(() => {
    const activeId = props.options.activeSemesterId;
    if (!activeId) {
        return null;
    }

    return props.options.semesters.find((semester) => semester.id === activeId) ?? null;
});

const isSemesterBeforeActive = (semester: Semester): boolean => {
    const active = activeSemester.value;
    if (!active || semester.id === active.id) {
        return false;
    }

    if (active.start_date && semester.start_date) {
        return new Date(semester.start_date) < new Date(active.start_date);
    }

    return false;
};

const selectableFromSemesters = computed(() => props.options.semesters.filter((semester) => !isSemesterBeforeActive(semester)));

// Reset form fields when action type changes
watch(
    () => form.action_type,
    () => {
        form.from_semester_id = props.options.activeSemesterId ?? null;
        form.return_semester_id = props.options.activeSemesterId ?? null;
        form.intended_intake_semester_id = null;
        form.dropout_semester_id = null;
        form.to_campus_id = null;
        form.effective_at = '';
        form.effective_semester_id = null;
        form.decision_number = '';
        form.decision_signed_at = '';
        form.decision_signer = '';
        form.decision_id = null;
        // Keep from_campus_id as student's current campus
        form.from_campus_id = props.student.campus_id;
        // Reset defer case fields
        form.defer_scope_type = 'FULL';
        form.defer_fee_policy = 'FORFEIT';
        form.defer_preserve_amount = null;
        form.defer_course_registration_ids = [];
        form.defer_egc_charge_ids = [];
        form.egc_defer_from_block_number = form.action_type === StudentActionType.ACADEMIC_DEFER && props.student.status === 'intake_pre_uni_gc' ? 1 : null;
        // For waiting course opening, default block if pre-uni context
        if (form.action_type === StudentActionType.WAITING_COURSE_OPENING && props.student.status === 'intake_pre_uni_gc') {
            form.egc_defer_from_block_number = form.egc_defer_from_block_number ?? 1;
        }

        if (props.options.activeSemesterId && form.from_semester_id) {
            const semester = props.options.semesters.find((item) => item.id === form.from_semester_id);
            if (semester && isSemesterBeforeActive(semester)) {
                form.from_semester_id = props.options.activeSemesterId;
            }
        }
    },
);

watch(
    () => form.from_semester_id,
    (value) => {
        if (!value || !props.options.activeSemesterId) {
            return;
        }

        const semester = props.options.semesters.find((item) => item.id === value);
        if (semester && isSemesterBeforeActive(semester)) {
            form.from_semester_id = props.options.activeSemesterId;
        }
    },
);
// Computed: filter course registrations by selected from_semester_id
const isEgcStudent = computed(() => props.student.status === 'intake_pre_uni_gc');
const filteredCourseRegistrations = computed(() => {
    if (!props.options.courseRegistrations) return [];
    if (!form.from_semester_id) return props.options.courseRegistrations;
    return props.options.courseRegistrations.filter((reg) => reg.semester_id === form.from_semester_id);
});

const filteredEgcCharges = computed(() => {
    if (!props.options.egcCharges) return [];
    if (!form.from_semester_id) return props.options.egcCharges;
    return props.options.egcCharges.filter((charge) => charge.semester_id === form.from_semester_id);
});

const hasUnpaidEgcCharges = computed(() => {
    if (!isEgcStudent.value) return false;
    if (filteredEgcCharges.value.length === 0) return false;
    return filteredEgcCharges.value.every((charge) => !charge.is_fully_paid);
});

// Filtered available actions per current student status (business labels, not raw statuses).
// Backend still fully enforces in RecordStudentActionAction.
const availableActions = computed(() => {
    const status = props.student.status;
    const full = props.options.actionTypes;

    if (status === 'pending') {
        return [
            { value: StudentActionType.STUDENT_ENROLLMENT_NE, label: 'NE Enrollment' },
            { value: StudentActionType.ADMISSION_DEFERRAL, label: 'Hoãn nhập học (Admission Deferral)' },
            { value: StudentActionType.ACADEMIC_DROPOUT, label: 'Bỏ học (Dropout)' },
        ];
    }

    if (status === 'intake_pre_uni_gc' || status === 'intake_course') {
        return [
            { value: StudentActionType.WAITING_COURSE_OPENING, label: 'Chờ mở môn' },
            { value: StudentActionType.ACADEMIC_DEFER, label: 'Bảo lưu (Defer)' },
            { value: StudentActionType.ACADEMIC_DROPOUT, label: 'Bỏ học (Dropout)' },
            { value: StudentActionType.CAMPUS_TRANSFER, label: 'Chuyển campus (Campus Transfer)' },
        ];
    }

    if (status === 'pending_course_opening') {
        // Two continue options both map to ACADEMIC_RESUME; backend history resolver picks the exact prior study status.
        return [
            { value: StudentActionType.ACADEMIC_RESUME, label: 'Tiếp tục học Pre-Uni / EGC' },
            { value: StudentActionType.ACADEMIC_RESUME, label: 'Tiếp tục học Course chính' },
            { value: StudentActionType.ACADEMIC_DEFER, label: 'Bảo lưu (Defer)' },
            { value: StudentActionType.ACADEMIC_DROPOUT, label: 'Bỏ học (Dropout)' },
        ];
    }

    if (status === 'deferred') {
        return [
            { value: StudentActionType.ACADEMIC_RESUME, label: 'Quay lại học' },
            { value: StudentActionType.ACADEMIC_DEFER, label: 'Bảo lưu tiếp' },
        ];
    }

    // Fallback: show all (should not normally happen for this page)
    return full.map((a) => ({ value: a.value, label: a.label }));
});
watch(
    () => [selectedActionType.value, isEgcStudent.value],
    () => {
        if (selectedActionType.value === StudentActionType.ACADEMIC_DEFER && isEgcStudent.value) {
            form.defer_scope_type = 'FULL';
            form.defer_course_registration_ids = [];
            form.egc_defer_from_block_number = form.egc_defer_from_block_number ?? 1;
        } else {
            form.egc_defer_from_block_number = null;
        }
    },
);

watch(
    () => form.defer_fee_policy,
    (value) => {
        if (value === 'FORFEIT') {
            form.defer_egc_charge_ids = [];
        }
    },
);

watch(
    () => [isEgcStudent.value, hasUnpaidEgcCharges.value, selectedActionType.value],
    () => {
        if (selectedActionType.value !== StudentActionType.ACADEMIC_DEFER || !isEgcStudent.value) {
            return;
        }

        if (hasUnpaidEgcCharges.value) {
            form.defer_fee_policy = 'FORFEIT';
        }
    },
);

const formatCurrency = (amount: number | string): string => {
    const numeric = typeof amount === 'string' ? Number(amount) : amount;
    if (Number.isNaN(numeric)) return String(amount);
    return numeric.toLocaleString('vi-VN');
};

const handleSubmit = () => {
    form.post(route('students.actions.store', { student: props.student.id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Action recorded successfully');
            isDialogOpen.value = false;
            form.reset();
            form.student_id = props.student.id;
            form.from_campus_id = props.student.campus_id;
        },
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        onError: (errors) => {
            toast.error('Failed to record action. Please check the form.');
        },
    });
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, { preserveState: true, preserveScroll: true });
};

const handlePageSizeChange = (size: number) => {
    router.visit(route('students.actions.index', { student: props.student.id, per_page: size }), {
        preserveState: true,
        preserveScroll: true,
    });
};

const formatDate = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDateOnly = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
};

const getSummary = (log: StudentActionLog): string => {
    switch (log.action_type) {
        case StudentActionType.ACADEMIC_DEFER: {
            const blockText = log.egc_defer_from_block_number ? `, from Block ${log.egc_defer_from_block_number}` : '';
            if (log.defer_case?.scope_type === 'COURSES') {
                return `Defer by courses from ${log.from_semester?.name ?? 'N/A'} to ${log.return_semester?.name ?? 'N/A'}${blockText}`;
            }
            return `Defer full semester from ${log.from_semester?.name ?? 'N/A'} to ${log.return_semester?.name ?? 'N/A'}${blockText}`;
        }
        case StudentActionType.ACADEMIC_RESUME:
            return `Resume in ${log.return_semester?.name ?? 'N/A'}`;
        case StudentActionType.ADMISSION_DEFERRAL:
            return `Admission deferred for ${log.intended_intake_semester?.name ?? 'N/A'}`;
        case StudentActionType.ACADEMIC_DROPOUT:
            return `Dropout in ${log.dropout_semester?.name ?? 'N/A'}`;
        case StudentActionType.CAMPUS_TRANSFER:
            return `Transfer from ${log.from_campus?.name ?? 'N/A'} to ${log.to_campus?.name ?? 'N/A'}`;
        default:
            return '';
    }
};
</script>

<template>
    <Head :title="`Actions - ${student.full_name}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Student Actions</h1>
                <p class="text-muted-foreground mt-1">
                    {{ student.full_name }} ({{ student.student_id }}) -
                    <Badge variant="outline">{{ student.status }}</Badge>
                </p>
            </div>

            <Dialog v-model:open="isDialogOpen">
                <DialogTrigger as-child>
                    <Button>
                        <Plus class="mr-2 h-4 w-4" />
                        New Action
                    </Button>
                </DialogTrigger>
                <DialogContent class="!max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Record Administrative Action</DialogTitle>
                        <DialogDescription> Create a new action record for {{ student.full_name }}. </DialogDescription>
                    </DialogHeader>

                    <form @submit.prevent="handleSubmit" class="space-y-4">
                        <!-- Action Type -->
                        <div class="space-y-2">
                            <Label for="action_type">Action Type *</Label>
                            <Select v-model="form.action_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select action type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="opt in availableActions" :key="opt.value + '::' + opt.label" :value="opt.value">
                                        {{ opt.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.action_type" class="text-sm text-red-500">{{ form.errors.action_type }}</p>
                        </div>

                        <!-- ACADEMIC_DEFER fields -->
                        <template v-if="selectedActionType === StudentActionType.ACADEMIC_DEFER">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="from_semester_id">From Semester *</Label>
                                    <Select v-model="form.from_semester_id" disabled>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select semester" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="sem in selectableFromSemesters" :key="sem.id" :value="sem.id">
                                                {{ sem.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-muted-foreground text-xs">From semester defaults to the current semester; earlier semesters cannot be selected.</p>
                                    <p v-if="form.errors.from_semester_id" class="text-sm text-red-500">{{ form.errors.from_semester_id }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="return_semester_id">Return Semester *</Label>
                                    <Select v-model="form.return_semester_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select semester" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id">
                                                {{ sem.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="form.errors.return_semester_id" class="text-sm text-red-500">{{ form.errors.return_semester_id }}</p>
                                </div>
                            </div>

                            <div v-if="isEgcStudent" class="space-y-2">
                                <Label for="egc_defer_from_block_number">EGC Defer From Block *</Label>
                                <Select :model-value="form.egc_defer_from_block_number ? String(form.egc_defer_from_block_number) : undefined" @update:model-value="(value) => (form.egc_defer_from_block_number = Number(value))">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select block" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="block in options.egcDeferBlocks ?? []" :key="block.value" :value="String(block.value)">
                                            {{ block.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.egc_defer_from_block_number" class="text-sm text-red-500">{{ form.errors.egc_defer_from_block_number }}</p>
                            </div>

                            <!-- Finance Section -->
                            <div class="border-border bg-muted/30 mt-4 rounded-lg border p-4">
                                <h4 class="text-primary mb-3 font-semibold">💰 Tài chính (Finance)</h4>

                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Scope Type -->
                                    <div v-if="!isEgcStudent" class="space-y-2">
                                        <Label for="defer_scope_type">Phạm vi bảo lưu *</Label>
                                        <Select v-model="form.defer_scope_type">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Chọn phạm vi" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="scope in options.deferScopeTypes" :key="scope.value" :value="scope.value">
                                                    {{ scope.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p v-if="form.errors.defer_scope_type" class="text-sm text-red-500">{{ form.errors.defer_scope_type }}</p>
                                    </div>
                                    <div v-else class="space-y-2">
                                        <Label>Phạm vi bảo lưu</Label>
                                        <Input value="Toàn kỳ" disabled />
                                    </div>

                                    <!-- Fee Policy -->
                                    <div class="space-y-2">
                                        <Label for="defer_fee_policy">Chính sách học phí *</Label>
                                        <Select v-model="form.defer_fee_policy" :disabled="isEgcStudent && hasUnpaidEgcCharges">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Chọn chính sách" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="policy in options.deferFeePolicies" :key="policy.value" :value="policy.value">
                                                    {{ policy.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p v-if="form.errors.defer_fee_policy" class="text-sm text-red-500">{{ form.errors.defer_fee_policy }}</p>
                                        <p v-else-if="isEgcStudent && hasUnpaidEgcCharges" class="text-sm text-amber-600">Chưa có thanh toán cho học phí EGC, mặc định mất học phí.</p>
                                    </div>
                                </div>

                                <!-- Preserve Amount (visible when PARTIAL) -->
                                <div v-if="form.defer_fee_policy === 'PARTIAL'" class="mt-4 space-y-2">
                                    <Label for="defer_preserve_amount">Số tiền bảo lưu (VNĐ) *</Label>
                                    <Input v-model.number="form.defer_preserve_amount" type="number" min="0" placeholder="Nhập số tiền bảo lưu" />
                                    <p v-if="form.errors.defer_preserve_amount" class="text-sm text-red-500">{{ form.errors.defer_preserve_amount }}</p>
                                </div>

                                <!-- Course Selection (visible when COURSES scope) -->
                                <div v-if="!isEgcStudent && form.defer_scope_type === 'COURSES'" class="mt-4 space-y-2">
                                    <Label>Chọn môn học bảo lưu *</Label>
                                    <div v-if="filteredCourseRegistrations.length === 0" class="text-muted-foreground text-sm">Không có môn học nào trong kỳ đã chọn.</div>
                                    <div v-else class="max-h-48 space-y-2 overflow-y-auto rounded border p-2">
                                        <div v-for="reg in filteredCourseRegistrations" :key="reg.id" class="flex items-center space-x-2">
                                            <Checkbox
                                                :id="`course-${reg.id}`"
                                                :model-value="form.defer_course_registration_ids?.includes(reg.id)"
                                                :disabled="reg.registration_status === 'defer'"
                                                @update:model-value="
                                                    (checked) => {
                                                        if (!form.defer_course_registration_ids) form.defer_course_registration_ids = [];
                                                        if (checked) {
                                                            if (reg.registration_status === 'defer') return;
                                                            form.defer_course_registration_ids.push(reg.id);
                                                        } else {
                                                            form.defer_course_registration_ids = form.defer_course_registration_ids.filter((id) => id !== reg.id);
                                                        }
                                                    }
                                                "
                                            />
                                            <Label :for="`course-${reg.id}`" class="text-sm" :class="reg.registration_status === 'defer' ? 'text-muted-foreground cursor-not-allowed' : 'cursor-pointer'">
                                                <span class="font-medium">{{ reg.course_code }}</span> - {{ reg.course_name }}
                                                <span class="text-muted-foreground text-xs">({{ reg.semester_name }})</span>
                                                <span v-if="reg.registration_status === 'defer'" class="ml-2 text-xs text-amber-600"> (Đã bảo lưu) </span>
                                            </Label>
                                        </div>
                                    </div>
                                    <p v-if="form.errors.defer_course_registration_ids" class="text-sm text-red-500">{{ form.errors.defer_course_registration_ids }}</p>
                                </div>

                                <div v-if="isEgcStudent" class="mt-4 space-y-3">
                                    <div>
                                        <Label>Danh sách môn đã đăng ký (EGC)</Label>
                                        <div v-if="filteredCourseRegistrations.length === 0" class="text-muted-foreground text-sm">Chưa có môn đăng ký trong kỳ hiện tại.</div>
                                        <div v-else class="mt-2 max-h-40 space-y-2 overflow-y-auto rounded border p-2">
                                            <div v-for="reg in filteredCourseRegistrations" :key="reg.id" class="text-sm">
                                                <span class="font-medium">{{ reg.course_code }}</span> - {{ reg.course_name }}
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <Label>Học phí EGC đã đóng (theo level)</Label>
                                        <div v-if="filteredEgcCharges.length === 0" class="text-muted-foreground text-sm">Chưa có học phí EGC trong kỳ hiện tại.</div>
                                        <div v-else class="mt-2 max-h-48 space-y-2 overflow-y-auto rounded border p-2">
                                            <div v-for="charge in filteredEgcCharges" :key="charge.id" class="flex items-center space-x-2">
                                                <Checkbox
                                                    :id="`egc-charge-${charge.id}`"
                                                    :model-value="form.defer_egc_charge_ids?.includes(charge.id)"
                                                    :disabled="form.defer_fee_policy === 'FORFEIT'"
                                                    @update:model-value="
                                                        (checked) => {
                                                            if (!form.defer_egc_charge_ids) form.defer_egc_charge_ids = [];
                                                            if (checked) {
                                                                if (form.defer_fee_policy === 'FORFEIT') return;
                                                                form.defer_egc_charge_ids.push(charge.id);
                                                            } else {
                                                                form.defer_egc_charge_ids = form.defer_egc_charge_ids.filter((id) => id !== charge.id);
                                                            }
                                                        }
                                                    "
                                                />
                                                <Label :for="`egc-charge-${charge.id}`" class="text-sm"> {{ charge.description ?? 'EGC Level Fee' }} - {{ formatCurrency(charge.amount) }} </Label>
                                            </div>
                                        </div>
                                        <p v-if="form.errors.defer_egc_charge_ids" class="text-sm text-red-500">{{ form.errors.defer_egc_charge_ids }}</p>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- ACADEMIC_RESUME fields -->
                        <template v-if="selectedActionType === StudentActionType.ACADEMIC_RESUME">
                            <div class="space-y-2">
                                <Label for="return_semester_id">Return Semester *</Label>
                                <Select v-model="form.return_semester_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id">
                                            {{ sem.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.return_semester_id" class="text-sm text-red-500">{{ form.errors.return_semester_id }}</p>
                            </div>
                        </template>

                        <!-- WAITING_COURSE_OPENING fields (from_semester + block for timing context) -->
                        <template v-if="selectedActionType === StudentActionType.WAITING_COURSE_OPENING">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="from_semester_id">From Semester *</Label>
                                    <Select v-model="form.from_semester_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select semester" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id" :disabled="isSemesterBeforeActive(sem)">
                                                {{ sem.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-muted-foreground text-xs">From semester defaults to the current semester; earlier semesters are disabled.</p>
                                    <p v-if="form.errors.from_semester_id" class="text-sm text-red-500">{{ form.errors.from_semester_id }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="egc_defer_from_block_number">Block *</Label>
                                    <Select :model-value="form.egc_defer_from_block_number ? String(form.egc_defer_from_block_number) : undefined" @update:model-value="(value) => (form.egc_defer_from_block_number = Number(value))">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select block" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="block in options.egcDeferBlocks ?? []" :key="block.value" :value="String(block.value)">
                                                {{ block.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="form.errors.egc_defer_from_block_number" class="text-sm text-red-500">{{ form.errors.egc_defer_from_block_number }}</p>
                                </div>
                            </div>
                        </template>

                        <!-- ADMISSION_DEFERRAL fields -->
                        <template v-if="selectedActionType === StudentActionType.ADMISSION_DEFERRAL">
                            <div class="space-y-2">
                                <Label for="intended_intake_semester_id">Intended Intake Semester *</Label>
                                <Select v-model="form.intended_intake_semester_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id">
                                            {{ sem.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.intended_intake_semester_id" class="text-sm text-red-500">{{ form.errors.intended_intake_semester_id }}</p>
                            </div>
                        </template>

                        <!-- ACADEMIC_DROPOUT fields -->
                        <template v-if="selectedActionType === StudentActionType.ACADEMIC_DROPOUT">
                            <div class="space-y-2">
                                <Label for="dropout_semester_id">Dropout Semester *</Label>
                                <Select v-model="form.dropout_semester_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id">
                                            {{ sem.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.dropout_semester_id" class="text-sm text-red-500">{{ form.errors.dropout_semester_id }}</p>
                            </div>
                        </template>

                        <!-- CAMPUS_TRANSFER fields -->
                        <template v-if="selectedActionType === StudentActionType.CAMPUS_TRANSFER">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="from_campus_id">From Campus</Label>
                                    <Select v-model="form.from_campus_id" disabled>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Current campus" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="campus in options.campuses" :key="campus.id" :value="campus.id">
                                                {{ campus.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-2">
                                    <Label for="to_campus_id">To Campus *</Label>
                                    <Select v-model="form.to_campus_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select target campus" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="campus in options.campuses.filter((c) => c.id !== student.campus_id)" :key="campus.id" :value="campus.id">
                                                {{ campus.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="form.errors.to_campus_id" class="text-sm text-red-500">{{ form.errors.to_campus_id }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="effective_at">Effective Date *</Label>
                                    <Input type="datetime-local" v-model="form.effective_at" />
                                    <p v-if="form.errors.effective_at" class="text-sm text-red-500">{{ form.errors.effective_at }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="effective_semester_id">Effective Semester (Optional)</Label>
                                    <Select v-model="form.effective_semester_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select semester" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id">
                                                {{ sem.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </template>

                        <!-- Common fields -->
                        <div class="space-y-2">
                            <Label for="reason">Reason *</Label>
                            <Textarea v-model="form.reason" placeholder="Enter the reason for this action..." rows="3" />
                            <p v-if="form.errors.reason" class="text-sm text-red-500">{{ form.errors.reason }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="decision_id">Linked Decision (Optional)</Label>
                                <Select :model-value="form.decision_id ? String(form.decision_id) : 'none'" @update:model-value="(value) => (form.decision_id = value === 'none' ? null : Number(value))">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select decision" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">No linked decision</SelectItem>
                                        <SelectItem v-for="decision in options.studentDecisions ?? []" :key="decision.id" :value="String(decision.id)"> {{ decision.decision_number }} - {{ decision.decision_name }} </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-2">
                                <Label for="decision_signed_at">Decision Signed Date (Optional)</Label>
                                <Input type="date" v-model="form.decision_signed_at" />
                            </div>
                            <div class="space-y-2">
                                <Label for="decision_number">Decision Number (Optional)</Label>
                                <Input v-model="form.decision_number" placeholder="Decision number..." />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="decision_signer">Decision Signer (Optional)</Label>
                                <Input v-model="form.decision_signer" placeholder="Signer name..." />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="notes">Notes (Optional)</Label>
                            <Input v-model="form.notes" placeholder="Internal notes..." />
                        </div>

                        <div class="flex items-center space-x-2">
                            <Checkbox id="missing_documents" v-model:model-value="form.missing_documents" />
                            <Label for="missing_documents" class="cursor-pointer">Bổ sung hồ sơ sau (Missing documents)</Label>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isDialogOpen = false"> Cancel </Button>
                            <Button type="submit" :disabled="form.processing || !form.action_type"> Record Action </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Action History</CardTitle>
                <CardDescription> Timeline of all administrative actions for this student. </CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="actionLogs.data.length === 0" class="text-muted-foreground py-12 text-center">
                    <FileText class="mx-auto h-12 w-12 opacity-50" />
                    <p class="mt-2">No action records found for this student.</p>
                </div>

                <div v-else class="space-y-4">
                    <div v-for="log in actionLogs.data" :key="log.id" class="border-border rounded-lg border p-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <Badge :class="getActionTypeBadgeClass(log.action_type)">
                                        {{ getActionTypeLabel(log.action_type) }}
                                    </Badge>
                                    <Badge v-if="log.action_type === StudentActionType.ACADEMIC_DEFER && log.defer_case?.scope_type" variant="outline">
                                        {{ log.defer_case.scope_type === 'COURSES' ? 'Course' : 'Toàn kỳ' }}
                                    </Badge>
                                    <Badge v-if="log.egc_defer_from_block_number" variant="outline"> Block {{ log.egc_defer_from_block_number }} </Badge>
                                    <span v-if="log.missing_documents" class="text-xs text-amber-600"> (Missing Documents) </span>
                                </div>
                                <p class="mt-2 text-sm font-medium">{{ getSummary(log) }}</p>
                                <p class="text-muted-foreground mt-1 text-sm">{{ log.reason }}</p>

                                <div class="text-muted-foreground mt-3 flex items-center gap-4 text-xs">
                                    <span class="flex items-center">
                                        <Calendar class="mr-1 h-3 w-3" />
                                        {{ formatDate(log.created_at) }}
                                    </span>
                                    <span class="flex items-center">
                                        <User class="mr-1 h-3 w-3" />
                                        {{ log.changed_by?.name ?? 'Unknown' }}
                                    </span>
                                    <span v-if="log.decision_number" class="flex items-center"> Decision No: {{ log.decision_number }} </span>
                                    <span v-if="log.decision_signed_at" class="flex items-center"> Decision Signed: {{ formatDateOnly(log.decision_signed_at) }} </span>
                                    <span v-if="log.decision_signer" class="flex items-center"> Signer: {{ log.decision_signer }} </span>
                                    <span v-if="log.decision" class="flex items-center"> Linked: {{ log.decision.decision_number }} </span>
                                    <span v-if="log.attachments && log.attachments.length > 0" class="flex items-center">
                                        <Paperclip class="mr-1 h-3 w-3" />
                                        {{ log.attachments.length }} file(s)
                                    </span>
                                </div>

                                <div v-if="log.previous_status && log.new_status" class="mt-2 flex items-center gap-2 text-xs">
                                    <Badge variant="outline">{{ log.previous_status }}</Badge>
                                    <ArrowRight class="h-3 w-3" />
                                    <Badge variant="outline">{{ log.new_status }}</Badge>
                                </div>
                            </div>

                            <!-- Action buttons -->
                            <div class="ml-4 flex items-center gap-2">
                                <Link :href="studentRoutes.studentStatusActionShow(log.id)">
                                    <Button variant="outline" size="sm">
                                        <Eye class="mr-1 h-3 w-3" />
                                        View
                                    </Button>
                                </Link>
                                <Link v-if="log.missing_documents" :href="studentRoutes.studentStatusActionShow(log.id)">
                                    <Button variant="default" size="sm" class="bg-amber-600 hover:bg-amber-700">
                                        <Upload class="mr-1 h-3 w-3" />
                                        Upload Docs
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <DataPagination :pagination-data="actionLogs" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
