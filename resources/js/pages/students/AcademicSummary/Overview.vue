<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { Student, StudentOverview } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import OverviewTab from '../AcademicSummary/OverviewTab.vue';

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status' | 'email' | 'intake'>;
    overview: StudentOverview;
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Overview - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="overview">
            <OverviewTab :overview="overview" :loading="false" />
        </StudentLayout>
    </div>
</template>
