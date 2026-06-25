<script setup lang="ts">
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList, ComboboxTrigger, ComboboxViewport } from '@/components/ui/combobox';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { GradingScheme } from '@/types/grading-scheme';
import { Head, router } from '@inertiajs/vue3';
import MetropoliaSchemeBuilder from './components/MetropoliaSchemeBuilder.vue';
import GradingSchemePreview from './components/GradingSchemePreview.vue';
import type { ExampleComponent } from './components/grading-scheme-examples';
import { METROPOLIA_COMPONENT_CODES } from './components/metropolia-component-codes';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, ChevronsUpDown, Plus, Save, Trash2 } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points?: number;
}

interface AssessmentComponentDetail {
    id?: number;
    name: string;
    weight: number | null;
}

interface AssessmentComponent {
    id?: number;
    name: string;
    code?: string;
    weight: number;
    type: string;
    is_required_to_sit_final_exam: boolean;
    details: AssessmentComponentDetail[];
}

interface SyllabusTemplate {
    id: number;
    unit_id: number;
    title: string;
    version: string;
    description: string | null;
    total_hours: number;
    total_sessions: number;
    min_attendance_threshold: number;
    min_grade_threshold: number;
    exam_resit_fee: number | null;
    is_active: boolean;
    assessment_components: AssessmentComponent[];
}
const props = defineProps<{
    unit: { id: number; code: string; name: string };
    syllabusTemplate: SyllabusTemplate;
    units: Unit[];
    assessmentTypes: Record<string, string>;
}>();
console.log('assessmentTypes', props.assessmentTypes);

// Validation Schema (similar to create)
const formSchema = toTypedSchema(
    z.object({
        title: z.string().min(1, { message: 'Title is required' }),
        version: z.string().min(1, { message: 'Version is required' }),
        description: z.string().optional(),
        total_hours: z.number().min(0, { message: 'Total hours must be a positive number' }),
        total_sessions: z.number().int({ message: 'Total sessions must be an integer' }).min(1, { message: 'Total sessions must be at least 1' }).optional(),
        min_attendance_threshold: z.number().min(0).max(100).default(80),
        min_grade_threshold: z.number().min(0).max(100).default(40),
        exam_resit_fee: z.number().min(1, { message: 'Phí thi lại phải lớn hơn 0' }),
        unit_id: z.string({ message: 'Please select a curriculum unit' }),
        is_active: z.boolean().default(true).optional(),
        assessment_components: z.array(z.any()).optional(),
    }),
);

// Build initial values from template
const initialValues = {
    title: props.syllabusTemplate.title ?? '',
    version: props.syllabusTemplate.version ?? '',
    description: props.syllabusTemplate.description ?? '',
    total_hours: Number(props.syllabusTemplate.total_hours ?? 0),
    total_sessions: Number(props.syllabusTemplate.total_sessions ?? 1),
    min_attendance_threshold: Number(props.syllabusTemplate.min_attendance_threshold ?? 80),
    min_grade_threshold: Number(props.syllabusTemplate.min_grade_threshold ?? 40),
    exam_resit_fee: Number(props.syllabusTemplate.exam_resit_fee ?? 0) || 750000,
    unit_id: (props.syllabusTemplate.unit_id ?? props.unit.id).toString(),
    is_active: !!props.syllabusTemplate.is_active,
    assessment_components:
        props.syllabusTemplate.assessment_components.map((comp) => ({
            ...comp,
            code: comp.code ?? '',
            weight: Number(comp.weight) || 0,
            type: comp.type as 'quiz' | 'assignment' | 'project' | 'exam' | 'online_activity' | 'other',
            details:
                comp.details?.map((detail) => ({
                    ...detail,
                    weight: detail.weight !== null && detail.weight !== undefined ? Number(detail.weight) : null,
                })) || [],
        })) || [],
};
console.log('initialValues', initialValues);

const form = useForm({
    validationSchema: formSchema,
    initialValues,
});

const isSubmitting = ref(false);
const addUnitSearch = ref('');
// Grading scheme (null = default weighted percentage)
const gradingScheme = ref<GradingScheme | null>((props.syllabusTemplate.grading_scheme as GradingScheme | null) ?? null);

// Assessment components surfaced to the builder (single source of truth).
const builderComponents = computed(() =>
    ((form.values.assessment_components as Array<{ code?: string; name?: string }>) || []).map((component) => ({ code: component.code ?? '', label: component.name ?? '' })),
);

// Guide-modal example fills the Assessment Components below + the scheme.
const applyExampleComponents = (components: ExampleComponent[]) => {
    form.setFieldValue(
        'assessment_components',
        components.map((component) => ({ name: component.name, code: component.code, weight: component.weight, type: component.type, details: [] })),
    );
};

