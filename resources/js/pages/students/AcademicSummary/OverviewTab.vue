<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { StudentOverview } from '@/types/models';
import { useQRCode } from '@vueuse/integrations/useQRCode';
import { AlertTriangle, Award, BookOpen, Building, Calendar, GraduationCap, Mail, MapPin, Phone, TrendingUp, User, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    overview: StudentOverview;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});
console.log('%c props overview', 'color: red', props.overview);
const qrcode = useQRCode(props.overview.student_info.student_id);
// Status badge styling
const getStatusBadgeVariant = (status: string) => {
    const variants: Record<string, string> = {
        admitted: 'secondary',
        enrolled: 'default',
        active: 'default',
        inactive: 'outline',
        on_leave: 'outline',
        suspended: 'destructive',
        graduated: 'secondary',
        dropped_out: 'outline',
    };
    return variants[status] || 'outline';
};

const getAcademicStandingColor = (standing: string) => {
    const colors: Record<string, string> = {
        excellent: 'text-green-600',
        good_standing: 'text-green-600',
        satisfactory: 'text-blue-600',
        warning: 'text-yellow-600',
        probation: 'text-red-600',
        suspension: 'text-red-600',
        unknown: 'text-gray-600',
    };
    return colors[standing] || 'text-gray-600';
};

