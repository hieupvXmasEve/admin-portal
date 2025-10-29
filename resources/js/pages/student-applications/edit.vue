<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import * as z from 'zod';

interface Campus {
    id: number;
    code: string;
    name: string;
}

interface Program {
    id: number;
    name: string;
    code: string;
}

interface StudentApplication {
    id: number;
    full_name: string;
    student_code: string;
    gender: string | null;
    ethnicity: string | null;
    birth_day: number | null;
    birth_month: number | null;
    birth_year: number | null;
    national_id: string | null;
    phone: string;
    email: string;
    address: string | null;
    health_information: string | null;
    parent_phone: string | null;
    parent_email: string | null;
    campus_code: string;
    intended_program: string | null;
    intended_specialization: string | null;
    intake: string | null;
    exam_date: string | null;
    english_test_type: string | null;
    listening: number | null;
    reading: number | null;
    writing: number | null;
    speaking: number | null;
    overall: number | null;
    submitted_photo: string | null;
    submitted_cccd: string | null;
    submitted_ccta: string | null;
    submitted_tn_translate: string | null;
    submitted_hb_translate: string | null;
    submitted_other: string | null;
    submitted_insurance_card: string | null;
    submitted_exemption_gc: string | null;
    study_link_status: string | null;
    english_qualifications: string | null;
    sut_id: string | null;
    is_international_applicant: boolean;
    exception_units: string | null;
    status: 'pending' | 'reviewed' | 'approved' | 'rejected';
}

interface Props {
    application: StudentApplication;
    campuses: Campus[];
    programs: Program[];
}

const props = defineProps<Props>();
console.log(props.application);
// Validation schema matching Laravel UpdateStudentApplicationRequest
const formSchema = toTypedSchema(
    z.object({
        full_name: z.string().min(1, 'Full name is required').max(255),
        gender: z.enum(['male', 'female', 'other']).nullable(),
        ethnicity: z.string().max(100).nullable(),
        birth_day: z.number().int().min(1, 'Birth day must be between 1 and 31').max(31, 'Birth day must be between 1 and 31').nullable(),
        birth_month: z.number().int().min(1, 'Birth month must be between 1 and 12').max(12, 'Birth month must be between 1 and 12').nullable(),
        birth_year: z
            .number()
            .int()
            .min(1900, 'Birth year must be 1900 or later')
            .max(new Date().getFullYear() + 1, 'Birth year cannot be in the future')
            .nullable(),
        national_id: z.string().max(20).nullable(),
        phone: z.string().min(1, 'Phone number is required').max(20),
        email: z.string().email('Email must be a valid email address').max(255),
        address: z.string().nullable(),
        health_information: z.string().nullable(),
        parent_phone: z.string().max(20).nullable(),
        parent_email: z.string().email('Parent email must be valid').or(z.literal('')).nullable(),
        campus_code: z.string().min(1, 'Campus is required'),
        intended_program: z.string().max(255).nullable(),
        intended_specialization: z.string().max(255).nullable(),
        intake: z.string().max(50).nullable(),
        exam_date: z.string().nullable(),
        english_test_type: z.string().max(50).nullable(),
        listening: z.number().min(0).max(10).nullable(),
        reading: z.number().min(0).max(10).nullable(),
        writing: z.number().min(0).max(10).nullable(),
        speaking: z.number().min(0).max(10).nullable(),
        overall: z.number().min(0).max(10).nullable(),
        submitted_photo: z.string().nullable(),
        submitted_cccd: z.string().nullable(),
        submitted_ccta: z.string().nullable(),
        submitted_tn_translate: z.string().nullable(),
        submitted_hb_translate: z.string().nullable(),
        submitted_other: z.string().nullable(),
        submitted_insurance_card: z.string().nullable(),
        submitted_exemption_gc: z.string().nullable(),
        study_link_status: z.string().max(50).nullable(),
        english_qualifications: z.string().nullable(),
        sut_id: z.string().max(50).nullable(),
        is_international_applicant: z.boolean(),
        exception_units: z.string().nullable(),
        status: z.enum(['pending', 'reviewed', 'approved', 'rejected']),
        student_code: z.string().min(1, 'Student code is required').max(20),
    }),
);

