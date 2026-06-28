<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    StudentActionType,
    type ActionTypeOption,
    type Campus,
    type CourseRegistrationPreview,
    type EgcChargePreview,
    type EgcDeferBlockOption,
    type Semester,
    type StoreStudentActionForm,
} from '@/types/student-action';
import { useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface StudentSummary {
    id: number;
    student_id: string;
    full_name: string;
    status: string;
}

interface ActionOptions {
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
}

interface Props {
    student: StudentSummary;
    options: Record<string, unknown>;
}

const props = defineProps<Props>();

// The orchestrator types `options` loosely as Record<string, unknown>; the actual
// payload is built by LifecycleFormOptions::actionOptions(). Narrow it once here.
const actionOptions = computed(() => props.options as unknown as ActionOptions);

const isDialogOpen = ref(false);

const form = useForm<StoreStudentActionForm>({
    student_id: props.student.id,
    action_type: '',
    reason: '',
    notes: '',
    signed_at: '',
    decision_number: '',
    decision_signed_at: '',
    decision_signer: '',
    decision_id: null,
    missing_documents: true,
    from_semester_id: actionOptions.value.activeSemesterId ?? null,
    return_semester_id: actionOptions.value.activeSemesterId ?? null,
    egc_defer_from_block_number: null,
    intended_intake_semester_id: null,
    dropout_semester_id: null,
    from_campus_id: null,
    to_campus_id: null,
    effective_at: '',
    effective_semester_id: null,
    defer_scope_type: 'FULL',
    defer_fee_policy: 'FORFEIT',
    defer_preserve_amount: null,
    defer_course_registration_ids: [],
    defer_egc_charge_ids: [],
});

const isEgcStudent = computed(() => props.student.status === 'intake_pre_uni_gc');

const selectedActionType = computed<StudentActionType | null>(() => (form.action_type ? (form.action_type as StudentActionType) : null));

const activeSemester = computed<Semester | null>(() => {
    const activeId = actionOptions.value.activeSemesterId;
    if (!activeId) {
        return null;
    }

    return actionOptions.value.semesters.find((semester) => semester.id === activeId) ?? null;
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

const selectableFromSemesters = computed(() => actionOptions.value.semesters.filter((semester) => !isSemesterBeforeActive(semester)));

const filteredCourseRegistrations = computed(() => {
    const registrations = actionOptions.value.courseRegistrations ?? [];
    if (!form.from_semester_id) {
        return registrations;
    }

    return registrations.filter((reg) => reg.semester_id === form.from_semester_id);
});

const filteredEgcCharges = computed(() => {
    const charges = actionOptions.value.egcCharges ?? [];
    if (!form.from_semester_id) {
        return charges;
    }

    return charges.filter((charge) => charge.semester_id === form.from_semester_id);
});

const hasUnpaidEgcCharges = computed(() => {
    if (!isEgcStudent.value || filteredEgcCharges.value.length === 0) {
        return false;
    }

    return filteredEgcCharges.value.every((charge) => !charge.is_fully_paid);
});

const formatCurrency = (amount: number | string): string => {
    const numeric = typeof amount === 'string' ? Number(amount) : amount;
    if (Number.isNaN(numeric)) {
        return String(amount);
    }

    return numeric.toLocaleString('vi-VN');
};

// Reset action-specific fields whenever the selected action type changes.
watch(
    () => form.action_type,
    () => {
        form.from_semester_id = actionOptions.value.activeSemesterId ?? null;
        form.return_semester_id = actionOptions.value.activeSemesterId ?? null;
        form.intended_intake_semester_id = null;
        form.dropout_semester_id = null;
        form.from_campus_id = null;
        form.to_campus_id = null;
        form.effective_at = '';
        form.effective_semester_id = null;
        form.decision_number = '';
        form.decision_signed_at = '';
        form.decision_signer = '';
        form.decision_id = null;
        form.defer_scope_type = 'FULL';
        form.defer_fee_policy = 'FORFEIT';
        form.defer_preserve_amount = null;
        form.defer_course_registration_ids = [];
        form.defer_egc_charge_ids = [];
        form.egc_defer_from_block_number =
            form.action_type === StudentActionType.ACADEMIC_DEFER && isEgcStudent.value ? 1 : null;

        if (form.action_type === StudentActionType.WAITING_COURSE_OPENING && isEgcStudent.value) {
            form.egc_defer_from_block_number = form.egc_defer_from_block_number ?? 1;
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
    () => [hasUnpaidEgcCharges.value, selectedActionType.value],
    () => {
        if (selectedActionType.value !== StudentActionType.ACADEMIC_DEFER || !isEgcStudent.value) {
            return;
        }

        if (hasUnpaidEgcCharges.value) {
            form.defer_fee_policy = 'FORFEIT';
        }
    },
);

const toggleCourseRegistration = (registrationId: number, checked: boolean): void => {
    const current = form.defer_course_registration_ids ?? [];
    form.defer_course_registration_ids = checked ? [...current, registrationId] : current.filter((id) => id !== registrationId);
};

const toggleEgcCharge = (chargeId: number, checked: boolean): void => {
    const current = form.defer_egc_charge_ids ?? [];
    form.defer_egc_charge_ids = checked ? [...current, chargeId] : current.filter((id) => id !== chargeId);
};

const handleSubmit = (): void => {
    form.post(route('students.actions.store', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Action recorded successfully');
            isDialogOpen.value = false;
            form.reset();
            form.student_id = props.student.id;
        },
        onError: () => {
            toast.error('Failed to record action. Please check the form.');
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogTrigger as-child>
            <Button>
                <Plus class="mr-2 h-4 w-4" />
                Record action
            </Button>
        </DialogTrigger>
        <DialogContent class="!max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Record action</DialogTitle>
                <DialogDescription>Create a new lifecycle action record for {{ student.full_name }}.</DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="handleSubmit">
                <!-- Action Type -->
                <div class="space-y-2">
                    <Label for="action_type">Action Type *</Label>
                    <Select v-model="form.action_type">
                        <SelectTrigger id="action_type">
                            <SelectValue placeholder="Select action type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in actionOptions.actionTypes" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.action_type" class="text-sm text-red-500">{{ form.errors.action_type }}</p>
                </div>

                <!-- ACADEMIC_DEFER -->
                <template v-if="selectedActionType === StudentActionType.ACADEMIC_DEFER">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="defer_from_semester">From Semester *</Label>
                            <Select v-model="form.from_semester_id" disabled>
                                <SelectTrigger id="defer_from_semester">
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="sem in selectableFromSemesters" :key="sem.id" :value="sem.id">
                                        {{ sem.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-muted-foreground text-xs">From semester defaults to the current semester.</p>
                            <p v-if="form.errors.from_semester_id" class="text-sm text-red-500">{{ form.errors.from_semester_id }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="defer_return_semester">Return Semester *</Label>
                            <Select v-model="form.return_semester_id">
                                <SelectTrigger id="defer_return_semester">
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="sem in actionOptions.semesters" :key="sem.id" :value="sem.id">
                                        {{ sem.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.return_semester_id" class="text-sm text-red-500">{{ form.errors.return_semester_id }}</p>
                        </div>
                    </div>

                    <div v-if="isEgcStudent" class="space-y-2">
                        <Label for="defer_egc_block">EGC Defer From Block *</Label>
                        <Select
                            :model-value="form.egc_defer_from_block_number ? String(form.egc_defer_from_block_number) : undefined"
                            @update:model-value="(value) => (form.egc_defer_from_block_number = value ? Number(value) : null)"
                        >
                            <SelectTrigger id="defer_egc_block">
                                <SelectValue placeholder="Select block" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="block in actionOptions.egcDeferBlocks ?? []" :key="block.value" :value="String(block.value)">
                                    {{ block.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.egc_defer_from_block_number" class="text-sm text-red-500">{{ form.errors.egc_defer_from_block_number }}</p>
                    </div>

                    <!-- Finance section -->
                    <div class="border-border bg-muted/30 mt-2 rounded-lg border p-4">
                        <h4 class="text-primary mb-3 font-semibold">Tài chính (Finance)</h4>

                        <div class="grid grid-cols-2 gap-4">
                            <div v-if="!isEgcStudent" class="space-y-2">
                                <Label for="defer_scope_type">Phạm vi bảo lưu *</Label>
                                <Select v-model="form.defer_scope_type">
                                    <SelectTrigger id="defer_scope_type">
                                        <SelectValue placeholder="Chọn phạm vi" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="scope in actionOptions.deferScopeTypes ?? []" :key="scope.value" :value="scope.value">
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

                            <div class="space-y-2">
                                <Label for="defer_fee_policy">Chính sách học phí *</Label>
                                <Select v-model="form.defer_fee_policy" :disabled="isEgcStudent && hasUnpaidEgcCharges">
                                    <SelectTrigger id="defer_fee_policy">
                                        <SelectValue placeholder="Chọn chính sách" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="policy in actionOptions.deferFeePolicies ?? []" :key="policy.value" :value="policy.value">
                                            {{ policy.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.defer_fee_policy" class="text-sm text-red-500">{{ form.errors.defer_fee_policy }}</p>
                                <p v-else-if="isEgcStudent && hasUnpaidEgcCharges" class="text-sm text-amber-600">
                                    Chưa có thanh toán cho học phí EGC, mặc định mất học phí.
                                </p>
                            </div>
                        </div>

                        <div v-if="form.defer_fee_policy === 'PARTIAL'" class="mt-4 space-y-2">
                            <Label for="defer_preserve_amount">Số tiền bảo lưu (VNĐ) *</Label>
                            <Input id="defer_preserve_amount" v-model.number="form.defer_preserve_amount" type="number" min="0" placeholder="Nhập số tiền bảo lưu" />
                            <p v-if="form.errors.defer_preserve_amount" class="text-sm text-red-500">{{ form.errors.defer_preserve_amount }}</p>
                        </div>

                        <div v-if="!isEgcStudent && form.defer_scope_type === 'COURSES'" class="mt-4 space-y-2">
                            <Label>Chọn môn học bảo lưu *</Label>
                            <div v-if="filteredCourseRegistrations.length === 0" class="text-muted-foreground text-sm">Không có môn học nào trong kỳ đã chọn.</div>
                            <div v-else class="max-h-48 space-y-2 overflow-y-auto rounded border p-2">
                                <div v-for="reg in filteredCourseRegistrations" :key="reg.id" class="flex items-center space-x-2">
                                    <Checkbox
                                        :id="`course-${reg.id}`"
                                        :model-value="form.defer_course_registration_ids?.includes(reg.id)"
                                        :disabled="reg.registration_status === 'defer'"
                                        @update:model-value="(checked) => toggleCourseRegistration(reg.id, checked === true)"
                                    />
                                    <Label
                                        :for="`course-${reg.id}`"
                                        class="text-sm"
                                        :class="reg.registration_status === 'defer' ? 'text-muted-foreground cursor-not-allowed' : 'cursor-pointer'"
                                    >
                                        <span class="font-medium">{{ reg.course_code }}</span> - {{ reg.course_name }}
                                        <span class="text-muted-foreground text-xs">({{ reg.semester_name }})</span>
                                        <span v-if="reg.registration_status === 'defer'" class="ml-2 text-xs text-amber-600">(Đã bảo lưu)</span>
                                    </Label>
                                </div>
                            </div>
                            <p v-if="form.errors.defer_course_registration_ids" class="text-sm text-red-500">{{ form.errors.defer_course_registration_ids }}</p>
                        </div>

                        <div v-if="isEgcStudent" class="mt-4 space-y-2">
                            <Label>Học phí EGC đã đóng (theo level)</Label>
                            <div v-if="filteredEgcCharges.length === 0" class="text-muted-foreground text-sm">Chưa có học phí EGC trong kỳ hiện tại.</div>
                            <div v-else class="max-h-48 space-y-2 overflow-y-auto rounded border p-2">
                                <div v-for="charge in filteredEgcCharges" :key="charge.id" class="flex items-center space-x-2">
                                    <Checkbox
                                        :id="`egc-charge-${charge.id}`"
                                        :model-value="form.defer_egc_charge_ids?.includes(charge.id)"
                                        :disabled="form.defer_fee_policy === 'FORFEIT'"
                                        @update:model-value="(checked) => toggleEgcCharge(charge.id, checked === true)"
                                    />
                                    <Label :for="`egc-charge-${charge.id}`" class="text-sm">
                                        {{ charge.description ?? 'EGC Level Fee' }} - {{ formatCurrency(charge.amount) }}
                                    </Label>
                                </div>
                            </div>
                            <p v-if="form.errors.defer_egc_charge_ids" class="text-sm text-red-500">{{ form.errors.defer_egc_charge_ids }}</p>
                        </div>
                    </div>
                </template>

                <!-- ACADEMIC_RESUME -->
                <template v-if="selectedActionType === StudentActionType.ACADEMIC_RESUME">
                    <div class="space-y-2">
                        <Label for="resume_return_semester">Return Semester *</Label>
                        <Select v-model="form.return_semester_id">
                            <SelectTrigger id="resume_return_semester">
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sem in actionOptions.semesters" :key="sem.id" :value="sem.id">
                                    {{ sem.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.return_semester_id" class="text-sm text-red-500">{{ form.errors.return_semester_id }}</p>
                    </div>
                </template>

                <!-- WAITING_COURSE_OPENING -->
                <template v-if="selectedActionType === StudentActionType.WAITING_COURSE_OPENING">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="waiting_from_semester">From Semester *</Label>
                            <Select v-model="form.from_semester_id">
                                <SelectTrigger id="waiting_from_semester">
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="sem in actionOptions.semesters" :key="sem.id" :value="sem.id" :disabled="isSemesterBeforeActive(sem)">
                                        {{ sem.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.from_semester_id" class="text-sm text-red-500">{{ form.errors.from_semester_id }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="waiting_block">Block *</Label>
                            <Select
                                :model-value="form.egc_defer_from_block_number ? String(form.egc_defer_from_block_number) : undefined"
                                @update:model-value="(value) => (form.egc_defer_from_block_number = value ? Number(value) : null)"
                            >
                                <SelectTrigger id="waiting_block">
                                    <SelectValue placeholder="Select block" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="block in actionOptions.egcDeferBlocks ?? []" :key="block.value" :value="String(block.value)">
                                        {{ block.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.egc_defer_from_block_number" class="text-sm text-red-500">{{ form.errors.egc_defer_from_block_number }}</p>
                        </div>
                    </div>
                </template>

                <!-- ADMISSION_DEFERRAL -->
                <template v-if="selectedActionType === StudentActionType.ADMISSION_DEFERRAL">
                    <div class="space-y-2">
                        <Label for="intended_intake_semester">Intended Intake Semester *</Label>
                        <Select v-model="form.intended_intake_semester_id">
                            <SelectTrigger id="intended_intake_semester">
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sem in actionOptions.semesters" :key="sem.id" :value="sem.id">
                                    {{ sem.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.intended_intake_semester_id" class="text-sm text-red-500">{{ form.errors.intended_intake_semester_id }}</p>
                    </div>
                </template>

                <!-- ACADEMIC_DROPOUT -->
                <template v-if="selectedActionType === StudentActionType.ACADEMIC_DROPOUT">
                    <div class="space-y-2">
                        <Label for="dropout_semester">Dropout Semester *</Label>
                        <Select v-model="form.dropout_semester_id">
                            <SelectTrigger id="dropout_semester">
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sem in actionOptions.semesters" :key="sem.id" :value="sem.id">
                                    {{ sem.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.dropout_semester_id" class="text-sm text-red-500">{{ form.errors.dropout_semester_id }}</p>
                    </div>
                </template>

                <!-- CAMPUS_TRANSFER -->
                <template v-if="selectedActionType === StudentActionType.CAMPUS_TRANSFER">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="from_campus">From Campus *</Label>
                            <Select v-model="form.from_campus_id">
                                <SelectTrigger id="from_campus">
                                    <SelectValue placeholder="Select current campus" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="campus in actionOptions.campuses" :key="campus.id" :value="campus.id">
                                        {{ campus.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.from_campus_id" class="text-sm text-red-500">{{ form.errors.from_campus_id }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="to_campus">To Campus *</Label>
                            <Select v-model="form.to_campus_id">
                                <SelectTrigger id="to_campus">
                                    <SelectValue placeholder="Select target campus" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="campus in actionOptions.campuses.filter((c) => c.id !== form.from_campus_id)"
                                        :key="campus.id"
                                        :value="campus.id"
                                    >
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
                            <Input id="effective_at" v-model="form.effective_at" type="datetime-local" />
                            <p v-if="form.errors.effective_at" class="text-sm text-red-500">{{ form.errors.effective_at }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="effective_semester">Effective Semester (Optional)</Label>
                            <Select v-model="form.effective_semester_id">
                                <SelectTrigger id="effective_semester">
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="sem in actionOptions.semesters" :key="sem.id" :value="sem.id">
                                        {{ sem.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.effective_semester_id" class="text-sm text-red-500">{{ form.errors.effective_semester_id }}</p>
                        </div>
                    </div>
                </template>

                <!-- Common: reason -->
                <div class="space-y-2">
                    <Label for="reason">Reason *</Label>
                    <Textarea id="reason" v-model="form.reason" rows="3" placeholder="Enter the reason for this action..." />
                    <p v-if="form.errors.reason" class="text-sm text-red-500">{{ form.errors.reason }}</p>
                </div>

                <!-- Authorizing decision -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="decision_id">Linked Decision (Optional)</Label>
                        <Select
                            :model-value="form.decision_id ? String(form.decision_id) : 'none'"
                            @update:model-value="(value) => (form.decision_id = value === 'none' ? null : Number(value))"
                        >
                            <SelectTrigger id="decision_id">
                                <SelectValue placeholder="Select decision" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">No linked decision</SelectItem>
                                <SelectItem v-for="decision in actionOptions.studentDecisions ?? []" :key="decision.id" :value="String(decision.id)">
                                    {{ decision.decision_number }} - {{ decision.decision_name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.decision_id" class="text-sm text-red-500">{{ form.errors.decision_id }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="decision_signed_at">Decision Signed Date (Optional)</Label>
                        <Input id="decision_signed_at" v-model="form.decision_signed_at" type="date" />
                        <p v-if="form.errors.decision_signed_at" class="text-sm text-red-500">{{ form.errors.decision_signed_at }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="decision_number">Decision Number (Optional)</Label>
                        <Input id="decision_number" v-model="form.decision_number" placeholder="Decision number..." />
                        <p v-if="form.errors.decision_number" class="text-sm text-red-500">{{ form.errors.decision_number }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="decision_signer">Decision Signer (Optional)</Label>
                        <Input id="decision_signer" v-model="form.decision_signer" placeholder="Signer name..." />
                        <p v-if="form.errors.decision_signer" class="text-sm text-red-500">{{ form.errors.decision_signer }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="signed_at">Signed At (Optional)</Label>
                        <Input id="signed_at" v-model="form.signed_at" type="date" />
                        <p v-if="form.errors.signed_at" class="text-sm text-red-500">{{ form.errors.signed_at }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="notes">Notes (Optional)</Label>
                        <Input id="notes" v-model="form.notes" placeholder="Internal notes..." />
                        <p v-if="form.errors.notes" class="text-sm text-red-500">{{ form.errors.notes }}</p>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <Checkbox id="missing_documents" v-model:model-value="form.missing_documents" />
                    <Label for="missing_documents" class="cursor-pointer">Bổ sung hồ sơ sau (Missing documents)</Label>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isDialogOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="form.processing || !form.action_type">Record Action</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