// Assessment Component helpers (UI only)
const addAssessmentComponent = () => {
    const currentComponents = (form.values.assessment_components as any[]) || [];
    form.setFieldValue('assessment_components', [...currentComponents, { name: '', code: '', weight: 0, type: 'assignment', details: [] }]);
};
const removeAssessmentComponent = (index: number) => {
    const current = (form.values.assessment_components as any[]) || [];
    const next = [...current];
    next.splice(index, 1);
    form.setFieldValue('assessment_components', next);
};
const addComponentDetail = (componentIndex: number) => {
    const current = (form.values.assessment_components as any[]) || [];
    // Rebuild the target component immutably — form.values is deeply readonly,
    // so pushing into the nested details array would fail silently.
    const next = current.map((component, index) =>
        index === componentIndex ? { ...component, details: [...(component.details || []), { name: '', weight: null }] } : component,
    );
    form.setFieldValue('assessment_components', next);
};
const removeComponentDetail = (componentIndex: number, detailIndex: number) => {
    const current = (form.values.assessment_components as any[]) || [];
    const next = current.map((component, index) =>
        index === componentIndex ? { ...component, details: (component.details || []).filter((_: unknown, di: number) => di !== detailIndex) } : component,
    );
    form.setFieldValue('assessment_components', next);
};

const getTotalWeight = () => {
    const components = (form.values.assessment_components as any[]) || [];
    const total = components.reduce((sum, c) => sum + (Number(c.weight) || 0), 0);
    return Math.round(total * 100) / 100;
};
const getSubComponentTotalWeight = (componentIndex: number) => {
    const components = (form.values.assessment_components as any[]) || [];
    const comp = components[componentIndex];
    if (!comp?.details?.length) return null;
    const total = comp.details.reduce((sum: number, d: any) => sum + (Number(d.weight) || 0), 0);
    return Math.round(total * 100) / 100;
};

const filteredAvailableUnits = computed(() => {
    if (!addUnitSearch.value.trim()) return props.units;
    const q = addUnitSearch.value.toLowerCase().trim();
    return props.units.filter((u) => u.code.toLowerCase().includes(q) || u.name.toLowerCase().includes(q));
});
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

const handleTypeChange = (componentIndex: number, newType: string) => {
    const current = (form.values.assessment_components as any[]) || [];
    const next = current.map((component, index) => {
        if (index !== componentIndex) {
            return component;
        }
        const updated = { ...component, type: newType };
        // If type is attendance, ensure exactly one detail with 100%
        if (newType === 'attendance') {
            updated.details = [{ name: 'Attendance', weight: 100 }];
        }
        return updated;
    });
    form.setFieldValue('assessment_components', next);
};

