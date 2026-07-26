<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Plus, Save, Trash2 } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
}

interface Semester {
    id: number;
    name: string;
    year: number;
}

interface CurriculumVersion {
    id: number;
    version: string;
    specialization: {
        id: number;
        name: string;
    };
    program: {
        id: number;
        name: string;
    };
}

interface CurriculumUnit {
    id: number;
    semester: Semester;
    curriculum_version: CurriculumVersion;
}

interface AssessmentComponentDetail {
    id?: number;
    name: string;
    weight: number | null;
}

interface AssessmentComponent {
    id?: number;
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
}

const props = defineProps<{
    unit: Unit;
    syllabus: Syllabus;
    curriculumUnits: CurriculumUnit[];
    assessmentTypes: Record<string, string>;
}>();
console.log(props.syllabus.curriculum_unit);

// Validation Schema
const formSchema = toTypedSchema(
    z.object({
        version: z.string().min(1, { message: 'Version is required' }),
        description: z.string().min(1, { message: 'Description is required' }),
        total_hours: z.number().positive({ message: 'Total hours must be a positive number' }),
        hours_per_session: z.number().positive({ message: 'Hours per session must be a positive number' }),
        is_active: z.boolean().default(false),
        assessment_components: z
            .array(
                z.object({
                    id: z.number().optional(),
                    name: z.string().min(1, { message: 'Component name is required' }),
                    weight: z.number().min(0, { message: 'Weight must be non-negative' }).max(100, { message: 'Weight cannot exceed 100%' }),
                    type: z.enum(['quiz', 'assignment', 'project', 'exam', 'online_activity', 'other'], {
                        errorMap: () => ({ message: 'Please select a valid assessment type' }),
                    }),
                    is_required_to_sit_final_exam: z.boolean().default(true),
                    details: z
                        .array(
                            z.object({
                                id: z.number().optional(),
                                name: z.string().min(1, { message: 'Detail name is required' }),
                                weight: z.number().min(0, { message: 'Weight must be non-negative' }).max(100, { message: 'Weight cannot exceed 100%' }).nullable(),
                            }),
                        )
                        .default([])
                        .refine(
                            (details) => {
                                // If there are details, their weights must sum to 100%
                                if (details.length === 0) return true;
                                const totalWeight = details.reduce((sum, detail) => sum + (detail.weight || 0), 0);
                                return Math.abs(totalWeight - 100) < 0.01; // Allow small floating point differences
                            },
                            { message: 'Subcomponent weights must sum to exactly 100%' },
                        ),
                }),
            )
            .min(1, { message: 'At least one assessment component is required' })
            .refine(
                (components) => {
                    const totalWeight = components.reduce((sum, comp) => sum + (comp.weight || 0), 0);
                    return Math.abs(totalWeight - 100) < 0.01; // Allow small floating point differences
                },
                { message: 'Total assessment weight must equal exactly 100%' },
            ),
    }),
);

// Form Setup
const form = useForm({
    validationSchema: formSchema,
    initialValues: {
        version: props.syllabus.version || '',
        description: props.syllabus.description || '',
        total_hours: Number(props.syllabus.total_hours) || 0,
        hours_per_session: Number(props.syllabus.hours_per_session) || 0,
        is_active: props.syllabus.is_active,
        assessment_components:
            props.syllabus.assessment_components.map((comp) => ({
                ...comp,
                weight: Number(comp.weight) || 0,
                type: comp.type as 'quiz' | 'assignment' | 'project' | 'exam' | 'online_activity' | 'other',
                details:
                    comp.details?.map((detail) => ({
                        ...detail,
                        weight: detail.weight !== null && detail.weight !== undefined ? Number(detail.weight) : null,
                    })) || [],
            })) || [],
    },
});

// Form State
const isSubmitting = ref(false);

// Assessment Component Management
const addAssessmentComponent = () => {
    const currentComponents = form.values.assessment_components || [];
    form.setFieldValue('assessment_components', [
        ...currentComponents,
        {
            name: '',
            weight: 0,
            type: 'assignment',
            is_required_to_sit_final_exam: true,
            details: [],
        },
    ]);
};

const removeAssessmentComponent = (index: number) => {
    const currentComponents = form.values.assessment_components || [];
    const newComponents = [...currentComponents];
    newComponents.splice(index, 1);
    form.setFieldValue('assessment_components', newComponents);
};

const addComponentDetail = (componentIndex: number) => {
    const currentComponents = form.values.assessment_components || [];
    const newComponents = [...currentComponents];
    if (newComponents[componentIndex]) {
        if (!newComponents[componentIndex].details) {
            newComponents[componentIndex].details = [];
        }
        newComponents[componentIndex].details.push({
            name: '',
            weight: null,
        });
        form.setFieldValue('assessment_components', newComponents);
    }
};

