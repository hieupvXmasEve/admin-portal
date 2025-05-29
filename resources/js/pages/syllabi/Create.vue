<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-vue-next';

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
}

interface Semester {
    id: number;
    name: string;
}

interface AssessmentComponentDetail {
    name: string;
    weight: number | null;
}

interface AssessmentComponent {
    name: string;
    weight: number;
    type: string;
    is_required_to_sit_final_exam: boolean;
    details: AssessmentComponentDetail[];
}

const props = defineProps<{
    unit: Unit;
    semesters: Semester[];
    assessmentTypes: Record<string, string>;
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
        title: 'Create',
        href: `/units/${props.unit.id}/syllabi/create`,
    },
];

const form = useForm({
    version: '',
    description: '',
    total_hours: undefined as number | undefined,
    hours_per_session: undefined as number | undefined,
    effective_from_semester_id: null as number | null,
    is_active: false,
    assessment_components: [] as any[],
});

const addAssessmentComponent = () => {
    (form.assessment_components as AssessmentComponent[]).push({
        name: '',
        weight: 0,
        type: 'assignment',
        is_required_to_sit_final_exam: true,
        details: [],
    });
};

const removeAssessmentComponent = (index: number) => {
    (form.assessment_components as AssessmentComponent[]).splice(index, 1);
};

const addComponentDetail = (componentIndex: number) => {
    const components = form.assessment_components as AssessmentComponent[];
    components[componentIndex].details.push({
        name: '',
        weight: null,
    });
};

const removeComponentDetail = (componentIndex: number, detailIndex: number) => {
    const components = form.assessment_components as AssessmentComponent[];
    components[componentIndex].details.splice(detailIndex, 1);
};

const getTotalWeight = () => {
    const components = form.assessment_components as AssessmentComponent[];
    return components.reduce((total, component) => total + (component.weight || 0), 0);
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

const submit = () => {
    form.post(`/units/${props.unit.id}/syllabi`, {
        onSuccess: () => {
            // Will redirect to syllabi index
        },
    });
};
</script>

<template>
    <Head :title="`Create Syllabus - ${unit.code}`" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Create Syllabus</h1>
                    <p class="text-xl text-gray-700">{{ unit.code }} - {{ unit.name }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" @click="router.visit(`/units/${unit.id}/syllabi`)">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                    <Button @click="submit" :disabled="form.processing">
                        <Save class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Creating...' : 'Create Syllabus' }}
                    </Button>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Basic Information</CardTitle>
                        <CardDescription>General syllabus details and metadata</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label for="version">Version</Label>
                                <Input id="version" v-model="form.version" placeholder="e.g., v1.0, v2.1" :error="form.errors.version" />
                                <p v-if="form.errors.version" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.version }}
                                </p>
                            </div>

                            <div>
                                <Label for="effective_from_semester_id">Effective From Semester</Label>
                                <Select v-model="form.effective_from_semester_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">
                                            {{ semester.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.effective_from_semester_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.effective_from_semester_id }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <Label for="total_hours">Total Hours</Label>
                                <Input
                                    id="total_hours"
                                    v-model.number="form.total_hours"
                                    type="number"
                                    min="0"
                                    placeholder="e.g., 120"
                                    :error="form.errors.total_hours"
                                />
                                <p v-if="form.errors.total_hours" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.total_hours }}
                                </p>
                            </div>

                            <div>
                                <Label for="hours_per_session">Hours per Session</Label>
                                <Input
                                    id="hours_per_session"
                                    v-model.number="form.hours_per_session"
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    placeholder="e.g., 2"
                                    :error="form.errors.hours_per_session"
                                />
                                <p v-if="form.errors.hours_per_session" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.hours_per_session }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <Label for="description">Description</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                placeholder="Describe the course content, objectives, and structure..."
                                :error="form.errors.description"
                            />
                            <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Checkbox id="is_active" v-model:checked="form.is_active" />
                            <Label for="is_active">Set as active syllabus</Label>
                        </div>
                        <p v-if="form.errors.is_active" class="mt-1 text-sm text-red-600">
                            {{ form.errors.is_active }}
                        </p>
                    </CardContent>
                </Card>

                <!-- Assessment Components -->
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle>Assessment Components</CardTitle>
                                <CardDescription>Define assessment structure and weightings</CardDescription>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold">Total Weight: {{ getTotalWeight() }}%</div>
                                <div v-if="getTotalWeight() !== 100" class="text-sm text-orange-600">⚠️ Should total 100%</div>
                                <div v-else class="text-sm text-green-600">✅ Complete</div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Button type="button" @click="addAssessmentComponent" variant="outline">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Assessment Component
                        </Button>

                        <div v-if="form.assessment_components.length > 0" class="space-y-4">
                            <div
                                v-for="(component, componentIndex) in form.assessment_components"
                                :key="componentIndex"
                                class="rounded-lg border p-4"
                            >
                                <div class="mb-4 flex items-center justify-between">
                                    <h3 class="text-lg font-medium">Component {{ componentIndex + 1 }}</h3>
                                    <Button type="button" variant="ghost" size="sm" @click="removeAssessmentComponent(componentIndex)">
                                        <Trash2 class="h-4 w-4 text-red-600" />
                                    </Button>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div>
                                        <Label :for="`component_name_${componentIndex}`">Name</Label>
                                        <Input :id="`component_name_${componentIndex}`" v-model="component.name" placeholder="e.g., Final Exam" />
                                    </div>

                                    <div>
                                        <Label :for="`component_weight_${componentIndex}`">Weight (%)</Label>
                                        <Input
                                            :id="`component_weight_${componentIndex}`"
                                            v-model.number="component.weight"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.1"
                                        />
                                    </div>

                                    <div>
                                        <Label :for="`component_type_${componentIndex}`">Type</Label>
                                        <Select v-model="component.type">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="(label, value) in assessmentTypes" :key="value" :value="value">
                                                    <div class="flex items-center gap-2">
                                                        <Badge :class="getAssessmentTypeColor(value)" class="text-xs">
                                                            {{ value.toUpperCase() }}
                                                        </Badge>
                                                        {{ label }}
                                                    </div>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <div class="flex items-center space-x-2">
                                        <Checkbox
                                            :id="`component_required_${componentIndex}`"
                                            v-model:checked="component.is_required_to_sit_final_exam"
                                        />
                                        <Label :for="`component_required_${componentIndex}`"> Required to sit final exam </Label>
                                    </div>
                                </div>

                                <!-- Component Details -->
                                <div class="mt-4">
                                    <div class="mb-2 flex items-center justify-between">
                                        <Label>Sub-components (optional)</Label>
                                        <Button type="button" variant="outline" size="sm" @click="addComponentDetail(componentIndex)">
                                            <Plus class="mr-1 h-3 w-3" />
                                            Add Detail
                                        </Button>
                                    </div>

                                    <div v-if="component.details.length > 0" class="space-y-2">
                                        <div v-for="(detail, detailIndex) in component.details" :key="detailIndex" class="flex items-center gap-2">
                                            <Input v-model="detail.name" placeholder="Detail name" class="flex-1" />
                                            <Input
                                                v-model.number="detail.weight"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.1"
                                                placeholder="Weight %"
                                                class="w-24"
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                @click="removeComponentDetail(componentIndex, detailIndex)"
                                            >
                                                <Trash2 class="h-4 w-4 text-red-600" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>
