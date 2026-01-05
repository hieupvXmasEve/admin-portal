<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Info, Loader2, Sparkles } from 'lucide-vue-next';
import { ref, watch } from 'vue';
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
    credit_points_attempted: number;
    credit_points_earned: number;
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

interface Filters {
    semester_id: string | number | null;
    campus_id: string | number | null;
}

const props = defineProps<{
    semesters: Semester[];
    campuses: Campus[];
    default_campus_id: number | string | null;
    filters: Filters;
    previewData: GpaPreview[];
    eligibilityResult: EligibilityResult | null;
}>();

const selectedSemesterId = ref<string | undefined>(props.filters.semester_id ? String(props.filters.semester_id) : undefined);
const selectedCampusId = ref<string | undefined>(props.filters.campus_id ? String(props.filters.campus_id) : (props.default_campus_id ? String(props.default_campus_id) : undefined));
const showFinalizeDialog = ref(false);
const isLoading = ref(false);

const updateFilters = () => {
    if (!selectedSemesterId.value) return;

    router.get(
        route('academic.gpa.finalize.index'),
        {
            semester_id: selectedSemesterId.value,
            campus_id: selectedCampusId.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['previewData', 'eligibilityResult', 'filters'],
            onStart: () => (isLoading.value = true),
            onFinish: () => (isLoading.value = false),
        },
    );
};

watch([selectedSemesterId, selectedCampusId], () => {
    if (selectedSemesterId.value) {
        updateFilters();
    }
});

const form = useForm({
    semester_id: null as number | null,
    campus_id: null as number | null,
});

const handleFinalize = () => {
    if (!selectedSemesterId.value) return;

    form.semester_id = parseInt(selectedSemesterId.value);
    form.campus_id = selectedCampusId.value ? parseInt(selectedCampusId.value) : null;

    form.post(route('academic.gpa.finalize.store'), {
        onSuccess: () => {
            showFinalizeDialog.value = false;
            toast.success('GPA finalized successfully!');
        },
    });
};
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
            <div class="flex flex-col items-end gap-4 sm:flex-row">
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
                            <SelectItem v-for="semester in semesters" :key="semester.id" :value="String(semester.id)"> {{ semester.name }} ({{ semester.code }}) </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <Button variant="secondary" @click="updateFilters" :disabled="!selectedSemesterId || isLoading">
                    <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                    Refresh Preview
                </Button>
            </div>

            <!-- GPA Calculation Formula Info -->
            <div class="mt-6">
                <Alert variant="default" class="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950">
                    <Info class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                    <AlertTitle class="text-blue-900 dark:text-blue-100">Công thức tính GPA</AlertTitle>
                    <AlertDescription class="mt-2 space-y-2 text-blue-800 dark:text-blue-200">
                        <div class="space-y-1.5 text-sm">
                            <p class="font-semibold">📊 Semester GPA (GPA học kỳ):</p>
                            <ul class="ml-2 list-inside list-disc space-y-1">
                                <li>Tính từ tất cả môn học trong kỳ hiện tại đang preview</li>
                                <li>Chỉ tính các môn: <code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900">excluded_from_gpa = false</code> và <code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900">credit_points &gt; 0</code></li>
                                <li>Công thức: <code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900">GPA = Σ(grade_points × credit_points) / Σ(credit_points)</code></li>
                            </ul>
                        </div>
                        <div class="space-y-1.5 text-sm">
                            <p class="font-semibold">📈 Cumulative GPA (GPA tích lũy):</p>
                            <ul class="ml-2 list-inside list-disc space-y-1">
                                <li><strong>Các kỳ đã chốt:</strong> Sử dụng snapshot từ bảng GPA đã finalized (đảm bảo tính nhất quán, không bị ảnh hưởng khi điểm thay đổi sau khi chốt)</li>
                                <li><strong>Kỳ hiện tại đang preview:</strong> Tính từ AcademicRecord (dữ liệu hiện tại chưa chốt)</li>
                                <li>Công thức: <code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900">Cumulative GPA = (Cumulative từ kỳ đã chốt + Semester GPA kỳ hiện tại × credits) / Tổng credits</code></li>
                            </ul>
                        </div>
                        <div class="mt-2 border-t border-blue-200 pt-2 text-xs text-blue-700 dark:border-blue-800 dark:text-blue-300">
                            <strong>Lưu ý:</strong> Nếu phát hiện tính toán sai, vui lòng kiểm tra lại dữ liệu AcademicRecord (grade_points, credit_points, excluded_from_gpa) và các snapshot GPA đã chốt.
                        </div>
                    </AlertDescription>
                </Alert>
            </div>

            <!-- Eligibility Alert -->
            <div v-if="eligibilityResult && selectedSemesterId" class="mt-6">
                <Alert :variant="eligibilityResult.ineligible_count > 0 ? 'destructive' : 'default'">
                    <CheckCircle2 v-if="eligibilityResult.ineligible_count === 0" class="h-4 w-4" />
                    <AlertTriangle v-else class="h-4 w-4" />
                    <AlertTitle>
                        {{ (eligibilityResult?.ineligible_count ?? 0) > 0 ? 'Partial Finalization Required' : 'Eligible for Finalization' }}
                    </AlertTitle>
                    <AlertDescription>
                        {{ eligibilityResult.message }}
                    </AlertDescription>
                </Alert>
            </div>

            <!-- Preview Table -->
            <div v-if="previewData.length > 0" class="mt-8">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold">GPA Preview ({{ previewData.length }} Students)</h3>
                </div>
                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student ID</TableHead>
                                <TableHead>Full Name</TableHead>
                                <TableHead>Program</TableHead>
                                <TableHead class="text-center">
                                    <div class="flex flex-col items-center">
                                        <span>Credits</span>
                                        <span class="text-muted-foreground text-xs font-normal">(Earned/Attempted)</span>
                                    </div>
                                </TableHead>
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
                                <TableCell class="text-center"> {{ row.credit_points_earned }} / {{ row.credit_points_attempted }} </TableCell>
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
                                    <p v-if="!row.is_eligible" class="text-destructive mt-1 text-[10px]">{{ row.reason }}</p>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>

            <div v-else-if="selectedSemesterId && !isLoading" class="mt-8 rounded-lg border-2 border-dashed py-12 text-center">
                <div class="text-muted-foreground mx-auto mb-4 h-12 w-12">
                    <Info class="h-full w-full opacity-20" />
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
                            <span v-if="(eligibilityResult?.ineligible_count ?? 0) > 0" class="text-destructive mt-2 block font-semibold">
                                Important: {{ eligibilityResult?.ineligible_count }} students with pending grades will be skipped and MUST be finalized later.
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
