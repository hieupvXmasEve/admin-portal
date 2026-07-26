<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { GraduationData, StudentHubContext } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import GraduationTab from '../AcademicSummary/GraduationTab.vue';

interface Props {
    student: StudentHubContext;
    graduation: GraduationData;
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Graduation - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="graduation">
            <GraduationTab :graduation="graduation" :loading="false" />
        </StudentLayout>
    </div>
</template>
