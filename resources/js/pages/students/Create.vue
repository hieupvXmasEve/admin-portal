<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { CurriculumVersion, Program, Specialization } from '@/types/models';
import { studentRoutes } from '@/utils/routes';
import { Head, router, useForm as useInertiaForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, GraduationCap, Phone, Save, User } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, watch } from 'vue';
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
        full_name: z.string().min(1, 'Full name is required').max(100, 'Full name is too long'),
        email: z.string().email('Invalid email format').max(255, 'Email is too long'),
        phone: z.string().max(20, 'Phone number is too long').optional(),
        date_of_birth: z.string().optional(),
        gender: z.enum(['male', 'female', 'other']).optional(),
        nationality: z.string().max(100, 'Nationality is too long').optional(),
        national_id: z.string().max(20, 'National ID is too long').optional(),
        address: z.string().optional(),
        program_id: z.string().min(1, 'Program is required'),
        specialization_id: z.string().min(1, 'Specialization is required'),
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

// Form validation
const { handleSubmit, isSubmitting, values, defineField, setFieldValue } = useForm({
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

const [fullName, fullNameAttrs] = defineField('full_name');
const [email, emailAttrs] = defineField('email');
const [phone, phoneAttrs] = defineField('phone');
const [dateOfBirth, dateOfBirthAttrs] = defineField('date_of_birth');
const [gender, genderAttrs] = defineField('gender');
const [nationality, nationalityAttrs] = defineField('nationality');
const [nationalId, nationalIdAttrs] = defineField('national_id');
const [address, addressAttrs] = defineField('address');
const [programId, programIdAttrs] = defineField('program_id');
const [specializationId, specializationIdAttrs] = defineField('specialization_id');
const [curriculumVersionId, curriculumVersionIdAttrs] = defineField('curriculum_version_id');
const [admissionDate, admissionDateAttrs] = defineField('admission_date');
const [expectedGraduationDate, expectedGraduationDateAttrs] = defineField('expected_graduation_date');
const [emergencyContactName, emergencyContactNameAttrs] = defineField('emergency_contact_name');
const [emergencyContactPhone, emergencyContactPhoneAttrs] = defineField('emergency_contact_phone');
const [emergencyContactRelationship, emergencyContactRelationshipAttrs] = defineField('emergency_contact_relationship');
const [highSchoolName, highSchoolNameAttrs] = defineField('high_school_name');
const [highSchoolGraduationYear, highSchoolGraduationYearAttrs] = defineField('high_school_graduation_year');
const [entranceExamScore, entranceExamScoreAttrs] = defineField('entrance_exam_score');
const [admissionNotes, admissionNotesAttrs] = defineField('admission_notes');

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

// Computed specializations filtered by selected program
const filteredSpecializations = computed(() => {
    if (!values.program_id) {
        return [];
    }
    const selectedProgram = props.programs.find((p) => p.id.toString() === values.program_id);
    return selectedProgram?.specializations || [];
});

// Computed curriculum versions filtered by program and specialization
const availableCurriculumVersions = computed(() => {
    if (!values.program_id) {
        return [];
    }
    const selectedProgramId = parseInt(values.program_id);
    const selectedSpecializationId = values.specialization_id && values.specialization_id !== 'none' ? parseInt(values.specialization_id) : null;

    return props.curriculumVersions.filter((cv) => {
        if (cv.program_id !== selectedProgramId) {
            return false;
        }
        if (selectedSpecializationId) {
            return cv.specialization_id === selectedSpecializationId;
        }
        return cv.specialization_id === null;
    });
});

// Watch for program changes to reset dependent fields
watch(
    () => values.program_id,
    (newProgramId, oldProgramId) => {
        if (newProgramId !== oldProgramId) {
            setFieldValue('specialization_id', '');
            setFieldValue('curriculum_version_id', '');
        }
    },
);

// Watch for specialization changes to fetch curriculum versions
watch(
    () => values.specialization_id,
    (newVal, oldVal) => {
        if (newVal !== oldVal) {
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

// Fix the form submission handler
const onCreateSubmit = (formValues: any) => {
    // Convert string values back to appropriate types
    const submitData = {
        ...formValues,
        program_id: parseInt(formValues.program_id),
        specialization_id: formValues.specialization_id
            ? formValues.specialization_id === 'none'
                ? null
                : parseInt(formValues.specialization_id)
            : null,
        curriculum_version_id: parseInt(formValues.curriculum_version_id),
        high_school_graduation_year: formValues.high_school_graduation_year ? parseInt(formValues.high_school_graduation_year) : null,
        entrance_exam_score: formValues.entrance_exam_score ? parseFloat(formValues.entrance_exam_score) : null,
        // Remove empty strings
        phone: formValues.phone || null,
        date_of_birth: formValues.date_of_birth || null,
        gender: formValues.gender || null,
        nationality: formValues.nationality || null,
        national_id: formValues.national_id || null,
        address: formValues.address || null,
        expected_graduation_date: formValues.expected_graduation_date || null,
        emergency_contact_name: formValues.emergency_contact_name || null,
        emergency_contact_phone: formValues.emergency_contact_phone || null,
        emergency_contact_relationship: formValues.emergency_contact_relationship || null,
        high_school_name: formValues.high_school_name || null,
        admission_notes: formValues.admission_notes || null,
        campus_id: props.current_campus_id,
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
};
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
        <form @submit.prevent="handleSubmit(onCreateSubmit)">
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
                                            <Input v-model="fullName" v-bind="fullNameAttrs" placeholder="Enter full name" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <FormField v-slot="{ componentField }" name="email">
                                    <FormItem>
                                        <FormLabel>Email *</FormLabel>
                                        <FormControl>
                                            <Input v-model="email" v-bind="emailAttrs" type="email" placeholder="Enter email" />
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
                                            <Input v-model="phone" v-bind="phoneAttrs" placeholder="Enter phone number" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <FormField v-slot="{ componentField }" name="date_of_birth">
                                    <FormItem>
                                        <FormLabel>Date of Birth</FormLabel>
                                        <FormControl>
                                            <Input v-model="dateOfBirth" v-bind="dateOfBirthAttrs" type="date" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <FormField v-slot="{ componentField }" name="gender">
                                    <FormItem>
                                        <FormLabel>Gender</FormLabel>
                                        <Select v-model="gender" v-bind="genderAttrs">
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
                                            <Input v-model="nationality" v-bind="nationalityAttrs" placeholder="Enter nationality" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <FormField v-slot="{ componentField }" name="national_id">
                                    <FormItem>
                                        <FormLabel>National ID</FormLabel>
                                        <FormControl>
                                            <Input v-model="nationalId" v-bind="nationalIdAttrs" placeholder="Enter national ID" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>

                            <FormField v-slot="{ componentField }" name="address">
                                <FormItem>
                                    <FormLabel>Address</FormLabel>
                                    <FormControl>
                                        <Textarea v-model="address" v-bind="addressAttrs" placeholder="Enter address" />
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
                                    <Select v-model="programId" v-bind="programIdAttrs">
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
                                    <Select v-model="specializationId" v-bind="specializationIdAttrs" :disabled="!filteredSpecializations.length">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select specialization" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem value="none">No specialization</SelectItem>
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
                                    <Select
                                        v-model="curriculumVersionId"
                                        v-bind="curriculumVersionIdAttrs"
                                        :disabled="!availableCurriculumVersions.length"
                                    >
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
                                            <Input v-model="admissionDate" v-bind="admissionDateAttrs" type="date" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <FormField v-slot="{ componentField }" name="expected_graduation_date">
                                    <FormItem>
                                        <FormLabel>Expected Graduation Date</FormLabel>
                                        <FormControl>
                                            <Input v-model="expectedGraduationDate" v-bind="expectedGraduationDateAttrs" type="date" />
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
                                    <Input v-model="emergencyContactName" v-bind="emergencyContactNameAttrs" placeholder="Enter contact name" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField v-slot="{ componentField }" name="emergency_contact_phone">
                                <FormItem>
                                    <FormLabel>Contact Phone</FormLabel>
                                    <FormControl>
                                        <Input
                                            v-model="emergencyContactPhone"
                                            v-bind="emergencyContactPhoneAttrs"
                                            placeholder="Enter contact phone"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="emergency_contact_relationship">
                                <FormItem>
                                    <FormLabel>Relationship</FormLabel>
                                    <FormControl>
                                        <Input
                                            v-model="emergencyContactRelationship"
                                            v-bind="emergencyContactRelationshipAttrs"
                                            placeholder="e.g., Parent, Guardian"
                                        />
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
                                    <Input v-model="highSchoolName" v-bind="highSchoolNameAttrs" placeholder="Enter high school name" />
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
                                            v-model="highSchoolGraduationYear"
                                            v-bind="highSchoolGraduationYearAttrs"
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
                                        <Input
                                            v-model="entranceExamScore"
                                            v-bind="entranceExamScoreAttrs"
                                            type="number"
                                            placeholder="Enter score"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                        />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <FormField v-slot="{ componentField }" name="admission_notes">
                            <FormItem>
                                <FormLabel>Admission Notes</FormLabel>
                                <FormControl>
                                    <Textarea v-model="admissionNotes" v-bind="admissionNotesAttrs" placeholder="Enter any additional notes" />
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
                    Create Student
                </Button>
            </div>
        </form>
    </div>
</template>
