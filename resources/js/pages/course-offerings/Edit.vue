<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { CourseOffering, Lecture, Semester, Unit } from '@/types/models';
import { ScheduleDay } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { z } from 'zod';

interface Props {
    courseOffering: CourseOffering;
    semesters: Semester[];
    units: Unit[];
    lectures: Lecture[];
}

const props = defineProps<Props>();

// Define validation schema following development standards
const formSchema = toTypedSchema(
    z.object({
        semester_id: z.string().min(1, 'Semester is required'),
        curriculum_unit_id: z.string().min(1, 'Curriculum unit is required'),
        lecture_id: z.string().optional(),
        section_code: z.string().max(10, 'Section code too long').optional(),
        max_capacity: z.number().int().min(1, 'Max capacity must be at least 1').max(500, 'Max capacity cannot exceed 500'),
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
        semester_id: props.courseOffering.semester_id.toString(),
        curriculum_unit_id: props.courseOffering.curriculum_unit_id?.toString() || '',
        section_code: props.courseOffering.section_code || '',
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
console.log('schedule_time_start', props.courseOffering.schedule_time_start);
const onSubmit = handleSubmit((values) => {
    const formData = {
        semester_id: values.semester_id,
        curriculum_unit_id: values.curriculum_unit_id,
        lecture_id: values.lecture_id === '' ? null : values.lecture_id,
        section_code: values.section_code || null,
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
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
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

                    <FormField v-slot="{ componentField }" name="curriculum_unit_id">
                        <FormItem>
                            <FormLabel>Curriculum Unit</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select curriculum unit" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="unit in units" :key="unit.id" :value="unit.id.toString()">
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
                                        <SelectItem v-for="lecture in lectures" :key="lecture.id" :value="lecture.id.toString()">
                                            {{ lecture.full_name }}
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
                                    <Input v-bind="componentField" type="number" min="1" max="500" />
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
                                            @change="(e) => {
                                                const target = e.target as HTMLInputElement;
                                                const currentValue = componentField.modelValue || [];
                                                if (target.checked) {
                                                    componentField['onUpdate:modelValue']([...currentValue, day]);
                                                } else {
                                                    componentField['onUpdate:modelValue'](currentValue.filter(d => d !== day));
                                                }
                                            }"
                                            class="rounded border-gray-300 text-primary focus:ring-primary"
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
