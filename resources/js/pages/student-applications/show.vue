<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Pencil, Plus, Star, Trash2, Undo2, XCircle } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
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

interface Guardian {
    id: number;
    full_name: string;
    relationship: string | null;
    phone: string | null;
    email: string | null;
    occupation: string | null;
    address: string | null;
    is_primary: boolean;
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
    parent_phone: string | null;
    parent_email: string | null;
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

interface Props {
    application: StudentApplication;
    guardianRelationships: string[];
}

const props = defineProps<Props>();

const guardians = computed<Guardian[]>(() => props.application.guardians ?? []);

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
            onSuccess: () => toast.success('Application approved. The student has been enrolled.'),
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
                toast.success('Application rejected.');
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
            onSuccess: () => {
                toast.success('Approval revoked. The application is pending again.');
                showRevokeDialog.value = false;
            },
            onError: () => toast.error('Failed to revoke application.'),
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

// --- Guardians -------------------------------------------------------------

const showGuardianDialog = ref(false);
const editingGuardianId = ref<number | null>(null);

const guardianForm = reactive({
    full_name: '',
    relationship: '' as string,
    phone: '',
    email: '',
    occupation: '',
    address: '',
    is_primary: false,
});

const guardianDialogTitle = computed(() => (editingGuardianId.value === null ? 'Add guardian' : 'Edit guardian'));

const resetGuardianForm = () => {
    guardianForm.full_name = '';
    guardianForm.relationship = '';
    guardianForm.phone = '';
    guardianForm.email = '';
    guardianForm.occupation = '';
    guardianForm.address = '';
    // The first guardian is always primary; offer it pre-checked when none exist.
    guardianForm.is_primary = guardians.value.length === 0;
};

const openAddGuardian = () => {
    editingGuardianId.value = null;
    resetGuardianForm();
    showGuardianDialog.value = true;
};

const openEditGuardian = (guardian: Guardian) => {
    editingGuardianId.value = guardian.id;
    guardianForm.full_name = guardian.full_name;
    guardianForm.relationship = guardian.relationship ?? '';
    guardianForm.phone = guardian.phone ?? '';
    guardianForm.email = guardian.email ?? '';
    guardianForm.occupation = guardian.occupation ?? '';
    guardianForm.address = guardian.address ?? '';
    guardianForm.is_primary = guardian.is_primary;
    showGuardianDialog.value = true;
};

const submitGuardian = () => {
    if (!guardianForm.full_name.trim()) {
        toast.error('A guardian name is required.');
        return;
    }

    const payload = {
        full_name: guardianForm.full_name,
        relationship: guardianForm.relationship || null,
        phone: guardianForm.phone || null,
        email: guardianForm.email || null,
        occupation: guardianForm.occupation || null,
        address: guardianForm.address || null,
        is_primary: guardianForm.is_primary,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(editingGuardianId.value === null ? 'Guardian added.' : 'Guardian updated.');
            showGuardianDialog.value = false;
        },
        onError: () => toast.error('Could not save the guardian. Check the fields and try again.'),
        onFinish: () => {
            isSubmitting.value = false;
        },
    };

    isSubmitting.value = true;

    if (editingGuardianId.value === null) {
        router.post(route('student-applications.guardians.store', props.application.id), payload, options);
    } else {
        router.put(route('student-applications.guardians.update', [props.application.id, editingGuardianId.value]), payload, options);
    }
};

