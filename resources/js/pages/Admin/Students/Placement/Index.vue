<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import FileUpload from '@/components/FileUpload.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { UploadedFile } from '@/types/fileUpload';
import { formatDateToShort } from '@/utils/date';
import { studentRoutes } from '@/utils/routes';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, ArrowRight, CheckCircle, FileText, GraduationCap, Plus, TrendingUp, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Semester {
    id: number;
    name: string;
    code: string;
}

interface IeltsCertificate {
    id: number;
    overall_score: number;
    submitted_at: string;
    missing_documents: boolean;
    issue_date: string | null;
    notes: string | null;
    upload_record: { id: number; url: string } | null;
    formatted_submitted_at: string;
    status_label: string;
    status_color: string;
}

interface ProgressionEvent {
    id: number;
    event_type: { value: string; labelEn: string };
    trigger_source: { value: string; label: string };
    from_course_stage: string | null;
    to_course_stage: string | null;
    from_english_level: number | null;
    to_english_level: number | null;
    effective_at: string;
    summary: string;
    notes: string | null;
    semester: Semester;
    created_by: { id: number; name: string } | null;
    ielts_certificate: IeltsCertificate | null;
}

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    status: string;
    status_label: string;
    gc_current_level: number | null;
    gc_starting_level: number | null;
    campus: { id: number; name: string };
}

interface Props {
    student: Student;
    progressionEvents: {
        data: ProgressionEvent[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
        links: { url: string | null; label: string; active: boolean }[];
    };
    ieltsCertificates: IeltsCertificate[];
    latestIelts: IeltsCertificate | null;
    canTransitionToIntake: boolean;
    filters: {
        event_type: string | null;
        per_page: number;
    };
    options: {
        eventTypes: { value: string; label: string; labelEn: string }[];
        triggerSources: { value: string; label: string }[];
        semesters: Semester[];
        englishLevels: { value: number; label: string }[];
        ieltsScoreThreshold: number;
    };
}

const props = withDefaults(defineProps<Props>(), {
    progressionEvents: () => ({
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: null,
        to: null,
        prev_page_url: null,
        next_page_url: null,
        links: [],
    }),
    ieltsCertificates: () => [],
    latestIelts: null,
    canTransitionToIntake: false,
    filters: () => ({ event_type: null, per_page: 10 }),
    options: () => ({
        eventTypes: [],
        triggerSources: [],
        semesters: [],
        englishLevels: [],
        ieltsScoreThreshold: 6.5,
    }),
});

// Dialog states
const isPlacementDialogOpen = ref(false);
const isIeltsDialogOpen = ref(false);
const isLevelDialogOpen = ref(false);
const isTransitionDialogOpen = ref(false);
const isUploadDocumentDialogOpen = ref(false);
const selectedCertificateForUpload = ref<IeltsCertificate | null>(null);

// Upload states
const placementUploadedFiles = ref<UploadedFile[]>([]);
const ieltsUploadedFiles = ref<UploadedFile[]>([]);
const documentUploadFiles = ref<UploadedFile[]>([]);

// Watch uploaded files to update form
watch(placementUploadedFiles, (files) => {
    placementForm.upload_record_id = files.length > 0 ? files[0].id : null;
    // If file uploaded, uncheck missing_documents
    if (files.length > 0) {
        placementForm.missing_documents = false;
    }
});

watch(ieltsUploadedFiles, (files) => {
    ieltsForm.upload_record_id = files.length > 0 ? files[0].id : null;
    // If file uploaded, uncheck missing_documents
    if (files.length > 0) {
        ieltsForm.missing_documents = false;
    }
});

// Forms
const placementForm = useForm({
    student_id: props.student.id,
    semester_id: '',
    has_ielts: false,
    ielts_score: '',
    upload_record_id: null as number | null,
    missing_documents: false,
    english_level: '',
    trigger_source: 'manual_admin',
    notes: '',
});

const ieltsForm = useForm({
    student_id: props.student.id,
    overall_score: '',
    semester_id: '',
    upload_record_id: null as number | null,
    missing_documents: false,
    issue_date: '',
    notes: '',
});

const levelForm = useForm({
    student_id: props.student.id,
    new_level: '',
    semester_id: '',
    trigger_source: 'manual_admin',
    notes: '',
});

const transitionForm = useForm({
    student_id: props.student.id,
    semester_id: '',
    ielts_certificate_id: '',
    allow_missing_documents: false,
    notes: '',
});

// Computed
const isPreIntake = computed(() => props.student.status === 'intake_pre_uni_gc');
const isIntakeCourse = computed(() => props.student.status === 'intake_course');
const isPending = computed(() => props.student.status === 'pending');

const qualifiedIeltsCertificates = computed(() => {
    return props.ieltsCertificates.filter((cert) => cert.overall_score >= props.options.ieltsScoreThreshold);
});

const availableLevels = computed(() => {
    const currentLevel = props.student.gc_current_level ?? 0;
    return props.options.englishLevels.filter((level) => level.value > currentLevel);
});

// Handlers
const handlePlacementSubmit = () => {
    placementForm.post(studentRoutes.studentPlacementInitialize(props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Placement initialized successfully');
            isPlacementDialogOpen.value = false;
            placementForm.reset();
            placementUploadedFiles.value = [];
        },
        onError: () => {
            toast.error('Failed to initialize placement');
        },
    });
};

