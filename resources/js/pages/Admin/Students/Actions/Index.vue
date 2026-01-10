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
import { getActionTypeBadgeClass, getActionTypeLabel, StudentActionType, type ActionTypeOption, type Campus, type Semester, type StoreStudentActionForm, type StudentActionLog } from '@/types/student-action';
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
        campuses: Campus[];
    };
}

const props = defineProps<Props>();

const isDialogOpen = ref(false);

const form = useForm<StoreStudentActionForm>({
    student_id: props.student.id,
    action_type: '',
    reason: '',
    notes: '',
    signed_at: '',
    missing_documents: true,
    attachment_ids: [],
    from_semester_id: null,
    return_semester_id: null,
    intended_intake_semester_id: null,
    dropout_semester_id: null,
    from_campus_id: props.student.campus_id,
    to_campus_id: null,
    effective_at: '',
    effective_semester_id: null,
});

const selectedActionType = computed(() => {
    return form.action_type ? (form.action_type as StudentActionType) : null;
});

// Reset form fields when action type changes
watch(
    () => form.action_type,
    () => {
        form.from_semester_id = null;
        form.return_semester_id = null;
        form.intended_intake_semester_id = null;
        form.dropout_semester_id = null;
        form.to_campus_id = null;
        form.effective_at = '';
        form.effective_semester_id = null;
        // Keep from_campus_id as student's current campus
        form.from_campus_id = props.student.campus_id;
    },
);

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
        case StudentActionType.ACADEMIC_DEFER:
            return `Defer from ${log.from_semester?.name ?? 'N/A'} to ${log.return_semester?.name ?? 'N/A'}`;
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
                <DialogContent class="max-w-2xl">
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
                                    <SelectItem v-for="actionType in options.actionTypes" :key="actionType.value" :value="actionType.value">
                                        {{ actionType.label }}
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
                                    <Select v-model="form.from_semester_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select semester" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id">
                                                {{ sem.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
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
                                <Label for="signed_at">Signed Date (Optional)</Label>
                                <Input type="date" v-model="form.signed_at" />
                            </div>
                            <div class="space-y-2">
                                <Label for="notes">Notes (Optional)</Label>
                                <Input v-model="form.notes" placeholder="Internal notes..." />
                            </div>
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
                                    <span v-if="log.signed_at" class="flex items-center">
                                        <FileText class="mr-1 h-3 w-3" />
                                        Signed: {{ formatDateOnly(log.signed_at) }}
                                    </span>
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
