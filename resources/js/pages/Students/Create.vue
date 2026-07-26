<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { NumberInput } from '@/components/ui/number-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { CurriculumVersion, Program, Specialization } from '@/types/models';
import { studentRoutes } from '@/utils/routes';
import { Head, router, useForm as useInertiaForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, GraduationCap, Phone, Save, User } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    programs: (Program & { specializations?: Specialization[] })[];
    specializations: Specialization[];
    curriculumVersions: CurriculumVersion[];
    current_campus_id: number;
}

const props = defineProps<Props>();

// Form validation schema
const createStudentSchema = toTypedSchema(
    z.object({
        full_name: z.string().min(1, 'Full name is required').max(100, 'Full name cannot exceed 100 characters'),
        email: z.string().email('Please provide a valid email address').max(255, 'Email cannot exceed 255 characters'),
        phone: z.string().min(1, 'Phone number is required').max(20, 'Phone number cannot exceed 20 characters'),
        date_of_birth: z.string().min(1, 'Date of birth is required'),
        gender: z.enum(['male', 'female', 'other'], {
            errorMap: () => ({ message: 'Gender must be male, female, or other' }),
        }),
        nationality: z.string().min(1, 'Nationality is required').max(100, 'Nationality cannot exceed 100 characters'),
        national_id: z.string().min(1, 'National ID is required').max(20, 'National ID cannot exceed 20 characters'),
        address: z.string().min(1, 'Address is required'),
        program_id: z.string().min(1, 'Program selection is required'),
        curriculum_version_id: z.string().min(1, 'Curriculum version is required'),
        admission_date: z
            .string()
            .min(1, 'Admission date is required')
            .refine((date) => {
                const admissionDate = new Date(date);
                const today = new Date();
                today.setHours(23, 59, 59, 999); // Set to end of day for comparison
                return admissionDate <= today;
            }, 'Admission date cannot be in the future'),
        emergency_contact_name: z.string().max(255, 'Emergency contact name cannot exceed 255 characters').optional().or(z.literal('')),
        emergency_contact_phone: z.string().max(20, 'Emergency contact phone cannot exceed 20 characters').optional().or(z.literal('')),
        emergency_contact_relationship: z.string().max(100, 'Emergency contact relationship cannot exceed 100 characters').optional().or(z.literal('')),
        high_school_name: z.string().min(1, 'High school name is required').max(255, 'High school name cannot exceed 255 characters'),
        high_school_graduation_year: z
            .number()
            .min(1, 'High school graduation year is required')
            .refine((year) => {
                const currentYear = new Date().getFullYear();
                return year >= 1900 && year <= currentYear + 1;
            }, 'High school graduation year must be between 1900 and next year'),
        entrance_exam_score: z
            .string()
            .optional()
            .or(z.literal(''))
            .refine((score) => {
                if (!score || score === '') return true;
                const scoreNum = parseFloat(score);
                return scoreNum >= 0 && scoreNum <= 100;
            }, 'Entrance exam score must be between 0 and 100'),
        admission_notes: z.string().optional().or(z.literal('')),
    }),
);

// Form validation with vee-validate
const { handleSubmit, isSubmitting, values, setFieldValue } = useForm({
    validationSchema: createStudentSchema,
    initialValues: {
        full_name: '',
        email: '',
        phone: '',
        date_of_birth: '',
        gender: undefined,
        nationality: 'Vietnamese',
        national_id: '',
        address: '',
        program_id: '',
        curriculum_version_id: '',
        admission_date: new Date().toISOString().split('T')[0],
        emergency_contact_name: '',
        emergency_contact_phone: '',
        emergency_contact_relationship: '',
        high_school_name: '',
        high_school_graduation_year: 2025,
        entrance_exam_score: '',
        admission_notes: '',
    },
});

// Inertia form for submission
const createInertiaForm = useInertiaForm({
    full_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    gender: '',
    nationality: 'Vietnamese',
    national_id: '',
    address: '',
    campus_id: '',
    program_id: '',
    curriculum_version_id: '',
    admission_date: new Date().toISOString().split('T')[0],
    emergency_contact_name: '',
    emergency_contact_phone: '',
    emergency_contact_relationship: '',
    high_school_name: '',
    high_school_graduation_year: 2025,
    entrance_exam_score: '',
    admission_notes: '',
});

