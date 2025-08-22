<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import StudentAvatar from '@/components/ui/avatar/StudentAvatar.vue';
import type { Campus, CurriculumVersion, Program, Specialization, Student } from '@/types/models';
import { ValidationRules } from '@/types/validation';
import { studentRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { Building, GraduationCap, Mail, Phone, Save, User, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref, watch } from 'vue';
import { z } from 'zod';

interface Props {
    student: Student;
    campuses: Campus[];
    programs: (Program & { specializations?: Specialization[] })[];
    curriculumVersions: CurriculumVersion[];
}

const props = defineProps<Props>();
console.log('Props', props.student);

// Form validation schema
const formSchema = toTypedSchema(
    z.object({
        full_name: z
            .string()
            .min(ValidationRules.student.firstName.minLength, 'First name is required')
            .max(ValidationRules.student.firstName.maxLength, 'First name is too long'),
        email: z.string().email('Invalid email format').max(ValidationRules.student.email.maxLength, 'Email is too long'),
        phone: z.string().max(ValidationRules.student.phone.maxLength, 'Phone number is too long').optional(),
        date_of_birth: z.string().optional(),
        gender: z.enum(['male', 'female', 'other']).optional(),
        nationality: z.string().max(ValidationRules.student.nationality.maxLength, 'Nationality is too long').optional(),
        national_id: z.string().max(ValidationRules.student.nationalId.maxLength, 'National ID is too long').optional(),
        address: z.string().optional(),
        campus_id: z.string().min(1, 'Campus is required'),
        program_id: z.string().min(1, 'Program is required'),
        specialization_id: z.string().optional(),
        curriculum_version_id: z.string().min(1, 'Curriculum version is required'),
        admission_date: z.string().min(1, 'Admission date is required'),
        expected_graduation_date: z.string().optional(),
        emergency_contact_name: z
            .string()
            .max(ValidationRules.student.emergencyContactName.maxLength, 'Emergency contact name is too long')
            .optional(),
        emergency_contact_phone: z
            .string()
            .max(ValidationRules.student.emergencyContactPhone.maxLength, 'Emergency contact phone is too long')
            .optional(),
        emergency_contact_relationship: z.string().max(100, 'Emergency contact relationship is too long').optional(),
        high_school_name: z.string().max(ValidationRules.student.highSchoolName.maxLength, 'High school name is too long').optional(),
        high_school_graduation_year: z.string().optional(),
        entrance_exam_score: z.string().optional(),
        admission_notes: z.string().optional(),
        status: z.enum(['active', 'inactive', 'suspended', 'graduated', 'admitted', 'dropped_out']),
    }),
);

const { handleSubmit, isSubmitting, setFieldValue, values } = useForm({
    validationSchema: formSchema,
    initialValues: {
        full_name: props.student.full_name,
        email: props.student.email,
        phone: props.student.phone || '',
        date_of_birth: props.student.date_of_birth || '',
        gender: props.student.gender,
        nationality: props.student.nationality || '',
        national_id: props.student.national_id || '',
        address: props.student.address || '',
        campus_id: props.student.campus_id.toString(),
        program_id: props.student.program_id.toString(),
        specialization_id: props.student.specialization_id?.toString() || '',
        curriculum_version_id: props.student.curriculum_version_id.toString(),
        admission_date: props.student.admission_date || '',
        emergency_contact_name: props.student.emergency_contact_name || '',
        emergency_contact_phone: props.student.emergency_contact_phone || '',
        emergency_contact_relationship: props.student.emergency_contact_relationship || '',
        high_school_name: props.student.high_school_name || '',
        high_school_graduation_year: props.student.high_school_graduation_year?.toString() || '',
        entrance_exam_score: props.student.entrance_exam_score?.toString() || '',
        admission_notes: props.student.admission_notes || '',
        status: props.student.status,
    },
});

// Reactive data for dependent dropdowns
const availableCurriculumVersions = ref<CurriculumVersion[]>([]);
const loadingCurriculumVersions = ref(false);

// Computed specializations filtered by selected program (same as Create.vue)
const filteredSpecializations = computed(() => {
    if (!values.program_id) return [];
    const selectedProgram = props.programs.find((p) => p.id.toString() === values.program_id);
    return selectedProgram?.specializations || [];
});

// Fetch curriculum versions based on program and specialization (same as Create.vue)
const fetchCurriculumVersions = async () => {
    if (!values.program_id || !values.specialization_id) {
        availableCurriculumVersions.value = [];
        return;
    }

    loadingCurriculumVersions.value = true;

    try {
        const params = new URLSearchParams({
            program_id: values.program_id,
            specialization_id: values.specialization_id,
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

// Watch for program changes to reset dependent fields (same as Create.vue)
watch(
    () => values.program_id,
    (newProgramId, oldProgramId) => {
        if (newProgramId !== oldProgramId) {
            setFieldValue('specialization_id', '');
            setFieldValue('curriculum_version_id', '');
            availableCurriculumVersions.value = [];
        }
    },
);

// Watch for specialization changes to fetch curriculum versions (same as Create.vue)
watch(
    () => values.specialization_id,
    (newSpecializationId, oldSpecializationId) => {
        if (newSpecializationId !== oldSpecializationId) {
            setFieldValue('curriculum_version_id', '');
            fetchCurriculumVersions();
        }
    },
);

// Initialize curriculum versions on mount if student has program and specialization
if (props.student.program_id && props.student.specialization_id) {
    fetchCurriculumVersions();
}

const onSubmit = handleSubmit((formData) => {
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

    router.put(route('students.update', props.student.id), submitData, {
        onSuccess: () => {
            router.visit(studentRoutes.show(props.student.id));
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
        },
    });
});

const handleCancel = () => {
    router.visit(studentRoutes.show(props.student.id));
};

// Photo handling
const selectedPhoto = ref<File | null>(null);
const photoPreviewUrl = ref<string | null>(null);

const handlePhotoSelected = (file: File, dataUrl: string) => {
    selectedPhoto.value = file;
    photoPreviewUrl.value = dataUrl;
};
</script>

<template>
    <Head title="Edit Student" />
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="bg-primary/10 flex h-16 w-16 items-center justify-center rounded-full">
                <User class="text-primary h-8 w-8" />
            </div>
            <div>
                <h1 class="text-3xl font-bold">Edit Student</h1>
                <p class="text-muted-foreground text-lg">{{ student.full_name }}</p>
            </div>
        </div>
    </div>

    <form @submit="onSubmit" class="space-y-6">
        <!-- Personal Information -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <User class="h-5 w-5" />
                    Personal Information
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Avatar Section -->
                <div class="mb-6">
                    <StudentAvatar
                        :student-id="student.id"
                        :current-avatar="photoPreviewUrl || student.avatar_url"
                        size="xl"
                        @photo-selected="handlePhotoSelected"
                    />
                </div>

                <div class="grid grid-cols-1">
                    <FormField v-slot="{ componentField }" name="full_name">
                        <FormItem>
                            <FormLabel>Full Name *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="Enter full name" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="email">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Mail class="h-4 w-4" />
                                Email *
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="email" placeholder="Enter email address" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="phone">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Phone class="h-4 w-4" />
                                Phone
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="Enter phone number" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <FormField v-slot="{ componentField }" name="date_of_birth">
                        <FormItem>
                            <FormLabel>Date of Birth</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="date" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

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
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="national_id">
                        <FormItem>
                            <FormLabel>National ID</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="Enter national ID" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

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
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="inactive">Inactive</SelectItem>
                                    <SelectItem value="suspended">Suspended</SelectItem>
                                    <SelectItem value="graduated">Graduated</SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <FormField v-slot="{ componentField }" name="address">
                    <FormItem>
                        <FormLabel>Address</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Enter full address" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </CardContent>
        </Card>

        <!-- Academic Information -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <GraduationCap class="h-5 w-5" />
                    Academic Information
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="campus_id">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Building class="h-4 w-4" />
                                Campus *
                            </FormLabel>
                            <Select v-bind="componentField">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select campus" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">
                                        {{ campus.name }}
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
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                                    <SelectItem v-for="version in availableCurriculumVersions" :key="version.id" :value="version.id.toString()">
                                        {{ version.version_code }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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

        <!-- Emergency Contact Information -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Phone class="h-5 w-5" />
                    Emergency Contact
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <FormField v-slot="{ componentField }" name="emergency_contact_name">
                    <FormItem>
                        <FormLabel>Contact Name</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" placeholder="Enter emergency contact name" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="emergency_contact_phone">
                        <FormItem>
                            <FormLabel>Contact Phone</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="Enter emergency contact phone" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="emergency_contact_relationship">
                        <FormItem>
                            <FormLabel>Relationship</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., Parent, Guardian, Spouse" />
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
                <CardTitle class="flex items-center gap-2">
                    <GraduationCap class="h-5 w-5" />
                    Academic Background
                </CardTitle>
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
                                    placeholder="Enter graduation year"
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
                                <Input v-bind="componentField" type="number" placeholder="Enter exam score" min="0" max="100" step="0.01" />
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

        <!-- Form Actions -->
        <div class="flex justify-end space-x-4">
            <Button type="button" variant="outline" @click="handleCancel">
                <X class="mr-2 h-4 w-4" />
                Cancel
            </Button>
            <Button type="submit" :disabled="isSubmitting">
                <Save class="mr-2 h-4 w-4" />
                Update Student
            </Button>
        </div>
    </form>
</template>
