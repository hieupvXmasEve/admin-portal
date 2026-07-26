<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, CheckCircle, ChevronDown, ChevronRight, Clock, Edit, FileText, User } from 'lucide-vue-next';
import { ref } from 'vue';

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

interface Program {
    id: number;
    name: string;
    degree_level?: string;
}

interface Specialization {
    id: number;
    name: string;
    description?: string;
}

interface CurriculumVersion {
    id: number;
    version_code: string;
    program: Program;
    specialization: Specialization;
}

interface CurriculumUnit {
    id: number;
    semester: Semester;
    curriculum_version: CurriculumVersion;
    type: 'core' | 'major' | 'elective';
    year_level?: number;
    semester_number?: number;
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
    curriculum_unit_id: number;
    version: string | null;
    description: string | null;
    total_hours: number | null;
    hours_per_session: number | null;
    is_active: boolean;
    curriculum_unit: CurriculumUnit;
    assessment_components: AssessmentComponent[];
    total_assessment_weight: number;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    unit: Unit;
    syllabus: Syllabus;
}>();
console.log('syllabus', props.syllabus.curriculum_unit);
// Track which components have expanded details
const expandedComponents = ref<Set<number>>(new Set());

const toggleComponentDetails = (componentId: number) => {
    if (expandedComponents.value.has(componentId)) {
        expandedComponents.value.delete(componentId);
    } else {
        expandedComponents.value.add(componentId);
    }
};

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
            <Button variant="outline" @click="router.visit(`/units/${unit.id}/syllabus`)">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to Syllabus
            </Button>
            <Button @click="router.visit(`/units/${unit.id}/syllabus/${syllabus.id}/edit`)">
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

                <div>
                    <h4 class="mb-1 text-sm font-medium text-gray-500">Context</h4>
                    <div class="flex items-center gap-2">
                        <Calendar class="h-4 w-4 text-gray-400" />
                        <div>
                            <p class="text-base font-semibold">{{ syllabus.curriculum_unit?.semester?.name }}</p>
                            <p class="text-sm text-gray-600">{{ syllabus.curriculum_unit?.curriculum_version?.specialization?.name }}</p>
                            <p class="text-xs text-gray-500">{{ syllabus.curriculum_unit?.curriculum_version?.program?.name }}</p>
                        </div>
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
                            Assessment Components
                        </CardTitle>
                        <CardDescription>Assessment components and their weightings</CardDescription>
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
                    <!-- Assessment Components Table -->
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Weight</TableHead>
                                <TableHead>Required for Final Exam?</TableHead>
                                <TableHead>Details</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="component in syllabus.assessment_components" :key="component.id">
                                <TableCell class="font-medium">{{ component.name }}</TableCell>
                                <TableCell>
                                    <Badge :class="getAssessmentTypeColor(component.type)">
                                        {{ component.type.toLowerCase().replace('_', ' ') }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ component.weight }}</TableCell>
                                <TableCell>
                                    <span v-if="component.is_required_to_sit_final_exam" class="text-orange-600">Yes</span>
                                    <span v-else class="text-gray-500">No</span>
                                </TableCell>
                                <TableCell>
                                    <Button v-if="component.details.length > 0" variant="outline" size="sm" @click="toggleComponentDetails(component.id)" class="text-xs"> View Details </Button>
                                    <span v-else class="text-sm text-gray-400">-</span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <!-- Component Details Sections -->
                    <div v-for="component in syllabus.assessment_components.filter((c) => c.details.length > 0)" :key="`details-${component.id}`">
                        <Collapsible :open="expandedComponents.has(component.id)">
                            <CollapsibleTrigger @click="toggleComponentDetails(component.id)" class="flex w-full items-center gap-2 rounded-lg border p-3 text-left transition-colors hover:bg-gray-50">
                                <ChevronRight v-if="!expandedComponents.has(component.id)" class="h-4 w-4 text-gray-400" />
                                <ChevronDown v-else class="h-4 w-4 text-gray-400" />
                                <span class="font-medium">{{ component.name }} - Sub-components</span>
                            </CollapsibleTrigger>
                            <CollapsibleContent class="mt-2">
                                <div class="border-l-2 border-gray-200 pl-6">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead class="text-sm">Sub-task</TableHead>
                                                <TableHead class="text-sm">Weight</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-for="detail in component.details" :key="detail.id">
                                                <TableCell class="text-sm">{{ detail.name }}</TableCell>
                                                <TableCell class="text-sm">{{ detail.weight || '-' }}</TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
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
</template>
