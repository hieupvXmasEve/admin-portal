<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Campus } from '@/types/models';
import { lecturerRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ChevronLeft, Save } from 'lucide-vue-next';
import { useForm as useVeeForm } from 'vee-validate';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import { z } from 'zod';

interface Props {
    campuses: Campus[];
    currentCampusId: string;
}

const props = defineProps<Props>();

// Validation schema
const validationSchema = toTypedSchema(
    z.object({
        employee_id: z.string().min(1, 'Employee ID is required').max(20, 'Employee ID must not exceed 20 characters'),
        title: z.string().optional(),
        first_name: z.string().min(1, 'First name is required').max(100, 'First name must not exceed 100 characters'),
        last_name: z.string().min(1, 'Last name is required').max(100, 'Last name must not exceed 100 characters'),
        email: z.string().email('Invalid email address').min(1, 'Email is required'),
        mobile_phone: z.string().optional(),
        campus_id: z.string().min(1, 'Campus is required'),
        department: z.string().optional(),
        faculty: z.string().optional(),
        specialization: z.string().optional(),
        expertise_areas: z.array(z.string()).optional(),
        academic_rank: z.enum(['lecturer', 'senior_lecturer', 'associate_professor', 'professor', 'emeritus_professor', 'visiting_lecturer', 'adjunct_professor'], { required_error: 'Academic rank is required' }),
        highest_degree: z.string().optional(),
        degree_field: z.string().optional(),
        alma_mater: z.string().optional(),
        graduation_year: z
            .number()
            .min(1900)
            .max(new Date().getFullYear() + 10)
            .optional(),
        hire_date: z.string().min(1, 'Hire date is required'),
        contract_start_date: z.string().optional(),
        contract_end_date: z.string().optional(),
        employment_type: z.enum(['full_time', 'part_time', 'contract', 'visiting', 'emeritus'], {
            required_error: 'Employment type is required',
        }),
        employment_status: z.enum(['active', 'on_leave', 'sabbatical', 'retired', 'terminated', 'suspended'], {
            required_error: 'Employment status is required',
        }),
        preferred_teaching_days: z.array(z.string()).optional(),
        preferred_start_time: z.string().optional(),
        preferred_end_time: z.string().optional(),
        max_teaching_hours_per_week: z.number().min(1).max(80).optional(),
        teaching_modalities: z.array(z.string()).optional(),
        office_address: z.string().optional(),
        // office_phone: z.string().optional(),
        emergency_contact_name: z.string().optional(),
        emergency_contact_phone: z.string().optional(),
        emergency_contact_relationship: z.string().optional(),
        biography: z.string().optional(),
        certifications: z.array(z.string()).optional(),
        languages: z.array(z.string()).optional(),
        hourly_rate: z.number().min(0).optional(),
        salary: z.number().min(0).optional(),
        is_active: z.boolean().optional(),
        can_teach_online: z.boolean().optional(),
        is_available_for_assignment: z.boolean().optional(),
        notes: z.string().optional(),
    }),
);

const { handleSubmit, defineField, errors, isSubmitting } = useVeeForm({
    validationSchema,
    initialValues: {
        campus_id: props.currentCampusId.toString(),
        is_active: true,
        can_teach_online: false,
        is_available_for_assignment: true,
        employment_status: 'active',
        employment_type: 'full_time',
        max_teaching_hours_per_week: 40,
        academic_rank: 'lecturer',
    },
});

