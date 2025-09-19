<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { GpaData, Student } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import GpaTab from '../AcademicSummary/GpaTab.vue';

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status'>;
    gpa: GpaData;
}

const props = defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - GPA - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="gpa">
            <GpaTab :gpa="gpa" :loading="false" />
        </StudentLayout>
    </div>
</template>
