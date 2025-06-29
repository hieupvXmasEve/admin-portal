<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { NumberInput } from '@/components/ui/number-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { CourseOfferingFormData, Lecture, Semester, Unit } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { ref } from 'vue';
import { z } from 'zod';

interface Props {
    semesters: Semester[];
    units: Unit[];
    lectures: Lecture[];
}

const props = defineProps<Props>();
const submitError = ref<string | null>(null);

// Define validation schema that exactly matches backend CourseOffering validation rules
const formSchema = toTypedSchema(
    z.object({
        semester_id: z.string().min(1, 'Semester is required'),
        unit_id: z.string().min(1, 'Unit is required'),
        lecture_id: z.string().optional(),
        section_code: z.string().max(10, 'Section code too long').optional(),
        max_capacity: z.number().int().min(1, 'Max capacity must be at least 1').max(500, 'Max capacity cannot exceed 500'),
        waitlist_capacity: z
            .number()
            .int()
            .min(0, 'Waitlist capacity must be 0 or greater')
            .max(100, 'Waitlist capacity cannot exceed 100')
            .default(10),
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

const { handleSubmit, isSubmitting } = useForm({
    validationSchema: formSchema,
    initialValues: {
        semester_id: '',
        unit_id: '',
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
    } satisfies CourseOfferingFormData,
});

const onSubmit = handleSubmit((values) => {
    submitError.value = null;

    console.log('Form values before transformation:', values);

    // Transform form data to match backend expectations
    const formData = {
        semester_id: values.semester_id,
        unit_id: values.unit_id,
        lecture_id: values.lecture_id === 'none' || values.lecture_id === '' ? null : values.lecture_id,
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
            console.log('Course offering created successfully');
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
            submitError.value = 'Please check the form for errors and try again.';

            // Log detailed error information for debugging
            Object.entries(errors).forEach(([field, messages]) => {
                console.error(`Field "${field}":`, messages);
            });
        },
        onFinish: () => {
            console.log('Request finished');
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

    <form @submit="onSubmit" class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Basic Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Basic Information</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <FormField v-slot="{ componentField }" name="semester_id">
                        <FormItem>
                            <FormLabel>Semester</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">
                                            {{ semester.name }} ({{ semester.code }})
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="unit_id">
                        <FormItem>
                            <FormLabel>Unit</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select unit" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="unit in props.units" :key="unit.id" :value="unit.id.toString()">
                                            {{ unit.code }} - {{ unit.name }} ({{ unit.credit_points }} credits)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
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
                                        <SelectItem v-for="lecture in props.lectures" :key="lecture.id" :value="lecture.id.toString()">
                                            {{ lecture.first_name }} {{ lecture.last_name }} ({{ lecture.academic_rank }})
                                        </SelectItem>
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