// Define form fields
const [employee_id] = defineField('employee_id');
const [title] = defineField('title');
const [first_name] = defineField('first_name');
const [last_name] = defineField('last_name');
const [email] = defineField('email');
const [mobile_phone] = defineField('mobile_phone');
const [campus_id] = defineField('campus_id');
const [department] = defineField('department');
const [faculty] = defineField('faculty');
const [specialization] = defineField('specialization');
const [expertise_areas] = defineField('expertise_areas');
const [academic_rank] = defineField('academic_rank');
const [highest_degree] = defineField('highest_degree');
const [degree_field] = defineField('degree_field');
const [alma_mater] = defineField('alma_mater');
const [graduation_year] = defineField('graduation_year');
const [hire_date] = defineField('hire_date');
const [contract_start_date] = defineField('contract_start_date');
const [contract_end_date] = defineField('contract_end_date');
const [employment_type] = defineField('employment_type');
const [employment_status] = defineField('employment_status');
const [preferred_teaching_days] = defineField('preferred_teaching_days');
const [preferred_start_time] = defineField('preferred_start_time');
const [preferred_end_time] = defineField('preferred_end_time');
const [max_teaching_hours_per_week] = defineField('max_teaching_hours_per_week');
const [teaching_modalities] = defineField('teaching_modalities');
const [office_address] = defineField('office_address');
// const [office_phone] = defineField('office_phone');
const [emergency_contact_name] = defineField('emergency_contact_name');
const [emergency_contact_phone] = defineField('emergency_contact_phone');
const [emergency_contact_relationship] = defineField('emergency_contact_relationship');
const [biography] = defineField('biography');
const [certifications] = defineField('certifications');
const [languages] = defineField('languages');
const [hourly_rate] = defineField('hourly_rate');
const [salary] = defineField('salary');
const [is_active] = defineField('is_active');
const [can_teach_online] = defineField('can_teach_online');
const [is_available_for_assignment] = defineField('is_available_for_assignment');
const [notes] = defineField('notes');

// Options
const academicRankOptions = [
    { value: 'lecturer', label: 'Lecturer' },
    { value: 'senior_lecturer', label: 'Senior Lecturer' },
    { value: 'associate_professor', label: 'Associate Professor' },
    { value: 'professor', label: 'Professor' },
    { value: 'emeritus_professor', label: 'Emeritus Professor' },
    { value: 'visiting_lecturer', label: 'Visiting Lecturer' },
    { value: 'adjunct_professor', label: 'Adjunct Professor' },
];

const employmentTypeOptions = [
    { value: 'full_time', label: 'Full Time' },
    { value: 'part_time', label: 'Part Time' },
    { value: 'contract', label: 'Contract' },
    { value: 'visiting', label: 'Visiting' },
    { value: 'emeritus', label: 'Emeritus' },
];

const employmentStatusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'on_leave', label: 'On Leave' },
    { value: 'sabbatical', label: 'Sabbatical' },
    { value: 'retired', label: 'Retired' },
    { value: 'terminated', label: 'Terminated' },
    { value: 'suspended', label: 'Suspended' },
];

const teachingDaysOptions = [
    { value: 'Monday', label: 'Monday' },
    { value: 'Tuesday', label: 'Tuesday' },
    { value: 'Wednesday', label: 'Wednesday' },
    { value: 'Thursday', label: 'Thursday' },
    { value: 'Friday', label: 'Friday' },
    { value: 'Saturday', label: 'Saturday' },
    { value: 'Sunday', label: 'Sunday' },
];

const teachingModalitiesOptions = [
    { value: 'in_person', label: 'In Person' },
    { value: 'online', label: 'Online' },
    { value: 'hybrid', label: 'Hybrid' },
];

const goBack = () => {
    router.visit(lecturerRoutes.index());
};

const onSubmit = handleSubmit((values) => {
    router.post(route('lectures.store'), values, {
        onSuccess: () => {
            toast.success('Lecturer created successfully');
        },
        onError: (errors) => {
            const errorMessage = Object.values(errors)?.[0] || 'An error occurred while creating the lecturer.';
            toast.error('Failed to create lecturer', {
                description: errorMessage,
            });
        },
    });
});
</script>