const makePrimary = (guardian: Guardian) => {
    isSubmitting.value = true;
    router.put(
        route('student-applications.guardians.update', [props.application.id, guardian.id]),
        { full_name: guardian.full_name, is_primary: true },
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Primary guardian updated.'),
            onError: () => toast.error('Could not set the primary guardian.'),
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const removeGuardian = (guardian: Guardian) => {
    if (!window.confirm(`Remove ${guardian.full_name} as a guardian?`)) {
        return;
    }
    isSubmitting.value = true;
    router.delete(route('student-applications.guardians.destroy', [props.application.id, guardian.id]), {
        preserveScroll: true,
        onSuccess: () => toast.success('Guardian removed.'),
        onError: () => toast.error('Could not remove the guardian.'),
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const relationshipLabel = (value: string | null): string => {
    if (!value) {
        return 'Guardian';
    }
    return value.charAt(0).toUpperCase() + value.slice(1);
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
                        <div>
                            <p class="text-muted-foreground text-xs">Guardian email</p>
                            <p class="font-medium">{{ application.parent_email ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">Guardian phone</p>
                            <p class="font-medium">{{ application.parent_phone ?? '—' }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Guardians -->
                <Card>
                    <CardHeader class="flex flex-row items-start justify-between gap-4 space-y-0">
                        <div>
                            <CardTitle>Guardians</CardTitle>
                            <CardDescription>Parents and responsible adults. Exactly one is primary.</CardDescription>
                        </div>
                        <Button v-if="isPending" size="sm" variant="outline" :disabled="isSubmitting" @click="openAddGuardian">
                            <Plus class="mr-2 h-4 w-4" />
                            Add guardian
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p v-if="guardians.length === 0" class="text-muted-foreground text-sm">No guardians recorded yet.</p>

                        <div
                            v-for="guardian in guardians"
                            :key="guardian.id"
                            class="flex flex-wrap items-start justify-between gap-3 rounded-lg border p-4"
                            :class="guardian.is_primary ? 'border-amber-300 bg-amber-50/60 dark:border-amber-700 dark:bg-amber-900/10' : ''"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold">{{ guardian.full_name }}</p>
                                    <Badge v-if="guardian.is_primary" class="bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                                        Primary
                                    </Badge>
                                    <Badge variant="secondary">{{ relationshipLabel(guardian.relationship) }}</Badge>
                                </div>
                                <div class="text-muted-foreground mt-1 space-y-0.5 text-sm">
                                    <p v-if="guardian.phone">📞 {{ guardian.phone }}</p>
                                    <p v-if="guardian.email">✉️ {{ guardian.email }}</p>
                                    <p v-if="guardian.occupation">💼 {{ guardian.occupation }}</p>
                                    <p v-if="guardian.address">🏠 {{ guardian.address }}</p>
                                </div>
                            </div>

                            <div v-if="isPending" class="flex items-center gap-1">
                                <Button
                                    v-if="!guardian.is_primary"
                                    size="sm"
                                    variant="ghost"
                                    :disabled="isSubmitting"
                                    title="Set as primary"
                                    @click="makePrimary(guardian)"
                                >
                                    <Star class="h-4 w-4" />
                                </Button>
                                <Button size="sm" variant="ghost" :disabled="isSubmitting" title="Edit" @click="openEditGuardian(guardian)">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="text-rose-600 hover:text-rose-700"
                                    :disabled="isSubmitting"
                                    title="Remove"
                                    @click="removeGuardian(guardian)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
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
                    This tears down the enrolled student account, user, and roles, and returns the application to pending for
                    correction. It is only possible while the student has no academic or financial activity. Use Withdraw for a
                    student who has already studied.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <Button variant="outline" :disabled="isSubmitting" @click="showRevokeDialog = false">Cancel</Button>
                <Button variant="destructive" :disabled="isSubmitting" @click="submitRevoke">Confirm revoke</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Guardian add/edit dialog -->
    <Dialog v-model:open="showGuardianDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ guardianDialogTitle }}</DialogTitle>
                <DialogDescription>Record a parent or responsible adult for this application.</DialogDescription>
            </DialogHeader>

            <div class="grid grid-cols-1 gap-4 py-2 sm:grid-cols-2">
                <div class="space-y-2 sm:col-span-2">
                    <Label for="guardian-name">Full name</Label>
                    <Input id="guardian-name" v-model="guardianForm.full_name" placeholder="Full name" />
                </div>

                <div class="space-y-2">
                    <Label for="guardian-relationship">Relationship</Label>
                    <Select v-model="guardianForm.relationship">
                        <SelectTrigger id="guardian-relationship">
                            <SelectValue placeholder="Select relationship" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="option in guardianRelationships" :key="option" :value="option">
                                {{ relationshipLabel(option) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <Label for="guardian-phone">Phone</Label>
                    <Input id="guardian-phone" v-model="guardianForm.phone" placeholder="Phone" />
                </div>

                <div class="space-y-2">
                    <Label for="guardian-email">Email</Label>
                    <Input id="guardian-email" v-model="guardianForm.email" type="email" placeholder="Email" />
                </div>

                <div class="space-y-2">
                    <Label for="guardian-occupation">Occupation</Label>
                    <Input id="guardian-occupation" v-model="guardianForm.occupation" placeholder="Occupation" />
                </div>

                <div class="space-y-2 sm:col-span-2">
                    <Label for="guardian-address">Address</Label>
                    <Input id="guardian-address" v-model="guardianForm.address" placeholder="Address" />
                </div>

                <label class="flex items-center gap-2 sm:col-span-2">
                    <Checkbox v-model="guardianForm.is_primary" />
                    <span class="text-sm">Primary guardian (emergency contact &amp; parent account)</span>
                </label>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="isSubmitting" @click="showGuardianDialog = false">Cancel</Button>
                <Button :disabled="isSubmitting" @click="submitGuardian">Save guardian</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
