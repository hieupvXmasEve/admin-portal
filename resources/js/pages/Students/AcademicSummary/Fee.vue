<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { Student } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import FeeTab from './FeeTab.vue';

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status' | 'email' | 'intake'>;
    feeSummary: any;
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Fee - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="fees">
            <FeeTab :fee-summary="feeSummary" :student="student" :loading="false" />
        </StudentLayout>
    </div>
</template>
