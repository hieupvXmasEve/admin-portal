<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { AcademicHold, CourseRegistration, Student } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Award,
    BookOpen,
    Building,
    Calendar,
    CheckCircle,
    Edit,
    Eye,
    GraduationCap,
    Mail,
    MapPin,
    Phone,
    Trash2,
    User,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    student: Student & {
        courseRegistrations?: CourseRegistration[];
        academicHolds?: AcademicHold[];
    };
    academicStats: {
        total_registrations: number;
        completed_courses: number;
        active_registrations: number;
        total_credits_earned: number;
        active_holds: number;
    };
}

const props = defineProps<Props>();

// Status badge styling
const getStatusBadgeVariant = (status: string) => {
    const variants: Record<string, string> = {
        admitted: 'secondary',
        enrolled: 'default',
        active: 'default',
        on_leave: 'outline',
        suspended: 'destructive',
        graduated: 'secondary',
        dropped_out: 'outline',
    };
    return variants[status] || 'outline';
};

const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        admitted: 'text-gray-600',
        enrolled: 'text-blue-600',
        active: 'text-green-600',
        on_leave: 'text-yellow-600',
        suspended: 'text-red-600',
        graduated: 'text-purple-600',
        dropped_out: 'text-gray-600',
    };
    return colors[status] || 'text-gray-600';
};