const onSubmit = form.handleSubmit((formData) => {
    console.log('...formData,', formData);

    isSubmitting.value = true;

    const submitData = {
        title: formData.title,
        version: formData.version,
        description: formData.description,
        total_hours: formData.total_hours,
        total_sessions: formData.total_sessions,
        min_attendance_threshold: formData.min_attendance_threshold,
        min_grade_threshold: formData.min_grade_threshold,
        exam_resit_fee: formData.exam_resit_fee,
        is_active: formData.is_active,
        assessment_components: formData.assessment_components,
        grading_scheme: gradingScheme.value,
        // unit_id intentionally omitted from update on backend (not supported),
        // but kept in UI for consistency
    } as Record<string, any>;

    router.put(`/syllabus-templates/${props.syllabusTemplate.id}`, submitData, {
        onSuccess: () => {
            toast.success('Syllabus template updated');
            router.visit('/syllabus-templates');
        },
        onError: (errors) => {
            toast.error('Failed to update syllabus template');
            console.error(errors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
});
</script>

<template>

    <Head :title="`Edit Syllabus Template`" />

    <div class="flex flex-col items-start justify-between gap-4 lg:flex-row lg:items-center">
        <div>
            <h1 class="text-3xl font-bold">Edit Syllabus Template</h1>
            <div class="text-muted-foreground">{{ unit.code }} — {{ unit.name }}</div>
        </div>
        <div class="flex items-center gap-3">
            <Button variant="outline" @click="router.visit('/syllabus-templates')">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back
            </Button>
            <Button type="button" @click="onSubmit">
                <Save class="mr-2 h-4 w-4" />
                {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
            </Button>
        </div>
    </div>

    <form class="space-y-6" @submit="onSubmit">
        <datalist id="metropolia-component-codes">
            <option v-for="entry in METROPOLIA_COMPONENT_CODES" :key="entry.code" :value="entry.code">{{ entry.label }}</option>
        </datalist>

        <!-- Grading scheme (S-003) -->
        <Card>
            <CardHeader>
                <CardTitle>Grading scheme</CardTitle>
                <CardDescription>
                    Configure and preview a rule-engine grading scheme, or keep the default weighted percentage.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-6 lg:grid-cols-2">
                <MetropoliaSchemeBuilder v-model="gradingScheme" :components="builderComponents" @apply-components="applyExampleComponents" />
                <GradingSchemePreview :scheme="gradingScheme" />
            </CardContent>
        </Card>

        <!-- Basic Information -->
        <Card>
            <CardHeader>
                <CardTitle>Basic Information</CardTitle>
                <CardDescription>General syllabus details and metadata</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="title">
                        <FormItem>
                            <FormLabel for="title">Title *</FormLabel>
                            <FormControl>
                                <Input id="title" type="text" placeholder="Title" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="version">
                        <FormItem>
                            <FormLabel for="version">Version *</FormLabel>
                            <FormControl>
                                <Input id="version" type="text" placeholder="e.g., v1.0, v2.1"
                                    v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="unit_id">
                        <FormItem>
                            <FormLabel>Unit *</FormLabel>
                            <FormControl>
                                <Combobox v-bind="componentField" disabled>
                                    <ComboboxAnchor>
                                        <div class="relative w-full items-center">
                                            <ComboboxInput v-model="addUnitSearch" placeholder="Search for a unit..."
                                                :display-value="(value) => {
                                                    const u = units.find((x) => x.id.toString() === value?.toString());
                                                    return u ? `${u.code} - ${u.name}` : '';
                                                }
                                                    " />
                                            <ComboboxTrigger
                                                class="absolute inset-y-0 end-0 flex items-center justify-center px-3">
                                                <ChevronsUpDown class="text-muted-foreground size-4" />
                                            </ComboboxTrigger>
                                        </div>
                                    </ComboboxAnchor>
                                    <ComboboxList
                                        class="max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-y-auto">
                                        <ComboboxViewport>
                                            <ComboboxEmpty>No units</ComboboxEmpty>
                                            <ComboboxItem v-for="u in filteredAvailableUnits" :key="u.id"
                                                :value="u.id.toString()">{{ u.code }} - {{ u.name }}</ComboboxItem>
                                        </ComboboxViewport>
                                    </ComboboxList>
                                </Combobox>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="total_hours" :transform="(value: any) => {
                        if (value === '' || value === null || value === undefined) return 0;
                        const num = typeof value === 'string' ? parseFloat(value.replace(',', '.')) : Number(value);
                        return isNaN(num) ? 0 : num;
                    }
                        ">
                        <FormItem>
                            <FormLabel for="total_hours">Total Hours *</FormLabel>
                            <FormControl>
                                <NumberField
                                    :model-value="typeof componentField.modelValue === 'string' ? parseFloat(componentField.modelValue) || 0 : componentField.modelValue"
                                    @update:model-value="componentField['onUpdate:modelValue']" :step="0.5"
                                    :format-options="{ minimumFractionDigits: 0, maximumFractionDigits: 2 }">
                                    <NumberFieldContent>
                                        <NumberFieldInput />
                                    </NumberFieldContent>
                                </NumberField>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="total_sessions" :transform="(value: any) => {
                        if (value === '' || value === null || value === undefined) return 1;
                        const num = typeof value === 'string' ? parseInt(value, 10) : Number(value);
                        return isNaN(num) ? 1 : Math.round(num);
                    }
                        ">
                        <FormItem>
                            <FormLabel for="total_sessions">Total Sessions *</FormLabel>
                            <FormControl>
                                <NumberField
                                    :model-value="typeof componentField.modelValue === 'string' ? parseInt(componentField.modelValue, 10) || 1 : componentField.modelValue"
                                    @update:model-value="componentField['onUpdate:modelValue']" :step="1"
                                    :format-options="{ minimumFractionDigits: 0, maximumFractionDigits: 0 }">
                                    <NumberFieldContent>
                                        <NumberFieldInput />
                                    </NumberFieldContent>
                                </NumberField>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="min_attendance_threshold" :transform="(value: any) => {
                        if (value === '' || value === null || value === undefined) return 80;
                        const num = typeof value === 'string' ? parseFloat(value.replace(',', '.')) : Number(value);
                        return isNaN(num) ? 80 : num;
                    }
                        ">
                        <FormItem>
                            <FormLabel for="min_attendance_threshold">Min Attendance (%) *</FormLabel>
                            <FormControl>
                                <NumberField
                                    :model-value="typeof componentField.modelValue === 'string' ? parseFloat(componentField.modelValue) || 80 : (componentField.modelValue as number)"
                                    @update:model-value="componentField['onUpdate:modelValue']" :step="1" :min="0"
                                    :max="100">
                                    <NumberFieldContent>
                                        <NumberFieldInput />
                                    </NumberFieldContent>
                                </NumberField>
                            </FormControl>
                            <FormDescription>Students must attend at least this percentage to pass.</FormDescription>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="min_grade_threshold" :transform="(value: any) => {
                        if (value === '' || value === null || value === undefined) return 40;
                        const num = typeof value === 'string' ? parseFloat(value.replace(',', '.')) : Number(value);
                        return isNaN(num) ? 40 : num;
                    }
                        ">
                        <FormItem>
                            <FormLabel for="min_grade_threshold">Min Pass Grade (out of 100) *</FormLabel>
                            <FormControl>
                                <NumberField
                                    :model-value="typeof componentField.modelValue === 'string' ? parseFloat(componentField.modelValue) || 40 : (componentField.modelValue as number)"
                                    @update:model-value="componentField['onUpdate:modelValue']" :step="0.5" :min="0"
                                    :max="100">
                                    <NumberFieldContent>
                                        <NumberFieldInput />
                                    </NumberFieldContent>
                                </NumberField>
                            </FormControl>
                            <FormDescription>Minimum total score required to pass the course.</FormDescription>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="exam_resit_fee" :transform="(value: any) => {
                        if (value === '' || value === null || value === undefined) return 0;
                        const num = typeof value === 'string' ? parseFloat(value.replace(',', '.')) : Number(value);
                        return isNaN(num) ? 0 : num;
                    }
                        ">
                        <FormItem>
                            <FormLabel for="exam_resit_fee">Phí thi lại (VND) *</FormLabel>
                            <FormControl>
                                <NumberField
                                    :model-value="typeof componentField.modelValue === 'string' ? parseFloat(componentField.modelValue) || 0 : componentField.modelValue"
                                    @update:model-value="componentField['onUpdate:modelValue']" :step="1000" :min="1"
                                    :format-options="{
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    }">
                                    <NumberFieldContent>
                                        <NumberFieldInput />
                                    </NumberFieldContent>
                                </NumberField>
                            </FormControl>
                            <FormDescription>Phí thi lại bắt buộc khi tạo đăng ký thi lại cho học phần này.</FormDescription>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <FormField v-slot="{ componentField }" name="description">
                    <FormItem>
                        <FormLabel for="description">Description</FormLabel>
                        <FormControl>
                            <Textarea id="description" rows="4"
                                placeholder="Describe the course content, objectives, and structure..."
                                v-bind="componentField" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ value, handleChange }" name="is_active">
                    <FormItem class="flex flex-row items-start space-y-0 space-x-3">
                        <FormControl>
                            <Checkbox :model-value="value" @update:model-value="handleChange" />
                        </FormControl>
                        <div class="space-y-1 leading-none">
                            <FormLabel>Set as active syllabus</FormLabel>
                        </div>
                    </FormItem>
                </FormField>
            </CardContent>
        </Card>

        <!-- Assessment Components (UI parity) -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle class="flex items-center gap-2">
                        Assessment Components
                        <Badge
                            :class="getTotalWeight() === 100 ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'">
                            {{ getTotalWeight() }}% </Badge>
                    </CardTitle>
                    <CardDescription>Define the assessment structure and weightings for this syllabus.</CardDescription>
                </div>
                <Button type="button" variant="outline" @click="addAssessmentComponent">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Component
                </Button>
            </CardHeader>
            <CardContent class="space-y-6">
                <div v-for="(component, componentIndex) in form.values.assessment_components || []"
                    :key="componentIndex" class="rounded-lg border p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h4 class="font-medium">Component {{ componentIndex + 1 }}</h4>
                            <Badge v-if="component.type" :class="getAssessmentTypeColor(component.type)">
                                {{ assessmentTypes[component.type] || component.type }}
                            </Badge>
                        </div>
                        <Button v-if="(form.values.assessment_components || []).length > 1" type="button"
                            variant="ghost" size="sm" @click="removeAssessmentComponent(componentIndex)">
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

                        <FormField v-slot="{ componentField }" :name="`assessment_components.${componentIndex}.code`">
                            <FormItem>
                                <FormLabel>Code</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" list="metropolia-component-codes" placeholder="e.g., EXAM" class="font-mono uppercase" />
                                </FormControl>
                                <FormDescription>Chọn mã chuẩn hoặc tự nhập. Bắt buộc khi dùng chấm điểm Metropolia.</FormDescription>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ value, setValue }"
                            :name="`assessment_components.${componentIndex}.weight`">
                            <FormItem>
                                <FormLabel>Weight (%)</FormLabel>
                                <FormControl>
                                    <NumberField :model-value="value" @update:model-value="
                                        (v) => {
                                            if (v) {
                                                setValue(v);
                                            } else {
                                                setValue(0);
                                            }
                                        }
                                    " :default-value="0" :min="0" :max="100">
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
                                    <Select :model-value="componentField.modelValue" @update:model-value="
                                        (value) => {
                                            componentField['onUpdate:modelValue']?.(value);
                                            handleTypeChange(componentIndex, String(value));
                                        }
                                    ">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="(label, value) in assessmentTypes" :key="value"
                                                :value="value">
                                                {{ label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <div class="flex items-end">
                            <Button type="button" variant="outline" size="sm"
                                @click="addComponentDetail(componentIndex)" :disabled="component.type === 'attendance'">
                                <Plus class="mr-1 h-3 w-3" />
                                Add Detail
                            </Button>
                        </div>
                    </div>

                    <FormField v-slot="{ value, handleChange }"
                        :name="`assessment_components.${componentIndex}.is_required_to_sit_final_exam`">
                        <FormItem class="mt-4 flex items-center space-y-0 space-x-3">
                            <FormControl>
                                <Checkbox :model-value="value" @update:model-value="handleChange" />
                            </FormControl>
                            <FormLabel class="text-sm">Required to sit final exam</FormLabel>
                        </FormItem>
                    </FormField>

                    <!-- Component Details -->
                    <FormField v-if="component.details && component.details.length > 0" v-slot="{ }"
                        :name="`assessment_components.${componentIndex}.details`">
                        <div class="mt-4 space-y-3">
                            <div class="flex items-center gap-2">
                                <Label class="text-sm font-medium">Subcomponents</Label>
                                <Badge v-if="getSubComponentTotalWeight(componentIndex) !== null"
                                    :class="getSubComponentTotalWeight(componentIndex) === 100 ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'">
                                    {{ getSubComponentTotalWeight(componentIndex) }}%
                                </Badge>
                            </div>
                            <div class="rounded border bg-gray-50 p-3">
                                <div v-for="(detail, detailIndex) in component.details" :key="detailIndex"
                                    class="mb-3 flex items-end gap-3 last:mb-0">
                                    <FormField v-slot="{ componentField }"
                                        :name="`assessment_components.${componentIndex}.details.${detailIndex}.name`">
                                        <FormItem class="flex-1">
                                            <FormLabel v-if="detailIndex === 0" class="text-xs">Detail Name</FormLabel>
                                            <FormControl>
                                                <Input v-bind="componentField" placeholder="e.g., Part A"
                                                    class="text-sm" :disabled="component.type === 'attendance'" />
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    </FormField>

                                    <FormField v-slot="{ value, setValue }"
                                        :name="`assessment_components.${componentIndex}.details.${detailIndex}.weight`">
                                        <FormItem class="w-24">
                                            <FormLabel v-if="detailIndex === 0" class="text-xs">Weight (%)</FormLabel>
                                            <FormControl>
                                                <NumberField :model-value="(value as number)" @update:model-value="
                                                    (v) => {
                                                        if (v) {
                                                            setValue(v);
                                                        } else {
                                                            setValue(0);
                                                        }
                                                    }
                                                " :default-value="0" :min="0" :max="100"
                                                    :disabled="component.type === 'attendance'">
                                                    <NumberFieldContent>
                                                        <NumberFieldInput class="text-sm" />
                                                    </NumberFieldContent>
                                                </NumberField>
                                            </FormControl>
                                            <FormMessage />
                                        </FormItem>
                                    </FormField>

                                    <Button type="button" variant="ghost" size="sm"
                                        @click="removeComponentDetail(componentIndex, detailIndex)" class="mb-1"
                                        :disabled="component.type === 'attendance'">
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
                <FormField v-slot="{ }" name="assessment_components">
                    <div class="rounded-lg bg-gray-50 p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-medium">Total Assessment Weight:</span>
                            <Badge
                                :class="getTotalWeight() === 100 ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'">
                                {{ getTotalWeight() }}% / 100% </Badge>
                        </div>
                        <p class="mt-1 text-sm text-gray-600">All assessment components must total exactly 100% for a
                            valid syllabus.</p>
                        <FormItem class="mt-2">
                            <FormMessage />
                        </FormItem>
                    </div>
                </FormField>
            </CardContent>
        </Card>
    </form>
</template>
