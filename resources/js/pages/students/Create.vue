<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Campus, CurriculumVersion, Program, Specialization } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Save, User } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    campuses: Campus[];
    programs: (Program & { specializations?: Specialization[] })[];
}

const props = defineProps<Props>();

// Define validation schema following Laravel validation rules
const formSchema = toTypedSchema(
    z.object({
        full_name: z.string().min(1, 'Full name is required').max(100, 'Full name too long'),
        email: z.string().min(1, 'Email is required').email('Invalid email format').max(255, 'Email too long'),
        phone: z.string().min(1, 'Phone is required').max(20, 'Phone number too long'),
        date_of_birth: z.string().min(1, 'Date of birth is required'),
        gender: z.enum(['male', 'female', 'other'], { required_error: 'Gender is required' }),
        nationality: z.string().min(1, 'Nationality is required').max(100, 'Nationality too long'),
        national_id: z.string().min(1, 'National ID is required').max(20, 'National ID too long'),
        address: z.string().min(1, 'Address is required'),
        campus_id: z.string().min(1, 'Campus is required'),
        program_id: z.string().min(1, 'Program is required'),
        specialization_id: z.string().min(1, 'Specialization is required'),
        curriculum_version_id: z.string().min(1, 'Curriculum version is required'),
        admission_date: z.string().min(1, 'Admission date is required'),
        parent_guardian_name: z.string().min(1, 'Parent/Guardian name is required').max(255, 'Parent/Guardian name too long'),
        parent_guardian_phone: z.string().min(1, 'Parent/Guardian phone is required').max(20, 'Parent/Guardian phone too long'),
        parent_guardian_email: z
            .string()
            .min(1, 'Parent/Guardian email is required')
            .email('Invalid parent/guardian email')
            .max(255, 'Parent/Guardian email too long'),
        emergency_contact_name: z.string().min(1, 'Emergency contact name is required').max(255, 'Emergency contact name too long'),
        emergency_contact_phone: z.string().min(1, 'Emergency contact phone is required').max(20, 'Emergency contact phone too long'),
        high_school_name: z.string().min(1, 'High school name is required').max(255, 'High school name too long'),
        high_school_graduation_year: z.string().min(1, 'High school graduation year is required'),
        // entrance_exam_score: z.string().min(1, 'Entrance exam score is required'),
        admission_notes: z.string().optional(),
        status: z.enum(['active', 'inactive', 'suspended']).default('active'),
    }),
);

// Reactive states for dependent selects
const curriculumVersions = ref<CurriculumVersion[]>([]);
const loadingCurriculumVersions = ref(false);

// Form setup following development standards
const { handleSubmit, isSubmitting, values, setFieldValue } = useForm({
    validationSchema: formSchema,
    initialValues: {
        full_name: 'hieu pham',
        email: 'hieupham@gmail.com',
        phone: '0901234567',
        date_of_birth: '2000-01-01',
        gender: 'male',
        nationality: 'Việt Nam',
        national_id: '1234567890',
        address: '1234567890',
        campus_id: '',
        program_id: '',
        specialization_id: '',
        curriculum_version_id: '',
        admission_date: new Date().toISOString().split('T')[0],
        parent_guardian_name: 'parent',
        parent_guardian_phone: '0901234567',
        parent_guardian_email: 'parent@gmail.com',
        emergency_contact_name: 'emergency',
        emergency_contact_phone: '0901234567',
        high_school_name: 'high school',
        high_school_graduation_year: '2020',
        // entrance_exam_score: '',
        admission_notes: '',
        status: 'active' as const,
    },
});

// Computed specializations filtered by selected program
const filteredSpecializations = computed(() => {
    if (!values.program_id) return [];
    const selectedProgram = props.programs.find((p) => p.id.toString() === values.program_id);
    return selectedProgram?.specializations || [];
});

// Watch for program changes to reset dependent fields
watch(
    () => values.program_id,
    (newProgramId, oldProgramId) => {
        if (newProgramId !== oldProgramId) {
            setFieldValue('specialization_id', '');
            setFieldValue('curriculum_version_id', '');
            curriculumVersions.value = [];
        }
    },
);

// Watch for specialization changes to fetch curriculum versions
watch(
    () => values.specialization_id,
    (newSpecializationId, oldSpecializationId) => {
        if (newSpecializationId !== oldSpecializationId) {
            setFieldValue('curriculum_version_id', '');
            fetchCurriculumVersions();
        }
    },
);

// Fetch curriculum versions based on program and specialization
const fetchCurriculumVersions = async () => {
    if (!values.program_id || !values.specialization_id) {
        curriculumVersions.value = [];
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
        curriculumVersions.value = data;

        // Auto-select curriculum version if only one exists
        if (data.length === 1) {
            setFieldValue('curriculum_version_id', data[0].id.toString());
        }
    } catch (error) {
        console.error('Error fetching curriculum versions:', error);
        toast.error('Failed to load curriculum versions');
    } finally {
        loadingCurriculumVersions.value = false;
    }
};

// Submit form
const onSubmit = handleSubmit((values) => {
    const formData = {
        ...values,
        campus_id: parseInt(values.campus_id),
        program_id: parseInt(values.program_id),
        specialization_id: parseInt(values.specialization_id),
        curriculum_version_id: parseInt(values.curriculum_version_id),
        high_school_graduation_year: values.high_school_graduation_year ? parseInt(values.high_school_graduation_year) : null,
        // entrance_exam_score: values.entrance_exam_score ? parseFloat(values.entrance_exam_score) : null,
    };

    router.post('/students', formData, {
        onSuccess: () => {
            toast.success('Student created successfully');
        },
        onError: (errors) => {
            toast.error('Failed to create student');
            console.error('Validation errors:', errors);
        },
    });
});