<template>
    <Head title="Add Lecturer" />

    <div class="mb-6 flex items-center gap-4">
        <Button variant="ghost" size="sm" @click="goBack">
            <ChevronLeft class="mr-2 h-4 w-4" />
            Back to Lecturers
        </Button>
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Add New Lecturer</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Create a new lecturer profile with their information and settings.</p>
        </div>
    </div>

    <form @submit="onSubmit" class="space-y-6">
        <!-- Basic Information -->
        <Card>
            <CardHeader>
                <CardTitle>Basic Information</CardTitle>
                <CardDescription>Enter the lecturer's basic personal and contact information.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <Label for="employee_id">Employee ID *</Label>
                        <Input id="employee_id" v-model="employee_id" placeholder="Enter employee ID" :class="{ 'border-red-500': errors.employee_id }" />
                        <p v-if="errors.employee_id" class="mt-1 text-sm text-red-600">{{ errors.employee_id }}</p>
                    </div>

                    <div>
                        <Label for="title">Title</Label>
                        <Select v-model="title">
                            <SelectTrigger>
                                <SelectValue placeholder="Select title" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="Mr">Mr</SelectItem>
                                <SelectItem value="Ms">Ms</SelectItem>
                                <SelectItem value="Mrs">Mrs</SelectItem>
                                <SelectItem value="Dr">Dr</SelectItem>
                                <SelectItem value="Prof">Prof</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div>
                        <Label for="first_name">First Name *</Label>
                        <Input id="first_name" v-model="first_name" placeholder="Enter first name" :class="{ 'border-red-500': errors.first_name }" />
                        <p v-if="errors.first_name" class="mt-1 text-sm text-red-600">{{ errors.first_name }}</p>
                    </div>

                    <div>
                        <Label for="last_name">Last Name *</Label>
                        <Input id="last_name" v-model="last_name" placeholder="Enter last name" :class="{ 'border-red-500': errors.last_name }" />
                        <p v-if="errors.last_name" class="mt-1 text-sm text-red-600">{{ errors.last_name }}</p>
                    </div>

                    <div>
                        <Label for="email">Email *</Label>
                        <Input id="email" v-model="email" type="email" placeholder="Enter email address" :class="{ 'border-red-500': errors.email }" />
                        <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
                    </div>

                    <!-- <div>
                        <Label for="phone">Phone</Label>
                        <Input
                            id="phone"
                            v-model="phone"
                            placeholder="Enter phone number"
                        />
                    </div> -->

                    <div>
                        <Label for="mobile_phone">Mobile Phone</Label>
                        <Input id="mobile_phone" v-model="mobile_phone" placeholder="Enter mobile phone number" />
                    </div>

                    <div>
                        <Label for="campus_id">Campus *</Label>
                        <Select v-model="campus_id">
                            <SelectTrigger :class="{ 'border-red-500': errors.campus_id }">
                                <SelectValue placeholder="Select campus" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">
                                    {{ campus.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.campus_id" class="mt-1 text-sm text-red-600">{{ errors.campus_id }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Academic Information -->
        <Card>
            <CardHeader>
                <CardTitle>Academic Information</CardTitle>
                <CardDescription>Enter the lecturer's academic background and qualifications.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <Label for="academic_rank">Academic Rank *</Label>
                        <Select v-model="academic_rank">
                            <SelectTrigger :class="{ 'border-red-500': errors.academic_rank }">
                                <SelectValue placeholder="Select academic rank" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="rank in academicRankOptions" :key="rank.value" :value="rank.value">
                                    {{ rank.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.academic_rank" class="mt-1 text-sm text-red-600">{{ errors.academic_rank }}</p>
                    </div>

                    <div>
                        <Label for="department">Department</Label>
                        <Input id="department" v-model="department" placeholder="Enter department" />
                    </div>

                    <div>
                        <Label for="faculty">Faculty</Label>
                        <Input id="faculty" v-model="faculty" placeholder="Enter faculty" />
                    </div>

                    <div>
                        <Label for="highest_degree">Highest Degree</Label>
                        <Input id="highest_degree" v-model="highest_degree" placeholder="e.g., PhD, Masters, Bachelor" />
                    </div>

                    <div>
                        <Label for="degree_field">Degree Field</Label>
                        <Input id="degree_field" v-model="degree_field" placeholder="Field of study" />
                    </div>

                    <div>
                        <Label for="alma_mater">Alma Mater</Label>
                        <Input id="alma_mater" v-model="alma_mater" placeholder="University/Institution" />
                    </div>

                    <div>
                        <Label for="graduation_year">Graduation Year</Label>
                        <Input id="graduation_year" v-model.number="graduation_year" type="number" :min="1900" :max="new Date().getFullYear() + 10" placeholder="YYYY" />
                    </div>

                    <div>
                        <Label for="specialization">Specialization</Label>
                        <Input id="specialization" v-model="specialization" placeholder="Area of specialization" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Employment Information -->
        <Card>
            <CardHeader>
                <CardTitle>Employment Information</CardTitle>
                <CardDescription>Enter the lecturer's employment details and work arrangements.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <Label for="hire_date">Hire Date *</Label>
                        <DatePicker id="hire_date" v-model="hire_date" placeholder="Select hire date" :class="{ 'border-red-500': errors.hire_date }" />
                        <p v-if="errors.hire_date" class="mt-1 text-sm text-red-600">{{ errors.hire_date }}</p>
                    </div>

                    <div>
                        <Label for="employment_type">Employment Type *</Label>
                        <Select v-model="employment_type">
                            <SelectTrigger :class="{ 'border-red-500': errors.employment_type }">
                                <SelectValue placeholder="Select employment type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="type in employmentTypeOptions" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.employment_type" class="mt-1 text-sm text-red-600">{{ errors.employment_type }}</p>
                    </div>

                    <div>
                        <Label for="employment_status">Employment Status *</Label>
                        <Select v-model="employment_status">
                            <SelectTrigger :class="{ 'border-red-500': errors.employment_status }">
                                <SelectValue placeholder="Select employment status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="status in employmentStatusOptions" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="errors.employment_status" class="mt-1 text-sm text-red-600">{{ errors.employment_status }}</p>
                    </div>

                    <div>
                        <Label for="contract_start_date">Contract Start Date</Label>
                        <DatePicker id="contract_start_date" v-model="contract_start_date" placeholder="Select contract start date" />
                    </div>

                    <div>
                        <Label for="contract_end_date">Contract End Date</Label>
                        <DatePicker id="contract_end_date" v-model="contract_end_date" placeholder="Select contract end date" />
                    </div>

                    <div>
                        <Label for="max_teaching_hours_per_week">Max Teaching Hours/Week</Label>
                        <Input id="max_teaching_hours_per_week" v-model.number="max_teaching_hours_per_week" type="number" min="1" max="80" placeholder="40" />
                    </div>

                    <div>
                        <Label for="office_address">Office Address</Label>
                        <Input id="office_address" v-model="office_address" placeholder="Office location" />
                    </div>

                    <!-- <div>
                        <Label for="office_phone">Office Phone</Label>
                        <Input
                            id="office_phone"
                            v-model="office_phone"
                            placeholder="Office phone number"
                        />
                    </div> -->
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <Label for="hourly_rate">Hourly Rate</Label>
                        <Input id="hourly_rate" v-model.number="hourly_rate" type="number" min="0" step="0.01" placeholder="0.00" />
                    </div>

                    <div>
                        <Label for="salary">Annual Salary</Label>
                        <Input id="salary" v-model.number="salary" type="number" min="0" step="0.01" placeholder="0.00" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Status & Settings -->
        <Card>
            <CardHeader>
                <CardTitle>Status & Settings</CardTitle>
                <CardDescription>Configure the lecturer's status and availability settings.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-center space-x-2">
                    <Checkbox id="is_active" v-model:checked="is_active" />
                    <Label for="is_active">Active Status</Label>
                </div>

                <div class="flex items-center space-x-2">
                    <Checkbox id="can_teach_online" v-model:checked="can_teach_online" />
                    <Label for="can_teach_online">Can Teach Online</Label>
                </div>

                <div class="flex items-center space-x-2">
                    <Checkbox id="is_available_for_assignment" v-model:checked="is_available_for_assignment" />
                    <Label for="is_available_for_assignment">Available for Assignment</Label>
                </div>
            </CardContent>
        </Card>

        <!-- Notes -->
        <Card>
            <CardHeader>
                <CardTitle>Additional Notes</CardTitle>
                <CardDescription>Any additional information or notes about the lecturer.</CardDescription>
            </CardHeader>
            <CardContent>
                <div>
                    <Label for="notes">Notes</Label>
                    <Textarea id="notes" v-model="notes" placeholder="Enter any additional notes..." rows="4" />
                </div>
            </CardContent>
        </Card>

        <!-- Actions -->
        <div class="flex justify-end space-x-4">
            <Button type="button" variant="outline" @click="goBack"> Cancel </Button>
            <Button type="submit" :disabled="isSubmitting">
                <Save class="mr-2 h-4 w-4" />
                {{ isSubmitting ? 'Creating...' : 'Create Lecturer' }}
            </Button>
        </div>
    </form>
</template>
