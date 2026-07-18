<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { StudentOverview } from '@/types/models';
import { capitalizeFirst } from '@/utils/string';
import { useQRCode } from '@vueuse/integrations/useQRCode';
import { AlertTriangle, Award, BookOpen, Building, Calendar, GraduationCap, Info, Mail, MapPin, Phone, TrendingUp, User, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    overview: StudentOverview;
    loading?: boolean;
    /** False for view-only roles that receive a reduced read-only field set (ADR-0007). */
    canAct?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    canAct: true,
});
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

const formatGpa = (gpa: number | string | null | undefined) => {
    if (gpa === null || gpa === undefined || gpa === '') {
        return 'N/A';
    }
    const numGpa = typeof gpa === 'string' ? parseFloat(gpa) : gpa;
    if (isNaN(numGpa) || numGpa <= 0) {
        return 'N/A';
    }
    return parseFloat(numGpa.toFixed(2));
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

// Additional info folded from the retired Show page.
const hasAdditionalInfo = computed(() => {
    const info = props.overview.additional_info;
    return !!(info && (info.high_school_name || info.high_school_graduation_year || info.entrance_exam_score || info.admission_notes));
});

// Recent registrations folded from the retired Show page.
const recentRegistrations = computed(() => props.overview.recent_registrations ?? []);
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
            <!-- Reduced read-only view notice -->
            <Alert v-if="!canAct" class="mb-6">
                <Info class="h-4 w-4" />
                <AlertDescription> You're viewing a limited read-only profile. Full operational details are available to Academic Affairs staff. </AlertDescription>
            </Alert>

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

                                    <div v-if="overview.student_info.ethnicity" class="flex items-center gap-3">
                                        <Users class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                        <div>
                                            <p class="text-muted-foreground text-sm">Ethnicity</p>
                                            <p class="font-medium">{{ capitalizeFirst(overview.student_info.ethnicity) }}</p>
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
                                        <p class="text-muted-foreground text-sm font-semibold">Address of Permanent Residence (CCCD):</p>
                                        <div v-if="overview.student_info.cccd_address_line || overview.student_info.cccd_ward || overview.student_info.cccd_province" class="mt-1 space-y-1">
                                            <p v-if="overview.student_info.cccd_address_line" class="font-medium">{{ capitalizeFirst(overview.student_info.cccd_address_line) }}</p>
                                            <p v-if="overview.student_info.cccd_ward || overview.student_info.cccd_province" class="text-muted-foreground text-sm">
                                                {{ [capitalizeFirst(overview.student_info.cccd_ward), capitalizeFirst(overview.student_info.cccd_province)].filter(Boolean).join(', ') }}
                                            </p>
                                            <p v-if="overview.student_info.cccd_country" class="text-muted-foreground text-sm">{{ capitalizeFirst(overview.student_info.cccd_country) }}</p>
                                        </div>
                                        <p v-else-if="overview.student_info.cccd_address" class="font-medium">{{ overview.student_info.cccd_address }}</p>
                                        <p v-else class="text-muted-foreground font-medium">N/A</p>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-4">
                                <Separator class="mb-4" />
                                <div class="flex items-start gap-3">
                                    <MapPin class="text-muted-foreground mt-1 h-4 w-4 flex-shrink-0" />
                                    <div class="min-w-0 flex-1">
                                        <p class="text-muted-foreground text-sm font-semibold">Current Address:</p>
                                        <div v-if="overview.student_info.current_address_line || overview.student_info.current_ward || overview.student_info.current_province" class="mt-1 space-y-1">
                                            <p v-if="overview.student_info.current_address_line" class="font-medium">{{ capitalizeFirst(overview.student_info.current_address_line) }}</p>
                                            <p v-if="overview.student_info.current_ward || overview.student_info.current_province" class="text-muted-foreground text-sm">
                                                {{ [capitalizeFirst(overview.student_info.current_ward), capitalizeFirst(overview.student_info.current_province)].filter(Boolean).join(', ') }}
                                            </p>
                                            <p v-if="overview.student_info.current_country" class="text-muted-foreground text-sm">{{ capitalizeFirst(overview.student_info.current_country) }}</p>
                                        </div>
                                        <p v-else-if="overview.student_info.address" class="font-medium">{{ overview.student_info.address }}</p>
                                        <p v-else class="text-muted-foreground font-medium">N/A</p>
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

                            <!-- Guardian relationships and independent portal access -->
                            <div v-if="overview.student_info.guardians !== undefined" class="space-y-4 pt-4">
                                <Separator />
                                <div class="space-y-3">
                                    <h4 class="text-sm font-semibold">Guardians</h4>
                                    <p v-if="!overview.student_info.guardians?.length" class="text-muted-foreground text-sm">No Guardian relationships recorded.</p>
                                    <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div v-for="guardian in overview.student_info.guardians" :key="guardian.id" class="space-y-2 rounded-lg border p-3">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="truncate font-medium capitalize">{{ guardian.full_name }}</p>
                                                    <p class="text-muted-foreground text-sm capitalize">{{ guardian.relationship_type || 'Guardian' }}</p>
                                                </div>
                                                <Badge v-if="guardian.is_primary" variant="outline">Primary</Badge>
                                            </div>
                                            <div v-if="guardian.phone" class="text-muted-foreground flex items-center gap-2 text-sm">
                                                <Phone class="h-4 w-4" />
                                                <span>{{ guardian.phone }}</span>
                                            </div>
                                            <div v-if="guardian.email" class="text-muted-foreground flex min-w-0 items-center gap-2 text-sm">
                                                <Mail class="h-4 w-4 flex-shrink-0" />
                                                <span class="truncate">{{ guardian.email }}</span>
                                            </div>
                                            <Badge v-if="guardian.has_active_access" variant="secondary">Portal access active</Badge>
                                            <Badge v-else-if="guardian.can_receive_access" variant="outline">No portal access</Badge>
                                            <p v-else class="text-muted-foreground text-xs">No email — portal access unavailable</p>
                                        </div>
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

                            <div v-if="overview.academic_info.intake_school" class="flex items-center gap-3">
                                <BookOpen class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-muted-foreground text-sm">Intake University</p>
                                    <p class="font-medium">{{ overview.academic_info.intake_school.code }} - {{ overview.academic_info.intake_school.intake_year }}</p>
                                </div>
                            </div>
                            <div v-if="overview.academic_info.intake_major" class="flex items-center gap-3">
                                <BookOpen class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-muted-foreground text-sm">Intake Major</p>
                                    <p class="font-medium">{{ overview.academic_info.intake_major.code }}</p>
                                    <!-- <p class="font-medium">{{ overview.academic_info.intake_major.name }}</p> -->
                                </div>
                            </div>

                            <div v-if="overview.student_info.scholarship" class="flex items-center gap-3">
                                <Award class="text-muted-foreground h-4 w-4 flex-shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-muted-foreground text-sm">Scholarship</p>
                                    <p class="font-medium">{{ overview.student_info.scholarship.name }}</p>
                                    <div class="flex items-center gap-2">
                                        <p class="text-muted-foreground text-xs">{{ overview.student_info.scholarship.code }}</p>
                                        <span v-if="overview.student_info.scholarship.amount" class="text-muted-foreground text-xs">•</span>
                                        <p v-if="overview.student_info.scholarship.amount" class="text-xs font-medium text-green-600">
                                            <template v-if="overview.student_info.scholarship.type === 'percentage'"> {{ overview.student_info.scholarship.amount }}% </template>
                                            <template v-else> {{ Number(overview.student_info.scholarship.amount).toLocaleString('vi-VN') }} VND </template>
                                        </p>
                                    </div>
                                    <p v-if="overview.student_info.scholarship.awarded_at" class="text-muted-foreground text-xs">Awarded: {{ formatDate(overview.student_info.scholarship.awarded_at) }}</p>
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

            <!-- Additional Information (folded from the retired Show page) -->
            <Card v-if="hasAdditionalInfo" class="mt-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <GraduationCap class="h-5 w-5" />
                        Additional Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div v-if="overview.additional_info?.high_school_name">
                            <p class="text-muted-foreground text-sm">High School</p>
                            <p class="font-medium">{{ overview.additional_info.high_school_name }}</p>
                        </div>
                        <div v-if="overview.additional_info?.high_school_graduation_year">
                            <p class="text-muted-foreground text-sm">Graduation Year</p>
                            <p class="font-medium">{{ overview.additional_info.high_school_graduation_year }}</p>
                        </div>
                        <div v-if="overview.additional_info?.entrance_exam_score">
                            <p class="text-muted-foreground text-sm">Entrance Exam Score</p>
                            <p class="font-medium">{{ overview.additional_info.entrance_exam_score }}</p>
                        </div>
                        <div v-if="overview.additional_info?.admission_notes" class="md:col-span-2">
                            <p class="text-muted-foreground text-sm">Admission Notes</p>
                            <p class="font-medium">{{ overview.additional_info.admission_notes }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Course Registrations (folded from the retired Show page) -->
            <Card v-if="recentRegistrations.length > 0" class="mt-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <BookOpen class="h-5 w-5" />
                        Recent Course Registrations
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="registration in recentRegistrations" :key="registration.id" class="flex items-center justify-between gap-4 rounded-lg border p-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ registration.unit_name || 'N/A' }}</p>
                            <p class="text-muted-foreground text-sm">
                                <span v-if="registration.unit_code">{{ registration.unit_code }}</span>
                                <span v-if="registration.credit_points"> • {{ registration.credit_points }} credits</span>
                                <span v-if="registration.semester"> • {{ registration.semester }}</span>
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <Badge :variant="registration.registration_status === 'completed' ? 'default' : 'outline'">
                                {{ formatStatus(registration.registration_status) }}
                            </Badge>
                            <p class="text-muted-foreground mt-1 text-xs">{{ formatDate(registration.registration_date) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
