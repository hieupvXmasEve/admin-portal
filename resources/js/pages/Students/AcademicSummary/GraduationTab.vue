<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';
import type { GraduationData, RequirementStatus } from '@/types/models';
import { AlertTriangle, Award, BookOpen, Calendar, CheckCircle, Clock, GraduationCap, Target, TrendingUp, Users, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    graduation: GraduationData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

// Requirement status styling
const getRequirementStatusIcon = (status: string) => {
    const icons: Record<string, any> = {
        completed: CheckCircle,
        in_progress: Clock,
        pending: Target,
        not_started: XCircle,
    };
    return icons[status] || XCircle;
};

const getRequirementStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        completed: 'text-green-600',
        in_progress: 'text-blue-600',
        pending: 'text-yellow-600',
        not_started: 'text-red-600',
    };
    return colors[status] || 'text-gray-600';
};

const getRequirementStatusBadge = (status: string) => {
    const variants: Record<string, string> = {
        completed: 'default',
        in_progress: 'secondary',
        pending: 'outline',
        not_started: 'destructive',
    };
    return variants[status] || 'outline';
};

const getRiskLevelColor = (level: string) => {
    const colors: Record<string, string> = {
        low: 'text-green-600',
        medium: 'text-yellow-600',
        high: 'text-red-600',
    };
    return colors[level] || 'text-gray-600';
};

const getRiskLevelBadge = (level: string) => {
    const variants: Record<string, string> = {
        low: 'default',
        medium: 'outline',
        high: 'destructive',
    };
    return variants[level] || 'outline';
};

