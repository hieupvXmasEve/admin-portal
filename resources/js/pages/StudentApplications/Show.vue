<script setup lang="ts">
import GuardianManager from '@/components/student-applications/GuardianManager.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Guardian } from '@/types/application-guardian';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, CheckCircle2, ExternalLink, FileText, Pencil, Undo2, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface LinkedStudent {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    status: string;
}

interface Actor {
    id: number;
    name: string;
    email: string;
}

interface StudentApplication {
    id: number;
    full_name: string;
    student_code: string;
    gender: string | null;
    ethnicity: string | null;
    birth_day: number | null;
    birth_month: number | null;
    birth_year: number | null;
    national_id: string | null;
    phone: string | null;
    email: string;
    address: string | null;
    health_information: string | null;
    campus_code: string;
    intended_program: string | null;
    intended_specialization: string | null;
    intake: string | null;
    exam_date: string | null;
    english_test_type: string | null;
    listening: number | null;
    reading: number | null;
    writing: number | null;
    speaking: number | null;
    overall: number | null;
    is_international_applicant: boolean;
    status: 'pending' | 'enrolled' | 'rejected';
    student_id: number | null;
    approved_at: string | null;
    rejected_at: string | null;
    rejected_reason: string | null;
    revoked_at: string | null;
    student?: LinkedStudent | null;
    guardians?: Guardian[];
    approved_by_user?: Actor | null;
    rejected_by_user?: Actor | null;
    revoked_by_user?: Actor | null;
}

interface ApplicationDocument {
    id: number;
    page_index: number;
    original_name: string | null;
    link: string;
    mime_type: string | null;
    size: number | null;
    status: string | null;
}

interface DocumentGroup {
    code: string;
    name: string;
    required: boolean;
    is_missing: boolean;
    documents: ApplicationDocument[];
}

interface DocumentChecklist {
    groups: DocumentGroup[];
    missing_required: string[];
}

interface ConversionReadiness {
    ready: boolean;
    missing: { field: string; crm_value: string | null; kind: string | null; reason: string; program_id?: number; semester_id?: number }[];
    warnings: { field: string; crm_value: string; kind: string }[];
}

const CURRICULUM_REASONS = ['no_curriculum', 'ambiguous_curriculum'];

// The CRM mapping screen has no curriculum-version concept — a "no curriculum
// version" block can only be resolved on the Curriculum Versions screen.
const missingItemLink = (item: ConversionReadiness['missing'][number]): string =>
    CURRICULUM_REASONS.includes(item.reason) && item.program_id
        ? route('curriculum_versions.index', { program_id: item.program_id })
        : route('student-applications.crm-mappings.index');

interface Props {
    application: StudentApplication;
    guardianRelationships: string[];
    documentChecklist: DocumentChecklist;
    conversionReadiness: ConversionReadiness | null;
}

const props = defineProps<Props>();

const guardians = computed<Guardian[]>(() => props.application.guardians ?? []);

const documentGroups = computed<DocumentGroup[]>(() => props.documentChecklist?.groups ?? []);
const missingRequired = computed<string[]>(() => props.documentChecklist?.missing_required ?? []);
const hasDocuments = computed(() => documentGroups.value.some((group) => group.documents.length > 0));

