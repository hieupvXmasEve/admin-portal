<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, CheckCircle, Clock, Edit, FileText, User } from 'lucide-vue-next';

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
}

interface Semester {
    id: number;
    name: string;
    semester_type: string;
    year: string;
}

interface AssessmentComponentDetail {
    id: number;
    name: string;
    weight: number | null;
}

interface AssessmentComponent {
    id: number;
    name: string;
    weight: number;
    type: string;
    is_required_to_sit_final_exam: boolean;
    details: AssessmentComponentDetail[];
}

interface Syllabus {
    id: number;
    version: string | null;
    description: string | null;
    total_hours: number | null;
    hours_per_session: number | null;
    is_active: boolean;
    effective_from_semester: Semester | null;
    assessment_components: AssessmentComponent[];
    total_assessment_weight: number;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    unit: Unit;
    syllabus: Syllabus;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Units',
        href: '/units',
    },
    {
        title: props.unit.code,
        href: `/units/${props.unit.id}`,
    },
    {
        title: 'Syllabi',
        href: `/units/${props.unit.id}/syllabi`,
    },
    {
        title: props.syllabus.version || 'Untitled',
        href: `/units/${props.unit.id}/syllabi/${props.syllabus.id}`,
    },
];

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
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
</script>

<template>
    <Head :title="`Syllabus ${syllabus.version || 'Untitled'} - ${unit.code}`" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="mb-2 flex items-center gap-3">
                        <h1 class="text-3xl font-bold">{{ syllabus.version || 'Untitled Syllabus' }}</h1>
                        <Badge v-if="syllabus.is_active" class="bg-green-100 text-green-800"> Active </Badge>
                        <Badge v-else class="bg-gray-100 text-gray-800"> Inactive </Badge>
                    </div>
                    <p class="text-xl text-gray-700">{{ unit.code }} - {{ unit.name }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" @click="router.visit(`/units/${unit.id}/syllabi`)">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Syllabi
                    </Button>
                    <Button @click="router.visit(`/units/${unit.id}/syllabi/${syllabus.id}/edit`)">
                        <Edit class="mr-2 h-4 w-4" />
                        Edit
                    </Button>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Basic Information -->
                <Card class="lg:col-span-1">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="h-5 w-5" />
                            Syllabus Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <h4 class="mb-1 text-sm font-medium text-gray-500">Version</h4>
                            <p class="text-lg font-semibold">{{ syllabus.version || 'Not specified' }}</p>
                        </div>

                        <div v-if="syllabus.effective_from_semester">
                            <h4 class="mb-1 text-sm font-medium text-gray-500">Effective From</h4>
                            <div class="flex items-center gap-2">
                                <Calendar class="h-4 w-4 text-gray-400" />
                                <p class="text-lg">
                                    {{ syllabus.effective_from_semester.semester_type }}
                                    {{ syllabus.effective_from_semester.year }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div v-if="syllabus.total_hours">
                                <h4 class="mb-1 text-sm font-medium text-gray-500">Total Hours</h4>
                                <div class="flex items-center gap-2">
                                    <Clock class="h-4 w-4 text-gray-400" />
                                    <p class="font-semibold">{{ syllabus.total_hours }}</p>
                                </div>
                            </div>
                            <div v-if="syllabus.hours_per_session">
                                <h4 class="mb-1 text-sm font-medium text-gray-500">Hours/Session</h4>
                                <div class="flex items-center gap-2">
                                    <User class="h-4 w-4 text-gray-400" />
                                    <p class="font-semibold">{{ syllabus.hours_per_session }}</p>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        <div class="grid grid-cols-1 gap-4 text-sm">
                            <div>
                                <h4 class="mb-1 font-medium text-gray-500">Created</h4>
                                <p>{{ formatDate(syllabus.created_at) }}</p>
                            </div>
                            <div>
                                <h4 class="mb-1 font-medium text-gray-500">Last Updated</h4>
                                <p>{{ formatDate(syllabus.updated_at) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Assessment Overview -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <CheckCircle class="h-5 w-5" />
                                    Assessment Overview
                                </CardTitle>
                                <CardDescription> Assessment components and their weightings </CardDescription>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold">{{ syllabus.total_assessment_weight || 0 }}%</div>
                                <div class="text-sm text-gray-500">Total Weight</div>
                                <div v-if="syllabus.total_assessment_weight !== 100" class="text-xs text-orange-600">⚠️ Incomplete structure</div>
                                <div v-else class="text-xs text-green-600">✅ Complete structure</div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="syllabus.assessment_components.length > 0" class="space-y-4">
                            <div v-for="component in syllabus.assessment_components" :key="component.id" class="rounded-lg border p-4">
                                <div class="mb-3 flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="mb-2 flex items-center gap-3">
                                            <h3 class="text-lg font-semibold">{{ component.name }}</h3>
                                            <Badge :class="getAssessmentTypeColor(component.type)">
                                                {{ component.type.toUpperCase() }}
                                            </Badge>
                                        </div>
                                        <div class="flex items-center gap-4 text-sm text-gray-600">
                                            <span>Weight: {{ component.weight }}%</span>
                                            <span v-if="component.is_required_to_sit_final_exam" class="text-orange-600">
                                                Required for final exam
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold">{{ component.weight }}%</div>
                                    </div>
                                </div>

                                <!-- Component Details -->
                                <div v-if="component.details.length > 0" class="mt-3">
                                    <h4 class="mb-2 text-sm font-medium text-gray-700">Sub-components</h4>
                                    <div class="grid gap-2 md:grid-cols-2">
                                        <div v-for="detail in component.details" :key="detail.id" class="rounded border bg-gray-50 p-3">
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm font-medium">{{ detail.name }}</span>
                                                <span class="text-sm text-gray-500">{{ detail.weight }}%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-8 text-center">
                            <CheckCircle class="mx-auto mb-3 h-12 w-12 text-gray-400" />
                            <p class="text-gray-500">No assessment components defined</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Description -->
            <Card v-if="syllabus.description">
                <CardHeader>
                    <CardTitle>Description</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="prose max-w-none">
                        <p class="whitespace-pre-wrap text-gray-700">{{ syllabus.description }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