const formatDate = (date: string | undefined) => {
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

const formatRisk = (risk: string) => {
    const riskLabels: Record<string, string> = {
        low_credit_completion: 'Low Credit Completion',
        internship_pending: 'Internship Pending',
        thesis_pending: 'Thesis Pending',
        english_requirement_pending: 'English Requirement Pending',
    };
    return riskLabels[risk] || risk.replace(/_/g, ' ').toUpperCase();
};

// Calculate requirement completion percentage
const getRequirementProgress = (requirement: RequirementStatus) => {
    if (typeof requirement.required === 'boolean') {
        return requirement.completed ? 100 : 0;
    }
    if (typeof requirement.required === 'number' && typeof requirement.earned === 'number') {
        return Math.min((requirement.earned / requirement.required) * 100, 100);
    }
    return 0;
};

// Check if graduation is at risk
const isGraduationAtRisk = computed(() => {
    return props.graduation.graduation_status.risk_level === 'high' || props.graduation.graduation_status.risks.length > 2;
});

// Calculate overall completion percentage
const overallProgress = computed(() => {
    const requirements = props.graduation.requirements;
    const totalRequirements = Object.keys(requirements).length;
    const completedRequirements = Object.values(requirements).filter((req) => req.status === 'completed').length;
    return Math.round((completedRequirements / totalRequirements) * 100);
});

// Get requirement display info
const getRequirementDisplayInfo = (key: string) => {
    const info: Record<string, { title: string; description: string }> = {
        core_credits: {
            title: 'Core Credits',
            description: 'Required core curriculum credits',
        },
        elective_credits: {
            title: 'Elective Credits',
            description: 'Required elective credits',
        },
        internship: {
            title: 'Internship',
            description: 'Practical work experience requirement',
        },
        thesis: {
            title: 'Thesis/Capstone',
            description: 'Final project or thesis requirement',
        },
        english_requirement: {
            title: 'English Proficiency',
            description: 'English language proficiency requirement',
        },
    };
    return info[key] || { title: key, description: 'Academic requirement' };
};
</script>

<template>
    <div class="space-y-6">
        <!-- Loading State -->
        <div v-if="loading" class="space-y-4">
            <div class="animate-pulse">
                <div class="mb-4 h-8 w-1/3 rounded bg-gray-200"></div>
                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div v-for="i in 4" :key="i" class="h-24 rounded bg-gray-200"></div>
                </div>
                <div class="space-y-4">
                    <div v-for="i in 3" :key="i" class="h-32 rounded bg-gray-200"></div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div v-else>
            <!-- Graduation Risk Alert -->
            <Alert v-if="isGraduationAtRisk" variant="destructive" class="mb-6">
                <AlertTriangle class="h-4 w-4" />
                <AlertDescription>
                    <strong>Graduation Risk Alert:</strong>
                    Risk level: {{ formatStatus(graduation.graduation_status.risk_level) }}. {{ graduation.graduation_status.risks.length }} risk factor(s) identified. Please review graduation requirements and timeline.
                </AlertDescription>
            </Alert>

            <!-- Graduation Readiness Alert -->
            <Alert v-else-if="graduation.graduation_status.ready_to_graduate" class="mb-6">
                <CheckCircle class="h-4 w-4" />
                <AlertDescription>
                    <strong>Graduation Ready:</strong>
                    All requirements have been met. Student is eligible for graduation.
                </AlertDescription>
            </Alert>

            <!-- Summary Cards -->
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Credits Earned</p>
                                <p class="text-2xl font-bold text-green-600">{{ graduation.credit_summary.total_earned }}</p>
                                <p class="text-muted-foreground text-xs">of {{ graduation.credit_summary.total_required }}</p>
                            </div>
                            <Award class="h-8 w-8 text-green-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Completion</p>
                                <p class="text-2xl font-bold text-blue-600">{{ graduation.credit_summary.completion_percentage.toFixed(1) }}%</p>
                            </div>
                            <TrendingUp class="h-8 w-8 text-blue-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Credits Remaining</p>
                                <p class="text-2xl font-bold" :class="graduation.credit_summary.remaining === 0 ? 'text-green-600' : 'text-yellow-600'">
                                    {{ graduation.credit_summary.remaining }}
                                </p>
                            </div>
                            <BookOpen class="h-8 w-8 text-yellow-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Risk Level</p>
                                <Badge :variant="getRiskLevelBadge(graduation.graduation_status.risk_level) as any">
                                    {{ formatStatus(graduation.graduation_status.risk_level) }}
                                </Badge>
                            </div>
                            <AlertTriangle :class="getRiskLevelColor(graduation.graduation_status.risk_level)" class="h-8 w-8" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Credit Progress -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <TrendingUp class="h-5 w-5" />
                        Credit Progress
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div>
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-medium">Overall Progress</span>
                                <span class="text-muted-foreground text-sm"> {{ graduation.credit_summary.total_earned }}/{{ graduation.credit_summary.total_required }} credits </span>
                            </div>
                            <Progress :value="graduation.credit_summary.completion_percentage" class="h-3" />
                            <p class="text-muted-foreground mt-1 text-xs">{{ graduation.credit_summary.completion_percentage.toFixed(1) }}% complete</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 pt-4 md:grid-cols-3">
                            <div class="bg-muted rounded-lg p-4 text-center">
                                <p class="text-muted-foreground text-sm">Total Required</p>
                                <p class="text-2xl font-bold">{{ graduation.credit_summary.total_required }}</p>
                            </div>
                            <div class="bg-muted rounded-lg p-4 text-center">
                                <p class="text-muted-foreground text-sm">Earned</p>
                                <p class="text-2xl font-bold text-green-600">{{ graduation.credit_summary.total_earned }}</p>
                            </div>
                            <div class="bg-muted rounded-lg p-4 text-center">
                                <p class="text-muted-foreground text-sm">Remaining</p>
                                <p class="text-2xl font-bold" :class="graduation.credit_summary.remaining === 0 ? 'text-green-600' : 'text-yellow-600'">
                                    {{ graduation.credit_summary.remaining }}
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Requirements Tracking -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <CheckCircle class="h-5 w-5" />
                            Graduation Requirements
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div v-for="(requirement, key) in graduation.requirements" :key="key" class="rounded-lg border p-4">
                                <div class="mb-3 flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <component :is="getRequirementStatusIcon(requirement.status)" :class="getRequirementStatusColor(requirement.status)" class="h-5 w-5 flex-shrink-0" />
                                        <div>
                                            <h4 class="font-medium">{{ getRequirementDisplayInfo(key).title }}</h4>
                                            <p class="text-muted-foreground text-sm">
                                                {{ getRequirementDisplayInfo(key).description }}
                                            </p>
                                        </div>
                                    </div>
                                    <Badge :variant="getRequirementStatusBadge(requirement.status) as any">
                                        {{ formatStatus(requirement.status) }}
                                    </Badge>
                                </div>

                                <!-- Progress for credit requirements -->
                                <div v-if="typeof requirement.required === 'number'">
                                    <div class="mb-2 flex items-center justify-between">
                                        <span class="text-sm">Progress</span>
                                        <span class="text-muted-foreground text-sm"> {{ requirement.earned || 0 }}/{{ requirement.required }} credits </span>
                                    </div>
                                    <Progress :value="getRequirementProgress(requirement)" class="h-2" />
                                </div>

                                <!-- Status for boolean requirements -->
                                <div v-else-if="typeof requirement.required === 'boolean'" class="text-sm">
                                    <span class="text-muted-foreground">Status: </span>
                                    <span :class="requirement.completed ? 'text-green-600' : 'text-red-600'">
                                        {{ requirement.completed ? 'Completed' : 'Not Completed' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Overall Requirements Progress -->
                            <Separator />
                            <div class="pt-2">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="font-medium">Overall Requirements</span>
                                    <span class="text-muted-foreground text-sm"> {{ Object.values(graduation.requirements).filter((req) => req.status === 'completed').length }}/{{ Object.keys(graduation.requirements).length }} completed </span>
                                </div>
                                <Progress :value="overallProgress" class="h-2" />
                                <p class="text-muted-foreground mt-1 text-xs">{{ overallProgress }}% of requirements completed</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Timeline and Status -->
                <div class="space-y-6">
                    <!-- Current Status -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <GraduationCap class="h-5 w-5" />
                                Graduation Status
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div class="bg-muted flex items-center justify-between rounded-lg p-3">
                                    <span class="font-medium">Ready to Graduate</span>
                                    <div class="flex items-center gap-2">
                                        <component :is="graduation.graduation_status.ready_to_graduate ? CheckCircle : XCircle" :class="graduation.graduation_status.ready_to_graduate ? 'text-green-600' : 'text-red-600'" class="h-5 w-5" />
                                        <Badge :variant="graduation.graduation_status.ready_to_graduate ? 'default' : 'destructive'">
                                            {{ graduation.graduation_status.ready_to_graduate ? 'Yes' : 'No' }}
                                        </Badge>
                                    </div>
                                </div>

                                <div v-if="graduation.graduation_status.expected_graduation">
                                    <p class="text-muted-foreground text-sm">Expected Graduation</p>
                                    <p class="font-medium">{{ formatDate(graduation.graduation_status.expected_graduation) }}</p>
                                </div>

                                <div>
                                    <p class="text-muted-foreground text-sm">Risk Level</p>
                                    <Badge :variant="getRiskLevelBadge(graduation.graduation_status.risk_level) as any">
                                        {{ formatStatus(graduation.graduation_status.risk_level) }}
                                    </Badge>
                                </div>

                                <!-- Risk Factors -->
                                <div v-if="graduation.graduation_status.risks.length > 0">
                                    <p class="text-muted-foreground mb-2 text-sm">Risk Factors</p>
                                    <div class="space-y-1">
                                        <Badge v-for="risk in graduation.graduation_status.risks" :key="risk" variant="outline" class="mr-2 mb-1">
                                            {{ formatRisk(risk) }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Timeline -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                Timeline
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <!-- Current Semester -->
                                <div class="rounded-lg border p-3">
                                    <div class="mb-2 flex items-center gap-2">
                                        <Users class="h-4 w-4 text-blue-600" />
                                        <span class="font-medium">Current Semester</span>
                                    </div>
                                    <div class="ml-6 space-y-1">
                                        <p class="text-sm">
                                            <span class="text-muted-foreground">Semester: </span>
                                            {{ graduation.progress_timeline.current_semester.semester || 'Not enrolled' }}
                                        </p>
                                        <p class="text-sm">
                                            <span class="text-muted-foreground">Enrolled Credits: </span>
                                            {{ graduation.progress_timeline.current_semester.enrolled_credits }}
                                        </p>
                                        <Badge :variant="graduation.progress_timeline.current_semester.status === 'enrolled' ? 'default' : 'outline'">
                                            {{ formatStatus(graduation.progress_timeline.current_semester.status) }}
                                        </Badge>
                                    </div>
                                </div>

                                <!-- Projected Completion -->
                                <div class="rounded-lg border p-3">
                                    <div class="mb-2 flex items-center gap-2">
                                        <Target class="h-4 w-4 text-purple-600" />
                                        <span class="font-medium">Projected Completion</span>
                                    </div>
                                    <div class="ml-6 space-y-1">
                                        <p class="text-sm">
                                            <span class="text-muted-foreground">Semesters Remaining: </span>
                                            {{ graduation.progress_timeline.projected_completion.semesters_remaining }}
                                        </p>
                                        <p class="text-sm">
                                            <span class="text-muted-foreground">Projected Date: </span>
                                            {{ formatDate(graduation.progress_timeline.projected_completion.projected_date) }}
                                        </p>
                                        <Badge :variant="graduation.progress_timeline.projected_completion.on_track ? 'default' : 'destructive'">
                                            {{ graduation.progress_timeline.projected_completion.on_track ? 'On Track' : 'Behind Schedule' }}
                                        </Badge>
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