const removeComponentDetail = (componentIndex: number, detailIndex: number) => {
    const currentComponents = form.values.assessment_components || [];
    const newComponents = [...currentComponents];
    if (newComponents[componentIndex]?.details) {
        newComponents[componentIndex].details.splice(detailIndex, 1);
        form.setFieldValue('assessment_components', newComponents);
    }
};

// Computed Values
const getTotalWeight = () => {
    const components = form.values.assessment_components || [];
    const total = components.reduce((total, component) => {
        const weight = Number(component.weight) || 0;
        return total + weight;
    }, 0);
    return Math.round(total * 100) / 100; // Round to 2 decimal places
};

const getSubcomponentTotalWeight = (componentIndex: number) => {
    const component = form.values.assessment_components?.[componentIndex];
    if (!component?.details || component.details.length === 0) return null;
    const total = component.details.reduce((total, detail) => {
        const weight = Number(detail.weight) || 0;
        return total + weight;
    }, 0);
    return Math.round(total * 100) / 100; // Round to 2 decimal places
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

// Form Submission
const onSubmit = form.handleSubmit(async (formData) => {
    isSubmitting.value = true;

    const submitData = {
        ...formData,
    };

    try {
        router.put(`/units/${props.unit.id}/syllabus/${props.syllabus.id}`, submitData, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Syllabus updated successfully!');
                router.visit(`/units/${props.unit.id}/syllabus`);
            },
            onError: (errors) => {
                console.error('Form submission errors:', errors);
                toast.error('Failed to update syllabus. Please check the form for errors.');
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        });
    } catch (error) {
        console.error('Form submission error:', error);
        toast.error('An error occurred while updating the syllabus.');
        isSubmitting.value = false;
    }
});
</script>

