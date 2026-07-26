<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { CourseOffering } from '@/types/models';
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Calendar, Clock, Save, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    courseOfferings: CourseOffering[];
}

defineProps<Props>();

const isSubmitting = ref(false);

// Form validation schema
const formSchema = toTypedSchema(
    z
        .object({
            course_offering_id: z.string().min(1, 'Course offering is required'),
            session_title: z.string().min(1, 'Session title is required').max(255, 'Title is too long'),
            session_description: z.string().optional(),
            session_date: z.string().min(1, 'Session date is required'),
            start_time: z
                .string()
                .min(1, 'Start time is required')
                .regex(/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Invalid time format (HH:MM)'),
            end_time: z
                .string()
                .min(1, 'End time is required')
                .regex(/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/, 'Invalid time format (HH:MM)'),
            session_type: z.enum(['lecture', 'tutorial', 'practical', 'workshop', 'seminar', 'exam'], {
                required_error: 'Session type is required',
            }),
            delivery_mode: z.enum(['in_person', 'online', 'hybrid'], {
                required_error: 'Delivery mode is required',
            }),
            status: z.enum(['scheduled', 'in_progress', 'completed', 'cancelled'], {
                required_error: 'Status is required',
            }),
            attendance_required: z.boolean().default(false),
            attendance_tracking_enabled: z.boolean().default(false),
            online_meeting_url: z.string().url('Invalid URL format').optional().or(z.literal('')),
            instructor_notes: z.string().optional(),
            learning_objectives: z.string().optional(),
            required_materials: z.string().optional(),
            topics_covered: z.string().optional(),
        })
        .refine(
            (data) => {
                if (data.start_time && data.end_time) {
                    const start = new Date(`2000-01-01T${data.start_time}`);
                    const end = new Date(`2000-01-01T${data.end_time}`);
                    return end > start;
                }
                return true;
            },
            {
                message: 'End time must be after start time',
                path: ['end_time'],
            },
        )
        .refine(
            (data) => {
                if (data.delivery_mode === 'online' && data.online_meeting_url === '') {
                    return false;
                }
                return true;
            },
            {
                message: 'Meeting URL is required for online sessions',
                path: ['online_meeting_url'],
            },
        ),
);

// Initialize form
const { handleSubmit, values } = useForm({
    validationSchema: formSchema,
    initialValues: {
        course_offering_id: '',
        session_title: '',
        session_description: '',
        session_date: '',
        start_time: '',
        end_time: '',
        session_type: 'lecture',
        delivery_mode: 'in_person',
        status: 'scheduled',
        attendance_required: false,
        attendance_tracking_enabled: false,
        online_meeting_url: '',
        instructor_notes: '',
        learning_objectives: '',
        required_materials: '',
        topics_covered: '',
    },
});

// Form submission
const onSubmit = handleSubmit((formValues) => {
    isSubmitting.value = true;

    // Transform array fields
    const data = {
        ...formValues,
        course_offering_id: parseInt(formValues.course_offering_id),
        learning_objectives: formValues.learning_objectives ? formValues.learning_objectives.split('\n').filter((line) => line.trim()) : [],
        required_materials: formValues.required_materials ? formValues.required_materials.split('\n').filter((line) => line.trim()) : [],
        topics_covered: formValues.topics_covered ? formValues.topics_covered.split('\n').filter((line) => line.trim()) : [],
    };

    router.post('/class-sessions', data, {
        onSuccess: () => {
            toast.success('Class session created successfully');
            router.visit('/class-sessions');
        },
        onError: (errors) => {
            toast.error('Failed to create class session');
            console.error('Form errors:', errors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
});

// Navigation
const navigateBack = () => {
    router.visit('/class-sessions');
};

// Options for dropdowns
const sessionTypeOptions = [
    { value: 'lecture', label: 'Lecture' },
    { value: 'tutorial', label: 'Tutorial' },
    { value: 'practical', label: 'Practical' },
    { value: 'workshop', label: 'Workshop' },
    { value: 'seminar', label: 'Seminar' },
    { value: 'exam', label: 'Exam' },
];

const deliveryModeOptions = [
    { value: 'in_person', label: 'In Person' },
    { value: 'online', label: 'Online' },
    { value: 'hybrid', label: 'Hybrid' },
];

const statusOptions = [
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

// Get minimum date (today)
const today = new Date().toISOString().split('T')[0];
</script>

<template>
    <Head title="Create Class Session" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="sm" @click="navigateBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Sessions
                </Button>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Create Class Session</h1>
                    <p class="text-muted-foreground">Schedule a new class session for your course</p>
                </div>
            </div>
        </div>

        <form @submit="onSubmit" class="space-y-6">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Form -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Basic Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                Basic Information
                            </CardTitle>
                            <CardDescription> Enter the session title, description, and course details. </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Course Offering -->
                            <FormField v-slot="{ componentField }" name="course_offering_id">
                                <FormItem>
                                    <FormLabel>Course Offering *</FormLabel>
                                    <Select v-bind="componentField">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a course offering" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem v-for="offering in courseOfferings" :key="offering.id" :value="offering.id.toString()">
                                                {{ offering.curriculum_unit?.unit?.code }} -
                                                {{ offering.curriculum_unit?.unit?.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <!-- Session Title -->
                            <FormField v-slot="{ componentField }" name="session_title">
                                <FormItem>
                                    <FormLabel>Session Title *</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" placeholder="Enter session title" maxlength="255" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <!-- Session Description -->
                            <FormField v-slot="{ componentField }" name="session_description">
                                <FormItem>
                                    <FormLabel>Description</FormLabel>
                                    <FormControl>
                                        <Textarea v-bind="componentField" placeholder="Brief description of the session" rows="3" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </CardContent>
                    </Card>

                    <!-- Schedule Details -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Clock class="h-5 w-5" />
                                Schedule & Timing
                            </CardTitle>
                            <CardDescription> Set the date, time, and duration for this session. </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <!-- Session Date -->
                                <FormField v-slot="{ componentField }" name="session_date">
                                    <FormItem>
                                        <FormLabel>Session Date *</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" type="date" :min="today" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <!-- Start Time -->
                                <FormField v-slot="{ componentField }" name="start_time">
                                    <FormItem>
                                        <FormLabel>Start Time *</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" type="time" placeholder="HH:MM" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <!-- End Time -->
                                <FormField v-slot="{ componentField }" name="end_time">
                                    <FormItem>
                                        <FormLabel>End Time *</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" type="time" placeholder="HH:MM" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <!-- Session Type -->
                                <FormField v-slot="{ componentField }" name="session_type">
                                    <FormItem>
                                        <FormLabel>Session Type *</FormLabel>
                                        <Select v-bind="componentField">
                                            <FormControl>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select type" />
                                                </SelectTrigger>
                                            </FormControl>
                                            <SelectContent>
                                                <SelectItem v-for="option in sessionTypeOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <!-- Delivery Mode -->
                                <FormField v-slot="{ componentField }" name="delivery_mode">
                                    <FormItem>
                                        <FormLabel>Delivery Mode *</FormLabel>
                                        <Select v-bind="componentField">
                                            <FormControl>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select mode" />
                                                </SelectTrigger>
                                            </FormControl>
                                            <SelectContent>
                                                <SelectItem v-for="option in deliveryModeOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <!-- Status -->
                                <FormField v-slot="{ componentField }" name="status">
                                    <FormItem>
                                        <FormLabel>Status *</FormLabel>
                                        <Select v-bind="componentField">
                                            <FormControl>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select status" />
                                                </SelectTrigger>
                                            </FormControl>
                                            <SelectContent>
                                                <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>

                            <!-- Online Meeting URL -->
                            <FormField v-slot="{ componentField }" name="online_meeting_url">
                                <FormItem>
                                    <FormLabel>
                                        Online Meeting URL
                                        <span v-if="values.delivery_mode === 'online'" class="text-destructive">*</span>
                                    </FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" type="url" placeholder="https://..." :disabled="values.delivery_mode === 'in_person'" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </CardContent>
                    </Card>

                    <!-- Learning Content -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Learning Content</CardTitle>
                            <CardDescription> Define the learning objectives, topics, and materials for this session. </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Learning Objectives -->
                            <FormField v-slot="{ componentField }" name="learning_objectives">
                                <FormItem>
                                    <FormLabel>Learning Objectives</FormLabel>
                                    <FormControl>
                                        <Textarea v-bind="componentField" placeholder="Enter each objective on a new line" rows="4" />
                                    </FormControl>
                                    <p class="text-muted-foreground text-xs">Enter each objective on a new line</p>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <!-- Topics Covered -->
                            <FormField v-slot="{ componentField }" name="topics_covered">
                                <FormItem>
                                    <FormLabel>Topics Covered</FormLabel>
                                    <FormControl>
                                        <Textarea v-bind="componentField" placeholder="Enter each topic on a new line" rows="4" />
                                    </FormControl>
                                    <p class="text-muted-foreground text-xs">Enter each topic on a new line</p>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <!-- Required Materials -->
                            <FormField v-slot="{ componentField }" name="required_materials">
                                <FormItem>
                                    <FormLabel>Required Materials</FormLabel>
                                    <FormControl>
                                        <Textarea v-bind="componentField" placeholder="Enter each material on a new line" rows="3" />
                                    </FormControl>
                                    <p class="text-muted-foreground text-xs">Enter each material on a new line</p>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </CardContent>
                    </Card>

                    <!-- Notes -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Additional Notes</CardTitle>
                            <CardDescription> Add any additional notes or instructions for this session. </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FormField v-slot="{ componentField }" name="instructor_notes">
                                <FormItem>
                                    <FormLabel>Instructor Notes</FormLabel>
                                    <FormControl>
                                        <Textarea v-bind="componentField" placeholder="Notes for the instructor conducting this session" rows="4" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </CardContent>
                    </Card>
                </div>

                <!-- Settings Sidebar -->
                <div class="space-y-6">
                    <!-- Attendance Settings -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Attendance Settings</CardTitle>
                            <CardDescription> Configure attendance tracking for this session. </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <FormField v-slot="{ componentField }" name="attendance_required">
                                <FormItem>
                                    <div class="flex items-center justify-between">
                                        <div class="space-y-0.5">
                                            <FormLabel>Attendance Required</FormLabel>
                                            <p class="text-muted-foreground text-xs">Whether attendance is mandatory for this session</p>
                                        </div>
                                        <FormControl>
                                            <Switch v-bind="componentField" />
                                        </FormControl>
                                    </div>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="attendance_tracking_enabled">
                                <FormItem>
                                    <div class="flex items-center justify-between">
                                        <div class="space-y-0.5">
                                            <FormLabel>Enable Tracking</FormLabel>
                                            <p class="text-muted-foreground text-xs">Enable automatic attendance tracking</p>
                                        </div>
                                        <FormControl>
                                            <Switch v-bind="componentField" />
                                        </FormControl>
                                    </div>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </CardContent>
                    </Card>

                    <!-- Actions -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <Button type="submit" class="w-full" :disabled="isSubmitting">
                                <Save class="mr-2 h-4 w-4" />
                                {{ isSubmitting ? 'Creating...' : 'Create Session' }}
                            </Button>

                            <Button type="button" variant="outline" class="w-full" @click="navigateBack" :disabled="isSubmitting">
                                <X class="mr-2 h-4 w-4" />
                                Cancel
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    </div>
</template>