// Initialize form with application data
const { handleSubmit } = useForm({
    validationSchema: formSchema,
    initialValues: {
        full_name: props.application.full_name,
        gender: props.application.gender as 'male' | 'female' | 'other' | null,
        ethnicity: props.application.ethnicity,
        birth_day: props.application.birth_day,
        birth_month: props.application.birth_month,
        birth_year: props.application.birth_year,
        national_id: props.application.national_id,
        phone: props.application.phone,
        email: props.application.email,
        address: props.application.address,
        health_information: props.application.health_information,
        parent_phone: props.application.parent_phone,
        parent_email: props.application.parent_email,
        campus_code: props.application.campus_code,
        intended_program: props.application.intended_program,
        intended_specialization: props.application.intended_specialization,
        intake: props.application.intake,
        exam_date: props.application.exam_date,
        english_test_type: props.application.english_test_type,
        listening: props.application.listening,
        reading: props.application.reading,
        writing: props.application.writing,
        speaking: props.application.speaking,
        overall: props.application.overall,
        submitted_photo: props.application.submitted_photo,
        submitted_cccd: props.application.submitted_cccd,
        submitted_ccta: props.application.submitted_ccta,
        submitted_tn_translate: props.application.submitted_tn_translate,
        submitted_hb_translate: props.application.submitted_hb_translate,
        submitted_other: props.application.submitted_other,
        submitted_insurance_card: props.application.submitted_insurance_card,
        submitted_exemption_gc: props.application.submitted_exemption_gc,
        study_link_status: props.application.study_link_status,
        english_qualifications: props.application.english_qualifications,
        sut_id: props.application.sut_id,
        is_international_applicant: props.application.is_international_applicant,
        exception_units: props.application.exception_units,
        status: props.application.status,
        student_code: props.application.student_code,
    },
});

// Form submission handler
const onSubmit = handleSubmit((formValues) => {
    // Convert form data types
    const data = {
        ...formValues,
        birth_day: formValues.birth_day ? Number(formValues.birth_day) : null,
        birth_month: formValues.birth_month ? Number(formValues.birth_month) : null,
        birth_year: formValues.birth_year ? Number(formValues.birth_year) : null,
        listening: formValues.listening ? Number(formValues.listening) : null,
        reading: formValues.reading ? Number(formValues.reading) : null,
        writing: formValues.writing ? Number(formValues.writing) : null,
        speaking: formValues.speaking ? Number(formValues.speaking) : null,
        overall: formValues.overall ? Number(formValues.overall) : null,
        parent_email: formValues.parent_email || null,
        national_id: formValues.national_id || null,
        ethnicity: formValues.ethnicity || null,
        address: formValues.address || null,
        health_information: formValues.health_information || null,
        parent_phone: formValues.parent_phone || null,
        intended_program: formValues.intended_program || null,
        intended_specialization: formValues.intended_specialization || null,
        intake: formValues.intake || null,
        exam_date: formValues.exam_date || null,
        english_test_type: formValues.english_test_type || null,
        study_link_status: formValues.study_link_status || null,
        english_qualifications: formValues.english_qualifications || null,
        sut_id: formValues.sut_id || null,
        exception_units: formValues.exception_units || null,
    };

    router.put(route('student-applications.update', props.application.id), data, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Student application updated successfully');
        },
        onError: (errors) => {
            toast.error('Failed to update student application');
            console.error('Validation errors:', errors);
        },
    });
});

const handleCancel = () => {
    router.visit(route('student-applications.show', props.application.id));
};
</script>

