<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { GraduationData, Student } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import GraduationTab from '../AcademicSummary/GraduationTab.vue';

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status'>;
    graduation: GraduationData;
}

const props = defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Graduation - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="graduation">
            <GraduationTab :graduation="graduation" :loading="false" />
        </StudentLayout>
    </div>
</template>
