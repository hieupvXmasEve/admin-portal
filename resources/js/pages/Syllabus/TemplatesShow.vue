<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { formatCurrency } from '@/types/finance';
import type { GradingScheme } from '@/types/grading-scheme';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, BookOpen, Calendar, CheckCircle2, Clock, Edit, Globe, Lock, MapPin, User, Users, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import GradingSchemePreview from './components/GradingSchemePreview.vue';

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points?: number;
}

interface Program {
    id: number;
    name: string;
    code?: string;
}

interface Campus {
    id: number;
    name: string;
    code?: string;
}

interface User {
    id: number;
    first_name: string;
    last_name: string;
    email?: string;
}

interface AssessmentComponentDetail {
    id: number;
    name: string;
    weight: number | null;
}

interface AssessmentComponent {
    id: number;
    name: string;
    code?: string;
    weight: number;
    type: string;
    is_required_to_sit_final_exam: boolean;
    details: AssessmentComponentDetail[];
}

interface SyllabusTemplate {
    id: number;
    title: string;
    version: string;
    description: string | null;
    total_hours: number;
    total_sessions: number;
    min_attendance_threshold: number | string;
    min_grade_threshold: number | string;
    exam_resit_fee: number | string | null;
    learning_outcomes?: string[];
    grading_criteria?: Array<{ name: string; weight: number }>;
    required_materials?: string[];
    assessment_policy?: string;
    delivery_mode: string | null;
    is_default: boolean;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    unit: Unit;
    applicable_program?: Program;
    applicable_campus?: Campus;
    creator?: User;
    source_template?: SyllabusTemplate;
    assessment_components?: AssessmentComponent[];
    grading_scheme?: GradingScheme | null;
}

const props = defineProps<{
    template: SyllabusTemplate;
    unit: Unit;
    can_edit: boolean;
}>();

const gradingScheme = computed(() => (props.template.grading_scheme as GradingScheme | null) ?? null);

const getDeliveryModeLabel = (mode: string | null) => {
    if (!mode) return 'Not specified';
    switch (mode) {
        case 'in_person':
            return 'In Person';
        case 'online':
            return 'Online';
        case 'hybrid':
            return 'Hybrid';
        case 'blended':
            return 'Blended';
        default:
            return mode.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
    }
};

const assessmentTypeLabels: Record<string, string> = {
    quiz: 'Quiz',
    assignment: 'Assignment',
    project: 'Project',
    exam: 'Exam',
    online_activity: 'Online Activity',
    attendance: 'Attendance',
    other: 'Other',
};