const formatFileSize = (bytes: number | null): string => {
    if (!bytes) {
        return '';
    }
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const isPending = computed(() => props.application.status === 'pending');
const isEnrolled = computed(() => props.application.status === 'enrolled');
const showRejectDialog = ref(false);
const showRevokeDialog = ref(false);
const rejectReason = ref('');
const isSubmitting = ref(false);

const statusMeta = computed(() => {
    switch (props.application.status) {
        case 'enrolled':
            return { label: 'Enrolled', class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' };
        case 'rejected':
            return { label: 'Rejected', class: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300' };
        default:
            return { label: 'Pending', class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' };
    }
});

const birthDate = computed(() => {
    const { birth_day, birth_month, birth_year } = props.application;
    if (!birth_day || !birth_month || !birth_year) {
        return '—';
    }
    return `${String(birth_day).padStart(2, '0')}/${String(birth_month).padStart(2, '0')}/${birth_year}`;
});

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }
    return new Date(value).toLocaleString();
};

const goBack = () => router.visit(route('student-applications.index'));
const goEdit = () => router.visit(route('student-applications.edit', props.application.id));

const approve = () => {
    isSubmitting.value = true;
    router.post(
        route('student-applications.approve', props.application.id),
        {},
        {
            preserveScroll: true,
            // No onSuccess toast here: the controller redirects back with 200
            // on both real success and a caught domain failure (e.g. missing
            // curriculum version), so Inertia's onSuccess fires either way.
            // The server-flashed message (success or error) is the only
            // trustworthy signal — useFlashToast (AppLayout) renders it.
            onError: () => toast.error('Failed to approve application.'),
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const submitReject = () => {
    if (!rejectReason.value.trim()) {
        toast.error('A rejection reason is required.');
        return;
    }
    isSubmitting.value = true;
    router.post(
        route('student-applications.reject', props.application.id),
        { rejected_reason: rejectReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showRejectDialog.value = false;
            },
            onError: () => toast.error('Failed to reject application.'),
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const submitRevoke = () => {
    isSubmitting.value = true;
    router.post(
        route('student-applications.revoke', props.application.id),
        {},
        {
            preserveScroll: true,
            // No onSuccess toast here — same reason as approve() above: the
            // server-flashed message is the only trustworthy success/error
            // signal, rendered globally by useFlashToast.
            onSuccess: () => {
                showRevokeDialog.value = false;
            },
            onError: () => toast.error('Failed to revoke application.'),
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

</script>

<template>
    <Head :title="`Application — ${application.full_name}`" />

    <div class="mx-auto max-w-5xl">
        <Button variant="ghost" class="mb-4" @click="goBack">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back to applications
        </Button>

        <!-- Header -->
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold tracking-tight">{{ application.full_name }}</h1>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusMeta.class">{{ statusMeta.label }}</span>
                </div>
                <p class="text-muted-foreground mt-1 text-sm">
                    {{ application.student_code }} · {{ application.campus_code }}
                    <span v-if="application.intake"> · {{ application.intake }}</span>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button v-if="isPending" variant="outline" @click="goEdit">
                    <Pencil class="mr-2 h-4 w-4" />
                    Edit
                </Button>
                <Button v-if="isPending" :disabled="isSubmitting" class="bg-emerald-600 text-white hover:bg-emerald-700" @click="approve">
                    <CheckCircle2 class="mr-2 h-4 w-4" />
                    Approve
                </Button>
                <Button v-if="isPending" variant="destructive" :disabled="isSubmitting" @click="showRejectDialog = true">
                    <XCircle class="mr-2 h-4 w-4" />
                    Reject
                </Button>
                <Button v-if="isEnrolled" variant="outline" :disabled="isSubmitting" @click="showRevokeDialog = true">
                    <Undo2 class="mr-2 h-4 w-4" />
                    Revoke
                </Button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <!-- Identity -->
                <Card>
                    <CardHeader>
                        <CardTitle>Applicant identity</CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-muted-foreground text-xs">Gender</p>
                            <p class="font-medium capitalize">{{ application.gender ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">Date of birth</p>
                            <p class="font-medium">{{ birthDate }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">Ethnicity</p>
                            <p class="font-medium">{{ application.ethnicity ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">National ID</p>
                            <p class="font-medium">{{ application.national_id ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">International applicant</p>
                            <p class="font-medium">{{ application.is_international_applicant ? 'Yes' : 'No' }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Contact -->
                <Card>
                    <CardHeader>
                        <CardTitle>Contact</CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-muted-foreground text-xs">Email</p>
                            <p class="font-medium">{{ application.email }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">Phone</p>
                            <p class="font-medium">{{ application.phone ?? '—' }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-muted-foreground text-xs">Address</p>
                            <p class="font-medium">{{ application.address ?? '—' }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Guardians -->
                <GuardianManager :application-id="application.id" :guardians="guardians" :guardian-relationships="guardianRelationships" :editable="isPending" />

                <!-- Documents -->
                <Card>
                    <CardHeader>
                        <CardTitle>Documents</CardTitle>
                        <CardDescription>External link references from the admissions catalog, grouped by type.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="missingRequired.length > 0" class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50/60 p-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/10 dark:text-amber-300">
                            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                            <span>
                                <span class="font-semibold">{{ missingRequired.length }} required document(s) missing.</span>
                                Approving an incomplete dossier is discouraged.
                            </span>
                        </div>

                        <p v-if="documentGroups.length === 0" class="text-muted-foreground text-sm">No document types in the catalog yet.</p>
                        <p v-else-if="!hasDocuments && missingRequired.length === 0" class="text-muted-foreground text-sm">No documents recorded yet.</p>

                        <div v-for="docGroup in documentGroups" :key="docGroup.code" class="rounded-lg border p-4" :class="docGroup.is_missing ? 'border-amber-300 bg-amber-50/40 dark:border-amber-700 dark:bg-amber-900/10' : ''">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-semibold">{{ docGroup.name }}</p>
                                <Badge v-if="docGroup.required" variant="secondary">Required</Badge>
                                <Badge v-if="docGroup.is_missing" class="bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"> Missing </Badge>
                            </div>

                            <ul v-if="docGroup.documents.length > 0" class="mt-2 space-y-1.5">
                                <li v-for="doc in docGroup.documents" :key="doc.id" class="flex items-center gap-2 text-sm">
                                    <FileText class="text-muted-foreground h-4 w-4 shrink-0" />
                                    <a :href="doc.link" target="_blank" rel="noopener noreferrer" class="text-primary inline-flex items-center gap-1 font-medium hover:underline">
                                        {{ doc.original_name ?? doc.link }}
                                        <ExternalLink class="h-3 w-3" />
                                    </a>
                                    <span v-if="doc.size" class="text-muted-foreground text-xs">{{ formatFileSize(doc.size) }}</span>
                                </li>
                            </ul>
                            <p v-else class="text-muted-foreground mt-1 text-sm">No file provided.</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Admission intent + English test -->
                <Card>
                    <CardHeader>
                        <CardTitle>Admission intent</CardTitle>
                        <CardDescription>Program, intake and English test result</CardDescription>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-muted-foreground text-xs">Intended program</p>
                            <p class="font-medium">{{ application.intended_program ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">Specialization</p>
                            <p class="font-medium">{{ application.intended_specialization ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">English test</p>
                            <p class="font-medium">{{ application.english_test_type ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">Overall</p>
                            <p class="font-medium">{{ application.overall ?? '—' }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Lifecycle / audit sidebar -->
            <div class="space-y-6">
                <Card v-if="conversionReadiness && !conversionReadiness.ready" class="border-amber-300">
                    <CardHeader>
                        <CardTitle class="text-amber-800">Chưa map — not conversion-ready</CardTitle>
                        <CardDescription>Resolve these before this application can be approved.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <div v-for="(item, index) in conversionReadiness.missing" :key="index" class="rounded-md bg-amber-50 px-3 py-2">
                            <p class="font-medium text-amber-900 capitalize">{{ item.field.replace(/_/g, ' ') }}</p>
                            <p v-if="item.crm_value" class="text-amber-700">CRM value: "{{ item.crm_value }}"</p>
                            <p class="text-xs text-amber-600">{{ item.reason.replace(/_/g, ' ') }}</p>
                            <a :href="missingItemLink(item)" class="text-xs text-amber-700 underline">
                                {{ CURRICULUM_REASONS.includes(item.reason) ? 'Open Curriculum Versions' : 'Open the mapping screen' }}
                            </a>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Lifecycle</CardTitle>
                        <CardDescription>Who acted and when</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <div v-if="application.status === 'enrolled'">
                            <p class="text-muted-foreground text-xs">Approved by</p>
                            <p class="font-medium">{{ application.approved_by_user?.name ?? 'Unknown' }}</p>
                            <p class="text-muted-foreground text-xs">{{ formatDateTime(application.approved_at) }}</p>
                        </div>

                        <div v-else-if="application.status === 'rejected'" class="space-y-2">
                            <div>
                                <p class="text-muted-foreground text-xs">Rejected by</p>
                                <p class="font-medium">{{ application.rejected_by_user?.name ?? 'Unknown' }}</p>
                                <p class="text-muted-foreground text-xs">{{ formatDateTime(application.rejected_at) }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs">Reason</p>
                                <p class="font-medium">{{ application.rejected_reason ?? '—' }}</p>
                            </div>
                        </div>

                        <template v-else>
                            <p class="text-muted-foreground">Awaiting a decision. Approve to enroll the student, or reject with a reason.</p>
                            <div v-if="application.revoked_at" class="border-t pt-3">
                                <p class="text-muted-foreground text-xs">Previously revoked by</p>
                                <p class="font-medium">{{ application.revoked_by_user?.name ?? 'Unknown' }}</p>
                                <p class="text-muted-foreground text-xs">{{ formatDateTime(application.revoked_at) }}</p>
                            </div>
                        </template>
                    </CardContent>
                </Card>

                <Card v-if="application.student">
                    <CardHeader>
                        <CardTitle>Enrolled student</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-1 text-sm">
                        <p class="font-medium">{{ application.student.full_name }}</p>
                        <p class="text-muted-foreground">{{ application.student.student_id }}</p>
                        <Badge variant="secondary" class="mt-2 capitalize">{{ application.student.status }}</Badge>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <!-- Reject dialog -->
    <Dialog v-model:open="showRejectDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Reject application</DialogTitle>
                <DialogDescription>Record why this application is being rejected. No student will be created.</DialogDescription>
            </DialogHeader>

            <div class="space-y-2 py-2">
                <Label for="reject-reason">Reason</Label>
                <Textarea id="reject-reason" v-model="rejectReason" rows="4" placeholder="Explain the reason for rejection" />
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="isSubmitting" @click="showRejectDialog = false">Cancel</Button>
                <Button variant="destructive" :disabled="isSubmitting" @click="submitReject">Confirm rejection</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Revoke dialog -->
    <Dialog v-model:open="showRevokeDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Revoke approval</DialogTitle>
                <DialogDescription>
                    This tears down the enrolled student account, user, and roles, and returns the application to pending for correction. It is only possible while the student has no academic or financial activity. Use Withdraw for a student who has
                    already studied.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <Button variant="outline" :disabled="isSubmitting" @click="showRevokeDialog = false">Cancel</Button>
                <Button variant="destructive" :disabled="isSubmitting" @click="submitRevoke">Confirm revoke</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

</template>