const formatDate = (date: string | null | undefined) => {
    if (!date) return 'Not set';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatStatus = (status: string) => {
    return status.replace(/_/g, ' ').toUpperCase();
};

const formatGpa = (gpa: number) => {
    return gpa > 0 ? gpa.toFixed(2) : 'N/A';
};

// Academic progress calculation
const academicProgress = computed(() => {
    const { total_registrations, completed_courses } = props.overview.academic_stats;
    if (total_registrations === 0) return 0;
    return Math.round((completed_courses / total_registrations) * 100);
});

// Credit completion percentage
const creditProgress = computed(() => {
    const { total_credits_earned, total_credits_attempted } = props.overview.academic_stats;
    if (total_credits_attempted === 0) return 0;
    return Math.round((total_credits_earned / total_credits_attempted) * 100);
});

// Check if student has concerning status
const hasAcademicConcerns = computed(() => {
    const { academic_standing, active_holds } = props.overview.academic_stats;
    return active_holds > 0 || ['warning', 'probation', 'suspension'].includes(academic_standing);
});
</script>

<template>
    <div class="space-y-6">
        <!-- Loading State -->
        <div v-if="loading" class="space-y-4">
            <div class="animate-pulse">
                <div class="mb-4 h-8 w-1/3 rounded bg-gray-200"></div>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div v-for="i in 4" :key="i" class="h-24 rounded bg-gray-200"></div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div v-else>
            <!-- Student Header -->
            <div class="mb-6 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                <!-- <StudentAvatar :student-id="overview.student_info.id" :current-avatar="overview.student_info.avatar_url" size="xl" /> -->
                <!-- Show avatar -->
                <div class="bg-primary/10 flex size-20 flex-shrink-0 items-center justify-center rounded-full">
                    <!-- <img :src="overview.student_info.avatar_url" alt="Student Avatar" class="h-full w-full rounded-full object-cover" /> -->
                    <Avatar class="size-20">
                        <AvatarImage :src="overview.student_info.avatar_url || '/placeholder.svg'" alt="Student Avatar" />
                        <AvatarFallback>Asia</AvatarFallback>
                    </Avatar>
                </div>
                <div class="">
                    <h2 class="text-2xl font-bold">{{ overview.student_info.full_name }}</h2>
                    <p class="text-muted-foreground text-lg">{{ overview.student_info.student_id }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <Badge :variant="getStatusBadgeVariant(overview.student_info.status) as any">
                            {{ formatStatus(overview.student_info.status) }}
                        </Badge>
                        <Badge v-if="overview.student_info.academic_status" variant="outline" :class="getAcademicStandingColor(overview.academic_stats.academic_standing)">
                            {{ formatStatus(overview.academic_stats.academic_standing) }}
                        </Badge>
                    </div>
                </div>
                <div>
                    <img :src="qrcode" alt="QR Code" class="border" />
                </div>
            </div>

            <!-- Academic Concerns Alert -->
            <Alert v-if="hasAcademicConcerns" variant="destructive" class="mb-6">
                <AlertTriangle class="h-4 w-4" />
                <AlertDescription>
                    <span v-if="overview.academic_stats.active_holds > 0"> This student has {{ overview.academic_stats.active_holds }} active hold(s). </span>
                    <span v-if="['warning', 'probation', 'suspension'].includes(overview.academic_stats.academic_standing)"> Academic standing: {{ formatStatus(overview.academic_stats.academic_standing) }}. </span>
                    Please review their academic status.
                </AlertDescription>
            </Alert>

            <!-- Quick Stats Cards -->
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Total Courses</p>
                                <p class="text-2xl font-bold">{{ overview.academic_stats.total_registrations }}</p>
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
                                <p class="text-2xl font-bold">{{ overview.academic_stats.total_credits_earned }}</p>
                            </div>
                            <Award class="h-8 w-8 text-green-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Current GPA</p>
                                <p class="text-2xl font-bold">{{ formatGpa(overview.academic_stats.current_gpa) }}</p>
                            </div>
                            <TrendingUp class="h-8 w-8 text-purple-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Active Holds</p>
                                <p class="text-2xl font-bold" :class="overview.academic_stats.active_holds > 0 ? 'text-red-600' : 'text-green-600'">
                                    {{ overview.academic_stats.active_holds }}
                                </p>
                            </div>
                            <AlertTriangle :class="overview.academic_stats.active_holds > 0 ? 'text-red-600' : 'text-green-600'" class="h-8 w-8" />
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
                                        <Mail class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <div class="min-w-0 flex-1">
                                            <p class="text-muted-foreground text-sm">Email</p>
                                            <p class="truncate font-medium">{{ overview.student_info.email }}</p>
                                        </div>
                                    </div>

                                    <div v-if="overview.student_info.phone" class="flex items-center gap-3">
                                        <Phone class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <div>
                                            <p class="text-muted-foreground text-sm">Phone</p>
                                            <p class="font-medium">{{ overview.student_info.phone }}</p>
                                        </div>
                                    </div>

                                    <div v-if="overview.student_info.date_of_birth" class="flex items-center gap-3">
                                        <Calendar class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <div>
                                            <p class="text-muted-foreground text-sm">Date of Birth</p>
                                            <p class="font-medium">{{ formatDate(overview.student_info.date_of_birth) }}</p>
                                        </div>
                                    </div>

                                    <div v-if="overview.student_info.gender" class="flex items-center gap-3">
                                        <Users class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <div>
                                            <p class="text-muted-foreground text-sm">Gender</p>
                                            <p class="font-medium capitalize">{{ overview.student_info.gender }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div v-if="overview.student_info.nationality" class="flex items-center gap-3">
                                        <MapPin class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <div>
                                            <p class="text-muted-foreground text-sm">Nationality</p>
                                            <p class="font-medium">{{ overview.student_info.nationality }}</p>
                                        </div>
                                    </div>

                                    <div v-if="overview.student_info.national_id" class="flex items-center gap-3">
                                        <div class="text-muted-foreground h-4 w-4 flex-shrink-0 text-xs font-bold">ID</div>
                                        <div>
                                            <p class="text-muted-foreground text-sm">National ID</p>
                                            <p class="font-medium">{{ overview.student_info.national_id }}</p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <Calendar class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <div>
                                            <p class="text-muted-foreground text-sm">Admission Date</p>
                                            <p class="font-medium">{{ formatDate(overview.student_info.admission_date) }}</p>
                                        </div>
                                    </div>

                                    <div v-if="overview.student_info.expected_graduation_date" class="flex items-center gap-3">
                                        <GraduationCap class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <div>
                                            <p class="text-muted-foreground text-sm">Expected Graduation</p>
                                            <p class="font-medium">{{ formatDate(overview.student_info.expected_graduation_date) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4">
                                <Separator class="mb-4" />
                                <div class="flex items-start gap-3">
                                    <MapPin class="text-muted-foreground mt-1 h-4 w-4 flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-muted-foreground text-sm">Address of Permanent Residence:</p>
                                        <p class="font-medium">{{ overview.student_info.cccd_address || 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-4">
                                <Separator class="mb-4" />
                                <div class="flex items-start gap-3">
                                    <MapPin class="text-muted-foreground mt-1 h-4 w-4 flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-muted-foreground text-sm">Current Address</p>
                                        <p class="font-medium">{{ overview.student_info.address || 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4 pt-4">
                                <Separator />
                                <div class="flex items-start gap-3">
                                    <User class="text-muted-foreground mt-1 h-4 w-4 flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-muted-foreground text-sm">Emergency Name 1</p>
                                        <p class="font-medium">{{ overview.student_info.emergency_contact_name || 'N/A' }}</p>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-muted-foreground text-sm">Emergency Phone 1</p>
                                        <p class="font-medium">{{ overview.student_info.emergency_contact_phone || 'N/A' }}</p>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-muted-foreground text-sm">Emergency Email 1</p>
                                        <p class="font-medium">{{ overview.student_info.emergency_contact_email || 'N/A' }}</p>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-muted-foreground text-sm">Emergency Relationship 1</p>
                                        <Badge class="uppercase">{{ overview.student_info.emergency_contact_relationship || 'N/A' }}</Badge>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <User class="text-muted-foreground mt-1 h-4 w-4 flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-muted-foreground text-sm">Emergency Name 2</p>
                                        <p class="font-medium">{{ overview.student_info.emergency_contact_name_1 || 'N/A' }}</p>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-muted-foreground text-sm">Emergency Phone 2</p>
                                        <p class="font-medium">{{ overview.student_info.emergency_contact_phone_1 || 'N/A' }}</p>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-muted-foreground text-sm">Emergency Email 2</p>
                                        <p class="font-medium">{{ overview.student_info.emergency_contact_email_1 || 'N/A' }}</p>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-muted-foreground text-sm">Emergency Relationship 2</p>
                                        <Badge class="uppercase">{{ overview.student_info.emergency_contact_relationship_1 || 'N/A' }}</Badge>
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
                            <div v-if="overview.program_info.campus" class="flex items-center gap-3">
                                <Building class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-muted-foreground text-sm">Campus</p>
                                    <p class="font-medium">{{ overview.program_info.campus.name }}</p>
                                    <p class="text-muted-foreground text-xs">{{ overview.program_info.campus.code }}</p>
                                </div>
                            </div>

                            <div v-if="overview.program_info.program" class="flex items-center gap-3">
                                <BookOpen class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-muted-foreground text-sm">Program</p>
                                    <p class="font-medium">{{ overview.program_info.program.name }}</p>
                                    <p class="text-muted-foreground text-xs">{{ overview.program_info.program.code }}</p>
                                </div>
                            </div>

                            <div v-if="overview.program_info.specialization" class="flex items-center gap-3">
                                <GraduationCap class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-muted-foreground text-sm">Specialization</p>
                                    <p class="font-medium">{{ overview.program_info.specialization.name }}</p>
                                    <p class="text-muted-foreground text-xs">{{ overview.program_info.specialization.code }}</p>
                                </div>
                            </div>

                            <div v-if="overview.program_info.curriculum_version" class="flex items-center gap-3">
                                <BookOpen class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-muted-foreground text-sm">Curriculum Version</p>
                                    <p class="font-medium">{{ overview.program_info.curriculum_version.version_code }}</p>
                                </div>
                            </div>

                            <div v-if="overview.program_info.semester" class="flex items-center gap-3">
                                <BookOpen class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-muted-foreground text-sm">Intake Year</p>
                                    <p class="font-medium">{{ overview.program_info.semester.code }}</p>
                                    <p class="font-medium">{{ overview.program_info.semester.intake_year }}</p>
                                </div>
                            </div>
                            <div v-if="overview.program_info.semester" class="flex items-center gap-3">
                                <BookOpen class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-muted-foreground text-sm">Intake Course</p>
                                    <p class="font-medium">{{ overview.program_info.semester.intake_year }}</p>
                                </div>
                            </div>

                            <!-- Academic Progress -->
                            <div class="pt-4">
                                <Separator class="mb-4" />
                                <div class="space-y-4">
                                    <!-- Course Progress -->
                                    <div>
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-muted-foreground text-sm">Course Progress</p>
                                            <p class="text-sm font-medium">{{ academicProgress }}%</p>
                                        </div>
                                        <div class="h-2 w-full rounded-full bg-gray-200">
                                            <div class="bg-primary h-2 rounded-full transition-all duration-300" :style="{ width: `${academicProgress}%` }"></div>
                                        </div>
                                        <p class="text-muted-foreground mt-1 text-xs">{{ overview.academic_stats.completed_courses }} of {{ overview.academic_stats.total_registrations }} courses completed</p>
                                    </div>

                                    <!-- Credit Progress -->
                                    <div>
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-muted-foreground text-sm">Credit Progress</p>
                                            <p class="text-sm font-medium">{{ creditProgress }}%</p>
                                        </div>
                                        <div class="h-2 w-full rounded-full bg-gray-200">
                                            <div class="h-2 rounded-full bg-green-500 transition-all duration-300" :style="{ width: `${creditProgress}%` }"></div>
                                        </div>
                                        <p class="text-muted-foreground mt-1 text-xs">{{ overview.academic_stats.total_credits_earned }} of {{ overview.academic_stats.total_credits_attempted }} credits earned</p>
                                    </div>

                                    <!-- GPA Information -->
                                    <div class="grid grid-cols-2 gap-4 pt-2">
                                        <div class="text-center">
                                            <p class="text-muted-foreground text-xs">Current GPA</p>
                                            <p class="text-lg font-bold">{{ formatGpa(overview.academic_stats.current_gpa) }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-muted-foreground text-xs">Cumulative GPA</p>
                                            <p class="text-lg font-bold">{{ formatGpa(overview.academic_stats.cumulative_gpa) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