const handleIeltsSubmit = () => {
    ieltsForm.post(studentRoutes.studentPlacementIeltsStore(props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('IELTS certificate recorded successfully');
            isIeltsDialogOpen.value = false;
            ieltsForm.reset();
            ieltsUploadedFiles.value = [];
        },
        onError: () => {
            toast.error('Failed to record IELTS certificate');
        },
    });
};

const handleLevelSubmit = () => {
    levelForm.post(studentRoutes.studentPlacementLevelUpdate(props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('English level updated successfully');
            isLevelDialogOpen.value = false;
            levelForm.reset();
        },
        onError: () => {
            toast.error('Failed to update English level');
        },
    });
};

const handleTransitionSubmit = () => {
    transitionForm.post(studentRoutes.studentPlacementTransition(props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Student transitioned to Intake Course successfully');
            isTransitionDialogOpen.value = false;
            transitionForm.reset();
        },
        onError: () => {
            toast.error('Failed to transition student');
        },
    });
};

// Document upload for existing IELTS certificate
const documentUploadForm = useForm({
    upload_record_id: null as number | null,
});

watch(documentUploadFiles, (files) => {
    documentUploadForm.upload_record_id = files.length > 0 ? files[0].id : null;
});

const openUploadDocumentDialog = (cert: IeltsCertificate) => {
    selectedCertificateForUpload.value = cert;
    documentUploadFiles.value = [];
    documentUploadForm.reset();
    isUploadDocumentDialogOpen.value = true;
};

const handleDocumentUploadSubmit = () => {
    if (!selectedCertificateForUpload.value) return;

    documentUploadForm.post(studentRoutes.ieltsCertificateDocumentUpload(selectedCertificateForUpload.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('IELTS document uploaded successfully');
            isUploadDocumentDialogOpen.value = false;
            selectedCertificateForUpload.value = null;
            documentUploadFiles.value = [];
            documentUploadForm.reset();
        },
        onError: () => {
            toast.error('Failed to upload document');
        },
    });
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, { preserveState: true, preserveScroll: true });
};

const getEventTypeBadgeVariant = (eventType: string) => {
    switch (eventType) {
        case 'PLACEMENT_INITIALIZED':
            return 'default';
        case 'ENGLISH_LEVEL_CHANGED':
            return 'secondary';
        case 'COURSE_STAGE_CHANGED':
            return 'success';
        case 'IELTS_RECORDED':
            return 'outline';
        default:
            return 'default';
    }
};

const getStageBadgeVariant = (stage: string) => {
    return stage === 'intake_course' ? 'success' : 'warning';
};
</script>