// Computed curriculum versions filtered by program and specialization
const availableCurriculumVersions = computed(() => {
    if (!values.program_id) {
        return [];
    }
    const selectedProgramId = parseInt(values.program_id);

    return props.curriculumVersions.filter((cv) => {
        if (cv.program_id !== selectedProgramId) {
            return false;
        }
        return cv.specialization_id === null;
    });
});

// Watch for program changes to reset dependent fields
watch(
    () => values.program_id,
    (newProgramId, oldProgramId) => {
        if (newProgramId !== oldProgramId) {
            setFieldValue('curriculum_version_id', '');
        }
    },
);

// Watch available curriculum versions to auto-select if only one is available
watch(availableCurriculumVersions, (newVersions) => {
    const currentVersionId = values.curriculum_version_id;

    if (newVersions.length === 1) {
        setFieldValue('curriculum_version_id', newVersions[0].id.toString());
    } else if (currentVersionId && !newVersions.find((v) => v.id.toString() === currentVersionId)) {
        // Clear selection if it's no longer in the available list
        setFieldValue('curriculum_version_id', '');
    }
});

// Form submission handler
const onCreateSubmit = handleSubmit(async (formValues: any) => {
    // Convert string values back to appropriate types for backend
    const submitData = {
        ...formValues,
        program_id: parseInt(formValues.program_id),
        curriculum_version_id: parseInt(formValues.curriculum_version_id),
        high_school_graduation_year: formValues.high_school_graduation_year,
        entrance_exam_score: formValues.entrance_exam_score ? parseFloat(formValues.entrance_exam_score) : null,
        // Keep required fields as is, only convert optional fields to null if empty
        phone: formValues.phone || null,
        date_of_birth: formValues.date_of_birth,
        gender: formValues.gender,
        nationality: formValues.nationality,
        national_id: formValues.national_id,
        address: formValues.address,
        emergency_contact_name: formValues.emergency_contact_name || null,
        emergency_contact_phone: formValues.emergency_contact_phone || null,
        emergency_contact_relationship: formValues.emergency_contact_relationship || null,
        high_school_name: formValues.high_school_name,
        admission_notes: formValues.admission_notes || null,
        campus_id: props.current_campus_id,
    };

    // Update Inertia form with all submit data
    Object.assign(createInertiaForm, submitData);

    createInertiaForm.post(studentRoutes.store(), {
        onSuccess: () => {
            console.log('Student created successfully');
            toast.success('Student created successfully');
            router.visit(studentRoutes.list());
        },
        onError: (errors) => {
            console.error('Form submission errors:', errors);
            toast.error('Failed to create student', {
                description: JSON.stringify(errors),
            });
        },
        onFinish: () => {
            console.log('Form submission finished');
        },
    });
});
</script>

