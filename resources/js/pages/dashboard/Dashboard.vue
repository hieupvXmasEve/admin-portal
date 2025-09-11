<script setup lang="ts">
import Alerts from '@/components/dashboard/Alerts.vue';
import ChartStudentByProgram from '@/components/dashboard/ChartStudentByProgram.vue';
import CurrentSemesterWidget from '@/components/dashboard/CurrentSemesterWidget.vue';
import StatCards from '@/components/dashboard/StatCards.vue';
import { Head } from '@inertiajs/vue3';

interface DashboardStats {
    students: { total: number; by_status: Record<string, number> };
    lecturers: { total: number; by_employment_type: Record<string, number> };
    academics: { programs: number; specializations: number; active_curriculum_versions: number };
    semester: null | {
        id: number;
        code: string;
        name: string;
        start_date: string | null;
        end_date: string | null;
        enrollment_start_date: string | null;
        enrollment_end_date: string | null;
        is_registration_open: boolean;
    };
    rooms: { total: number; available: number; occupied: number; maintenance: number };
}

interface AlertItem {
    id: number;
    type: 'academic_hold' | 'attendance' | 'program_change';
    severity: 'high' | 'medium' | 'low';
    title: string;
    message: string;
    count?: number;
    created_at: string;
}

const props = defineProps<{ stats: DashboardStats; alerts: AlertItem[] }>();
</script>

<template>
    <Head title="Dashboard" />

    <div class="dashboard-container space-y-6">
        <!-- Stats Cards -->
        <StatCards :stats="props.stats" />
        <CurrentSemesterWidget :semester="props.stats.semester" />
        <!-- Alerts Row -->
        <Alerts :alerts="props.alerts" />

        <!-- Charts Section -->
        <div class="space-y-6">
            <!-- Student Distribution and Enrollment Growth -->

            <ChartStudentByProgram />
            <!-- <ChartEnrollmentGrowth /> -->

            <!-- Academic Standing and Graduation Rates -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- <ChartAcademicStanding /> -->
                <!-- <ChartGraduationRate /> -->
            </div>
        </div>

        <!-- Legacy Enrollment Trend (keep for comparison) -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- <RecentActivities /> -->
        </div>
    </div>
</template>
