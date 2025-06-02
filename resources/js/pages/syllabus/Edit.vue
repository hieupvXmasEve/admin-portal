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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
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
    version: string | null;
    description: string | null;
    total_hours: number | null;
    hours_per_session: number | null;
    is_active: boolean;
    effective_from_semester_id: number | null;
    assessment_components: AssessmentComponent[];
}

const props = defineProps<{
    unit: Unit;
    syllabus: Syllabus;
    semesters: Semester[];
    assessmentTypes: Record<string, string>;
}>();
console.log('semesters', props.semesters);
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
        title: 'syllabus',
        href: `/units/${props.unit.id}/syllabus`,
    },
    {
        title: props.syllabus.version || 'Edit',
        href: `/units/${props.unit.id}/syllabus/${props.syllabus.id}/edit`,
    },
];

// Validation Schema
const formSchema = toTypedSchema(
    z.object({
        version: z.string().min(1, { message: 'Version is required' }),
        description: z.string().min(1, { message: 'Description is required' }),
        total_hours: z.number().positive({ message: 'Total hours must be a positive number' }),
        hours_per_session: z.number().positive({ message: 'Hours per session must be a positive number' }),
        effective_from_semester_id: z.number().int().positive().optional().nullable(),
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
                                weight: z
                                    .number()
                                    .min(0, { message: 'Weight must be non-negative' })
                                    .max(100, { message: 'Weight cannot exceed 100%' })
                                    .nullable(),
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
        effective_from_semester_id: props.syllabus.effective_from_semester_id,
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

// Form Submission using the shadcn-vue pattern
const onSubmit = form.handleSubmit((formData) => {
    console.log('Assessment Components:', JSON.stringify(formData.assessment_components, null, 2));
    console.log('Form is valid, submitting:', formData);
    isSubmitting.value = true;

    // Convert effective_from_semester_id to number if it's a string
    const submitData = {
        ...formData,
        effective_from_semester_id: formData.effective_from_semester_id ? Number(formData.effective_from_semester_id) : null,
    };

    router.put(`/units/${props.unit.id}/syllabus/${props.syllabus.id}`, submitData, {
        onSuccess: () => {
            toast.success('Syllabus updated successfully');
        },
        onError: (serverErrors) => {
            toast.error('Failed to update syllabus. Please check the form for errors.');
            console.error('Server validation errors:', serverErrors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
});
</script>

<template>
    <Head :title="`Edit Syllabus - ${unit.code}`" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Edit Syllabus</h1>
                    <p class="text-xl text-gray-700">{{ unit.code }} - {{ unit.name }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" @click="router.visit(`/units/${unit.id}/syllabus`)">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                    <Button @click="onSubmit" :disabled="isSubmitting">
                        <Save class="mr-2 h-4 w-4" />
                        {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </div>

            <form class="space-y-6" @submit="onSubmit">
                <!-- Basic Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Basic Information</CardTitle>
                        <CardDescription>General syllabus details and metadata</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField v-slot="{ componentField }" name="version">
                                <FormItem>
                                    <FormLabel for="version">Version</FormLabel>
                                    <FormControl>
                                        <Input id="version" type="text" placeholder="e.g., v1.0, v2.1" v-bind="componentField" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="effective_from_semester_id">
                                <FormItem>
                                    <FormLabel for="effective_from_semester_id">Effective From Semester</FormLabel>
                                    <FormControl>
                                        <Select v-bind="componentField">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select semester" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id">
                                                    {{ semester.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField
                                v-slot="{ componentField }"
                                name="total_hours"
                                :transform="
                                    (value: any) => {
                                        if (value === '' || value === null || value === undefined) return 0;
                                        const num = typeof value === 'string' ? parseFloat(value.replace(',', '.')) : Number(value);
                                        return isNaN(num) ? 0 : num;
                                    }
                                "
                            >
                                <FormItem>
                                    <FormLabel for="total_hours">Total Hours</FormLabel>
                                    <FormControl>
                                        <NumberField
                                            v-bind="componentField"
                                            :step="0.5"
                                            :format-options="{
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 2,
                                            }"
                                        >
                                            <NumberFieldContent>
                                                <NumberFieldInput />
                                            </NumberFieldContent>
                                        </NumberField>
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField
                                v-slot="{ componentField }"
                                name="hours_per_session"
                                :transform="
                                    (value: any) => {
                                        if (value === '' || value === null || value === undefined) return 0;
                                        const num = typeof value === 'string' ? parseFloat(value.replace(',', '.')) : Number(value);
                                        return isNaN(num) ? 0 : num;
                                    }
                                "
                            >
                                <FormItem>
                                    <FormLabel for="hours_per_session">Hours per Session</FormLabel>
                                    <FormControl>
                                        <NumberField
                                            v-bind="componentField"
                                            :step="0.5"
                                            :format-options="{
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 2,
                                            }"
                                        >
                                            <NumberFieldContent>
                                                <NumberFieldInput />
                                            </NumberFieldContent>
                                        </NumberField>
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <FormField v-slot="{ componentField }" name="description">
                            <FormItem>
                                <FormLabel for="description">Description</FormLabel>
                                <FormControl>
                                    <Textarea
                                        id="description"
                                        rows="4"
                                        placeholder="Describe the course content, objectives, and structure..."
                                        v-bind="componentField"
                                    />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="is_active">
                            <FormItem class="flex flex-row items-start space-y-0 space-x-3">
                                <FormControl>
                                    <Checkbox v-bind="componentField" />
                                </FormControl>
                                <div class="space-y-1 leading-none">
                                    <FormLabel>Set as active syllabus</FormLabel>
                                </div>
                            </FormItem>
                        </FormField>
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
                                <div class="text-lg font-bold">Total Weight: {{ getTotalWeight().toFixed(2) }}%</div>
                                <div v-if="Math.abs(getTotalWeight() - 100) >= 0.01" class="text-sm text-red-600">Must equal exactly 100%</div>
                                <div v-else class="text-sm text-green-600">Complete</div>
                            </div>
                        </div>
                        <FormField name="assessment_components">
                            <FormItem>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <Button type="button" @click="addAssessmentComponent" variant="outline">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Assessment Component
                        </Button>

                        <div v-if="form.values.assessment_components && form.values.assessment_components.length > 0" class="space-y-4">
                            <div
                                v-for="(component, componentIndex) in form.values.assessment_components"
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
                                    <FormField v-slot="{ componentField }" :name="`assessment_components.${componentIndex}.name`">
                                        <FormItem>
                                            <FormLabel :for="`component_name_${componentIndex}`">Name</FormLabel>
                                            <FormControl>
                                                <Input
                                                    :id="`component_name_${componentIndex}`"
                                                    placeholder="e.g., Final Exam"
                                                    v-bind="componentField"
                                                />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    </FormField>

                                    <FormField
                                        v-slot="{ componentField }"
                                        :name="`assessment_components.${componentIndex}.weight`"
                                        :transform="
                                            (value: any) => {
                                                if (value === '' || value === null || value === undefined) return 0;
                                                const num = typeof value === 'string' ? parseFloat(value.replace(',', '.')) : Number(value);
                                                return isNaN(num) ? 0 : num;
                                            }
                                        "
                                    >
                                        <FormItem>
                                            <FormLabel :for="`component_weight_${componentIndex}`">Weight (%)</FormLabel>
                                            <FormControl>
                                                <NumberField
                                                    :model-value="
                                                        typeof componentField.modelValue === 'string'
                                                            ? parseFloat(componentField.modelValue) || 0
                                                            : componentField.modelValue
                                                    "
                                                    @update:model-value="componentField['onUpdate:modelValue']"
                                                    :step="0.01"
                                                    :format-options="{
                                                        minimumFractionDigits: 0,
                                                        maximumFractionDigits: 2,
                                                    }"
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
                                            <FormLabel :for="`component_type_${componentIndex}`">Type</FormLabel>
                                            <FormControl>
                                                <Select v-bind="componentField">
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
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    </FormField>
                                </div>

                                <div class="mt-4">
                                    <FormField
                                        v-slot="{ componentField }"
                                        :name="`assessment_components.${componentIndex}.is_required_to_sit_final_exam`"
                                    >
                                        <FormItem class="flex flex-row items-start space-y-0 space-x-3">
                                            <FormControl>
                                                <Checkbox v-bind="componentField" />
                                            </FormControl>
                                            <div class="space-y-1 leading-none">
                                                <FormLabel :for="`component_required_${componentIndex}`">Required to sit final exam</FormLabel>
                                            </div>
                                        </FormItem>
                                    </FormField>
                                </div>

                                <!-- Component Details -->
                                <div class="mt-4">
                                    <div class="mb-2 flex items-center justify-between">
                                        <Label>Sub-components (optional)</Label>
                                        <div class="flex items-center gap-2">
                                            <div v-if="getSubcomponentTotalWeight(componentIndex) !== null" class="text-sm">
                                                <span class="font-medium"
                                                    >Subcomponent Total: {{ getSubcomponentTotalWeight(componentIndex)?.toFixed(2) }}%</span
                                                >
                                                <span
                                                    v-if="Math.abs((getSubcomponentTotalWeight(componentIndex) || 0) - 100) >= 0.01"
                                                    class="ml-2 text-red-600"
                                                    >❌ Must equal 100%</span
                                                >
                                                <span v-else class="ml-2 text-green-600">✅</span>
                                            </div>
                                            <Button type="button" variant="outline" size="sm" @click="addComponentDetail(componentIndex)">
                                                <Plus class="mr-1 h-3 w-3" />
                                                Add Detail
                                            </Button>
                                        </div>
                                    </div>

                                    <div v-if="component.details && component.details.length > 0" class="space-y-2">
                                        <FormField :name="`assessment_components.${componentIndex}.details`">
                                            <FormItem>
                                                <FormMessage />
                                            </FormItem>
                                        </FormField>
                                        <div v-for="(detail, detailIndex) in component.details" :key="detailIndex" class="flex items-center gap-2">
                                            <FormField
                                                v-slot="{ componentField }"
                                                :name="`assessment_components.${componentIndex}.details.${detailIndex}.name`"
                                            >
                                                <FormItem class="flex-1">
                                                    <FormControl>
                                                        <Input placeholder="Detail name" v-bind="componentField" />
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>
                                            <FormField
                                                v-slot="{ componentField }"
                                                :name="`assessment_components.${componentIndex}.details.${detailIndex}.weight`"
                                                :transform="
                                                    (value: any) => {
                                                        if (value === '' || value === null || value === undefined) return null;
                                                        const num = typeof value === 'string' ? parseFloat(value.replace(',', '.')) : Number(value);
                                                        return isNaN(num) ? null : num;
                                                    }
                                                "
                                            >
                                                <FormItem class="w-24">
                                                    <FormControl>
                                                        <NumberField
                                                            :model-value="
                                                                typeof componentField.modelValue === 'string'
                                                                    ? parseFloat(componentField.modelValue) || null
                                                                    : componentField.modelValue
                                                            "
                                                            @update:model-value="componentField['onUpdate:modelValue']"
                                                            :step="0.01"
                                                            :format-options="{
                                                                minimumFractionDigits: 0,
                                                                maximumFractionDigits: 2,
                                                            }"
                                                        >
                                                            <NumberFieldContent>
                                                                <NumberFieldInput />
                                                            </NumberFieldContent>
                                                        </NumberField>
                                                    </FormControl>
                                                    <FormMessage />
                                                </FormItem>
                                            </FormField>
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