<template>
    <Head title="Create Student" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Create New Student</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Add a new student to the system</p>
            </div>
            <Button variant="outline" @click="router.visit(studentRoutes.list())">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to Students
            </Button>
        </div>

        <!-- Main Form -->
        <form @submit="onCreateSubmit" class="space-y-6">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Basic Information -->
                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center">
                                <User class="mr-2 h-5 w-5" />
                                Basic Information
                            </CardTitle>
                            <CardDescription>Student's personal details</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <FormField v-slot="{ componentField }" name="full_name">
                                    <FormItem>
                                        <FormLabel>Full Name *</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" placeholder="Enter full name" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <FormField v-slot="{ componentField }" name="email">
                                    <FormItem>
                                        <FormLabel>Email *</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" type="email" placeholder="Enter email" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <FormField v-slot="{ componentField }" name="phone">
                                    <FormItem>
                                        <FormLabel>Phone</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" placeholder="Enter phone number" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <FormField v-slot="{ componentField }" name="date_of_birth">
                                    <FormItem>
                                        <FormLabel>Date of Birth *</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" type="date" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <FormField v-slot="{ componentField }" name="gender">
                                    <FormItem>
                                        <FormLabel>Gender *</FormLabel>
                                        <Select v-bind="componentField">
                                            <FormControl>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select gender" />
                                                </SelectTrigger>
                                            </FormControl>
                                            <SelectContent>
                                                <SelectItem value="male">Male</SelectItem>
                                                <SelectItem value="female">Female</SelectItem>
                                                <SelectItem value="other">Other</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <FormField v-slot="{ componentField }" name="nationality">
                                    <FormItem>
                                        <FormLabel>Nationality *</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" placeholder="Enter nationality" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <FormField v-slot="{ componentField }" name="national_id">
                                    <FormItem>
                                        <FormLabel>National ID *</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" placeholder="Enter national ID" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>

                            <FormField v-slot="{ componentField }" name="address">
                                <FormItem>
                                    <FormLabel>Address *</FormLabel>
                                    <FormControl>
                                        <Textarea v-bind="componentField" placeholder="Enter address" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </CardContent>
                    </Card>
                </div>

                <!-- Academic Information -->
                <div>
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center">
                                <GraduationCap class="mr-2 h-5 w-5" />
                                Academic Assignment
                            </CardTitle>
                            <CardDescription>Program and campus details</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <FormField v-slot="{ componentField }" name="program_id">
                                <FormItem>
                                    <FormLabel>Program *</FormLabel>
                                    <Select v-bind="componentField">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select program" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem v-for="program in programs" :key="program.id" :value="program.id.toString()">
                                                {{ program.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="curriculum_version_id">
                                <FormItem>
                                    <FormLabel>Curriculum Version *</FormLabel>
                                    <Select v-bind="componentField" :disabled="!availableCurriculumVersions.length">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select curriculum version" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem v-for="version in availableCurriculumVersions" :key="version.id" :value="version.id.toString()">
                                                {{ version.version_code }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <div class="grid grid-cols-1 gap-4">
                                <FormField v-slot="{ componentField }" name="admission_date">
                                    <FormItem>
                                        <FormLabel>Admission Date *</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" type="date" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Emergency Contact -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center">
                            <Phone class="mr-2 h-5 w-5" />
                            Emergency Contact
                        </CardTitle>
                        <CardDescription>Emergency contact information</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <FormField v-slot="{ componentField }" name="emergency_contact_name">
                            <FormItem>
                                <FormLabel>Contact Name</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter contact name" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField v-slot="{ componentField }" name="emergency_contact_phone">
                                <FormItem>
                                    <FormLabel>Contact Phone</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" placeholder="Enter contact phone" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="emergency_contact_relationship">
                                <FormItem>
                                    <FormLabel>Relationship</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" placeholder="e.g., Parent, Guardian" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>
                    </CardContent>
                </Card>

                <!-- Academic Background -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center">
                            <GraduationCap class="mr-2 h-5 w-5" />
                            Academic Background
                        </CardTitle>
                        <CardDescription>Previous education details</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <FormField v-slot="{ componentField }" name="high_school_name">
                            <FormItem>
                                <FormLabel>High School Name *</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter high school name" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField v-slot="{ componentField }" name="high_school_graduation_year">
                                <FormItem>
                                    <FormLabel>Graduation Year *</FormLabel>
                                    <FormControl>
                                        <NumberInput
                                            :model-value="componentField.modelValue"
                                            @update:model-value="componentField['onUpdate:modelValue']"
                                            :min="2000"
                                            :max="new Date().getFullYear() + 1"
                                            :allow-decimal="false"
                                            :allow-negative="false"
                                            placeholder="Enter year"
                                            :name="componentField.name"
                                            @blur="componentField.onBlur"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="entrance_exam_score">
                                <FormItem>
                                    <FormLabel>Entrance Exam Score</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" type="number" placeholder="Enter score" min="0" max="100" step="0.01" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <FormField v-slot="{ componentField }" name="admission_notes">
                            <FormItem>
                                <FormLabel>Admission Notes</FormLabel>
                                <FormControl>
                                    <Textarea v-bind="componentField" placeholder="Enter any additional notes" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </CardContent>
                </Card>
            </div>

            <!-- Form Actions -->
            <div class="mt-6 flex justify-end space-x-4">
                <Button type="button" variant="outline" @click="router.visit(studentRoutes.list())"> Cancel </Button>
                <Button type="submit" :disabled="isSubmitting || createInertiaForm.processing">
                    <Save class="mr-2 h-4 w-4" />
                    {{ isSubmitting || createInertiaForm.processing ? 'Creating...' : 'Create Student' }}
                </Button>
            </div>
        </form>
    </div>
</template>
