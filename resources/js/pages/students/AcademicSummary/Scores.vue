<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { ScoresData, Student } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import ScoresTab from '../AcademicSummary/ScoresTab.vue';

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status'>;
    scores: ScoresData;
}

const props = defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Scores - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="scores">
            <ScoresTab :scores="scores" :loading="false" />
        </StudentLayout>
    </div>
</template>
