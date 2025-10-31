<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxList, ComboboxTrigger, ComboboxViewport } from '@/components/ui/combobox';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { NumberInput } from '@/components/ui/number-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { CourseOfferingFormData, Lecture } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Check, ChevronsUpDown, Save, Search } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    activeSemester: {
        id: number;
        name: string;
        code: string;
        start_date: string;
        end_date: string;
    } | null;
    units: Array<{
        unit_id: number;
        code: string;
        name: string;
        credit_points: number;
        level: number | null;
        unit_type: string | null;
    }>;
    lectures: Lecture[];
    syllabusTemplates: Array<{
        id: number;
        unit_id: number;
        title: string;
        version: string;
        description: string;
        delivery_mode: string;
        unit?: {
            id: number;
            code: string;
            name: string;
        };
        applicable_campus?: {
            id: number;
            name: string;
        };
        applicable_program?: {
            id: number;
            name: string;
        };
    }>;
    error: string | null;
}

const props = defineProps<Props>();
const submitError = ref<string | null>(null);
// Define validation schema that exactly matches backend CourseOffering validation rules
const formSchema = toTypedSchema(
    z.object({
        unit_id: z.string().min(1, 'Unit is required'),
        syllabus_template_id: z.string().min(1, 'Syllabus template is required'),
        lecture_id: z.string().optional(),
        section_code: z.string().max(40, 'Section code too long').optional(),
        max_capacity: z.number().int().min(1, 'Max capacity must be at least 1').max(500, 'Max capacity cannot exceed 500'),
        waitlist_capacity: z.number().int().min(0, 'Waitlist capacity must be 0 or greater').max(100, 'Waitlist capacity cannot exceed 100').default(10),
        delivery_mode: z.enum(['in_person', 'online', 'hybrid', 'blended'], {
            errorMap: () => ({ message: 'Please select a delivery mode' }),
        }),
        schedule_days: z.array(z.enum(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])).optional(),
        schedule_time_start: z.string().optional(),
        schedule_time_end: z.string().optional(),
        location: z.string().max(255, 'Location too long').optional(),
        enrollment_status: z.enum(['open', 'closed', 'waitlist_only', 'cancelled']).default('open'),
        registration_start_date: z.string().optional(),
        registration_end_date: z.string().optional(),
        special_requirements: z.string().max(1000, 'Special requirements too long').optional(),
        notes: z.string().max(1000, 'Notes too long').optional(),
    }),
);

const { handleSubmit, isSubmitting, values, setFieldValue } = useForm({
    validationSchema: formSchema,
    initialValues: {
        unit_id: '',
        syllabus_template_id: '',
        lecture_id: '',
        section_code: '',
        max_capacity: 30,
        waitlist_capacity: 10,
        delivery_mode: 'in_person' as const,
        schedule_days: [],
        schedule_time_start: '',
        schedule_time_end: '',
        location: '',
        enrollment_status: 'open' as const,
        registration_start_date: '',
        registration_end_date: '',
        special_requirements: '',
        notes: '',
    } satisfies Omit<CourseOfferingFormData, 'semester_id'>,
});

// Get the selected unit based on unit_id
const selectedUnit = computed(() => {
    if (!values.unit_id) return null;
    return props.units.find((unit) => unit.unit_id.toString() === values.unit_id);
});

// Search term for filtering units
const searchTerm = ref('');

// Filtered units based on search term
const filteredUnits = computed(() => {
    if (!searchTerm.value) return props.units;
    const term = searchTerm.value.toLowerCase();
    return props.units.filter((unit) => unit.code.toLowerCase().includes(term) || unit.name.toLowerCase().includes(term));
});

// Filter syllabus templates based on selected unit
const filteredSyllabusTemplates = computed(() => {
    if (!selectedUnit.value) return [];
    return props.syllabusTemplates.filter((template) => template.unit_id === selectedUnit.value!.unit_id);
});

// Watch for unit changes and clear syllabus template selection
watch(
    () => values.unit_id,
    (newUnitId, oldUnitId) => {
        // Clear syllabus template selection when unit changes
        if (newUnitId !== oldUnitId && values.syllabus_template_id) {
            setFieldValue('syllabus_template_id', '');
        }
    },
);

