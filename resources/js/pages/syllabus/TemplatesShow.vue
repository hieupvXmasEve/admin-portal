<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { GradingScheme } from '@/types/grading-scheme';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Edit, FileText, Globe, MapPin, User, Users, Calendar, Clock } from 'lucide-vue-next';

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
}>();

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
            return mode.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
};

const getAssessmentTypeLabel = (type: string) => {
    switch (type) {
        case 'quiz':
            return 'Quiz';
        case 'assignment':
            return 'Assignment';
        case 'project':
            return 'Project';
        case 'exam':
            return 'Exam';
        case 'online_activity':
            return 'Online Activity';
        case 'other':
            return 'Other';
        default:
            return type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
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
        case 'other':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const totalAssessmentWeight = props.template.assessment_components?.reduce((sum, component) => sum + Number(component.weight), 0) || 0;
</script>

<template>
    <Head :title="`${template.title} (${template.version})`" />

    <!-- Header -->
    <div class="flex flex-col items-start justify-between gap-4 lg:flex-row lg:items-center">
        <div>
            <h1 class="text-3xl font-bold">{{ template.title }}</h1>
            <div class="text-muted-foreground">
                Version {{ template.version }} • {{ unit.code }} — {{ unit.name }}
            </div>
        </div>
        <div class="flex items-center gap-3">
            <Button variant="outline" @click="router.visit('/syllabus-templates')">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to Templates
            </Button>
            <Button @click="router.visit(`/syllabus-templates/${template.id}/edit`)">
                <Edit class="mr-2 h-4 w-4" />
                Edit Template
            </Button>
        </div>
    </div>

    <div class="space-y-6">
        <!-- Status and Basic Info -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Template Overview
                    </CardTitle>
                    <div class="flex gap-2">
                        <Badge v-if="template.is_default" variant="default">Default</Badge>
                        <Badge :variant="template.is_active ? 'default' : 'secondary'">
                            {{ template.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500">Unit Information</div>
                        <div>
                            <div class="font-semibold">{{ unit.code }}</div>
                            <div class="text-sm text-gray-600">{{ unit.name }}</div>
                            <div v-if="unit.credit_points" class="text-xs text-gray-500">
                                {{ unit.credit_points }} Credit Points
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500">Duration</div>
                        <div>
                            <div class="font-semibold">{{ template.total_hours }} Hours Total</div>
                            <div class="text-sm text-gray-600">{{ template.total_sessions }} Sessions</div>
                            <div v-if="template.total_hours && template.total_sessions" class="text-xs text-gray-500">
                                ~{{ Math.round((template.total_hours / template.total_sessions) * 10) / 10 }} hours per session
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500">Delivery Mode</div>
                        <div>
                            <Badge :class="template.delivery_mode ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'">
                                <Globe class="mr-1 h-3 w-3" />
                                {{ getDeliveryModeLabel(template.delivery_mode) }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div v-if="template.description" class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">Description</div>
                    <p class="text-sm text-gray-700 leading-relaxed">{{ template.description }}</p>
                </div>

                <!-- Applicable Program and Campus -->
                <div v-if="template.applicable_program || template.applicable_campus" class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div v-if="template.applicable_program" class="space-y-2">
                        <div class="text-sm font-medium text-gray-500">Applicable Program</div>
                        <div class="flex items-center gap-2">
                            <Users class="h-4 w-4 text-gray-400" />
                            <span class="font-medium">{{ template.applicable_program.name }}</span>
                            <span v-if="template.applicable_program.code" class="text-sm text-gray-500">
                                ({{ template.applicable_program.code }})
                            </span>
                        </div>
                    </div>

                    <div v-if="template.applicable_campus" class="space-y-2">
                        <div class="text-sm font-medium text-gray-500">Applicable Campus</div>
                        <div class="flex items-center gap-2">
                            <MapPin class="h-4 w-4 text-gray-400" />
                            <span class="font-medium">{{ template.applicable_campus.name }}</span>
                            <span v-if="template.applicable_campus.code" class="text-sm text-gray-500">
                                ({{ template.applicable_campus.code }})
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Metadata -->
                <Separator />
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div v-if="template.creator" class="space-y-2">
                        <div class="text-sm font-medium text-gray-500">Created By</div>
                        <div class="flex items-center gap-2">
                            <User class="h-4 w-4 text-gray-400" />
                            <span>{{ template.creator.first_name }} {{ template.creator.last_name }}</span>
                            <span v-if="template.creator.email" class="text-sm text-gray-500">
                                ({{ template.creator.email }})
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500">Dates</div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-sm">
                                <Calendar class="h-3 w-3 text-gray-400" />
                                <span class="text-gray-500">Created:</span>
                                <span>{{ formatDate(template.created_at) }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <Clock class="h-3 w-3 text-gray-400" />
                                <span class="text-gray-500">Updated:</span>
                                <span>{{ formatDate(template.updated_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Source Template -->
                <div v-if="template.source_template" class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">Cloned From</div>
                    <div class="rounded-lg border p-3 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium">{{ template.source_template.title }}</div>
                                <div class="text-sm text-gray-600">Version {{ template.source_template.version }}</div>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="router.visit(`/syllabus-templates/${template.source_template.id}`)"
                            >
                                View Original
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Assessment Components -->
        <Card v-if="template.assessment_components && template.assessment_components.length > 0">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <BookOpen class="h-5 w-5" />
                            Assessment Components
                            <Badge :class="totalAssessmentWeight === 100 ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'">
                                {{ totalAssessmentWeight }}%
                            </Badge>
                        </CardTitle>
                        <CardDescription>Assessment structure and weightings for this syllabus template</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    <div
                        v-for="component in template.assessment_components"
                        :key="component.id"
                        class="rounded-lg border p-4 space-y-3"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="font-medium">{{ component.name }}</div>
                                <Badge v-if="component.code" variant="secondary" class="font-mono text-xs">
                                    {{ component.code }}
                                </Badge>
                                <Badge :class="getAssessmentTypeColor(component.type)">
                                    {{ getAssessmentTypeLabel(component.type) }}
                                </Badge>
                                <Badge v-if="component.is_required_to_sit_final_exam" variant="outline" class="text-xs">
                                    Required for Final
                                </Badge>
                            </div>
                            <div class="text-lg font-semibold">{{ component.weight }}%</div>
                        </div>

                        <!-- Component Details -->
                        <div v-if="component.details && component.details.length > 0" class="ml-4">
                            <div class="text-sm font-medium text-gray-500 mb-2">Sub-components</div>
                            <div class="space-y-2">
                                <div
                                    v-for="detail in component.details"
                                    :key="detail.id"
                                    class="flex items-center justify-between rounded border bg-gray-50 p-2"
                                >
                                    <span class="text-sm">{{ detail.name }}</span>
                                    <span class="text-sm font-medium">
                                        {{ detail.weight !== null ? `${detail.weight}%` : 'No weight' }}
                                    </span>
                                </div>
                            </div>
                            <div class="mt-2 text-xs text-gray-500">
                                Subtotal: {{ component.details.reduce((sum, d) => sum + (d.weight || 0), 0) }}%
                            </div>
                        </div>
                    </div>

                    <!-- Total Weight Summary -->
                    <div class="rounded-lg bg-gray-50 p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Total Assessment Weight:</span>
                            <Badge :class="totalAssessmentWeight === 100 ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'">
                                {{ totalAssessmentWeight }}% / 100%
                            </Badge>
                        </div>
                        <p v-if="totalAssessmentWeight !== 100" class="mt-1 text-sm text-orange-600">
                            ⚠️ Assessment components do not total 100%. This template may need adjustment.
                        </p>
                        <p v-else class="mt-1 text-sm text-green-600">
                            ✓ Assessment structure is complete and valid.
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Grading scheme (S-003) -->
        <Card v-if="template.grading_scheme">
            <CardHeader>
                <CardTitle>Grading scheme</CardTitle>
                <CardDescription>Rule-engine scheme applied to this template.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="flex flex-wrap gap-x-8 gap-y-2 text-sm">
                    <div>
                        <span class="text-muted-foreground">Engine</span>
                        <p class="font-medium">{{ template.grading_scheme.engine }}</p>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Scale</span>
                        <p class="font-medium">{{ template.grading_scheme.scale }}</p>
                    </div>
                    <div v-if="template.grading_scheme.source_reference">
                        <span class="text-muted-foreground">Source</span>
                        <p class="font-medium">{{ template.grading_scheme.source_reference }}</p>
                    </div>
                </div>
                <div v-if="template.grading_scheme.formula">
                    <span class="text-sm text-muted-foreground">Formula</span>
                    <p class="font-mono text-sm">{{ template.grading_scheme.formula }}</p>
                </div>
                <div v-if="template.grading_scheme.components?.length">
                    <span class="text-sm text-muted-foreground">Components</span>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <Badge v-for="component in template.grading_scheme.components" :key="component.code" variant="secondary">
                            {{ component.label ?? component.code }}
                        </Badge>
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
                            <span class="text-gray-400 mt-1">•</span>
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
                            <span class="text-gray-400 mt-1">•</span>
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
                        <div
                            v-for="(criteria, index) in template.grading_criteria"
                            :key="index"
                            class="flex items-center justify-between rounded border p-2"
                        >
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
                    <p class="text-sm text-gray-700 leading-relaxed">{{ template.assessment_policy }}</p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

<style scoped>
/* Add any specific styles if needed */
</style>
