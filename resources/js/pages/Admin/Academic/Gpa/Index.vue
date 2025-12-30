<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter
} from '@/components/ui/card';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger
} from '@/components/ui/dialog';
import { Info, AlertTriangle, CheckCircle2, Loader2, Sparkles } from 'lucide-vue-next';
import { useApi } from '@/composables/useApiRequest';
import { toast } from 'vue-sonner';

interface Semester {
    id: number;
    name: string;
    code: string;
}

interface Campus {
    id: number;
    name: string;
}

interface GpaPreview {
    id: number;
    student_id_code: string;
    full_name: string;
    program: string;
    semester_gpa: number;
    cumulative_gpa: number;
    academic_standing: string;
    credits_attempted: number;
    credits_earned: number;
    is_eligible: boolean;
    reason: string | null;
}

interface EligibilityResult {
    eligible: boolean;
    message: string;
    total_students: number;
    eligible_count: number;
    ineligible_count: number;
    partial: boolean;
}

const props = defineProps<{
    semesters: Semester[];
    campuses: Campus[];
    default_campus_id: number | string | null;
}>();

const api = useApi();

const selectedSemesterId = ref<string | undefined>(undefined);
const selectedCampusId = ref<string | undefined>(props.default_campus_id ? String(props.default_campus_id) : undefined);
const isLoadingPreview = ref(false);
const isCheckingEligibility = ref(false);
const previewData = ref<GpaPreview[]>([]);
const eligibilityResult = ref<EligibilityResult | null>(null);
const showFinalizeDialog = ref(false);

const checkEligibility = async (semesterId: string) => {
    isCheckingEligibility.value = true;
    const { data: apiData } = await api.get<EligibilityResult>(route('academic.gpa.finalize.check-eligibility'), {
        params: {
            semester_id: semesterId,
            campus_id: selectedCampusId.value
        }
    });

    if (apiData.value?.success) {
        eligibilityResult.value = apiData.value.data;
    } else {
        toast.error(apiData.value?.message || 'Failed to check eligibility');
    }
    isCheckingEligibility.value = false;
};

const fetchPreview = async () => {
    if (!selectedSemesterId.value) return;

    isLoadingPreview.value = true;
    previewData.value = [];

    const { data: apiData } = await api.get<GpaPreview[]>(route('academic.gpa.finalize.preview'), {
        params: {
            semester_id: selectedSemesterId.value,
            campus_id: selectedCampusId.value
        }
    });

    if (apiData.value?.success) {
        previewData.value = apiData.value.data;
        await checkEligibility(selectedSemesterId.value);
    } else {
        toast.error(apiData.value?.message || 'Failed to fetch GPA preview');
    }
    isLoadingPreview.value = false;
};

const form = useForm({
    semester_id: null as number | null,
    campus_id: null as number | null
});

const handleFinalize = () => {
    if (!selectedSemesterId.value) return;

    form.semester_id = parseInt(selectedSemesterId.value);
    form.campus_id = selectedCampusId.value ? parseInt(selectedCampusId.value) : null;

    form.post(route('academic.gpa.finalize.store'), {
        onSuccess: () => {
            showFinalizeDialog.value = false;
            previewData.value = [];
            eligibilityResult.value = null;
            selectedSemesterId.value = undefined;
            toast.success('GPA finalized successfully!');
        }
    });
};

watch([selectedSemesterId, selectedCampusId], ([newSemester, newCampus]) => {
    if (newSemester) {
        fetchPreview();
    } else {
        previewData.value = [];
        eligibilityResult.value = null;
    }
});
</script>