<template>
    <Head :title="`Edit Syllabus - ${unit.code}`" />

    <form @submit="onSubmit">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <div class="mb-2 flex items-center gap-3">
                    <h1 class="text-3xl font-bold">Edit Syllabus</h1>
                    <Badge class="bg-blue-100 text-blue-800">{{ unit.code }}</Badge>
                </div>
                <p class="text-xl text-gray-700">{{ unit.name }}</p>
                <div class="mt-2 text-sm text-gray-600">
                    <span class="font-medium">Context:</span>
                    {{ syllabus.curriculum_unit.semester?.name }} - {{ syllabus.curriculum_unit.curriculum_version.specialization?.name }}
                </div>
            </div>
            <div class="flex items-center gap-3">
                <Button type="button" variant="outline" @click="router.visit(`/units/${unit.id}/syllabus`)">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Syllabus
                </Button>
                <Button type="submit" :disabled="isSubmitting">
                    <Save class="mr-2 h-4 w-4" />
                    {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </div>

        <!-- Basic Information -->
        <Card class="mb-8">
            <CardHeader>
                <CardTitle>Basic Information</CardTitle>
                <CardDescription>Update the syllabus version, description, and basic details.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="version">
                        <FormItem>
                            <FormLabel for="version">Version</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., v1.0, v2.1" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField v-slot="{ componentField }" name="total_hours">
                            <FormItem>
                                <FormLabel for="total_hours">Total Hours</FormLabel>
                                <FormControl>
                                    <NumberField v-bind="componentField" :default-value="0" :min="0">
                                        <NumberFieldContent>
                                            <NumberFieldInput />
                                        </NumberFieldContent>
                                    </NumberField>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="hours_per_session">
                            <FormItem>
                                <FormLabel for="hours_per_session">Hours per Session</FormLabel>
                                <FormControl>
                                    <NumberField v-bind="componentField" :default-value="0" :min="0">
                                        <NumberFieldContent>
                                            <NumberFieldInput />
                                        </NumberFieldContent>
                                    </NumberField>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </div>

                <FormField v-slot="{ componentField }" name="description">
                    <FormItem>
                        <FormLabel for="description">Description</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Describe the syllabus content and objectives..." rows="4" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ value, handleChange }" name="is_active">
                    <FormItem class="flex items-center space-y-0 space-x-3">
                        <FormControl>
                            <Checkbox :checked="value" @update:checked="handleChange" />
                        </FormControl>
                        <div class="space-y-1 leading-none">
                            <FormLabel class="text-sm font-medium">Set as Active Syllabus</FormLabel>
                            <p class="text-muted-foreground text-xs">Only one syllabus per curriculum unit can be active at a time</p>
                        </div>
                    </FormItem>
                </FormField>
            </CardContent>
        </Card>

        <!-- Assessment Components -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        Assessment Components
                        <Badge :class="getTotalWeight() === 100 ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'"> {{ getTotalWeight() }}% </Badge>
                    </CardTitle>
                    <CardDescription>Define the assessment structure and weightings for this syllabus.</CardDescription>
                </div>
                <Button type="button" variant="outline" @click="addAssessmentComponent">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Component
                </Button>
            </CardHeader>
            <CardContent class="space-y-6">
                <div v-for="(component, componentIndex) in form.values.assessment_components || []" :key="componentIndex" class="rounded-lg border p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h4 class="font-medium">Component {{ componentIndex + 1 }}</h4>
                            <Badge v-if="component.type" :class="getAssessmentTypeColor(component.type)">
                                {{ assessmentTypes[component.type] || component.type }}
                            </Badge>
                        </div>
                        <Button v-if="(form.values.assessment_components || []).length > 1" type="button" variant="ghost" size="sm" @click="removeAssessmentComponent(componentIndex)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <FormField v-slot="{ componentField }" :name="`assessment_components.${componentIndex}.name`">
                            <FormItem>
                                <FormLabel>Component Name</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="e.g., Assignment 1" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ value, setValue }" :name="`assessment_components.${componentIndex}.weight`">
                            <FormItem>
                                <FormLabel>Weight (%)</FormLabel>
                                <FormControl>
                                    <NumberField
                                        :model-value="value"
                                        @update:model-value="
                                            (v) => {
                                                if (v) {
                                                    setValue(v);
                                                } else {
                                                    setValue(0);
                                                }
                                            }
                                        "
                                        :default-value="0"
                                        :min="0"
                                        :max="100"
                                    >
                                        <NumberFieldContent>
                                            <NumberFieldInput />
                                        </NumberFieldContent>
                                    </NumberField>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" :name="`assessment_components.${componentIndex}.type`">
                            <FormItem>
                                <FormLabel>Type</FormLabel>
                                <FormControl>
                                    <Select v-bind="componentField">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="(label, value) in assessmentTypes" :key="value" :value="value">
                                                {{ label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <div class="flex items-end">
                            <Button type="button" variant="outline" size="sm" @click="addComponentDetail(componentIndex)">
                                <Plus class="mr-1 h-3 w-3" />
                                Add Detail
                            </Button>
                        </div>
                    </div>

                    <FormField v-slot="{ value, handleChange }" :name="`assessment_components.${componentIndex}.is_required_to_sit_final_exam`">
                        <FormItem class="mt-4 flex items-center space-y-0 space-x-3">
                            <FormControl>
                                <Checkbox :checked="value" @update:checked="handleChange" />
                            </FormControl>
                            <FormLabel class="text-sm">Required to sit final exam</FormLabel>
                        </FormItem>
                    </FormField>

                    <!-- Component Details -->
                    <FormField v-if="component.details && component.details.length > 0" v-slot="{}" :name="`assessment_components.${componentIndex}.details`">
                        <div class="mt-4 space-y-3">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">Subcomponents</Label>
                                <Badge v-if="getSubcomponentTotalWeight(componentIndex) !== null" :class="getSubcomponentTotalWeight(componentIndex) === 100 ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'">
                                    {{ getSubcomponentTotalWeight(componentIndex) }}%
                                </Badge>
                            </div>
                            <div class="rounded border bg-gray-50 p-3">
                                <div v-for="(detail, detailIndex) in component.details" :key="detailIndex" class="mb-3 flex items-end gap-3 last:mb-0">
                                    <FormField v-slot="{ componentField }" :name="`assessment_components.${componentIndex}.details.${detailIndex}.name`">
                                        <FormItem class="flex-1">
                                            <FormLabel v-if="detailIndex === 0" class="text-xs">Detail Name</FormLabel>
                                            <FormControl>
                                                <Input v-bind="componentField" placeholder="e.g., Part A" class="text-sm" />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    </FormField>

                                    <FormField v-slot="{ value, setValue }" :name="`assessment_components.${componentIndex}.details.${detailIndex}.weight`">
                                        <FormItem class="w-24">
                                            <FormLabel v-if="detailIndex === 0" class="text-xs">Weight (%)</FormLabel>
                                            <FormControl>
                                                <NumberField
                                                    :model-value="value"
                                                    @update:model-value="
                                                        (v) => {
                                                            if (v) {
                                                                setValue(v);
                                                            } else {
                                                                setValue(0);
                                                            }
                                                        }
                                                    "
                                                    :default-value="0"
                                                    :min="0"
                                                    :max="100"
                                                >
                                                    <NumberFieldContent>
                                                        <NumberFieldInput class="text-sm" />
                                                    </NumberFieldContent>
                                                </NumberField>
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    </FormField>

                                    <Button type="button" variant="ghost" size="sm" @click="removeComponentDetail(componentIndex, detailIndex)" class="mb-1">
                                        <Trash2 class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                            <FormItem>
                                <FormMessage />
                            </FormItem>
                        </div>
                    </FormField>
                </div>

                <!-- Total Weight Summary -->
                <FormField v-slot="{}" name="assessment_components">
                    <div class="rounded-lg bg-gray-50 p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Total Assessment Weight:</span>
                            <Badge :class="getTotalWeight() === 100 ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'"> {{ getTotalWeight() }}% / 100% </Badge>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">All assessment components must total exactly 100% for a valid syllabus.</p>
                        <FormItem class="mt-2">
                            <FormMessage />
                        </FormItem>
                    </div>
                </FormField>
            </CardContent>
        </Card>
    </form>
</template>
