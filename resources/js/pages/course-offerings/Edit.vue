<script setup lang="ts">
import LectureCombobox from '@/components/LectureCombobox.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { CourseOffering, Lecture } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    courseOffering: CourseOffering;
    lectures: Lecture[];
    syllabusTemplates?: Array<{
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
}

const props = defineProps<Props>();

// Define validation schema following development standards
const formSchema = toTypedSchema(
    z.object({
        unit_id: z.number().int().positive('Unit ID is required'),
        lecture_id: z.string().optional(),
        section_code: z.string().max(40, 'Section code too long').optional(),
        syllabus_template_id: z.string().min(1, 'Syllabus template is required'),
        max_capacity: z.number().int().min(1, 'Max capacity must be at least 1').max(1000, 'Max capacity cannot exceed 1000'),
        waitlist_capacity: z.number().int().min(0, 'Waitlist capacity must be 0 or greater').max(100, 'Waitlist capacity cannot exceed 100'),
        delivery_mode: z.enum(['in_person', 'online', 'hybrid', 'blended'], {
            errorMap: () => ({ message: 'Please select a delivery mode' }),
        }),
        schedule_days: z.array(z.enum(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])).optional(),
        schedule_time_start: z.string().optional(),
        schedule_time_end: z.string().optional(),
        location: z.string().max(255, 'Location too long').optional(),
        enrollment_status: z.enum(['open', 'closed', 'waitlist_only', 'cancelled']),
        registration_start_date: z.string().optional(),
        registration_end_date: z.string().optional(),
        special_requirements: z.string().max(1000, 'Special requirements too long').optional(),
        notes: z.string().max(1000, 'Notes too long').optional(),
    }),
);

// Helper function to format date for input
const formatDateForInput = (dateString: string | null): string => {
    if (!dateString) return '';
    return new Date(dateString).toISOString().split('T')[0];
};

