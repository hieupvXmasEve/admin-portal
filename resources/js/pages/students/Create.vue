<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Campus, CurriculumVersion, Program, Specialization } from '@/types/models';
import { studentRoutes } from '@/utils/routes';
import { Head, router, useForm as useInertiaForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, GraduationCap, Phone, Save, User } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref, watch } from 'vue';
import { z } from 'zod';

interface Props {
    campuses: Campus[];
    programs: (Program & { specializations?: Specialization[] })[];
}

const props = defineProps<Props>();

// Form validation schema
const createStudentSchema = toTypedSchema(
    z.object({
        full_name: z.string().min(1, 'Full name is required').max(100, 'Full name is too long'),
        email: z.string().email('Invalid email format').max(255, 'Email is too long'),
        phone: z.string().max(20, 'Phone number is too long').optional(),
        date_of_birth: z.string().optional(),
        gender: z.enum(['male', 'female', 'other']).optional(),
        nationality: z.string().max(100, 'Nationality is too long').optional(),
        national_id: z.string().max(20, 'National ID is too long').optional(),
        address: z.string().optional(),
        campus_id: z.string().min(1, 'Campus is required'),
        program_id: z.string().min(1, 'Program is required'),
        specialization_id: z.string().optional(),
        curriculum_version_id: z.string().min(1, 'Curriculum version is required'),
        admission_date: z.string().min(1, 'Admission date is required'),
        expected_graduation_date: z.string().optional(),
        emergency_contact_name: z.string().max(255, 'Emergency contact name is too long').optional(),
        emergency_contact_phone: z.string().max(20, 'Emergency contact phone is too long').optional(),
        emergency_contact_relationship: z.string().max(100, 'Emergency contact relationship is too long').optional(),
        high_school_name: z.string().max(255, 'High school name is too long').optional(),
        high_school_graduation_year: z.string().optional(),
        entrance_exam_score: z.string().optional(),
        admission_notes: z.string().optional(),
    }),
);

// Inertia form for submission
const createInertiaForm = useInertiaForm({
    full_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    gender: undefined,
    nationality: 'Vietnamese',
    national_id: '',
    address: '',
    campus_id: '',
    program_id: '',
    specialization_id: '',
    curriculum_version_id: '',
    admission_date: '',
    expected_graduation_date: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    emergency_contact_relationship: '',
    high_school_name: '',
    high_school_graduation_year: '',
    entrance_exam_score: '',
    admission_notes: '',
});

// Form validation
const { handleSubmit, isSubmitting, setFieldValue, values } = useForm({
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
        campus_id: '',
        program_id: '',
        specialization_id: '',
        curriculum_version_id: '',
        admission_date: '',
        expected_graduation_date: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        emergency_contact_relationship: '',
        high_school_name: '',
        high_school_graduation_year: '',
        entrance_exam_score: '',
        admission_notes: '',
    },
});

// Reactive data for dependent dropdowns
const availableCurriculumVersions = ref<CurriculumVersion[]>([]);
const loadingCurriculumVersions = ref(false);

// Computed specializations filtered by selected program
const filteredSpecializations = computed(() => {
    if (!values.program_id) return [];
    const selectedProgram = props.programs.find((p) => p.id.toString() === values.program_id);
    return selectedProgram?.specializations || [];
});

// Fetch curriculum versions based on program and specialization
const fetchCurriculumVersions = async () => {
    if (!values.program_id) {
        availableCurriculumVersions.value = [];
        return;
    }

    loadingCurriculumVersions.value = true;

    try {
        const params = new URLSearchParams({
            program_id: values.program_id,
            ...(values.specialization_id && { specialization_id: values.specialization_id }),
        });

        const response = await fetch(`/api/curriculum-versions/by-program-specialization?${params}`);
        const data = await response.json();
        availableCurriculumVersions.value = data;

        // Auto-select curriculum version if only one exists
        if (data.length === 1) {
            setFieldValue('curriculum_version_id', data[0].id.toString());
        }
    } catch (error) {
        console.error('Error fetching curriculum versions:', error);
    } finally {
        loadingCurriculumVersions.value = false;
    }
};

// Watch for program changes to reset dependent fields
watch(
    () => values.program_id,
    (newProgramId, oldProgramId) => {
        if (newProgramId !== oldProgramId) {
            setFieldValue('specialization_id', '');
            setFieldValue('curriculum_version_id', '');
            availableCurriculumVersions.value = [];
            if (newProgramId) {
                fetchCurriculumVersions();
            }
        }
    },
);

