<script setup lang="ts">
import FileUpload from '@/components/FileUpload.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { UploadedFile } from '@/types/fileUpload';
import { getActionTypeBadgeClass, getActionTypeLabel, StudentActionType, type ActionTypeOption, type Campus, type EgcDeferBlockOption, type Semester, type StudentActionLog } from '@/types/student-action';
import { studentRoutes } from '@/utils/routes';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Calendar, Download, FileText, Loader2, Paperclip, Pencil, Upload, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    actionLog: StudentActionLog;
    options: {
        actionTypes: ActionTypeOption[];
        semesters: Semester[];
        campuses: Campus[];
        egcDeferBlocks?: EgcDeferBlockOption[];
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

const isEgcDeferAction = computed(() => {
    return props.actionLog.action_type === StudentActionType.ACADEMIC_DEFER && (props.actionLog.previous_status === 'intake_pre_uni_gc' || props.actionLog.egc_defer_from_block_number !== null);
});

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

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Upload functionality
const uploadedFiles = ref<UploadedFile[]>([]);
const markDocumentsComplete = ref(false);
const isSubmitting = ref(false);
const uploadComponentKey = ref(0);

const canUpload = computed(() => props.actionLog.missing_documents);

const submitAttachments = () => {
    const uploadedIds = uploadedFiles.value.map((f) => f.id);

    if (uploadedIds.length === 0) {
        toast.error('No files have been uploaded yet.');
        return;
    }

    isSubmitting.value = true;

    router.post(
        studentRoutes.studentStatusActionAttachmentsStore(props.actionLog.id),
        {
            attachment_ids: uploadedIds,
            mark_documents_complete: markDocumentsComplete.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(markDocumentsComplete.value ? 'Attachments uploaded and documents marked as complete.' : 'Attachments uploaded successfully.');
                uploadedFiles.value = [];
                markDocumentsComplete.value = false;
                uploadComponentKey.value++; // Reset file upload component
            },
            onError: () => {
                toast.error('Failed to save attachments. Please try again.');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

// Edit Functionality
const isEditOpen = ref(false);

const editForm = useForm({
    reason: '',
    notes: '',
    decision_number: '',
    decision_signed_at: '',
    decision_signer: '',
    decision_id: null as number | null,
    missing_documents: false,
    from_semester_id: null as number | null,
    return_semester_id: null as number | null,
    egc_defer_from_block_number: null as number | null,
    intended_intake_semester_id: null as number | null,
    dropout_semester_id: null as number | null,
    from_campus_id: null as number | null,
    to_campus_id: null as number | null,
    effective_at: '',
    effective_semester_id: null as number | null,
});

// Helper to format datetime for input
const toDateTimeLocal = (dateStr: string | null | undefined) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    // Format to YYYY-MM-DDThh:mm
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const openEdit = () => {
    editForm.reason = props.actionLog.reason;
    editForm.notes = props.actionLog.notes || '';
    editForm.decision_number = props.actionLog.decision_number || '';
    editForm.decision_signed_at = props.actionLog.decision_signed_at ? props.actionLog.decision_signed_at.toString().substring(0, 10) : '';
    editForm.decision_signer = props.actionLog.decision_signer || '';
    editForm.decision_id = props.actionLog.decision_id ?? null;
    editForm.missing_documents = !!props.actionLog.missing_documents;
    editForm.from_semester_id = props.actionLog.from_semester_id ?? null;
    editForm.return_semester_id = props.actionLog.return_semester_id ?? null;
    editForm.egc_defer_from_block_number = props.actionLog.egc_defer_from_block_number ?? null;
    editForm.intended_intake_semester_id = props.actionLog.intended_intake_semester_id ?? null;
    editForm.dropout_semester_id = props.actionLog.dropout_semester_id ?? null;
    editForm.from_campus_id = props.actionLog.from_campus_id ?? null;
    editForm.to_campus_id = props.actionLog.to_campus_id ?? null;
    editForm.effective_at = toDateTimeLocal(props.actionLog.effective_at);
    editForm.effective_semester_id = props.actionLog.effective_semester_id ?? null;

    isEditOpen.value = true;
};

const handleUpdate = () => {
    editForm.put(studentRoutes.studentStatusActionUpdate(props.actionLog.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Action updated successfully');
            isEditOpen.value = false;
        },
        onError: () => {
            toast.error('Failed to update action. Please check the form.');
        },
    });
};
</script>

<template>
    <Head :title="`Action Details - ${actionLog.student?.full_name}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Link :href="route('students.actions.index', { student: actionLog.student_id })">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to History
                    </Button>
                </Link>
                <Button variant="outline" size="sm" @click="openEdit">
                    <Pencil class="mr-2 h-4 w-4" />
                    Edit Action
                </Button>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Action Details</h1>
                <p class="text-muted-foreground mt-1">{{ actionLog.student?.full_name }} ({{ actionLog.student?.student_id }})</p>
            </div>
            <div class="flex items-center gap-2">
                <Badge :class="getActionTypeBadgeClass(actionLog.action_type)" class="px-4 py-2 text-lg">
                    {{ getActionTypeLabel(actionLog.action_type) }}
                </Badge>
                <Badge v-if="actionLog.action_type === StudentActionType.ACADEMIC_DEFER && actionLog.defer_case?.scope_type" variant="outline">
                    {{ actionLog.defer_case.scope_type === 'COURSES' ? 'Course' : 'Toàn kỳ' }}
                </Badge>
                <Badge v-if="actionLog.egc_defer_from_block_number" variant="outline"> Block {{ actionLog.egc_defer_from_block_number }} </Badge>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Main Info -->
            <Card>
                <CardHeader>
                    <CardTitle>Action Information</CardTitle>
                    <CardDescription>{{ getSummary(actionLog) }}</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-muted-foreground text-sm">Changed At</p>
                            <p class="flex items-center font-medium">
                                <Calendar class="mr-2 h-4 w-4" />
                                {{ formatDate(actionLog.created_at) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm">Changed By</p>
                            <p class="flex items-center font-medium">
                                <User class="mr-2 h-4 w-4" />
                                {{ actionLog.changed_by?.name ?? 'Unknown' }}
                            </p>
                        </div>
                    </div>

                    <div v-if="actionLog.decision_number">
                        <p class="text-muted-foreground text-sm">Decision Number</p>
                        <p class="font-medium">{{ actionLog.decision_number }}</p>
                    </div>

                    <div v-if="actionLog.decision_signed_at">
                        <p class="text-muted-foreground text-sm">Decision Signed Date</p>
                        <p class="font-medium">{{ formatDateOnly(actionLog.decision_signed_at) }}</p>
                    </div>

                    <div v-if="actionLog.decision_signer">
                        <p class="text-muted-foreground text-sm">Decision Signer</p>
                        <p class="font-medium">{{ actionLog.decision_signer }}</p>
                    </div>

                    <div v-if="actionLog.decision">
                        <p class="text-muted-foreground text-sm">Linked Decision</p>
                        <p class="font-medium">{{ actionLog.decision.decision_number }} - {{ actionLog.decision.decision_name }}</p>
                    </div>

                    <div>
                        <p class="text-muted-foreground text-sm">Reason</p>
                        <p class="font-medium whitespace-pre-wrap">{{ actionLog.reason }}</p>
                    </div>

                    <div v-if="actionLog.notes">
                        <p class="text-muted-foreground text-sm">Notes</p>
                        <p class="font-medium">{{ actionLog.notes }}</p>
                    </div>

                    <div v-if="actionLog.missing_documents" class="rounded-lg bg-amber-50 p-3 dark:bg-amber-950">
                        <p class="text-sm font-medium text-amber-800 dark:text-amber-200">⚠️ This action is marked as having missing documents.</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Status Change -->
            <Card>
                <CardHeader>
                    <CardTitle>Status Change</CardTitle>
                    <CardDescription>Changes applied to the student record</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="actionLog.previous_status && actionLog.new_status && !(actionLog.action_type === StudentActionType.ACADEMIC_DEFER && actionLog.defer_case?.scope_type === 'COURSES')" class="flex items-center gap-4">
                        <div class="text-center">
                            <p class="text-muted-foreground mb-1 text-sm">Previous Status</p>
                            <Badge variant="outline" class="capitalize">{{ actionLog.previous_status }}</Badge>
                        </div>
                        <ArrowRight class="text-muted-foreground h-6 w-6" />
                        <div class="text-center">
                            <p class="text-muted-foreground mb-1 text-sm">New Status</p>
                            <Badge variant="outline" class="capitalize">{{ actionLog.new_status }}</Badge>
                        </div>
                    </div>

                    <!-- Action-specific details -->
                    <div v-if="actionLog.action_type === StudentActionType.ACADEMIC_DEFER" class="space-y-2">
                        <div>
                            <p class="text-muted-foreground text-sm">From Semester</p>
                            <p class="font-medium">{{ actionLog.from_semester?.name ?? '-' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm">Return Semester</p>
                            <p class="font-medium">{{ actionLog.return_semester?.name ?? '-' }}</p>
                        </div>
                        <div v-if="actionLog.egc_defer_from_block_number">
                            <p class="text-muted-foreground text-sm">EGC From Block</p>
                            <p class="font-medium">Block {{ actionLog.egc_defer_from_block_number }}</p>
                        </div>
                    </div>

                    <div v-if="actionLog.action_type === StudentActionType.ACADEMIC_RESUME">
                        <p class="text-muted-foreground text-sm">Return Semester</p>
                        <p class="font-medium">{{ actionLog.return_semester?.name ?? '-' }}</p>
                    </div>

                    <div v-if="actionLog.action_type === StudentActionType.ADMISSION_DEFERRAL">
                        <p class="text-muted-foreground text-sm">Intended Intake Semester</p>
                        <p class="font-medium">{{ actionLog.intended_intake_semester?.name ?? '-' }}</p>
                    </div>

                    <div v-if="actionLog.action_type === StudentActionType.ACADEMIC_DROPOUT">
                        <p class="text-muted-foreground text-sm">Dropout Semester</p>
                        <p class="font-medium">{{ actionLog.dropout_semester?.name ?? '-' }}</p>
                    </div>

                    <div v-if="actionLog.action_type === StudentActionType.CAMPUS_TRANSFER" class="space-y-2">
                        <div class="flex items-center gap-4">
                            <div class="text-center">
                                <p class="text-muted-foreground mb-1 text-sm">From Campus</p>
                                <Badge variant="outline">{{ actionLog.from_campus?.name ?? '-' }}</Badge>
                            </div>
                            <ArrowRight class="text-muted-foreground h-6 w-6" />
                            <div class="text-center">
                                <p class="text-muted-foreground mb-1 text-sm">To Campus</p>
                                <Badge variant="outline">{{ actionLog.to_campus?.name ?? '-' }}</Badge>
                            </div>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm">Effective At</p>
                            <p class="font-medium">{{ formatDate(actionLog.effective_at) }}</p>
                        </div>
                        <div v-if="actionLog.effective_semester">
                            <p class="text-muted-foreground text-sm">Effective Semester</p>
                            <p class="font-medium">{{ actionLog.effective_semester.name }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Attachments -->
        <Card v-if="actionLog.attachments && actionLog.attachments.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center">
                    <Paperclip class="mr-2 h-5 w-5" />
                    Attachments ({{ actionLog.attachments.length }})
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="attachment in actionLog.attachments" :key="attachment.id" class="flex items-center justify-between rounded-lg border p-3">
                        <div class="flex items-center gap-2 overflow-hidden">
                            <FileText class="text-muted-foreground h-5 w-5 flex-shrink-0" />
                            <div class="overflow-hidden">
                                <p class="truncate text-sm font-medium">{{ attachment.original_name }}</p>
                                <p class="text-muted-foreground text-xs">{{ formatFileSize(attachment.size) }}</p>
                            </div>
                        </div>
                        <a :href="attachment.url" target="_blank" class="flex-shrink-0">
                            <Button variant="ghost" size="sm">
                                <Download class="h-4 w-4" />
                            </Button>
                        </a>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Upload Additional Documents (only when missing_documents=true) -->
        <Card v-if="canUpload">
            <CardHeader>
                <CardTitle class="flex items-center">
                    <Upload class="mr-2 h-5 w-5" />
                    Upload Additional Documents
                </CardTitle>
                <CardDescription> This action is marked as having missing documents. Upload the required files below. </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <FileUpload
                    :key="uploadComponentKey"
                    context="action_attachment"
                    multiple
                    v-model="uploadedFiles"
                    :allowed-types="['image/jpeg', 'image/png', 'application/pdf']"
                    accept=".jpg,.jpeg,.png,.pdf"
                    label="Drop files here or click to select (JPG, PNG, PDF)"
                />

                <!-- Actions -->
                <div v-if="uploadedFiles.length > 0" class="space-y-4 border-t pt-4">
                    <div class="flex items-center space-x-2">
                        <Checkbox id="mark_complete" v-model:model-value="markDocumentsComplete" />
                        <Label for="mark_complete" class="cursor-pointer"> Mark documents as complete (remove missing documents flag) </Label>
                    </div>

                    <div class="flex justify-end">
                        <Button @click="submitAttachments" :disabled="isSubmitting">
                            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            Save Attachments
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Edit Dialog -->
    <Dialog v-model:open="isEditOpen">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Edit Action Record</DialogTitle>
                <DialogDescription> Update the details of this action. Note that the action type cannot be changed. </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleUpdate" class="space-y-4">
                <!-- Action Type (Disabled) -->
                <div class="space-y-2">
                    <Label>Action Type</Label>
                    <div class="border-input bg-muted ring-offset-background flex h-10 w-full items-center rounded-md border px-3 py-2 text-sm">
                        {{ getActionTypeLabel(actionLog.action_type) }}
                    </div>
                </div>

                <!-- ACADEMIC_DEFER fields -->
                <template v-if="actionLog.action_type === StudentActionType.ACADEMIC_DEFER">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="from_semester_id">From Semester *</Label>
                            <Select v-model="editForm.from_semester_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id">
                                        {{ sem.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="editForm.errors.from_semester_id" class="text-sm text-red-500">{{ editForm.errors.from_semester_id }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="return_semester_id">Return Semester *</Label>
                            <Select v-model="editForm.return_semester_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id">
                                        {{ sem.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="editForm.errors.return_semester_id" class="text-sm text-red-500">{{ editForm.errors.return_semester_id }}</p>
                        </div>
                    </div>
                    <div v-if="isEgcDeferAction" class="space-y-2">
                        <Label for="egc_defer_from_block_number">EGC Defer From Block *</Label>
                        <Select :model-value="editForm.egc_defer_from_block_number ? String(editForm.egc_defer_from_block_number) : undefined" @update:model-value="(value) => (editForm.egc_defer_from_block_number = Number(value))">
                            <SelectTrigger>
                                <SelectValue placeholder="Select block" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="block in options.egcDeferBlocks ?? []" :key="block.value" :value="String(block.value)">
                                    {{ block.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="editForm.errors.egc_defer_from_block_number" class="text-sm text-red-500">{{ editForm.errors.egc_defer_from_block_number }}</p>
                    </div>
                </template>

                <!-- ACADEMIC_RESUME fields -->
                <template v-if="actionLog.action_type === StudentActionType.ACADEMIC_RESUME">
                    <div class="space-y-2">
                        <Label for="return_semester_id">Return Semester *</Label>
                        <Select v-model="editForm.return_semester_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id">
                                    {{ sem.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="editForm.errors.return_semester_id" class="text-sm text-red-500">{{ editForm.errors.return_semester_id }}</p>
                    </div>
                </template>

                <!-- ADMISSION_DEFERRAL fields -->
                <template v-if="actionLog.action_type === StudentActionType.ADMISSION_DEFERRAL">
                    <div class="space-y-2">
                        <Label for="intended_intake_semester_id">Intended Intake Semester *</Label>
                        <Select v-model="editForm.intended_intake_semester_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id">
                                    {{ sem.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="editForm.errors.intended_intake_semester_id" class="text-sm text-red-500">
                            {{ editForm.errors.intended_intake_semester_id }}
                        </p>
                    </div>
                </template>

                <!-- ACADEMIC_DROPOUT fields -->
                <template v-if="actionLog.action_type === StudentActionType.ACADEMIC_DROPOUT">
                    <div class="space-y-2">
                        <Label for="dropout_semester_id">Dropout Semester *</Label>
                        <Select v-model="editForm.dropout_semester_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sem in options.semesters" :key="sem.id" :value="sem.id">
                                    {{ sem.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="editForm.errors.dropout_semester_id" class="text-sm text-red-500">{{ editForm.errors.dropout_semester_id }}</p>
                    </div>
                </template>

                <!-- CAMPUS_TRANSFER fields -->
                <template v-if="actionLog.action_type === StudentActionType.CAMPUS_TRANSFER">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="from_campus_id">From Campus</Label>
                            <Select v-model="editForm.from_campus_id" disabled>
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
                            <Select v-model="editForm.to_campus_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select target campus" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="campus in options.campuses" :key="campus.id" :value="campus.id">
                                        {{ campus.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="editForm.errors.to_campus_id" class="text-sm text-red-500">{{ editForm.errors.to_campus_id }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="effective_at">Effective Date *</Label>
                            <Input type="datetime-local" v-model="editForm.effective_at" />
                            <p v-if="editForm.errors.effective_at" class="text-sm text-red-500">{{ editForm.errors.effective_at }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="effective_semester_id">Effective Semester (Optional)</Label>
                            <Select v-model="editForm.effective_semester_id">
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
                    <Textarea v-model="editForm.reason" placeholder="Enter the reason for this action..." rows="3" />
                    <p v-if="editForm.errors.reason" class="text-sm text-red-500">{{ editForm.errors.reason }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="decision_id">Linked Decision (Optional)</Label>
                        <Select :model-value="editForm.decision_id ? String(editForm.decision_id) : 'none'" @update:model-value="(value) => (editForm.decision_id = value === 'none' ? null : Number(value))">
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
                        <Input type="date" v-model="editForm.decision_signed_at" />
                    </div>
                    <div class="space-y-2">
                        <Label for="decision_number">Decision Number (Optional)</Label>
                        <Input v-model="editForm.decision_number" placeholder="Decision number..." />
                    </div>
                    <div class="space-y-2">
                        <Label for="decision_signer">Decision Signer (Optional)</Label>
                        <Input v-model="editForm.decision_signer" placeholder="Signer name..." />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="notes">Notes (Optional)</Label>
                    <Input v-model="editForm.notes" placeholder="Internal notes..." />
                </div>

                <div class="flex items-center space-x-2">
                    <Checkbox id="edit_missing_documents" v-model:model-value="editForm.missing_documents" />
                    <Label for="edit_missing_documents" class="cursor-pointer">Bổ sung hồ sơ sau (Missing documents)</Label>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isEditOpen = false"> Cancel </Button>
                    <Button type="submit" :disabled="editForm.processing"> Update Action </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