const getAssessmentTypeLabel = (type: string) => {
    return assessmentTypeLabels[type] ?? type.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const getAssessmentTypeColor = (type: string) => {
    switch (type) {
        case 'quiz':
            return 'bg-blue-100 text-blue-800';
        case 'assignment':
            return 'bg-green-100 text-green-800';
        case 'project':
            return 'bg-purple-100 text-purple-800';
        case 'exam':
            return 'bg-red-100 text-red-800';
        case 'online_activity':
            return 'bg-yellow-100 text-yellow-800';
        case 'attendance':
            return 'bg-indigo-100 text-indigo-800';
        case 'other':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const formatThreshold = (value: number | string) => Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 });

const formatExamResitFee = (fee: number | string | null) => {
    const amount = Number(fee ?? 0);
    if (!amount || amount <= 0) {
        return '—';
    }
    return formatCurrency(amount);
};

const totalAssessmentWeight = props.template.assessment_components?.reduce((sum, component) => sum + Number(component.weight), 0) || 0;
</script>

<template>
    <Head :title="`${template.title} (${template.version})`" />

    <!-- Header -->
    <div class="flex flex-col items-start justify-between gap-4 lg:flex-row lg:items-center">
        <div>
            <h1 class="text-3xl font-bold">{{ template.title }}</h1>
            <div class="text-muted-foreground">Version {{ template.version }} • {{ unit.code }} — {{ unit.name }}</div>
        </div>
        <div class="flex items-center gap-3">
            <Button variant="outline" @click="router.visit('/syllabus-templates')">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to Templates
            </Button>
            <Button v-if="can_edit" @click="router.visit(`/syllabus-templates/${template.id}/edit`)">
                <Edit class="mr-2 h-4 w-4" />
                Edit Template
            </Button>
        </div>
    </div>

    <Alert v-if="!can_edit" class="mt-6 border-amber-200 bg-amber-50 text-amber-900">
        <Lock class="h-4 w-4" />
        <AlertTitle>Template is read-only</AlertTitle>
        <AlertDescription> This syllabus template is assigned to a course offering that already has completed class sessions, so it cannot be edited. </AlertDescription>
    </Alert>

    <div class="mt-6 space-y-6">
        <!-- Grading scheme -->
        <Card>
            <CardHeader>
                <CardTitle>Grading scheme</CardTitle>
                <CardDescription>Rule-engine grading scheme applied to this template.</CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="!gradingScheme" class="text-muted-foreground text-sm">Default weighted percentage (no custom scheme configured).</div>
                <template v-else>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <div class="space-y-3">
                            <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
                                <div>
                                    <span class="text-muted-foreground">Engine</span>
                                    <p class="font-medium">{{ gradingScheme.engine }}</p>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Scale</span>
                                    <p class="font-medium">{{ gradingScheme.scale }}</p>
                                </div>
                                <div v-if="gradingScheme.source_reference">
                                    <span class="text-muted-foreground">Source</span>
                                    <p class="font-medium">{{ gradingScheme.source_reference }}</p>
                                </div>
                            </div>
                            <div v-if="gradingScheme.formula">
                                <span class="text-muted-foreground text-sm">Formula</span>
                                <p class="font-mono text-sm">{{ gradingScheme.formula }}</p>
                            </div>
                            <div v-if="gradingScheme.components?.length">
                                <span class="text-muted-foreground text-sm">Components</span>
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <Badge v-for="component in gradingScheme.components" :key="component.code" variant="secondary">
                                        {{ component.label ?? component.code }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        <GradingSchemePreview :scheme="gradingScheme" />
                    </div>
                </template>
            </CardContent>
        </Card>

        <!-- Basic Information -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Basic Information</CardTitle>
                        <CardDescription>General syllabus details and metadata</CardDescription>
                    </div>
                    <div class="flex gap-2">
                        <Badge v-if="template.is_default" variant="default">Default</Badge>
                        <Badge :variant="template.is_active ? 'default' : 'secondary'">
                            {{ template.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Title</div>
                        <p class="font-medium">{{ template.title }}</p>
                    </div>
                    <div class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Version</div>
                        <p class="font-medium">{{ template.version }}</p>
                    </div>
                    <div class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Unit</div>
                        <div>
                            <div class="font-semibold">{{ unit.code }}</div>
                            <div class="text-muted-foreground text-sm">{{ unit.name }}</div>
                            <div v-if="unit.credit_points" class="text-muted-foreground text-xs">{{ unit.credit_points }} Credit Points</div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Delivery Mode</div>
                        <Badge :class="template.delivery_mode ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'">
                            <Globe class="mr-1 h-3 w-3" />
                            {{ getDeliveryModeLabel(template.delivery_mode) }}
                        </Badge>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Total Hours</div>
                        <p class="font-medium">{{ template.total_hours }}</p>
                    </div>
                    <div class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Total Sessions</div>
                        <p class="font-medium">{{ template.total_sessions }}</p>
                        <p v-if="template.total_hours && template.total_sessions" class="text-muted-foreground text-xs">~{{ Math.round((template.total_hours / template.total_sessions) * 10) / 10 }} hours per session</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Min Attendance (%)</div>
                        <p class="font-medium">{{ formatThreshold(template.min_attendance_threshold) }}%</p>
                    </div>
                    <div class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Min Pass Grade (out of 100)</div>
                        <p class="font-medium">{{ formatThreshold(template.min_grade_threshold) }}</p>
                    </div>
                    <div class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Phí thi lại (VND)</div>
                        <p class="font-medium">{{ formatExamResitFee(template.exam_resit_fee) }}</p>
                    </div>
                </div>

                <div v-if="template.description" class="space-y-2">
                    <div class="text-muted-foreground text-sm font-medium">Description</div>
                    <p class="text-sm leading-relaxed">{{ template.description }}</p>
                </div>

                <div class="flex items-center gap-2 text-sm">
                    <component :is="template.is_active ? CheckCircle2 : XCircle" class="h-4 w-4" :class="template.is_active ? 'text-green-600' : 'text-muted-foreground'" />
                    <span>{{ template.is_active ? 'Active syllabus' : 'Inactive syllabus' }}</span>
                </div>

                <!-- Applicable Program and Campus -->
                <div v-if="template.applicable_program || template.applicable_campus" class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div v-if="template.applicable_program" class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Applicable Program</div>
                        <div class="flex items-center gap-2">
                            <Users class="text-muted-foreground h-4 w-4" />
                            <span class="font-medium">{{ template.applicable_program.name }}</span>
                            <span v-if="template.applicable_program.code" class="text-muted-foreground text-sm"> ({{ template.applicable_program.code }}) </span>
                        </div>
                    </div>

                    <div v-if="template.applicable_campus" class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Applicable Campus</div>
                        <div class="flex items-center gap-2">
                            <MapPin class="text-muted-foreground h-4 w-4" />
                            <span class="font-medium">{{ template.applicable_campus.name }}</span>
                            <span v-if="template.applicable_campus.code" class="text-muted-foreground text-sm"> ({{ template.applicable_campus.code }}) </span>
                        </div>
                    </div>
                </div>

                <!-- Metadata -->
                <Separator />
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div v-if="template.creator" class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Created By</div>
                        <div class="flex items-center gap-2">
                            <User class="text-muted-foreground h-4 w-4" />
                            <span>{{ template.creator.first_name }} {{ template.creator.last_name }}</span>
                            <span v-if="template.creator.email" class="text-muted-foreground text-sm"> ({{ template.creator.email }}) </span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="text-muted-foreground text-sm font-medium">Dates</div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-sm">
                                <Calendar class="text-muted-foreground h-3 w-3" />
                                <span class="text-muted-foreground">Created:</span>
                                <span>{{ formatDate(template.created_at) }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <Clock class="text-muted-foreground h-3 w-3" />
                                <span class="text-muted-foreground">Updated:</span>
                                <span>{{ formatDate(template.updated_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Source Template -->
                <div v-if="template.source_template" class="space-y-2">
                    <div class="text-muted-foreground text-sm font-medium">Cloned From</div>
                    <div class="bg-muted/30 rounded-lg border p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium">{{ template.source_template.title }}</div>
                                <div class="text-muted-foreground text-sm">Version {{ template.source_template.version }}</div>
                            </div>
                            <Button variant="ghost" size="sm" @click="router.visit(`/syllabus-templates/${template.source_template.id}`)"> View Original </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Assessment Components -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <BookOpen class="h-5 w-5" />
                            Assessment Components
                            <Badge v-if="template.assessment_components?.length" :class="totalAssessmentWeight === 100 ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'"> {{ totalAssessmentWeight }}% </Badge>
                        </CardTitle>
                        <CardDescription>Assessment structure and weightings for this syllabus template</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="!template.assessment_components?.length" class="text-muted-foreground text-sm">No assessment components configured.</div>
                <div v-else class="space-y-4">
                    <div v-for="component in template.assessment_components" :key="component.id" class="space-y-3 rounded-lg border p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="font-medium">{{ component.name }}</div>
                                <Badge v-if="component.code" variant="secondary" class="font-mono text-xs">
                                    {{ component.code }}
                                </Badge>
                                <Badge :class="getAssessmentTypeColor(component.type)">
                                    {{ getAssessmentTypeLabel(component.type) }}
                                </Badge>
                                <Badge v-if="component.is_required_to_sit_final_exam" variant="outline" class="text-xs"> Required for Final </Badge>
                            </div>
                            <div class="text-lg font-semibold">{{ component.weight }}%</div>
                        </div>

                        <!-- Component Details -->
                        <div v-if="component.details && component.details.length > 0" class="ml-4">
                            <div class="text-muted-foreground mb-2 text-sm font-medium">Sub-components</div>
                            <div class="space-y-2">
                                <div v-for="detail in component.details" :key="detail.id" class="bg-muted/30 flex items-center justify-between rounded border p-2">
                                    <span class="text-sm">{{ detail.name }}</span>
                                    <span class="text-sm font-medium">
                                        {{ detail.weight !== null ? `${detail.weight}%` : 'No weight' }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-muted-foreground mt-2 text-xs">Subtotal: {{ component.details.reduce((sum, d) => sum + (d.weight || 0), 0) }}%</div>
                        </div>
                    </div>

                    <!-- Total Weight Summary -->
                    <div class="bg-muted/30 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Total Assessment Weight:</span>
                            <Badge :class="totalAssessmentWeight === 100 ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'"> {{ totalAssessmentWeight }}% / 100% </Badge>
                        </div>
                        <p v-if="totalAssessmentWeight !== 100" class="mt-1 flex items-center gap-1 text-sm text-orange-600">
                            <AlertTriangle class="h-4 w-4" />
                            Assessment components do not total 100%. This template may need adjustment.
                        </p>
                        <p v-else class="mt-1 flex items-center gap-1 text-sm text-green-600">
                            <CheckCircle2 class="h-4 w-4" />
                            Assessment structure is complete and valid.
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Additional Information -->
        <div v-if="template.learning_outcomes || template.grading_criteria || template.required_materials || template.assessment_policy" class="grid gap-6 lg:grid-cols-2">
            <!-- Learning Outcomes -->
            <Card v-if="template.learning_outcomes && template.learning_outcomes.length > 0">
                <CardHeader>
                    <CardTitle class="text-lg">Learning Outcomes</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-2">
                        <li v-for="(outcome, index) in template.learning_outcomes" :key="index" class="flex gap-2">
                            <span class="text-muted-foreground mt-1">•</span>
                            <span class="text-sm">{{ outcome }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Required Materials -->
            <Card v-if="template.required_materials && template.required_materials.length > 0">
                <CardHeader>
                    <CardTitle class="text-lg">Required Materials</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-2">
                        <li v-for="(material, index) in template.required_materials" :key="index" class="flex gap-2">
                            <span class="text-muted-foreground mt-1">•</span>
                            <span class="text-sm">{{ material }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Grading Criteria -->
            <Card v-if="template.grading_criteria && template.grading_criteria.length > 0">
                <CardHeader>
                    <CardTitle class="text-lg">Grading Criteria</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <div v-for="(criteria, index) in template.grading_criteria" :key="index" class="flex items-center justify-between rounded border p-2">
                            <span class="font-medium">{{ criteria.name }}</span>
                            <span class="text-sm font-medium">{{ criteria.weight }}%</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Assessment Policy -->
            <Card v-if="template.assessment_policy">
                <CardHeader>
                    <CardTitle class="text-lg">Assessment Policy</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm leading-relaxed">{{ template.assessment_policy }}</p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
