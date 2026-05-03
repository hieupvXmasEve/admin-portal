<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import type { CourseOffering } from '@/types/models';
import { Link, useForm } from '@inertiajs/vue3';
import { BarChart3, CheckCircle, ClipboardList, ExternalLink, FileText } from 'lucide-vue-next';
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
</script>

<template>
    <div class="space-y-6">
        <!-- Loading skeleton -->
        <div v-if="surveyData === undefined" class="space-y-4">
            <Skeleton class="h-32 w-full" />
            <Skeleton class="h-48 w-full" />
        </div>

        <!-- No survey created yet -->
        <template v-else-if="!surveyData?.surveyTarget">
            <div class="py-12 text-center">
                <ClipboardList class="text-muted-foreground mx-auto h-12 w-12" />
                <h3 class="mt-4 text-lg font-semibold">No survey created yet</h3>
                <p class="text-muted-foreground mt-2 text-sm">Create a course survey to collect student feedback.</p>
                <div class="mt-6">
                    <Button @click="openCreateDialog" :disabled="!surveyForms || surveyForms.length === 0">
                        <FileText class="mr-2 h-4 w-4" />
                        Create Survey
                    </Button>
                    <p v-if="!surveyForms || surveyForms.length === 0" class="text-muted-foreground mt-2 text-xs">No survey forms available. Please create a survey form first.</p>
                </div>
            </div>
        </template>

        <!-- Survey exists -->
        <template v-else>
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Survey Info -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-4 w-4" />
                                Survey Details
                            </CardTitle>
                            <Badge :variant="surveyData.surveyTarget.status === 'active' ? 'default' : 'secondary'">
                                {{ surveyData.surveyTarget.status.toUpperCase() }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="surveyData.formVersion">
                            <p class="text-muted-foreground text-sm font-medium">Form</p>
                            <p class="font-semibold">{{ surveyData.formVersion.title }}</p>
                            <p class="text-muted-foreground text-sm">{{ surveyData.formVersion.code }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Start Date</p>
                            <p class="font-semibold">{{ new Date(surveyData.surveyTarget.start_at).toLocaleDateString() }}</p>
                        </div>
                        <div v-if="surveyData.surveyTarget.end_at">
                            <p class="text-muted-foreground text-sm font-medium">End Date</p>
                            <p class="font-semibold">{{ new Date(surveyData.surveyTarget.end_at).toLocaleDateString() }}</p>
                        </div>
                        <div class="pt-2">
                            <Link :href="`/forms/admin/results?target_id=${surveyData.surveyTarget.id}`">
                                <Button variant="outline" size="sm">
                                    <BarChart3 class="mr-2 h-4 w-4" />
                                    View Full Results
                                    <ExternalLink class="ml-2 h-3 w-3" />
                                </Button>
                            </Link>
                        </div>
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
                    <CardContent class="space-y-4">
                        <div class="text-center">
                            <p class="text-4xl font-bold">{{ surveyData.completionStats.completed }}/{{ surveyData.completionStats.total }}</p>
                            <p class="text-muted-foreground mt-1 text-sm">Students Completed</p>
                            <div class="mt-3 h-2 w-full rounded-full bg-gray-200">
                                <div class="h-2 rounded-full bg-green-500" :style="{ width: `${surveyData.completionStats.total > 0 ? Math.round((surveyData.completionStats.completed / surveyData.completionStats.total) * 100) : 0}%` }"></div>
                            </div>
                            <p class="text-muted-foreground mt-1 text-sm">{{ surveyData.completionStats.total > 0 ? Math.round((surveyData.completionStats.completed / surveyData.completionStats.total) * 100) : 0 }}% completion rate</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4 pt-2 text-center">
                            <div>
                                <p class="text-2xl font-bold text-green-600">{{ surveyData.completionStats.completed }}</p>
                                <p class="text-muted-foreground text-sm">Completed</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-orange-500">{{ surveyData.completionStats.pending }}</p>
                                <p class="text-muted-foreground text-sm">Pending</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </template>

        <!-- Create Survey Dialog -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Create Course Survey</DialogTitle>
                    <DialogDescription>
                        Select a survey form for <strong>{{ courseOffering.course_code }}</strong>
                        <span v-if="courseOffering.section_code"> - Section {{ courseOffering.section_code }}</span>
                    </DialogDescription>
                </DialogHeader>
                <div class="min-w-0 space-y-4 overflow-hidden py-4">
                    <div class="min-w-0 space-y-2 overflow-hidden">
                        <label class="text-sm font-medium">Select Survey Form</label>
                        <div class="grid w-full min-w-0 grid-cols-1 overflow-hidden">
                            <Select v-model="createForm.form_id">
                                <SelectTrigger class="w-full min-w-0 overflow-hidden">
                                    <SelectValue placeholder="Select a form" class="block truncate text-left" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="form in surveyForms" :key="form.id" :value="form.id.toString()">
                                        <span class="block max-w-[280px] truncate" :title="`${form.title} (${form.code})`"> {{ form.title }} ({{ form.code }}) </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <p v-if="createForm.errors.form_id" class="text-destructive text-sm">{{ createForm.errors.form_id }}</p>
                    </div>
                    <div class="space-y-3 rounded-md border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                        <p class="text-sm text-blue-800 dark:text-blue-200">This will create a survey target for this course and automatically assign it to all enrolled students.</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="closeCreateDialog">Cancel</Button>
                    <Button :disabled="!createForm.form_id || createForm.processing" @click="handleCreateSurvey">
                        {{ createForm.processing ? 'Creating...' : 'Create Survey' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