<template>

    <Head title="GPA Management" />

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">GPA Management</h1>
            <p class="text-muted-foreground mt-1">Finalize and snapshot semester GPA for all students.</p>
        </div>
    </div>

    <Card>
        <CardHeader>
            <CardTitle>Semester Finalization</CardTitle>
            <CardDescription>Select a semester to review and finalize GPA calculations.</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="flex flex-col sm:flex-row gap-4 items-end">
                <div class="flex-1 space-y-2">
                    <label class="text-sm font-medium">Select Campus</label>
                    <Select v-model="selectedCampusId">
                        <SelectTrigger>
                            <SelectValue placeholder="Choose a campus..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="campus in campuses" :key="campus.id" :value="String(campus.id)">
                                {{ campus.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex-1 space-y-2">
                    <label class="text-sm font-medium">Select Semester</label>
                    <Select v-model="selectedSemesterId">
                        <SelectTrigger>
                            <SelectValue placeholder="Choose a semester..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="semester in semesters" :key="semester.id" :value="String(semester.id)">
                                {{ semester.name }} ({{ semester.code }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <Button variant="secondary" @click="fetchPreview" :disabled="!selectedSemesterId || isLoadingPreview">
                    <Loader2 v-if="isLoadingPreview" class="mr-2 h-4 w-4 animate-spin" />
                    Refresh Preview
                </Button>
            </div>

            <!-- Eligibility Alert -->
            <div v-if="eligibilityResult && selectedSemesterId" class="mt-6">
                <Alert :variant="eligibilityResult.ineligible_count > 0 ? 'destructive' : 'default'">
                    <CheckCircle2 v-if="eligibilityResult.ineligible_count === 0" class="h-4 w-4" />
                    <AlertTriangle v-else class="h-4 w-4" />
                    <AlertTitle>
                        {{
                            (eligibilityResult?.ineligible_count ?? 0) > 0
                                ? 'Partial Finalization Required'
                                : 'Eligible for Finalization'
                        }}
                    </AlertTitle>
                    <AlertDescription>
                        {{ eligibilityResult.message }}
                    </AlertDescription>
                </Alert>
            </div>

            <!-- Preview Table -->
            <div v-if="previewData.length > 0" class="mt-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">GPA Preview ({{ previewData.length }} Students)</h3>
                </div>
                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student ID</TableHead>
                                <TableHead>Full Name</TableHead>
                                <TableHead>Program</TableHead>
                                <TableHead class="text-center">Credits</TableHead>
                                <TableHead class="text-center">Semester GPA</TableHead>
                                <TableHead class="text-center">Cumulative GPA</TableHead>
                                <TableHead class="text-center">Standing</TableHead>
                                <TableHead class="text-right">Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in previewData" :key="row.id">
                                <TableCell class="font-medium">{{ row.student_id_code }}</TableCell>
                                <TableCell>{{ row.full_name }}</TableCell>
                                <TableCell>{{ row.program }}</TableCell>
                                <TableCell class="text-center">
                                    {{ row.credits_earned }} / {{ row.credits_attempted }}
                                </TableCell>
                                <TableCell class="text-center font-bold">
                                    {{ row.semester_gpa.toFixed(3) }}
                                </TableCell>
                                <TableCell class="text-center">
                                    {{ row.cumulative_gpa.toFixed(3) }}
                                </TableCell>
                                <TableCell class="text-center">
                                    <Badge :variant="row.academic_standing === 'normal' ? 'secondary' : 'destructive'">
                                        {{ row.academic_standing.toUpperCase() }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Badge :variant="row.is_eligible ? 'default' : 'destructive'">
                                        {{ row.is_eligible ? 'Eligible' : 'Pending' }}
                                    </Badge>
                                    <p v-if="!row.is_eligible" class="text-[10px] text-destructive mt-1">{{ row.reason
                                    }}</p>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <div v-else-if="selectedSemesterId && !isLoadingPreview"
                class="mt-8 text-center py-12 border-2 border-dashed rounded-lg">
                <div class="mx-auto w-12 h-12 text-muted-foreground mb-4">
                    <Info class="w-full h-full opacity-20" />
                </div>
                <p class="text-muted-foreground">No academic records found for this semester.</p>
            </div>
        </CardContent>
        <CardFooter v-if="previewData.length > 0" class="flex justify-end border-t p-6">
            <Dialog v-model:open="showFinalizeDialog">
                <DialogTrigger as-child>
                    <Button :disabled="!eligibilityResult?.eligible || form.processing" size="lg">
                        <Sparkles class="mr-2 h-4 w-4" />
                        Finalize Semester GPA
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Confirm Finalization</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to finalize GPA?
                            <span v-if="(eligibilityResult?.ineligible_count ?? 0) > 0"
                                class="block mt-2 font-semibold text-destructive">
                                Important: {{ eligibilityResult?.ineligible_count }} students with pending grades will
                                be skipped and MUST be finalized later.
                            </span>
                            This will create a snapshot for {{ eligibilityResult?.eligible_count }} eligible students.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" @click="showFinalizeDialog = false">Cancel</Button>
                        <Button :disabled="form.processing" @click="handleFinalize">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            Process Finalization
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </CardFooter>
    </Card>
</template>
