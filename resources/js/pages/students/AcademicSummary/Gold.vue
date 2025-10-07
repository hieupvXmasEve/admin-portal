<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { Student } from '@/types/models';
import type { StudentGoldTabData } from '@/types/wallet';
import { Head } from '@inertiajs/vue3';
import GoldTab from '../AcademicSummary/GoldTab.vue';

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status' | 'email'>;
    gold: StudentGoldTabData;
}
defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Gold - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="gold">
            <GoldTab :gold="gold" :student-id="student.id" :loading="false" />
        </StudentLayout>
    </div>
</template>
