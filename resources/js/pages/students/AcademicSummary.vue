<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { Student, StudentAcademicSummary } from '@/types/models';
import { studentRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, BarChart3, BookOpen, Download, GraduationCap, Target, User, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import AttendanceTab from './AcademicSummary/AttendanceTab.vue';
import GpaTab from './AcademicSummary/GpaTab.vue';
import GraduationTab from './AcademicSummary/GraduationTab.vue';
import OverviewTab from './AcademicSummary/OverviewTab.vue';
import RegistrationsTab from './AcademicSummary/RegistrationsTab.vue';
import ScoresTab from './AcademicSummary/ScoresTab.vue';

interface Props {
    student: Student;
    academicSummary: StudentAcademicSummary;
}

const props = defineProps<Props>();
console.log(props.academicSummary.scores);

// Tab state management with URL parameters
const validTabs = ['overview', 'registrations', 'scores', 'attendance', 'gpa', 'graduation'] as const;
type ValidTab = (typeof validTabs)[number];

// Get current tab from URL parameters
const getCurrentTabFromURL = (): ValidTab => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab') as ValidTab;
    return validTabs.includes(tabParam) ? tabParam : 'overview';
};

// Reactive tab state
const currentTab = ref<ValidTab>(getCurrentTabFromURL());
// Handle tab change
const handleTabChange = (newTab: string | number) => {
    const tabValue = String(newTab) as ValidTab;
    // Validate the tab value before setting
    if (validTabs.includes(tabValue)) {
        currentTab.value = tabValue;
        updateTabInURL(tabValue);
    }
};

// Update URL when tab changes
const updateTabInURL = (newTab: ValidTab) => {
    const url = new URL(window.location.href);

    if (newTab === 'overview') {
        // Remove tab parameter for default tab to keep URL clean
        url.searchParams.delete('tab');
    } else {
        // Remove all params except tab
        url.searchParams.set('tab', newTab);
    }

    // Update URL without page reload using Inertia.js
    router.visit(url.pathname + url.search, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: [], // Don't reload any props
    });
};
const loading = ref(false);

const goBack = () => {
    router.visit(studentRoutes.list());
};

const exportSummary = () => {
    // TODO: Implement export functionality
    console.log('Export academic summary for student:', props.student.id);
};

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

const formatStatus = (status: string) => {
    return status.replace(/_/g, ' ').toUpperCase();
};
</script>

<template>
    <div>
        <Head :title="`Academic Summary - ${student.full_name}`" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="sm" @click="goBack" class="flex items-center gap-2">
                        <ArrowLeft class="h-4 w-4" />
                        Back to Student
                    </Button>
                    <div>
                        <h1 class="text-2xl font-bold">Academic Summary</h1>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-muted-foreground">{{ student.full_name }}</span>
                            <Badge :variant="getStatusBadgeVariant(student.status) as any">
                                {{ formatStatus(student.status) }}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="exportSummary" class="flex items-center gap-2">
                        <Download class="h-4 w-4" />
                        Export
                    </Button>
                </div>
            </div>

            <!-- Main Content -->
            <Card>
                <CardContent class="p-0">
                    <Tabs v-model="currentTab" @update:model-value="handleTabChange" class="w-full">
                        <CardHeader class="pb-0">
                            <TabsList class="grid w-full grid-cols-6">
                                <TabsTrigger value="overview" class="flex items-center gap-2">
                                    <User class="h-4 w-4" />
                                    <span class="hidden sm:inline">Overview</span>
                                </TabsTrigger>
                                <TabsTrigger value="registrations" class="flex items-center gap-2">
                                    <BookOpen class="h-4 w-4" />
                                    <span class="hidden sm:inline">Registrations</span>
                                </TabsTrigger>
                                <TabsTrigger value="scores" class="flex items-center gap-2">
                                    <Target class="h-4 w-4" />
                                    <span class="hidden sm:inline">Scores</span>
                                </TabsTrigger>
                                <TabsTrigger value="attendance" class="flex items-center gap-2">
                                    <Users class="h-4 w-4" />
                                    <span class="hidden sm:inline">Attendance</span>
                                </TabsTrigger>
                                <TabsTrigger value="gpa" class="flex items-center gap-2">
                                    <BarChart3 class="h-4 w-4" />
                                    <span class="hidden sm:inline">GPA</span>
                                </TabsTrigger>
                                <TabsTrigger value="graduation" class="flex items-center gap-2">
                                    <GraduationCap class="h-4 w-4" />
                                    <span class="hidden sm:inline">Graduation</span>
                                </TabsTrigger>
                            </TabsList>
                        </CardHeader>

                        <div class="p-6">
                            <!-- Overview Tab -->
                            <TabsContent value="overview" class="mt-0" data-testid="overview-tab">
                                <OverviewTab :overview="academicSummary.overview" :loading="loading" />
                            </TabsContent>

                            <!-- Registrations Tab -->
                            <TabsContent value="registrations" class="mt-0" data-testid="registrations-tab">
                                <RegistrationsTab :registrations="academicSummary.registrations" :student-id="student.id" />
                            </TabsContent>

                            <!-- Scores Tab -->
                            <TabsContent value="scores" class="mt-0" data-testid="scores-tab">
                                <ScoresTab :scores="academicSummary.scores" :loading="loading" />
                            </TabsContent>

                            <!-- Attendance Tab -->
                            <TabsContent value="attendance" class="mt-0" data-testid="attendance-tab">
                                <AttendanceTab :attendance="academicSummary.attendance" :loading="loading" />
                            </TabsContent>

                            <!-- GPA Tab -->
                            <TabsContent value="gpa" class="mt-0" data-testid="gpa-tab">
                                <GpaTab :gpa="academicSummary.gpa" :loading="loading" />
                            </TabsContent>

                            <!-- Graduation Tab -->
                            <TabsContent value="graduation" class="mt-0" data-testid="graduation-tab">
                                <GraduationTab :graduation="academicSummary.graduation" :loading="loading" />
                            </TabsContent>
                        </div>
                    </Tabs>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

<style scoped>
/* Custom styles for better mobile responsiveness */
@media (max-width: 640px) {
    .grid-cols-6 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .grid-cols-6 > :nth-child(n + 4) {
        grid-column: span 1;
        margin-top: 0.5rem;
    }
}
</style>
