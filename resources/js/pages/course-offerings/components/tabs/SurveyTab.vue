<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import type { CourseOffering } from '@/types/models';
import { Link, useForm } from '@inertiajs/vue3';
import { BarChart3, CheckCircle, ClipboardList, ExternalLink, FileText, Info } from 'lucide-vue-next';
import { ref } from 'vue';
import { route } from 'ziggy-js';

// ---- Types matching GetCourseOfferingSurveyQuery output ----
export interface SurveyData {
    surveyTarget: {
        id: number;
        form_id: number;
        status: string;
        start_at: string;
        end_at: string | null;
    } | null;
    completionStats: {
        total: number;
        completed: number;
        pending: number;
    } | null;
    formVersion: {
        id: number;
        title: string;
        code: string;
    } | null;
}

interface Props {
    courseOffering: CourseOffering;
    surveyData?: SurveyData;
    surveyForms?: { id: number; title: string; code: string }[];
}

const props = defineProps<Props>();

const showCreateDialog = ref(false);
const createForm = useForm({
    form_id: '',
});

const openCreateDialog = () => {
    // Auto-select if only one form available
    if (props.surveyForms?.length === 1) {
        createForm.form_id = props.surveyForms[0].id.toString();
    } else {
        createForm.form_id = '';
    }
    showCreateDialog.value = true;
};

const closeCreateDialog = () => {
    showCreateDialog.value = false;
    createForm.reset();
};

const handleCreateSurvey = () => {
    if (!createForm.form_id) return;
    createForm.post(route('course-offerings.create-survey', props.courseOffering.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateDialog();
        },
    });
};

const surveyStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'active':
            return 'default';
        case 'closed':
            return 'outline';
        case 'draft':
            return 'secondary';
        default:
            return 'secondary';
    }
};

const completionRate = (stats: NonNullable<SurveyData['completionStats']>): number =>
    stats.total > 0 ? Math.round((stats.completed / stats.total) * 100) : 0;

const completionBarColor = (rate: number): string => {
    if (rate >= 80) return 'bg-green-500';
    if (rate >= 50) return 'bg-yellow-500';
    return 'bg-orange-500';
};
</script>

<template>
    <div class="space-y-6">
        <!-- No survey created yet -->
        <template v-if="!surveyData?.surveyTarget">
            <div class="py-12 text-center">
                <div class="bg-muted mx-auto flex h-14 w-14 items-center justify-center rounded-full">
                    <ClipboardList class="text-muted-foreground h-7 w-7" />
                </div>
                <h3 class="mt-3 text-base font-semibold">No survey created yet</h3>
                <p class="text-muted-foreground mt-1 text-sm">Create a course survey to collect student feedback.</p>
                <div class="mt-5 flex flex-col items-center gap-2">
                    <Button :disabled="!surveyForms || surveyForms.length === 0" @click="openCreateDialog">
                        <FileText class="mr-1.5 h-4 w-4" />
                        Create Survey
                    </Button>
                    <p v-if="!surveyForms || surveyForms.length === 0" class="text-muted-foreground text-xs">
                        No survey forms available. Please create a survey form first.
                    </p>
                </div>
            </div>
        </template>

        <!-- Survey exists -->
        <template v-else>
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Survey Info -->
                <Card>
                    <CardHeader>
                        <div class="flex items-start justify-between gap-2">
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-4 w-4" />
                                Survey Details
                            </CardTitle>
                            <Badge :variant="surveyStatusVariant(surveyData.surveyTarget.status)" class="capitalize shrink-0">
                                {{ surveyData.surveyTarget.status }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="surveyData.formVersion">
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Form</p>
                            <p class="mt-0.5 font-semibold">{{ surveyData.formVersion.title }}</p>
                            <p class="text-muted-foreground text-sm">{{ surveyData.formVersion.code }}</p>
                        </div>

                        <Separator />

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Start Date</p>
                                <p class="mt-0.5 font-semibold">
                                    {{ new Date(surveyData.surveyTarget.start_at).toLocaleDateString() }}
                                </p>
                            </div>
                            <div v-if="surveyData.surveyTarget.end_at">
                                <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">End Date</p>
                                <p class="mt-0.5 font-semibold">
                                    {{ new Date(surveyData.surveyTarget.end_at).toLocaleDateString() }}
                                </p>
                            </div>
                        </div>

                        <!-- /forms/admin/results has no named route yet — kept as literal path -->
                        <Link :href="`/forms/admin/results?target_id=${surveyData.surveyTarget.id}`">
                            <Button variant="outline" size="sm" class="w-full">
                                <BarChart3 class="mr-1.5 h-4 w-4" />
                                View Full Results
                                <ExternalLink class="ml-1.5 h-3.5 w-3.5" />
                            </Button>
                        </Link>
                    </CardContent>
                </Card>

                <!-- Completion Stats -->
                <Card v-if="surveyData.completionStats">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <CheckCircle class="h-4 w-4" />
                            Completion Stats
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <div>
                            <div class="flex items-end justify-between">
                                <p class="text-4xl font-bold">{{ surveyData.completionStats.completed }}</p>
                                <p class="text-muted-foreground pb-1 text-sm">/ {{ surveyData.completionStats.total }}</p>
                            </div>
                            <p class="text-muted-foreground mt-0.5 text-xs">Students completed</p>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                <div
                                    class="h-2 rounded-full transition-all"
                                    :class="completionBarColor(completionRate(surveyData.completionStats))"
                                    :style="{ width: `${completionRate(surveyData.completionStats)}%` }"
                                ></div>
                            </div>
                            <p class="text-muted-foreground mt-1 text-xs">
                                {{ completionRate(surveyData.completionStats) }}% completion rate
                            </p>
                        </div>

                        <Separator />

                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div class="rounded-lg border p-3">
                                <p class="text-2xl font-bold text-green-600">{{ surveyData.completionStats.completed }}</p>
                                <p class="text-muted-foreground mt-0.5 text-xs font-medium uppercase tracking-wide">Completed</p>
                            </div>
                            <div class="rounded-lg border p-3">
                                <p class="text-2xl font-bold text-orange-500">{{ surveyData.completionStats.pending }}</p>
                                <p class="text-muted-foreground mt-0.5 text-xs font-medium uppercase tracking-wide">Pending</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </template>

        <!-- Create Survey Dialog — form dialog (not a confirm dialog, so Dialog is correct here) -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Create Course Survey</DialogTitle>
                    <DialogDescription>
                        Select a survey form for <strong>{{ courseOffering.course_code }}</strong>
                        <span v-if="courseOffering.section_code"> — Section {{ courseOffering.section_code }}</span>
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Survey Form</label>
                        <Select v-model="createForm.form_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a form" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="form in surveyForms" :key="form.id" :value="form.id.toString()">
                                    <span class="block max-w-[280px] truncate" :title="`${form.title} (${form.code})`">
                                        {{ form.title }} ({{ form.code }})
                                    </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="createForm.errors.form_id" class="text-destructive text-sm">{{ createForm.errors.form_id }}</p>
                    </div>

                    <div class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                        <Info class="mt-0.5 h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            This will create a survey target for this course and automatically assign it to all enrolled students.
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="closeCreateDialog">Cancel</Button>
                    <Button :disabled="!createForm.form_id || createForm.processing" @click="handleCreateSurvey">
                        {{ createForm.processing ? 'Creating…' : 'Create Survey' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