const formatDate = (date: string | null) => {
    if (!date) return 'Not set';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatStatus = (status: string) => {
    return status.replace('_', ' ').toUpperCase();
};

const handleEdit = () => {
    router.visit(route('students.edit', props.student.id));
};

const handleDelete = () => {
    if (confirm(`Are you sure you want to delete ${props.student.full_name}? This action cannot be undone.`)) {
        router.delete(route('students.destroy', props.student.id), {
            onSuccess: () => {
                router.visit(route('students.index'));
            },
        });
    }
};

// Academic progress calculation
const academicProgress = computed(() => {
    const { total_registrations, completed_courses } = props.academicStats;
    if (total_registrations === 0) return 0;
    return Math.round((completed_courses / total_registrations) * 100);
});

// Recent course registrations
const recentRegistrations = computed(() => {
    return props.student.courseRegistrations?.slice(0, 5) || [];
});

// Active holds
const activeHolds = computed(() => {
    return props.student.academicHolds?.filter((hold) => hold.status === 'active') || [];
});
console.log('props', props.student);
</script>

<template>
    <Head :title="student.full_name" />
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="bg-primary/10 flex h-16 w-16 items-center justify-center rounded-full">
                <User class="text-primary h-8 w-8" />
            </div>
            <div>
                <h1 class="text-3xl font-bold">{{ student.full_name }}</h1>
                <p class="text-muted-foreground text-lg">{{ student.student_id }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <Button variant="outline" @click="handleEdit">
                <Edit class="mr-2 h-4 w-4" />
                Edit Student
            </Button>
            <Button variant="destructive" @click="handleDelete">
                <Trash2 class="mr-2 h-4 w-4" />
                Delete
            </Button>
        </div>
    </div>

    <!-- Status and Quick Stats -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <Card>
            <CardContent class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-muted-foreground text-sm">Status</p>
                        <Badge :variant="getStatusBadgeVariant(student.status) as any" class="mt-1">
                            {{ formatStatus(student.status) }}
                        </Badge>
                    </div>
                    <CheckCircle :class="getStatusColor(student.status)" class="h-8 w-8" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-muted-foreground text-sm">Total Courses</p>
                        <p class="text-2xl font-bold">{{ academicStats.total_registrations }}</p>
                    </div>
                    <BookOpen class="h-8 w-8 text-blue-600" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-muted-foreground text-sm">Credits Earned</p>
                        <p class="text-2xl font-bold">{{ academicStats.total_credits_earned }}</p>
                    </div>
                    <Award class="h-8 w-8 text-green-600" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-muted-foreground text-sm">Active Holds</p>
                        <p class="text-2xl font-bold" :class="academicStats.active_holds > 0 ? 'text-red-600' : 'text-green-600'">
                            {{ academicStats.active_holds }}
                        </p>
                    </div>
                    <AlertTriangle :class="academicStats.active_holds > 0 ? 'text-red-600' : 'text-green-600'" class="h-8 w-8" />
                </div>
            </CardContent>
        </Card>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Personal Information -->
        <div class="lg:col-span-2">
            <Card class="h-full">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <User class="h-5 w-5" />
                        Personal Information
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <Mail class="text-muted-foreground h-4 w-4" />
                                <div>
                                    <p class="text-muted-foreground text-sm">Email</p>
                                    <p class="font-medium">{{ student.email }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3" v-if="student.phone">
                                <Phone class="text-muted-foreground h-4 w-4" />
                                <div>
                                    <p class="text-muted-foreground text-sm">Phone</p>
                                    <p class="font-medium">{{ student.phone }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3" v-if="student.date_of_birth">
                                <Calendar class="text-muted-foreground h-4 w-4" />
                                <div>
                                    <p class="text-muted-foreground text-sm">Date of Birth</p>
                                    <p class="font-medium">{{ formatDate(student.date_of_birth) }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3" v-if="student.gender">
                                <Users class="text-muted-foreground h-4 w-4" />
                                <div>
                                    <p class="text-muted-foreground text-sm">Gender</p>
                                    <p class="font-medium capitalize">{{ student.gender }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center gap-3" v-if="student.nationality">
                                <MapPin class="text-muted-foreground h-4 w-4" />
                                <div>
                                    <p class="text-muted-foreground text-sm">Nationality</p>
                                    <p class="font-medium">{{ student.nationality }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3" v-if="student.national_id">
                                <div class="text-muted-foreground h-4 w-4">ID</div>
                                <div>
                                    <p class="text-muted-foreground text-sm">National ID</p>
                                    <p class="font-medium">{{ student.national_id }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <Calendar class="text-muted-foreground h-4 w-4" />
                                <div>
                                    <p class="text-muted-foreground text-sm">Admission Date</p>
                                    <p class="font-medium">{{ formatDate(student.admission_date) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3" v-if="student.expected_graduation_date">
                                <Calendar class="text-muted-foreground h-4 w-4" />
                                <div>
                                    <p class="text-muted-foreground text-sm">Expected Graduation</p>
                                    <p class="font-medium">{{ formatDate(student.expected_graduation_date) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="student.address" class="pt-4">
                        <Separator class="mb-4" />
                        <div class="flex items-start gap-3">
                            <MapPin class="text-muted-foreground mt-1 h-4 w-4" />
                            <div>
                                <p class="text-muted-foreground text-sm">Address</p>
                                <p class="font-medium">{{ student.address }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Academic Information -->
        <div>
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <GraduationCap class="h-5 w-5" />
                        Academic Information
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center gap-3">
                        <Building class="text-muted-foreground h-4 w-4" />
                        <div>
                            <p class="text-muted-foreground text-sm">Campus</p>
                            <p class="font-medium">{{ student.campus?.name || 'Not assigned' }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <BookOpen class="text-muted-foreground h-4 w-4" />
                        <div>
                            <p class="text-muted-foreground text-sm">Program</p>
                            <div class="flex items-center gap-2">
                                <p class="font-medium">{{ student.program?.name || 'Not assigned' }}</p>
                                <Link :href="route('programs.show', { program: student.program?.id })" class="ml-2">
                                    <Eye class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3" v-if="student.specialization">
                        <GraduationCap class="text-muted-foreground h-4 w-4" />
                        <div>
                            <p class="text-muted-foreground text-sm">Specialization</p>
                            <div class="flex items-center gap-2">
                                <p class="font-medium">{{ student.specialization.name }}</p>
                                <Link :href="route('specializations.show', { specialization: student.specialization?.id })" class="ml-2">
                                    <Eye class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Show curriculum version -->
                    <div class="flex items-center gap-3" v-if="student.curriculum_version">
                        <BookOpen class="text-muted-foreground h-4 w-4" />
                        <div>
                            <p class="text-muted-foreground text-sm">Curriculum Version</p>
                            <div class="flex items-center gap-2">
                                <p class="font-medium">{{ student.curriculum_version.version_code }}</p>
                                <Link :href="route('curriculum_version.show', { curriculum_version: student.curriculum_version?.id })" class="ml-2">
                                    <Eye class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Progress -->
                    <div class="pt-4">
                        <Separator class="mb-4" />
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-muted-foreground text-sm">Academic Progress</p>
                                <p class="text-sm font-medium">{{ academicProgress }}%</p>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-200">
                                <div class="bg-primary h-2 rounded-full transition-all duration-300" :style="{ width: `${academicProgress}%` }"></div>
                            </div>
                            <p class="text-muted-foreground mt-1 text-xs">
                                {{ academicStats.completed_courses }} of {{ academicStats.total_registrations }} courses completed
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Emergency Contact Information -->
    <Card v-if="student.parent_guardian_name || student.emergency_contact_name">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Phone class="h-5 w-5" />
                Emergency Contacts
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <div v-if="student.parent_guardian_name">
                    <h4 class="mb-3 font-medium">Parent/Guardian</h4>
                    <div class="space-y-2">
                        <p><span class="text-muted-foreground">Name:</span> {{ student.parent_guardian_name }}</p>
                        <p v-if="student.parent_guardian_phone">
                            <span class="text-muted-foreground">Phone:</span> {{ student.parent_guardian_phone }}
                        </p>
                        <p v-if="student.parent_guardian_email">
                            <span class="text-muted-foreground">Email:</span> {{ student.parent_guardian_email }}
                        </p>
                    </div>
                </div>

                <div v-if="student.emergency_contact_name">
                    <h4 class="mb-3 font-medium">Emergency Contact</h4>
                    <div class="space-y-2">
                        <p><span class="text-muted-foreground">Name:</span> {{ student.emergency_contact_name }}</p>
                        <p v-if="student.emergency_contact_phone">
                            <span class="text-muted-foreground">Phone:</span> {{ student.emergency_contact_phone }}
                        </p>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Academic Holds -->
    <Card v-if="activeHolds.length > 0">
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-red-600">
                <AlertTriangle class="h-5 w-5" />
                Active Academic Holds
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-4">
                <div v-for="hold in activeHolds" :key="hold.id" class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="font-medium text-red-900">{{ hold.title }}</h4>
                            <p class="mt-1 text-sm text-red-700">{{ hold.description }}</p>
                            <div class="mt-2 flex items-center gap-4 text-xs text-red-600">
                                <span>Type: {{ hold.hold_type.replace('_', ' ').toUpperCase() }}</span>
                                <span>Priority: {{ hold.priority.toUpperCase() }}</span>
                                <span>Placed: {{ formatDate(hold.placed_date) }}</span>
                            </div>
                        </div>
                        <Badge variant="destructive">{{ hold.priority.toUpperCase() }}</Badge>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Recent Course Registrations -->
    <Card v-if="recentRegistrations.length > 0">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <BookOpen class="h-5 w-5" />
                Recent Course Registrations
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-3">
                <div
                    v-for="registration in recentRegistrations"
                    :key="registration.id"
                    class="flex items-center justify-between rounded-lg border p-3"
                >
                    <div>
                        <p class="font-medium">{{ registration.course_offering?.course_title || 'Course Title' }}</p>
                        <p class="text-muted-foreground text-sm">
                            {{ registration.course_offering?.course_code || 'Course Code' }} • {{ registration.credit_hours }} credits
                        </p>
                    </div>
                    <div class="text-right">
                        <Badge :variant="registration.registration_status === 'completed' ? 'default' : 'outline'">
                            {{ registration.registration_status.replace('_', ' ').toUpperCase() }}
                        </Badge>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ formatDate(registration.registration_date) }}
                        </p>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Additional Information -->
    <Card v-if="student.high_school_name || student.entrance_exam_score || student.admission_notes">
        <CardHeader>
            <CardTitle>Additional Information</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div v-if="student.high_school_name" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <p class="text-muted-foreground text-sm">High School</p>
                    <p class="font-medium">{{ student.high_school_name }}</p>
                </div>
                <div v-if="student.high_school_graduation_year">
                    <p class="text-muted-foreground text-sm">Graduation Year</p>
                    <p class="font-medium">{{ student.high_school_graduation_year }}</p>
                </div>
            </div>

            <div v-if="student.entrance_exam_score">
                <p class="text-muted-foreground text-sm">Entrance Exam Score</p>
                <p class="font-medium">{{ student.entrance_exam_score }}%</p>
            </div>

            <div v-if="student.admission_notes">
                <p class="text-muted-foreground text-sm">Admission Notes</p>
                <p class="font-medium">{{ student.admission_notes }}</p>
            </div>
        </CardContent>
    </Card>
</template>
