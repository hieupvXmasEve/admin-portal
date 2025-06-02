<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { UnitData as Unit } from '@/types/Unit';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, CheckCircle, Clock, Edit, FileText, GitBranch, GraduationCap, Network, Shield, Target, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

interface Syllabus {
    id: number;
    version: string | null;
    description: string | null;
    total_hours: number | null;
    hours_per_session: number | null;
    is_active: boolean;
    effective_from_semester: {
        id: number;
        term: string;
        year: number;
    } | null;
    assessment_components: AssessmentComponent[];
    total_assessment_weight: number;
}

interface AssessmentComponent {
    id: number;
    name: string;
    weight: number;
    type: string;
    is_required_to_sit_final_exam: boolean;
    details: AssessmentComponentDetail[];
}

interface AssessmentComponentDetail {
    id: number;
    name: string;
    weight: number | null;
}

interface UnitData extends Unit {
    syllabus: Syllabus[];
}

interface RelationshipStats {
    prerequisite_count: number;
    corequisite_count: number;
    antirequisite_count: number;
    prerequisite_conditions_count: number;
    equivalent_count: number;
    curriculum_count: number;
    syllabus_count: number;
}

interface EquivalentUnitItem {
    id: number;
    unit: {
        id: number;
        code: string;
        name: string;
        credit_points: number;
    };
    reason: string | null;
    valid_from_semester: {
        id: number;
        term: string;
        year: number;
    } | null;
    relationship_type: 'equivalent_to' | 'equivalent_from';
}

const props = defineProps<{
    unit: UnitData;
    equivalentUnits: EquivalentUnitItem[];
    relationshipStats: RelationshipStats;
    prerequisiteDescriptions?: string | null;
    canEdit: boolean;
    canDelete: boolean;
}>();
console.log(props.unit);
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Units',
        href: '/units',
    },
    {
        title: props.unit.code,
        href: `/units/${props.unit.id}`,
    },
];

// Delete confirmation
const isDeleting = ref(false);

const deleteUnit = () => {
    isDeleting.value = true;

    router.delete(`/units/${props.unit.id}`, {
        onSuccess: () => {
            toast.success('Unit deleted successfully');
        },
        onError: () => {
            toast.error('Failed to delete unit');
            isDeleting.value = false;
        },
    });
};