<template>
    <Head :title="`Placement & Progression - ${student.full_name}`" />

    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="icon" as-child>
                    <Link :href="studentRoutes.show(student.id)">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Academic Placement & Progression</h1>
                    <p class="text-muted-foreground">{{ student.full_name }} ({{ student.student_id }}) - {{ student.campus?.name ?? 'N/A' }}</p>
                </div>
            </div>

            <div class="flex gap-2">
                <!-- Initialize Placement (only for pending students) -->
                <Dialog v-if="isPending" v-model:open="isPlacementDialogOpen">
                    <DialogTrigger as-child>
                        <Button>
                            <Plus class="mr-2 h-4 w-4" />
                            Initialize Placement
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Initialize Student Placement</DialogTitle>
                            <DialogDescription>Set the initial course stage and English level for this student.</DialogDescription>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="handlePlacementSubmit">
                            <div class="space-y-2">
                                <Label for="semester_id">Semester</Label>
                                <Select v-model="placementForm.semester_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="semester in options.semesters" :key="semester.id" :value="String(semester.id)">
                                            {{ semester.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="has_ielts" v-model:checked="placementForm.has_ielts" />
                                <Label for="has_ielts">Student has IELTS certificate</Label>
                            </div>

                            <template v-if="placementForm.has_ielts">
                                <div class="space-y-2">
                                    <Label for="ielts_score">IELTS Overall Score</Label>
                                    <Input id="ielts_score" v-model="placementForm.ielts_score" type="number" step="0.5" min="0" max="9" placeholder="e.g., 6.5" />
                                </div>

                                <div class="space-y-2">
                                    <Label>IELTS Certificate Scan</Label>
                                    <FileUpload v-model="placementUploadedFiles" context="ielts_certificate" :upload-immediately="true" accept=".pdf,.jpg,.jpeg,.png" label="Drop IELTS certificate scan here or click to upload" />
                                </div>

                                <div class="flex items-center space-x-2">
                                    <Switch id="missing_documents" v-model:checked="placementForm.missing_documents" :disabled="placementUploadedFiles.length > 0" />
                                    <Label for="missing_documents" :class="{ 'text-muted-foreground': placementUploadedFiles.length > 0 }">Missing IELTS file scan (exception)</Label>
                                </div>
                            </template>

                            <template v-if="!placementForm.has_ielts || (placementForm.has_ielts && Number(placementForm.ielts_score) < options.ieltsScoreThreshold)">
                                <div class="space-y-2">
                                    <Label for="english_level">English Level (0-5)</Label>
                                    <Select v-model="placementForm.english_level">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select level" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="level in options.englishLevels" :key="level.value" :value="String(level.value)">
                                                {{ level.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </template>

                            <div class="space-y-2">
                                <Label for="notes">Notes</Label>
                                <Textarea id="notes" v-model="placementForm.notes" placeholder="Optional notes..." />
                            </div>

                            <div class="flex justify-end gap-2">
                                <Button type="button" variant="outline" @click="isPlacementDialogOpen = false">Cancel</Button>
                                <Button type="submit" :disabled="placementForm.processing"> Initialize Placement </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>

                <!-- Record IELTS -->
                <Dialog v-model:open="isIeltsDialogOpen">
                    <DialogTrigger as-child>
                        <Button variant="outline">
                            <FileText class="mr-2 h-4 w-4" />
                            Record IELTS
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Record IELTS Certificate</DialogTitle>
                            <DialogDescription>Add a new IELTS certificate for this student.</DialogDescription>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="handleIeltsSubmit">
                            <div class="space-y-2">
                                <Label for="overall_score">Overall Score</Label>
                                <Input id="overall_score" v-model="ieltsForm.overall_score" type="number" step="0.5" min="0" max="9" placeholder="e.g., 6.5" />
                            </div>

                            <div class="space-y-2">
                                <Label for="ielts_semester">Semester</Label>
                                <Select v-model="ieltsForm.semester_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="semester in options.semesters" :key="semester.id" :value="String(semester.id)">
                                            {{ semester.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <Label for="issue_date">Issue Date (Optional)</Label>
                                <Input id="issue_date" v-model="ieltsForm.issue_date" type="date" />
                            </div>

                            <div class="space-y-2">
                                <Label>IELTS Certificate Scan</Label>
                                <FileUpload v-model="ieltsUploadedFiles" context="ielts_certificate" :upload-immediately="true" accept=".pdf,.jpg,.jpeg,.png" label="Drop IELTS certificate scan here or click to upload" />
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="ielts_missing" v-model:checked="ieltsForm.missing_documents" :disabled="ieltsUploadedFiles.length > 0" />
                                <Label for="ielts_missing" :class="{ 'text-muted-foreground': ieltsUploadedFiles.length > 0 }">Missing file scan (exception)</Label>
                            </div>

                            <div class="space-y-2">
                                <Label for="ielts_notes">Notes</Label>
                                <Textarea id="ielts_notes" v-model="ieltsForm.notes" placeholder="Optional notes..." />
                            </div>

                            <div class="flex justify-end gap-2">
                                <Button type="button" variant="outline" @click="isIeltsDialogOpen = false">Cancel</Button>
                                <Button type="submit" :disabled="ieltsForm.processing">Record IELTS</Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>

                <!-- Update Level (only for pre-intake students) -->
                <Dialog v-if="isPreIntake && availableLevels.length > 0" v-model:open="isLevelDialogOpen">
                    <DialogTrigger as-child>
                        <Button variant="outline">
                            <TrendingUp class="mr-2 h-4 w-4" />
                            Update Level
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Update English Level</DialogTitle>
                            <DialogDescription> Current level: {{ student.gc_current_level ?? 0 }}. Level can only increase. </DialogDescription>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="handleLevelSubmit">
                            <div class="space-y-2">
                                <Label for="new_level">New Level</Label>
                                <Select v-model="levelForm.new_level">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select new level" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="level in availableLevels" :key="level.value" :value="String(level.value)">
                                            {{ level.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <Label for="level_semester">Semester</Label>
                                <Select v-model="levelForm.semester_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="semester in options.semesters" :key="semester.id" :value="String(semester.id)">
                                            {{ semester.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <Label for="level_notes">Notes</Label>
                                <Textarea id="level_notes" v-model="levelForm.notes" placeholder="Optional notes..." />
                            </div>

                            <div class="flex justify-end gap-2">
                                <Button type="button" variant="outline" @click="isLevelDialogOpen = false">Cancel</Button>
                                <Button type="submit" :disabled="levelForm.processing">Update Level</Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>

                <!-- Transition to Intake Course (only for pre-intake with valid IELTS) -->
                <Dialog v-if="canTransitionToIntake" v-model:open="isTransitionDialogOpen">
                    <DialogTrigger as-child>
                        <Button>
                            <ArrowRight class="mr-2 h-4 w-4" />
                            Transition to Intake Course
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Transition to Intake Course</DialogTitle>
                            <DialogDescription> This will change the student's stage from Pre-Uni GC to Intake Course. </DialogDescription>
                        </DialogHeader>
                        <form class="space-y-4" @submit.prevent="handleTransitionSubmit">
                            <div class="space-y-2">
                                <Label for="trans_ielts">IELTS Certificate</Label>
                                <Select v-model="transitionForm.ielts_certificate_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select IELTS certificate" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="cert in qualifiedIeltsCertificates" :key="cert.id" :value="String(cert.id)"> Score {{ cert.overall_score }} - {{ cert.formatted_submitted_at }} </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <Label for="trans_semester">Semester</Label>
                                <Select v-model="transitionForm.semester_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="semester in options.semesters" :key="semester.id" :value="String(semester.id)">
                                            {{ semester.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <Label for="trans_notes">Notes</Label>
                                <Textarea id="trans_notes" v-model="transitionForm.notes" placeholder="Optional notes..." />
                            </div>

                            <div class="flex justify-end gap-2">
                                <Button type="button" variant="outline" @click="isTransitionDialogOpen = false">Cancel</Button>
                                <Button type="submit" :disabled="transitionForm.processing"> Transition to Intake Course </Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <!-- Current Status Card -->
        <Card>
            <CardHeader>
                <CardTitle>Current Status</CardTitle>
                <CardDescription>Student's current academic placement snapshot</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div class="space-y-2">
                        <p class="text-muted-foreground text-sm">Course Stage</p>
                        <Badge :variant="getStageBadgeVariant(student.status)" class="text-sm">
                            {{ student.status_label }}
                        </Badge>
                    </div>

                    <div v-if="isPreIntake" class="space-y-2">
                        <p class="text-muted-foreground text-sm">English Level</p>
                        <div class="flex items-center gap-2">
                            <Badge variant="outline" class="text-lg font-bold"> {{ student.gc_current_level ?? 0 }} / 5 </Badge>
                            <span v-if="student.gc_starting_level !== null" class="text-muted-foreground text-sm"> (Started at {{ student.gc_starting_level }}) </span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-muted-foreground text-sm">Latest IELTS</p>
                        <div v-if="latestIelts" class="flex items-center gap-2">
                            <Badge :variant="latestIelts.overall_score >= options.ieltsScoreThreshold ? 'success' : 'secondary'" class="text-sm">
                                {{ latestIelts.overall_score }}
                            </Badge>
                            <span v-if="latestIelts.missing_documents" class="flex items-center text-sm text-orange-500">
                                <AlertCircle class="mr-1 h-4 w-4" />
                                Missing scan
                            </span>
                            <span v-else class="flex items-center text-sm text-green-500">
                                <CheckCircle class="mr-1 h-4 w-4" />
                                Complete
                            </span>
                        </div>
                        <p v-else class="text-muted-foreground">No IELTS recorded</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- IELTS Certificates -->
        <Card v-if="ieltsCertificates.length > 0">
            <CardHeader>
                <CardTitle>IELTS Certificates</CardTitle>
                <CardDescription>All recorded IELTS certificates for this student</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    <div v-for="cert in ieltsCertificates" :key="cert.id" class="flex items-center justify-between rounded-lg border p-4">
                        <div class="flex items-center gap-4">
                            <Badge :variant="cert.overall_score >= options.ieltsScoreThreshold ? 'success' : 'secondary'" class="text-lg font-bold">
                                {{ cert.overall_score }}
                            </Badge>
                            <div>
                                <p class="font-medium">Submitted: {{ cert.formatted_submitted_at }}</p>
                                <p v-if="cert.issue_date" class="text-muted-foreground text-sm">Issue date: {{ cert.issue_date }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge v-if="cert.missing_documents" variant="destructive">
                                <AlertCircle class="mr-1 h-3 w-3" />
                                Missing Scan
                            </Badge>
                            <Badge v-else variant="outline">
                                <CheckCircle class="mr-1 h-3 w-3" />
                                Complete
                            </Badge>
                            <Button v-if="cert.missing_documents" size="sm" variant="outline" @click="openUploadDocumentDialog(cert)">
                                <Upload class="mr-1 h-4 w-4" />
                                Upload
                            </Button>
                            <a v-else-if="cert.upload_record?.url" :href="cert.upload_record.url" target="_blank" class="text-sm text-blue-600 hover:underline"> View File </a>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Progression Timeline -->
        <Card>
            <CardHeader>
                <CardTitle>Progression Timeline</CardTitle>
                <CardDescription>History of academic placement and progression events</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="progressionEvents.data.length === 0" class="py-8 text-center">
                    <GraduationCap class="text-muted-foreground mx-auto h-12 w-12" />
                    <p class="text-muted-foreground mt-2">No progression events recorded yet.</p>
                </div>

                <div v-else class="space-y-4">
                    <div v-for="event in progressionEvents.data" :key="event.id" class="border-muted relative border-l-2 pb-4 pl-6">
                        <div class="border-background bg-primary absolute top-0 -left-2 h-4 w-4 rounded-full border-2" />

                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge :variant="getEventTypeBadgeVariant(event.event_type)">
                                    {{ event.event_type }}
                                </Badge>
                                <Badge variant="outline">{{ event.trigger_source }}</Badge>
                                <span class="text-muted-foreground text-sm">{{ formatDateToShort(event.effective_at) }}</span>
                            </div>

                            <p class="font-medium">{{ event.summary }}</p>

                            <div class="text-muted-foreground flex flex-wrap gap-4 text-sm">
                                <span>Semester: {{ event.semester.name }}</span>
                                <span v-if="event.created_by">By: {{ event.created_by.name }}</span>
                            </div>

                            <p v-if="event.notes" class="text-muted-foreground text-sm italic">{{ event.notes }}</p>
                        </div>
                    </div>

                    <Separator />

                    <DataPagination :pagination-data="progressionEvents" @navigate="handlePaginationNavigate" />
                </div>
            </CardContent>
        </Card>

        <!-- Upload Document Dialog -->
        <Dialog v-model:open="isUploadDocumentDialogOpen">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Upload IELTS Certificate Scan</DialogTitle>
                    <DialogDescription> Upload the missing IELTS certificate scan for score {{ selectedCertificateForUpload?.overall_score }}. </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="handleDocumentUploadSubmit">
                    <div class="space-y-2">
                        <Label>IELTS Certificate Scan</Label>
                        <FileUpload v-model="documentUploadFiles" context="ielts_certificate" :upload-immediately="true" accept=".pdf,.jpg,.jpeg,.png" label="Drop IELTS certificate scan here or click to upload" />
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="isUploadDocumentDialogOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="documentUploadForm.processing || !documentUploadForm.upload_record_id">
                            <Upload class="mr-2 h-4 w-4" />
                            Upload Document
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