// Watch for specialization changes to fetch curriculum versions
watch(
    () => values.specialization_id,
    () => {
        setFieldValue('curriculum_version_id', '');
        fetchCurriculumVersions();
    },
);

const onCreateSubmit = handleSubmit((formData) => {
    // Convert string values back to appropriate types
    const submitData = {
        ...formData,
        campus_id: parseInt(formData.campus_id),
        program_id: parseInt(formData.program_id),
        specialization_id: formData.specialization_id ? parseInt(formData.specialization_id) : null,
        curriculum_version_id: parseInt(formData.curriculum_version_id),
        high_school_graduation_year: formData.high_school_graduation_year ? parseInt(formData.high_school_graduation_year) : null,
        entrance_exam_score: formData.entrance_exam_score ? parseFloat(formData.entrance_exam_score) : null,
        // Remove empty strings
        phone: formData.phone || null,
        date_of_birth: formData.date_of_birth || null,
        gender: formData.gender || null,
        nationality: formData.nationality || null,
        national_id: formData.national_id || null,
        address: formData.address || null,
        expected_graduation_date: formData.expected_graduation_date || null,
        emergency_contact_name: formData.emergency_contact_name || null,
        emergency_contact_phone: formData.emergency_contact_phone || null,
        emergency_contact_relationship: formData.emergency_contact_relationship || null,
        high_school_name: formData.high_school_name || null,
        admission_notes: formData.admission_notes || null,
    };

    // Update Inertia form data
    Object.assign(createInertiaForm, submitData);

    createInertiaForm.post(route('students.store'), {
        onSuccess: () => {
            router.visit(studentRoutes.list());
        },
        onError: (errors) => {
            console.error('Form submission errors:', errors);
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
        <Form :validation-schema="createStudentSchema" @submit="onCreateSubmit">
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
                                        <FormLabel>Date of Birth</FormLabel>
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
                                        <FormLabel>Gender</FormLabel>
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
                                        <FormLabel>Nationality</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" placeholder="Enter nationality" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <FormField v-slot="{ componentField }" name="national_id">
                                    <FormItem>
                                        <FormLabel>National ID</FormLabel>
                                        <FormControl>
                                            <Input v-bind="componentField" placeholder="Enter national ID" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>

                            <FormField v-slot="{ componentField }" name="address">
                                <FormItem>
                                    <FormLabel>Address</FormLabel>
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
                            <FormField v-slot="{ componentField }" name="campus_id">
                                <FormItem>
                                    <FormLabel>Campus *</FormLabel>
                                    <Select v-bind="componentField">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select campus" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">
                                                {{ campus.name }} ({{ campus.code }})
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

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

                            <FormField v-slot="{ componentField }" name="specialization_id">
                                <FormItem>
                                    <FormLabel>Specialization</FormLabel>
                                    <Select v-bind="componentField" :disabled="!filteredSpecializations.length">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select specialization" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value="">No specialization</SelectItem>
                                            <SelectItem
                                                v-for="specialization in filteredSpecializations"
                                                :key="specialization.id"
                                                :value="specialization.id.toString()"
                                            >
                                                {{ specialization.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="curriculum_version_id">
                                <FormItem>
                                    <FormLabel>Curriculum Version *</FormLabel>
                                    <Select v-bind="componentField" :disabled="loadingCurriculumVersions || !availableCurriculumVersions.length">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select curriculum version" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="version in availableCurriculumVersions"
                                                :key="version.id"
                                                :value="version.id.toString()"
                                            >
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

                                <FormField v-slot="{ componentField }" name="expected_graduation_date">
                                    <FormItem>
                                        <FormLabel>Expected Graduation Date</FormLabel>
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
                                <FormLabel>High School Name</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter high school name" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField v-slot="{ componentField }" name="high_school_graduation_year">
                                <FormItem>
                                    <FormLabel>Graduation Year</FormLabel>
                                    <FormControl>
                                        <Input
                                            v-bind="componentField"
                                            type="number"
                                            placeholder="Enter year"
                                            min="1900"
                                            :max="new Date().getFullYear() + 1"
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
            <div class="flex justify-end space-x-4">
                <Button type="button" variant="outline" @click="router.visit(studentRoutes.list())"> Cancel </Button>
                <Button type="submit" :disabled="isSubmitting || createInertiaForm.processing">
                    <Save class="mr-2 h-4 w-4" />
                    Create Student
                </Button>
            </div>
        </Form>
    </div>
</template>