// Helper functions
const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const getConditionTypeColor = (type: string) => {
    switch (type) {
        case 'prerequisite':
            return 'bg-blue-100 text-blue-800';
        case 'co_requisite':
            return 'bg-green-100 text-green-800';
        case 'concurrent':
            return 'bg-yellow-100 text-yellow-800';
        case 'anti_requisite':
            return 'bg-red-100 text-red-800';
        case 'assumed_knowledge':
            return 'bg-purple-100 text-purple-800';
        case 'credit_requirement':
            return 'bg-orange-100 text-orange-800';
        case 'textual':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getConditionTypeIcon = (type: string) => {
    switch (type) {
        case 'prerequisite':
            return BookOpen;
        case 'co_requisite':
            return GitBranch;
        case 'concurrent':
            return Clock;
        case 'anti_requisite':
            return Shield;
        case 'assumed_knowledge':
            return FileText;
        case 'credit_requirement':
            return Target;
        case 'textual':
            return FileText;
        default:
            return CheckCircle;
    }
};

const getGroupTypeColor = (groupType: string) => {
    switch (groupType) {
        case 'core':
            return 'bg-red-100 text-red-800';
        case 'major':
            return 'bg-blue-100 text-blue-800';
        case 'elective':
            return 'bg-green-100 text-green-800';
        case 'minor':
            return 'bg-purple-100 text-purple-800';
        case 'second_major':
            return 'bg-orange-100 text-orange-800';
        default:
            return 'bg-gray-100 text-gray-800';
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

// Check if unit has any relationships
const hasRelationships = () => {
    return (
        (props.unit.prerequisite_groups && props.unit.prerequisite_groups.length > 0) ||
        (props.equivalentUnits && props.equivalentUnits.length > 0) ||
        (props.unit.curriculum_units && props.unit.curriculum_units.length > 0)
    );
};
</script>

<template>
    <Head :title="`Unit - ${unit.code}`" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="mb-2 flex items-center gap-3">
                        <h1 class="text-3xl font-bold">{{ unit.code }}</h1>
                        <Badge class="bg-blue-100 text-blue-800">{{ unit.credit_points }} CP</Badge>
                    </div>
                    <p class="text-xl text-gray-700">{{ unit.name }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" @click="router.visit('/units')">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Units
                    </Button>
                    <Button v-if="canEdit" variant="outline" @click="router.visit(`/units/edit/${unit.id}`)">
                        <Edit class="mr-2 h-4 w-4" />
                        Edit
                    </Button>
                    <AlertDialog v-if="canDelete">
                        <AlertDialogTrigger as-child>
                            <Button variant="destructive" :disabled="isDeleting">
                                <Trash2 class="mr-2 h-4 w-4" />
                                {{ isDeleting ? 'Deleting...' : 'Delete' }}
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>Delete Unit</AlertDialogTitle>
                                <AlertDialogDescription>
                                    Are you sure you want to delete unit <strong>{{ unit.code }}</strong
                                    >? This action cannot be undone and will remove all related data.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Cancel</AlertDialogCancel>
                                <AlertDialogAction @click="deleteUnit" class="bg-red-600 hover:bg-red-700"> Delete Unit </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-7">
                <Card class="p-4">
                    <div class="flex items-center gap-2">
                        <Network class="h-5 w-5 text-blue-600" />
                        <div>
                            <p class="text-sm text-gray-600">Groups</p>
                            <p class="text-2xl font-bold">{{ relationshipStats.prerequisite_conditions_count }}</p>
                        </div>
                    </div>
                </Card>
                <Card class="p-4">
                    <div class="flex items-center gap-2">
                        <BookOpen class="h-5 w-5 text-green-600" />
                        <div>
                            <p class="text-sm text-gray-600">Prerequisites</p>
                            <p class="text-2xl font-bold">{{ relationshipStats.prerequisite_count }}</p>
                        </div>
                    </div>
                </Card>
                <Card class="p-4">
                    <div class="flex items-center gap-2">
                        <GitBranch class="h-5 w-5 text-yellow-600" />
                        <div>
                            <p class="text-sm text-gray-600">Corequisites</p>
                            <p class="text-2xl font-bold">{{ relationshipStats.corequisite_count }}</p>
                        </div>
                    </div>
                </Card>
                <Card class="p-4">
                    <div class="flex items-center gap-2">
                        <Shield class="h-5 w-5 text-red-600" />
                        <div>
                            <p class="text-sm text-gray-600">Antirequisites</p>
                            <p class="text-2xl font-bold">{{ relationshipStats.antirequisite_count }}</p>
                        </div>
                    </div>
                </Card>
                <Card class="p-4">
                    <div class="flex items-center gap-2">
                        <Target class="h-5 w-5 text-purple-600" />
                        <div>
                            <p class="text-sm text-gray-600">Equivalents</p>
                            <p class="text-2xl font-bold">{{ relationshipStats.equivalent_count }}</p>
                        </div>
                    </div>
                </Card>
                <Card class="p-4">
                    <div class="flex items-center gap-2">
                        <GraduationCap class="h-5 w-5 text-orange-600" />
                        <div>
                            <p class="text-sm text-gray-600">Curricula</p>
                            <p class="text-2xl font-bold">{{ relationshipStats.curriculum_count }}</p>
                        </div>
                    </div>
                </Card>
                <Card class="p-4">
                    <div class="flex items-center gap-2">
                        <FileText class="h-5 w-5 text-indigo-600" />
                        <div>
                            <p class="text-sm text-gray-600">syllabus</p>
                            <p class="text-2xl font-bold">{{ relationshipStats.syllabus_count || 0 }}</p>
                        </div>
                    </div>
                </Card>
            </div>

            <!-- Main Content Grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Basic Information -->
                <Card class="lg:col-span-1">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <BookOpen class="h-5 w-5" />
                            Unit Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <h4 class="mb-1 text-sm font-medium text-gray-500">Unit Code</h4>
                            <p class="font-mono text-lg font-semibold">{{ unit.code }}</p>
                        </div>
                        <div>
                            <h4 class="mb-1 text-sm font-medium text-gray-500">Unit Name</h4>
                            <p class="text-lg">{{ unit.name }}</p>
                        </div>
                        <div>
                            <h4 class="mb-1 text-sm font-medium text-gray-500">Credit Points</h4>
                            <p class="text-lg font-semibold text-blue-600">{{ unit.credit_points }}</p>
                        </div>
                        <Separator />
                        <div class="grid grid-cols-1 gap-4 text-sm">
                            <div>
                                <h4 class="mb-1 font-medium text-gray-500">Created</h4>
                                <p>{{ formatDate(unit.created_at) }}</p>
                            </div>
                            <div>
                                <h4 class="mb-1 font-medium text-gray-500">Last Updated</h4>
                                <p>{{ formatDate(unit.updated_at) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Prerequisite Groups -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Network class="h-5 w-5" />
                            Prerequisites Structure
                        </CardTitle>
                        <CardDescription> Requirements that students must meet before taking {{ unit.code }} </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="unit.prerequisite_groups && unit.prerequisite_groups.length > 0" class="space-y-4">
                            <!-- Human-readable description -->
                            <div v-if="prerequisiteDescriptions" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                                <h4 class="mb-2 text-sm font-semibold text-blue-800">Summary</h4>
                                <pre class="text-sm font-medium whitespace-pre-wrap text-blue-700">{{ prerequisiteDescriptions }}</pre>
                            </div>

                            <!-- Detailed prerequisite groups -->
                            <div class="space-y-4">
                                <div v-for="(group, groupIndex) in unit.prerequisite_groups" :key="group.id" class="rounded-lg border bg-gray-50 p-4">
                                    <div class="mb-3 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <Badge
                                                :class="
                                                    group.logic_operator === 'AND' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'
                                                "
                                            >
                                                {{ group.logic_operator }}
                                            </Badge>
                                            <span class="text-sm font-medium text-gray-600">Group {{ groupIndex + 1 }}</span>
                                        </div>
                                    </div>

                                    <div v-if="group.description" class="mb-3">
                                        <p class="text-sm text-gray-600 italic">{{ group.description }}</p>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div
                                            v-for="condition in group.conditions"
                                            :key="condition.id"
                                            class="rounded-lg border bg-white p-3 transition-colors hover:bg-gray-50"
                                            :class="condition.required_unit ? 'cursor-pointer' : ''"
                                            @click="condition.required_unit ? router.visit(`/units/${condition.required_unit.id}`) : null"
                                        >
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <div class="mb-2 flex items-center gap-2">
                                                        <component :is="getConditionTypeIcon(condition.type)" class="h-4 w-4" />
                                                        <Badge :class="getConditionTypeColor(condition.type)" class="text-xs">
                                                            {{ condition.type.replace('_', ' ').toUpperCase() }}
                                                        </Badge>
                                                    </div>

                                                    <!-- Unit prerequisite -->
                                                    <div v-if="condition.required_unit">
                                                        <code class="font-mono text-sm font-semibold">{{ condition.required_unit.code }}</code>
                                                        <p class="mt-1 text-sm text-gray-600">
                                                            {{ condition.required_unit.name }}
                                                        </p>
                                                        <p class="mt-1 text-xs text-gray-500">{{ condition.required_unit.credit_points }} CP</p>
                                                    </div>

                                                    <!-- Credit requirement -->
                                                    <div v-else-if="condition.required_credits">
                                                        <p class="text-sm font-medium">{{ condition.required_credits }} Credit Points Required</p>
                                                    </div>

                                                    <!-- Free text requirement -->
                                                    <div v-else-if="condition.free_text">
                                                        <p class="text-sm">{{ condition.free_text }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="py-8 text-center">
                            <Network class="mx-auto mb-3 h-12 w-12 text-gray-400" />
                            <p class="text-gray-500">No prerequisites required for this unit</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Equivalent Units Section -->
            <Card v-if="equivalentUnits && equivalentUnits.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Target class="h-5 w-5" />
                        Equivalent Units
                    </CardTitle>
                    <CardDescription> Units that are equivalent to {{ unit.code }} (including transitive relationships) </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div
                            v-for="equivalent in equivalentUnits"
                            :key="equivalent.id"
                            class="cursor-pointer rounded-lg border p-3 transition-colors hover:bg-gray-50"
                            @click="router.visit(`/units/${equivalent.unit.id}`)"
                        >
                            <div class="mb-2 flex items-start justify-between">
                                <div>
                                    <code class="font-mono text-sm font-semibold">{{ equivalent.unit.code }}</code>
                                    <p class="text-sm text-gray-600">{{ equivalent.unit.name }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge class="bg-purple-100 text-xs text-purple-800"> {{ equivalent.unit.credit_points }} CP </Badge>
                                    <Badge
                                        class="text-xs"
                                        :class="
                                            equivalent.relationship_type === 'equivalent_to'
                                                ? 'bg-blue-100 text-blue-800'
                                                : 'bg-green-100 text-green-800'
                                        "
                                    >
                                        {{ equivalent.relationship_type === 'equivalent_to' ? 'Replaces' : 'Replaced by' }}
                                    </Badge>
                                </div>
                            </div>
                            <p v-if="equivalent.reason" class="mb-1 text-xs text-gray-500">{{ equivalent.reason }}</p>
                            <p v-if="equivalent.valid_from_semester" class="text-xs text-gray-400">
                                Valid from {{ equivalent.valid_from_semester.term }} {{ equivalent.valid_from_semester.year }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Curriculum Usage Section -->
            <Card v-if="unit.curriculum_units && unit.curriculum_units.length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <GraduationCap class="h-5 w-5" />
                        Curriculum Usage
                    </CardTitle>
                    <CardDescription> Programs and specializations that include this unit</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="curriculumUnit in unit.curriculum_units"
                            :key="curriculumUnit.id"
                            class="rounded-lg border p-4 transition-colors hover:bg-gray-50"
                        >
                            <div class="mb-3 flex items-start justify-between">
                                <div class="flex-1">
                                    <p class="text-sm font-semibold">{{ curriculumUnit.curriculum_version.program.name }}</p>
                                    <p v-if="curriculumUnit.curriculum_version.specialization" class="text-xs text-gray-600">
                                        {{ curriculumUnit.curriculum_version.specialization.name }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ curriculumUnit.curriculum_version.version_code }}
                                    </p>
                                </div>
                                <Badge :class="getGroupTypeColor(curriculumUnit.group_type)" class="text-xs">
                                    {{ curriculumUnit.group_type.toUpperCase() }}
                                </Badge>
                            </div>

                            <div class="flex items-center justify-between text-xs">
                                <span :class="curriculumUnit.is_required ? 'font-medium text-red-600' : 'text-gray-500'">
                                    {{ curriculumUnit.is_required ? 'Required' : 'Elective' }}
                                </span>
                                <span v-if="curriculumUnit.year_level || curriculumUnit.semester_number" class="text-gray-500">
                                    <span v-if="curriculumUnit.year_level">Year {{ curriculumUnit.year_level }}</span>
                                    <span v-if="curriculumUnit.semester_number"> Sem {{ curriculumUnit.semester_number }}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- syllabus Section -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                syllabus
                            </CardTitle>
                            <CardDescription>Course syllabus and assessment structures for {{ unit.code }} </CardDescription>
                        </div>
                        <Button v-if="canEdit" variant="outline" @click="router.visit(`/units/${unit.id}/syllabus`)">
                            <FileText class="mr-2 h-4 w-4" />
                            Manage syllabus
                        </Button>
                    </div>
                </CardHeader>
                <CardContent v-if="unit.syllabus && unit.syllabus.length > 0">
                    <div class="space-y-4">
                        <div
                            v-for="syllabus in unit.syllabus"
                            :key="syllabus.id"
                            class="rounded-lg border p-4 transition-colors hover:bg-gray-50"
                            :class="syllabus.is_active ? 'border-green-200 bg-green-50' : ''"
                        >
                            <div class="mb-3 flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="mb-2 flex items-center gap-2">
                                        <span class="font-semibold">
                                            {{ syllabus.version || 'No Version' }}
                                        </span>
                                        <Badge v-if="syllabus.is_active" class="bg-green-100 text-green-800"> Active </Badge>
                                        <Badge v-else class="bg-gray-100 text-gray-800"> Inactive</Badge>
                                    </div>
                                    <div class="grid grid-cols-1 gap-2 text-sm md:grid-cols-3">
                                        <div v-if="syllabus.effective_from_semester">
                                            <span class="font-medium text-gray-500">Effective From:</span>
                                            <span class="ml-1">
                                                {{ syllabus.effective_from_semester.term }} {{ syllabus.effective_from_semester.year }}
                                            </span>
                                        </div>
                                        <div v-if="syllabus.total_hours">
                                            <span class="font-medium text-gray-500">Total Hours:</span>
                                            <span class="ml-1">{{ syllabus.total_hours }}</span>
                                        </div>
                                        <div v-if="syllabus.hours_per_session">
                                            <span class="font-medium text-gray-500">Hours/Session:</span>
                                            <span class="ml-1">{{ syllabus.hours_per_session }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div v-if="syllabus.assessment_components && syllabus.assessment_components.length > 0" class="text-right">
                                        <div class="text-sm font-medium">
                                            {{ syllabus.assessment_components.length }}
                                            Components
                                        </div>
                                        <div class="text-xs text-gray-500">{{ syllabus.total_assessment_weight || 0 }}% Total Weight</div>
                                        <div v-if="syllabus.total_assessment_weight !== 100" class="text-xs text-orange-600">⚠️ Incomplete</div>
                                    </div>
                                    <Button variant="ghost" size="sm" @click="router.visit(`/units/${unit.id}/syllabus/${syllabus.id}`)">
                                        View Details
                                    </Button>
                                </div>
                            </div>

                            <!-- Assessment Components Preview -->
                            <div v-if="syllabus.assessment_components && syllabus.assessment_components.length > 0" class="mt-3">
                                <h4 class="mb-2 text-sm font-medium text-gray-700">Assessment Components</h4>
                                <div class="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                                    <div
                                        v-for="component in syllabus.assessment_components.slice(0, 6)"
                                        :key="component.id"
                                        class="rounded border bg-white p-2"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium">{{ component.name }}</span>
                                            <div class="flex items-center gap-1">
                                                <Badge class="text-xs" :class="getAssessmentTypeColor(component.type)">
                                                    {{ component.type.toUpperCase() }}
                                                </Badge>
                                                <span class="text-xs text-gray-500">{{ component.weight }}%</span>
                                            </div>
                                        </div>
                                        <div v-if="component.details && component.details.length > 0" class="mt-1">
                                            <div class="text-xs text-gray-500">
                                                {{ component.details.length }}
                                                sub-tasks
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="syllabus.assessment_components.length > 6"
                                        class="flex items-center justify-center rounded border border-dashed p-2 text-sm text-gray-500"
                                    >
                                        +{{ syllabus.assessment_components.length - 6 }} more
                                    </div>
                                </div>
                            </div>

                            <!-- Description Preview -->
                            <div v-if="syllabus.description" class="mt-3">
                                <h4 class="mb-1 text-sm font-medium text-gray-700">Description</h4>
                                <p class="line-clamp-2 text-sm text-gray-600">{{ syllabus.description }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty State -->
            <Card v-if="!hasRelationships()">
                <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                    <BookOpen class="mb-4 h-16 w-16 text-gray-400" />
                    <h3 class="mb-2 text-xl font-semibold text-gray-900">No Relationships</h3>
                    <p class="max-w-md text-gray-500">
                        This unit doesn't have any prerequisites, equivalencies, or curriculum relationships yet. You can add these relationships by
                        editing the unit.
                    </p>
                    <Button v-if="canEdit" class="mt-4" as="a" :href="`/units/edit/${unit.id}`">
                        <Edit class="mr-2 h-4 w-4" />
                        Add Relationships
                    </Button>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