const { handleSubmit, isSubmitting } = useForm({
    validationSchema: formSchema,
    initialValues: {
        unit_id: props.courseOffering.unit_id,
        section_code: props.courseOffering.section_code || '',
        syllabus_template_id: props.courseOffering.syllabus_template?.id.toString() || '',
        max_capacity: props.courseOffering.max_capacity,
        waitlist_capacity: props.courseOffering.waitlist_capacity || 0,
        delivery_mode: props.courseOffering.delivery_mode,
        schedule_days: props.courseOffering.schedule_days || [],
        schedule_time_start: props.courseOffering.schedule_time_start || '',
        schedule_time_end: props.courseOffering.schedule_time_end || '',
        location: props.courseOffering.location || '',
        enrollment_status: props.courseOffering.enrollment_status,
        lecture_id: props.courseOffering.lecture_id?.toString() || '',
        registration_start_date: formatDateForInput(props.courseOffering.registration_start_date || null),
        registration_end_date: formatDateForInput(props.courseOffering.registration_end_date || null),
        special_requirements: props.courseOffering.special_requirements || '',
        notes: props.courseOffering.notes || '',
    },
});
const onSubmit = handleSubmit((values) => {
    const formData = {
        semester_id: props.courseOffering.semester_id,
        unit_id: Number(values.unit_id) || Number(props.courseOffering.unit_id),
        lecture_id: values.lecture_id === '' || values.lecture_id === 'none' ? null : values.lecture_id,
        section_code: values.section_code || null,
        syllabus_template_id: values.syllabus_template_id,
        max_capacity: Number(values.max_capacity),
        waitlist_capacity: Number(values.waitlist_capacity) || 0,
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

    router.put(`/course-offerings/${props.courseOffering.id}`, formData, {
        onSuccess: () => {
            // Success handled by redirect
            toast.success('Course offering updated successfully');
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
        },
    });
});

const goBack = () => window.history.back();

const deliveryModeOptions = [
    { value: 'in_person', label: 'In Person' },
    { value: 'online', label: 'Online' },
    { value: 'hybrid', label: 'Hybrid' },
    { value: 'blended', label: 'Blended' },
];

const enrollmentStatusOptions = [
    { value: 'open', label: 'Open' },
    { value: 'closed', label: 'Closed' },
    { value: 'waitlist_only', label: 'Waitlist Only' },
    { value: 'cancelled', label: 'Cancelled' },
];
</script>

<template>
    <Head title="Edit Course Offering" />
    <!-- Header -->
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Edit Course Offering</h1>
            <p class="text-muted-foreground">Update course offering details and registration settings</p>
        </div>
        <Button variant="outline" size="sm" @click="goBack">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back
        </Button>
    </div>

    <form @submit="onSubmit" class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Basic Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Basic Information</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Read-only Semester Information -->
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </div>
                            <div class="ml-3 flex-1">
                                <h3 class="text-sm font-medium text-blue-800">Semester</h3>
                                <div class="mt-1 text-sm text-blue-700">
                                    <p>
                                        <strong>{{ courseOffering.semester?.name }}</strong> ({{ courseOffering.semester?.code }})
                                    </p>
                                    <p class="mt-1 text-xs" v-if="courseOffering.semester">
                                        {{ new Date(courseOffering.semester.start_date).toLocaleDateString() }} -
                                        {{ new Date(courseOffering.semester.end_date).toLocaleDateString() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Read-only Unit Information -->
                    <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.93 3.618l2.42 2.42a.75.75 0 11-1.061 1.061l-2.42-2.42A7 7 0 012 9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3 flex-1">
                                <h3 class="text-sm font-medium text-green-800">Unit</h3>
                                <div class="mt-1 text-sm text-green-700">
                                    <p>
                                        <strong>{{ courseOffering.unit?.code }} - {{ courseOffering.unit?.name }}</strong>
                                    </p>
                                    <p class="mt-1 text-xs" v-if="courseOffering.unit">{{ courseOffering.unit.credit_points }} credits</p>
                                </div>
                            </div>
                        </div>
                    </div>

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
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select syllabus template" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <template v-if="syllabusTemplates">
                                            <SelectItem v-for="template in syllabusTemplates" :key="template.id" :value="template.id.toString()">
                                                <div class="flex flex-col">
                                                    <span class="font-medium">{{ template.title }} v{{ template.version }}</span>
                                                    <div class="text-muted-foreground text-xs">
                                                        <span v-if="template.unit">{{ template.unit.code }} - {{ template.unit.name }}</span>
                                                        <span v-if="template.delivery_mode" class="ml-2">• {{ template.delivery_mode }}</span>
                                                    </div>
                                                </div>
                                            </SelectItem>
                                        </template>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="lecture_id">
                        <FormItem>
                            <FormLabel>Lecturer</FormLabel>
                            <FormControl>
                                <LectureCombobox :model-value="componentField.modelValue" :lectures="lectures" @update:model-value="componentField['onUpdate:modelValue']" />
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
                                    <Input v-bind="componentField" type="number" min="1" max="1000" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="waitlist_capacity">
                            <FormItem>
                                <FormLabel>Waitlist Capacity</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="number" min="0" max="100" placeholder="0" />
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
                                    <div v-for="day in ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']" :key="day" class="flex items-center space-x-2">
                                        <input
                                            :id="day"
                                            type="checkbox"
                                            :value="day"
                                            :checked="componentField.modelValue?.includes(day)"
                                            @change="
                                                (e) => {
                                                    const target = e.target as HTMLInputElement;
                                                    const currentValue = componentField.modelValue || [];
                                                    if (target.checked) {
                                                        componentField['onUpdate:modelValue']?.([...currentValue, day]);
                                                    } else {
                                                        componentField['onUpdate:modelValue']?.(currentValue.filter((d: string) => d !== day));
                                                    }
                                                }
                                            "
                                            class="text-primary focus:ring-primary rounded border-gray-300"
                                        />
                                        <label :for="day" class="text-sm font-medium">{{ day }}</label>
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

        <!-- Actions -->
        <div class="flex justify-end gap-4">
            <Link href="/course-offerings">
                <Button type="button" variant="outline">Cancel</Button>
            </Link>
            <Button type="submit" :disabled="isSubmitting">
                <Save class="mr-2 h-4 w-4" />
                {{ isSubmitting ? 'Updating...' : 'Update Course Offering' }}
            </Button>
        </div>
    </form>
</template>