const onSubmit = handleSubmit((values) => {
    submitError.value = null;

    // Transform form data to match backend expectations
    const formData = {
        semester_id: props.activeSemester?.id,
        unit_id: values.unit_id,
        syllabus_template_id: !values.syllabus_template_id || values.syllabus_template_id === 'none' || values.syllabus_template_id === '' ? null : values.syllabus_template_id,
        lecture_id: !values.lecture_id || values.lecture_id === '' ? null : values.lecture_id,
        section_code: values.section_code || null,
        max_capacity: Number(values.max_capacity),
        waitlist_capacity: Number(values.waitlist_capacity) || 10,
        delivery_mode: values.delivery_mode,
        schedule_days: values.schedule_days?.length ? values.schedule_days : null,
        schedule_time_start: values.schedule_time_start || null,
        schedule_time_end: values.schedule_time_end || null,
        location: values.location || null,
        enrollment_status: values.enrollment_status,
        registration_start_date: values.registration_start_date || null,
        registration_end_date: values.registration_end_date || null,
        special_requirements: values.special_requirements || null,
        notes: values.notes || null,
    };

    console.log('Submitting form data:', formData);

    router.post('/course-offerings', formData, {
        onSuccess: () => {
            toast.success('Course offering created successfully');
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
            submitError.value = 'Please check the form for errors and try again.';

            // Log detailed error information for debugging
            Object.entries(errors).forEach(([field, messages]) => {
                console.error(`Field "${field}":`, messages);
            });
        },
    });
});

const deliveryModeOptions = [
    { value: 'in_person', label: 'In Person' },
    { value: 'online', label: 'Online' },
    { value: 'hybrid', label: 'Hybrid' },
    { value: 'blended', label: 'Blended' },
];

const enrollmentStatusOptions = [
    { value: 'open', label: 'Open' },
    { value: 'closed', label: 'Closed' },
    { value: 'waitlist_only', label: 'Wait list Only' },
    { value: 'cancelled', label: 'Cancelled' },
];

const dayOptions = [
    { value: 'Monday', label: 'Monday' },
    { value: 'Tuesday', label: 'Tuesday' },
    { value: 'Wednesday', label: 'Wednesday' },
    { value: 'Thursday', label: 'Thursday' },
    { value: 'Friday', label: 'Friday' },
    { value: 'Saturday', label: 'Saturday' },
    { value: 'Sunday', label: 'Sunday' },
];
</script>