<template>
    <Head title="Edit Student Application" />

    <div class="mb-6">
        <Button variant="ghost" @click="handleCancel" class="mb-4">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back to Details
        </Button>
        <h1 class="text-3xl font-bold">Edit Student Application</h1>
        <p class="text-muted-foreground mt-2">Update student application information</p>
    </div>

    <form @submit="onSubmit">
        <div class="space-y-6">
            <!-- Basic Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Basic Information</CardTitle>
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

                        <FormField v-slot="{ componentField }" name="student_code">
                            <FormItem>
                                <FormLabel>Student Code *</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" disabled placeholder="Student code" />
                                </FormControl>
                                <FormDescription>Student code cannot be changed</FormDescription>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ value, handleChange }" name="gender">
                            <FormItem>
                                <FormLabel>Gender</FormLabel>
                                <FormControl>
                                    <RadioGroup :model-value="value" @update:model-value="handleChange" class="flex flex-row space-x-4">
                                        <div class="flex items-center space-x-2">
                                            <RadioGroupItem id="male" value="male" />
                                            <label for="male">Male</label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <RadioGroupItem id="female" value="female" />
                                            <label for="female">Female</label>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <RadioGroupItem id="other" value="other" />
                                            <label for="other">Other</label>
                                        </div>
                                    </RadioGroup>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="ethnicity">
                            <FormItem>
                                <FormLabel>Ethnicity</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter ethnicity" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <FormField v-slot="{ componentField }" name="birth_day">
                            <FormItem>
                                <FormLabel>Birth Day</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="number" min="1" max="31" placeholder="DD" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="birth_month">
                            <FormItem>
                                <FormLabel>Birth Month</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="number" min="1" max="12" placeholder="MM" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="birth_year">
                            <FormItem>
                                <FormLabel>Birth Year</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="number" min="1900" :max="new Date().getFullYear() + 1" placeholder="YYYY" />
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

                        <FormField v-slot="{ componentField }" name="sut_id">
                            <FormItem>
                                <FormLabel>SUT ID</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter SUT ID" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </CardContent>
            </Card>

            <!-- Contact Information -->
            <Card>
                <CardHeader>
                    <CardTitle>Contact Information</CardTitle>
                    <CardDescription>Student and parent contact details</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ componentField }" name="email">
                            <FormItem>
                                <FormLabel>Email *</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="email" placeholder="student@example.com" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="phone">
                            <FormItem>
                                <FormLabel>Phone *</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter phone number" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <FormField v-slot="{ componentField }" name="address">
                        <FormItem>
                            <FormLabel>Address</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="Enter full address" rows="3" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ componentField }" name="parent_email">
                            <FormItem>
                                <FormLabel>Parent Email</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="email" placeholder="parent@example.com" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="parent_phone">
                            <FormItem>
                                <FormLabel>Parent Phone</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter parent phone" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <FormField v-slot="{ componentField }" name="health_information">
                        <FormItem>
                            <FormLabel>Health Information</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="Any health conditions or allergies" rows="3" />
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
                    <CardDescription>Program and campus details</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ componentField }" name="campus_code">
                            <FormItem>
                                <FormLabel>Campus *</FormLabel>
                                <FormControl>
                                    <Select v-bind="componentField">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select campus" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="campus in campuses" :key="campus.code" :value="campus.code">
                                                {{ campus.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="intended_program">
                            <FormItem>
                                <FormLabel>Intended Program</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" disabled placeholder="Enter program code" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ componentField }" name="intended_specialization">
                            <FormItem>
                                <FormLabel>Intended Specialization</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" disabled placeholder="Enter specialization" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="intake">
                            <FormItem>
                                <FormLabel>Intake</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" disabled placeholder="e.g., 2024-1" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <FormField v-slot="{ value, handleChange }" name="is_international_applicant">
                        <FormItem class="flex flex-row items-start space-y-0 space-x-3">
                            <FormControl>
                                <Checkbox :model-value="value" @update:model-value="handleChange" />
                            </FormControl>
                            <FormLabel class="font-normal">International Applicant</FormLabel>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="exception_units">
                        <FormItem>
                            <FormLabel>Exception Units</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="List any exception units" rows="2" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </CardContent>
            </Card>

            <!-- English Proficiency -->
            <Card>
                <CardHeader>
                    <CardTitle>English Proficiency</CardTitle>
                    <CardDescription>English test scores and qualifications</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ componentField }" name="english_test_type">
                            <FormItem>
                                <FormLabel>Test Type</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="e.g., IELTS, TOEFL" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="exam_date">
                            <FormItem>
                                <FormLabel>Exam Date</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="date" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                        <FormField v-slot="{ componentField }" name="listening">
                            <FormItem>
                                <FormLabel>Listening</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="number" step="0.5" min="0" max="10" placeholder="0.0" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="reading">
                            <FormItem>
                                <FormLabel>Reading</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="number" step="0.5" min="0" max="10" placeholder="0.0" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="writing">
                            <FormItem>
                                <FormLabel>Writing</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="number" step="0.5" min="0" max="10" placeholder="0.0" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="speaking">
                            <FormItem>
                                <FormLabel>Speaking</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="number" step="0.5" min="0" max="10" placeholder="0.0" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="overall">
                            <FormItem>
                                <FormLabel>Overall</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="number" step="0.5" min="0" max="10" placeholder="0.0" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <FormField v-slot="{ componentField }" name="english_qualifications">
                        <FormItem>
                            <FormLabel>English Qualifications</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="Additional English qualifications or notes" rows="2" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </CardContent>
            </Card>

            <!-- Submitted Documents -->
            <Card>
                <CardHeader>
                    <CardTitle>Submitted Documents</CardTitle>
                    <CardDescription>Check the documents that have been submitted</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ value, handleChange }" name="submitted_photo">
                            <FormItem>
                                <FormLabel class="font-normal">Photo</FormLabel>
                                <FormControl>
                                    <Input :model-value="value" @update:model-value="handleChange" />
                                </FormControl>
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ value, handleChange }" name="submitted_cccd">
                            <FormItem>
                                <FormControl>
                                    <FormLabel class="font-normal">CCCD</FormLabel>
                                    <Input :model-value="value" @update:model-value="handleChange" />
                                </FormControl>
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ value, handleChange }" name="submitted_ccta">
                            <FormItem>
                                <FormControl>
                                    <FormLabel class="font-normal">CCTA</FormLabel>
                                    <Input :model-value="value" @update:model-value="handleChange" />
                                </FormControl>
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ value, handleChange }" name="submitted_tn_translate">
                            <FormItem>
                                <FormControl>
                                    <FormLabel class="font-normal">TN Translate</FormLabel>
                                    <Input :model-value="value" @update:model-value="handleChange" />
                                </FormControl>
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ value, handleChange }" name="submitted_hb_translate">
                            <FormItem>
                                <FormControl>
                                    <FormLabel class="font-normal">HB Translate</FormLabel>
                                    <Input :model-value="value" @update:model-value="handleChange" />
                                </FormControl>
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ value, handleChange }" name="submitted_insurance_card">
                            <FormItem>
                                <FormControl>
                                    <FormLabel class="font-normal">Insurance Card</FormLabel>
                                    <Input :model-value="value" @update:model-value="handleChange" />
                                </FormControl>
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ value, handleChange }" name="submitted_exemption_gc">
                            <FormItem>
                                <FormControl>
                                    <FormLabel class="font-normal">Exemption GC</FormLabel>
                                    <Input :model-value="value" @update:model-value="handleChange" />
                                </FormControl>
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ value, handleChange }" name="submitted_other">
                            <FormItem>
                                <FormControl>
                                    <FormLabel class="font-normal">Other Documents</FormLabel>
                                    <Input :model-value="value" @update:model-value="handleChange" />
                                </FormControl>
                            </FormItem>
                        </FormField>
                    </div>
                </CardContent>
            </Card>

            <!-- Application Status -->
            <Card>
                <CardHeader>
                    <CardTitle>Application Status</CardTitle>
                    <CardDescription>Current status and additional information</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ componentField }" name="status">
                            <FormItem>
                                <FormLabel>Status *</FormLabel>
                                <FormControl>
                                    <Select v-bind="componentField">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="pending">Pending</SelectItem>
                                            <SelectItem value="reviewed">Reviewed</SelectItem>
                                            <SelectItem value="approved">Approved</SelectItem>
                                            <SelectItem value="rejected">Rejected</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="study_link_status">
                            <FormItem>
                                <FormLabel>StudyLink Status</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="StudyLink status" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </CardContent>
            </Card>

            <!-- Form Actions -->
            <div class="flex justify-end space-x-4">
                <Button type="button" variant="outline" @click="handleCancel">Cancel</Button>
                <Button type="submit">Save Changes</Button>
            </div>
        </div>
    </form>
</template>