// Generate years for high school graduation
const graduationYears = computed(() => {
    const currentYear = new Date().getFullYear();
    const years = [];
    for (let year = currentYear + 1; year >= 1990; year--) {
        years.push(year);
    }
    return years;
});
</script>

<template>
    <Head title="Create Student" />

    <AppLayout>
        <div class="space-y-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <Button as-child variant="outline" size="icon">
                        <Link :href="route('students.index')">
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 class="flex items-center space-x-2 text-2xl font-semibold text-gray-900">
                            <User class="h-6 w-6" />
                            <span>Create Student</span>
                        </h1>
                        <p class="mt-1 text-sm text-gray-600">Add a new student to the system</p>
                    </div>
                </div>
            </div>

            <form @submit="onSubmit" class="space-y-6">
                <!-- Personal Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Personal Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
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
                                    <FormLabel>Email *</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" type="email" placeholder="student@university.edu" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="phone">
                                <FormItem>
                                    <FormLabel>Phone *</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" placeholder="+84901234567" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField v-slot="{ componentField }" name="date_of_birth">
                                <FormItem>
                                    <FormLabel>Date of Birth *</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" type="date" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

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
                                        <Input v-bind="componentField" placeholder="e.g., Việt Nam" disabled />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                                    <Textarea v-bind="componentField" placeholder="Enter full address" rows="3" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </CardContent>
                </Card>

                <!-- Academic Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Academic Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                                    <FormLabel>Specialization *</FormLabel>
                                    <Select v-bind="componentField" :disabled="!values.program_id">
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
                                    <Select v-bind="componentField" :disabled="!values.specialization_id || loadingCurriculumVersions">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select curriculum version" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem v-for="version in curriculumVersions" :key="version.id" :value="version.id.toString()">
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
                        </div>
                    </CardContent>
                </Card>

                <!-- Background Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Background Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField v-slot="{ componentField }" name="high_school_name">
                                <FormItem>
                                    <FormLabel>High School Name *</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" placeholder="Enter high school name" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="high_school_graduation_year">
                                <FormItem>
                                    <FormLabel>High School Graduation Year *</FormLabel>
                                    <Select v-bind="componentField">
                                        <FormControl>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select year" />
                                            </SelectTrigger>
                                        </FormControl>
                                        <SelectContent>
                                            <SelectItem v-for="year in graduationYears" :key="year" :value="year.toString()">
                                                {{ year }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <!-- <FormField v-slot="{ componentField }" name="entrance_exam_score">
                            <FormItem>
                                <FormLabel>Entrance Exam Score (0-100) *</FormLabel>
                                <FormControl>
                                    <NumberField
                                        :model-value="
                                            typeof componentField.modelValue === 'string'
                                                ? parseFloat(componentField.modelValue) || 0
                                                : componentField.modelValue
                                        "
                                        @update:model-value="componentField['onUpdate:modelValue']"
                                        :step="0.1"
                                        :format-options="{
                                            minimumFractionDigits: 0,
                                            maximumFractionDigits: 2,
                                        }"
                                    >
                                        <NumberFieldContent>
                                            <NumberFieldInput />
                                        </NumberFieldContent>
                                    </NumberField>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField> -->
                    </CardContent>
                </Card>

                <!-- Contact Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Emergency & Parent Contact</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField v-slot="{ componentField }" name="parent_guardian_name">
                                <FormItem>
                                    <FormLabel>Parent/Guardian Name *</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" placeholder="Enter parent/guardian name" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="parent_guardian_phone">
                                <FormItem>
                                    <FormLabel>Parent/Guardian Phone *</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" placeholder="+84901234567" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="parent_guardian_email">
                                <FormItem>
                                    <FormLabel>Parent/Guardian Email *</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" type="email" placeholder="parent@example.com" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField v-slot="{ componentField }" name="emergency_contact_name">
                                <FormItem>
                                    <FormLabel>Emergency Contact Name *</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" placeholder="Enter emergency contact name" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>

                            <FormField v-slot="{ componentField }" name="emergency_contact_phone">
                                <FormItem>
                                    <FormLabel>Emergency Contact Phone *</FormLabel>
                                    <FormControl>
                                        <Input v-bind="componentField" placeholder="+84901234567" />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            </FormField>
                        </div>
                    </CardContent>
                </Card>

                <!-- Additional Notes -->
                <Card>
                    <CardHeader>
                        <CardTitle>Additional Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <FormField v-slot="{ componentField }" name="admission_notes">
                            <FormItem>
                                <FormLabel>Admission Notes</FormLabel>
                                <FormControl>
                                    <Textarea v-bind="componentField" placeholder="Any additional notes about the student's admission..." rows="4" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="status">
                            <FormItem>
                                <FormLabel>Student Status</FormLabel>
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
                                    </SelectContent>
                                </Select>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex justify-end space-x-4">
                    <Button as-child type="button" variant="outline">
                        <Link :href="route('students.index')"> Cancel </Link>
                    </Button>
                    <Button type="submit" :disabled="isSubmitting" class="min-w-32">
                        <Save class="mr-2 h-4 w-4" />
                        {{ isSubmitting ? 'Creating...' : 'Create Student' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