<template>
    <Head title="Create Course Offering" />
    <!-- Header -->
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Create Course Offering</h1>
            <p class="text-muted-foreground">Set up a new course offering for student registration</p>
        </div>
        <Link href="/course-offerings">
            <Button variant="outline" size="sm">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to Course Offerings
            </Button>
        </Link>
    </div>

    <!-- No Active Semester State -->
    <div v-if="!props.activeSemester" class="rounded-lg border border-red-200 bg-red-50 p-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
                        clip-rule="evenodd"
                    />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">No Active Semester</h3>
                <div class="mt-2 text-sm text-red-700">
                    <p>{{ props.error }}</p>
                </div>
                <div class="mt-4">
                    <Link href="/semesters">
                        <Button size="sm" variant="outline" class="border-red-300 text-red-700 hover:bg-red-100"> Manage Semesters </Button>
                    </Link>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Semester Info -->
    <div v-if="props.activeSemester" class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z"
                        clip-rule="evenodd"
                    />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-blue-800">Active Semester</h3>
                <div class="mt-1 text-sm text-blue-700">
                    <p>
                        <strong>{{ props.activeSemester.name }}</strong> ({{ props.activeSemester.code }})
                    </p>
                    <p class="mt-1 text-xs">
                        {{ new Date(props.activeSemester.start_date).toLocaleDateString() }} -
                        {{ new Date(props.activeSemester.end_date).toLocaleDateString() }}
                    </p>
                </div>
            </div>
            <div class="text-right text-xs text-blue-600">
                <p>
                    <strong>{{ props.units.length }}</strong> units available
                </p>
            </div>
        </div>
    </div>

    <form v-if="props.activeSemester" @submit="onSubmit" class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Basic Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Basic Information</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <FormField v-slot="{ componentField }" name="unit_id">
                        <FormItem>
                            <FormLabel>Unit *</FormLabel>
                            <FormControl>
                                <Combobox
                                    :model-value="componentField.modelValue ? props.units.find((u) => u.unit_id.toString() === componentField.modelValue) : undefined"
                                    @update:model-value="
                                        (val) => {
                                            componentField['onUpdate:modelValue'](val?.unit_id.toString() ?? '');
                                            searchTerm = '';
                                        }
                                    "
                                    by="unit_id"
                                    v-model:search-term="searchTerm"
                                >
                                    <ComboboxAnchor as-child>
                                        <ComboboxTrigger as-child>
                                            <Button variant="outline" class="w-full justify-between">
                                                <span class="truncate">
                                                    {{ componentField.modelValue && selectedUnit ? `${selectedUnit.code} - ${selectedUnit.name}` : 'Search units...' }}
                                                </span>
                                                <ChevronsUpDown class="text-muted-foreground ml-2 h-4 w-4 shrink-0 opacity-50" />
                                            </Button>
                                        </ComboboxTrigger>
                                    </ComboboxAnchor>

                                    <ComboboxList class="w-[var(--reka-combobox-trigger-width)]">
                                        <!-- Search Input -->
                                        <div class="relative w-full items-center">
                                            <ComboboxInput class="h-10 rounded-none border-0 border-b pr-4 pl-10 focus-visible:ring-0" placeholder="Search units by code or name..." @update:model-value="(value) => (searchTerm = value)" />
                                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center justify-center px-3">
                                                <Search class="text-muted-foreground size-4" />
                                            </span>
                                        </div>

                                        <!-- Content with proper height and scrolling -->
                                        <ComboboxViewport class="max-h-[300px] overflow-y-auto">
                                            <!-- Empty state -->
                                            <ComboboxEmpty>
                                                <div class="flex items-center justify-center py-6">
                                                    <span class="text-muted-foreground text-sm">No units found for "{{ searchTerm }}"</span>
                                                </div>
                                            </ComboboxEmpty>

                                            <!-- Unit list -->
                                            <ComboboxGroup v-if="filteredUnits.length > 0">
                                                <ComboboxItem v-for="unit in filteredUnits" :key="unit.unit_id" :value="unit">
                                                    <div class="flex w-full items-center justify-between gap-3">
                                                        <div class="flex min-w-0 flex-1 flex-col">
                                                            <span class="truncate font-medium">{{ unit.code }}</span>
                                                            <span class="text-muted-foreground truncate text-xs">{{ unit.name }}</span>
                                                        </div>
                                                        <div class="text-muted-foreground flex shrink-0 items-center gap-2 text-xs">
                                                            <span v-if="unit.level">Level {{ unit.level }}</span>
                                                            <span>{{ unit.credit_points }} credits</span>
                                                        </div>
                                                    </div>
                                                    <ComboboxItemIndicator>
                                                        <Check class="ml-2 h-4 w-4" />
                                                    </ComboboxItemIndicator>
                                                </ComboboxItem>
                                            </ComboboxGroup>
                                        </ComboboxViewport>
                                    </ComboboxList>
                                </Combobox>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="section_code">
                        <FormItem>
                            <FormLabel>Section Code</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., A, B1, 01" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="syllabus_template_id">
                        <FormItem>
                            <FormLabel>Syllabus Template *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField" :disabled="!selectedUnit">
                                    <SelectTrigger>
                                        <SelectValue :placeholder="!selectedUnit ? 'Select a curriculum unit first' : filteredSyllabusTemplates.length === 0 ? 'No syllabus templates available for this unit' : 'Select syllabus template'" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="template in filteredSyllabusTemplates" :key="template.id" :value="template.id.toString()"> {{ template.title }} (v{{ template.version }}) </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                            <p v-if="selectedUnit && filteredSyllabusTemplates.length === 0" class="text-muted-foreground mt-1 text-sm">
                                No syllabus templates found for {{ selectedUnit.code }}. Please create a template first before creating a course offering.
                            </p>
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="lecture_id">
                        <FormItem>
                            <FormLabel>Lecture</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select lecture (optional)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">No lecture assigned</SelectItem>
                                        <SelectItem v-for="lecture in props.lectures" :key="lecture.id" :value="lecture.id.toString()"> {{ lecture.first_name }} {{ lecture.last_name }} ({{ lecture.academic_rank }}) </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </CardContent>
            </Card>

            <!-- Enrollment & Delivery -->
            <Card>
                <CardHeader>
                    <CardTitle>Enrollment & Delivery</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <FormField v-slot="{ componentField }" name="max_capacity">
                            <FormItem>
                                <FormLabel>Max Capacity *</FormLabel>
                                <FormControl>
                                    <NumberInput
                                        :model-value="componentField.modelValue"
                                        @update:model-value="componentField['onUpdate:modelValue']"
                                        :min="1"
                                        :max="500"
                                        :allow-decimal="false"
                                        :allow-negative="false"
                                        placeholder="30"
                                        :name="componentField.name"
                                        @blur="componentField.onBlur"
                                    />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="waitlist_capacity">
                            <FormItem>
                                <FormLabel>Wait list Capacity</FormLabel>
                                <FormControl>
                                    <NumberInput
                                        :model-value="componentField.modelValue"
                                        @update:model-value="componentField['onUpdate:modelValue']"
                                        :min="0"
                                        :max="100"
                                        :allow-decimal="false"
                                        :allow-negative="false"
                                        placeholder="10"
                                        :name="componentField.name"
                                        @blur="componentField.onBlur"
                                    />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <FormField v-slot="{ componentField }" name="delivery_mode">
                        <FormItem>
                            <FormLabel>Delivery Mode</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select delivery mode" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in deliveryModeOptions" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="schedule_days">
                        <FormItem>
                            <FormLabel>Schedule Days</FormLabel>
                            <FormControl>
                                <div class="grid grid-cols-2 gap-2">
                                    <div v-for="day in dayOptions" :key="day.value" class="flex items-center space-x-2">
                                        <input
                                            :id="day.value"
                                            type="checkbox"
                                            :value="day.value"
                                            :checked="componentField.modelValue?.includes(day.value)"
                                            @change="
                                                (e) => {
                                                    const target = e.target as HTMLInputElement;
                                                    const currentValue = componentField.modelValue || [];
                                                    if (target.checked) {
                                                        componentField['onUpdate:modelValue']?.([...currentValue, day.value]);
                                                    } else {
                                                        componentField['onUpdate:modelValue']?.(currentValue.filter((d: string) => d !== day.value));
                                                    }
                                                }
                                            "
                                            class="text-primary focus:ring-primary rounded border-gray-300"
                                        />
                                        <label :for="day.value" class="text-sm font-medium">{{ day.label }}</label>
                                    </div>
                                </div>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField v-slot="{ componentField }" name="schedule_time_start">
                            <FormItem>
                                <FormLabel>Start Time</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="time" placeholder="09:00" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="schedule_time_end">
                            <FormItem>
                                <FormLabel>End Time</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="time" placeholder="10:30" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <FormField v-slot="{ componentField }" name="location">
                        <FormItem>
                            <FormLabel>Location</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., Room 101, Building A" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="enrollment_status">
                        <FormItem>
                            <FormLabel>Enrollment Status</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select enrollment status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in enrollmentStatusOptions" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </CardContent>
            </Card>

            <!-- Registration Dates -->
            <Card>
                <CardHeader>
                    <CardTitle>Registration Dates</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <FormField v-slot="{ componentField }" name="registration_start_date">
                            <FormItem>
                                <FormLabel>Registration Start Date</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="date" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="registration_end_date">
                            <FormItem>
                                <FormLabel>Registration End Date</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="date" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </CardContent>
            </Card>

            <!-- Additional Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Additional Information</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <FormField v-slot="{ componentField }" name="special_requirements">
                        <FormItem>
                            <FormLabel>Special Requirements</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="Any special requirements for this course..." rows="3" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="notes">
                        <FormItem>
                            <FormLabel>Notes</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="Additional notes..." rows="3" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </CardContent>
            </Card>
        </div>

        <!-- Error Display -->
        <div v-if="submitError" class="rounded-md bg-red-50 p-4">
            <div class="flex">
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Error creating course offering</h3>
                    <div class="mt-2 text-sm text-red-700">
                        {{ submitError }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-4">
            <Link href="/course-offerings">
                <Button type="button" variant="outline">Cancel</Button>
            </Link>
            <Button type="submit" :disabled="isSubmitting">
                <Save class="mr-2 h-4 w-4" />
                {{ isSubmitting ? 'Creating...' : 'Create Course Offering' }}
            </Button>
        </div>
    </form>
</template>
