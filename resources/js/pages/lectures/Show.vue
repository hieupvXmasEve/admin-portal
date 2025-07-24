<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { Campus, Lecture } from '@/types/models';
import { lecturerRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, Edit, Mail, MapPin, Phone, User } from 'lucide-vue-next';
import { route } from 'ziggy-js';

interface Props {
    lecture: Lecture;
    campuses: Campus[];
}

const props = defineProps<Props>();

const goBack = () => {
    router.visit(lecturerRoutes.index());
};

const goToEdit = () => {
    router.visit(route('lectures.edit', props.lecture.id));
};

// Helper functions
const formatAcademicRank = (rank: string) => {
    return rank
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const formatEmploymentType = (type: string) => {
    return type
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const formatEmploymentStatus = (status: string) => {
    return status
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'on_leave':
        case 'sabbatical':
            return 'secondary';
        case 'retired':
        case 'terminated':
        case 'suspended':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getRankBadgeVariant = (rank: string) => {
    switch (rank) {
        case 'professor':
        case 'emeritus_professor':
            return 'default';
        case 'associate_professor':
        case 'senior_lecturer':
            return 'secondary';
        case 'lecturer':
            return 'outline';
        case 'visiting_lecturer':
        case 'adjunct_professor':
            return 'secondary';
        default:
            return 'outline';
    }
};

const getCampusName = () => {
    const campus = props.campuses?.find((c) => c.id === props.lecture.campus_id);
    return campus?.name || 'Unknown Campus';
};
</script>

<template>
    <Head :title="`${lecture.display_name} - Lecturer Details`" />

    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <Button variant="ghost" size="sm" @click="goBack">
                <ChevronLeft class="mr-2 h-4 w-4" />
                Back to Lecturers
            </Button>
            <div>
                <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Lecturer Details</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">View comprehensive information about the lecture.</p>
            </div>
        </div>
        <Button @click="goToEdit">
            <Edit class="mr-2 h-4 w-4" />
            Edit Lecturer
        </Button>
    </div>

    <div class="space-y-6">
        <!-- Basic Information -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2">
                            <User class="h-5 w-5" />
                            {{ lecture.display_name }}
                        </CardTitle>
                        <CardDescription class="mt-2 flex items-center gap-4">
                            <span class="flex items-center gap-1">
                                <Mail class="h-4 w-4" />
                                {{ lecture.email }}
                            </span>
                            <span v-if="lecture.mobile_phone" class="flex items-center gap-1">
                                <Phone class="h-4 w-4" />
                                {{ lecture.mobile_phone }}
                            </span>
                            <span class="flex items-center gap-1">
                                <MapPin class="h-4 w-4" />
                                {{ getCampusName() }}
                            </span>
                        </CardDescription>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Badge :variant="getRankBadgeVariant(lecture.academic_rank)">
                            {{ formatAcademicRank(lecture.academic_rank) }}
                        </Badge>
                        <Badge :variant="getStatusBadgeVariant(lecture.employment_status)">
                            {{ formatEmploymentStatus(lecture.employment_status) }}
                        </Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Employee ID</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.employee_id }}</p>
                    </div>
                    <div v-if="lecture.title">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Title</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.title }}</p>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Years of Service</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.years_of_service }} years</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Academic Information -->
        <Card>
            <CardHeader>
                <CardTitle>Academic Information</CardTitle>
                <CardDescription>Educational background and academic qualifications</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div v-if="lecture.department">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Department</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.department }}</p>
                    </div>
                    <div v-if="lecture.faculty">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Faculty</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.faculty }}</p>
                    </div>
                    <div v-if="lecture.specialization">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Specialization</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.specialization }}</p>
                    </div>
                    <div v-if="lecture.highest_degree">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Highest Degree</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.highest_degree }}</p>
                    </div>
                    <div v-if="lecture.degree_field">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Degree Field</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.degree_field }}</p>
                    </div>
                    <div v-if="lecture.alma_mater">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Alma Mater</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.alma_mater }}</p>
                    </div>
                    <div v-if="lecture.graduation_year">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Graduation Year</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.graduation_year }}</p>
                    </div>
                </div>
                <div v-if="lecture.expertise_areas && lecture.expertise_areas.length > 0" class="mt-4">
                    <h4 class="mb-2 font-medium text-gray-900 dark:text-gray-100">Expertise Areas</h4>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="area in lecture.expertise_areas" :key="area" variant="outline">
                            {{ area }}
                        </Badge>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Employment Information -->
        <Card>
            <CardHeader>
                <CardTitle>Employment Information</CardTitle>
                <CardDescription>Employment details and work arrangements</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Hire Date</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ new Date(lecture.hire_date).toLocaleDateString() }}</p>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Employment Type</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ formatEmploymentType(lecture.employment_type) }}</p>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Max Teaching Hours/Week</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.max_teaching_hours_per_week }} hours</p>
                    </div>
                    <div v-if="lecture.contract_start_date">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Contract Start</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ new Date(lecture.contract_start_date).toLocaleDateString() }}</p>
                    </div>
                    <div v-if="lecture.contract_end_date">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Contract End</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ new Date(lecture.contract_end_date).toLocaleDateString() }}</p>
                    </div>
                    <div v-if="lecture.office_address">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Office Address</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.office_address }}</p>
                    </div>
                    <div v-if="lecture.hourly_rate">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Hourly Rate</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">${{ lecture.hourly_rate }}</p>
                    </div>
                    <div v-if="lecture.salary">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Annual Salary</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">${{ lecture.salary.toLocaleString() }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Teaching Preferences -->
        <Card v-if="lecture.preferred_teaching_days || lecture.teaching_modalities || lecture.preferred_start_time">
            <CardHeader>
                <CardTitle>Teaching Preferences</CardTitle>
                <CardDescription>Preferred schedule and teaching modalities</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-2">
                    <div v-if="lecture.preferred_teaching_days && lecture.preferred_teaching_days.length > 0">
                        <h4 class="mb-2 font-medium text-gray-900 dark:text-gray-100">Preferred Teaching Days</h4>
                        <div class="flex flex-wrap gap-2">
                            <Badge v-for="day in lecture.preferred_teaching_days" :key="day" variant="outline">
                                {{ day }}
                            </Badge>
                        </div>
                    </div>
                    <div v-if="lecture.teaching_modalities && lecture.teaching_modalities.length > 0">
                        <h4 class="mb-2 font-medium text-gray-900 dark:text-gray-100">Teaching Modalities</h4>
                        <div class="flex flex-wrap gap-2">
                            <Badge v-for="modality in lecture.teaching_modalities" :key="modality" variant="outline">
                                {{ modality.replace('_', ' ').toUpperCase() }}
                            </Badge>
                        </div>
                    </div>
                    <div v-if="lecture.preferred_start_time">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Preferred Start Time</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.preferred_start_time }}</p>
                    </div>
                    <div v-if="lecture.preferred_end_time">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Preferred End Time</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.preferred_end_time }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Status & Settings -->
        <Card>
            <CardHeader>
                <CardTitle>Status & Settings</CardTitle>
                <CardDescription>Current availability and settings</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium">Active Status:</span>
                        <Badge :variant="lecture.is_active ? 'default' : 'secondary'">
                            {{ lecture.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium">Can Teach Online:</span>
                        <Badge :variant="lecture.can_teach_online ? 'default' : 'outline'">
                            {{ lecture.can_teach_online ? 'Yes' : 'No' }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium">Available for Assignment:</span>
                        <Badge :variant="lecture.is_available_for_assignment ? 'default' : 'outline'">
                            {{ lecture.is_available_for_assignment ? 'Yes' : 'No' }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium">Contract Active:</span>
                        <Badge :variant="lecture.is_contract_active ? 'default' : 'secondary'">
                            {{ lecture.is_contract_active ? 'Active' : 'Inactive' }}
                        </Badge>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Additional Information -->
        <Card v-if="lecture.biography || lecture.notes || lecture.certifications || lecture.languages">
            <CardHeader>
                <CardTitle>Additional Information</CardTitle>
                <CardDescription>Biography, certifications, and additional notes</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-if="lecture.biography">
                    <h4 class="font-medium text-gray-900 dark:text-gray-100">Biography</h4>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ lecture.biography }}</p>
                </div>
                <div v-if="lecture.certifications && lecture.certifications.length > 0">
                    <h4 class="mb-2 font-medium text-gray-900 dark:text-gray-100">Certifications</h4>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="cert in lecture.certifications" :key="cert" variant="outline">
                            {{ cert }}
                        </Badge>
                    </div>
                </div>
                <div v-if="lecture.languages && lecture.languages.length > 0">
                    <h4 class="mb-2 font-medium text-gray-900 dark:text-gray-100">Languages</h4>
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="lang in lecture.languages" :key="lang" variant="outline">
                            {{ lang }}
                        </Badge>
                    </div>
                </div>
                <div v-if="lecture.notes">
                    <h4 class="font-medium text-gray-900 dark:text-gray-100">Notes</h4>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ lecture.notes }}</p>
                </div>
            </CardContent>
        </Card>

        <!-- Emergency Contact -->
        <Card v-if="lecture.emergency_contact_name || lecture.emergency_contact_phone">
            <CardHeader>
                <CardTitle>Emergency Contact</CardTitle>
                <CardDescription>Emergency contact information</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-3">
                    <div v-if="lecture.emergency_contact_name">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Name</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.emergency_contact_name }}</p>
                    </div>
                    <div v-if="lecture.emergency_contact_phone">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Phone</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.emergency_contact_phone }}</p>
                    </div>
                    <div v-if="lecture.emergency_contact_relationship">
                        <h4 class="font-medium text-gray-900 dark:text-gray-100">Relationship</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ lecture.emergency_contact_relationship }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
