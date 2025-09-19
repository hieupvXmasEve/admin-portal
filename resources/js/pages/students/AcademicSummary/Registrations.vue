<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { RegistrationsData, Student } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import RegistrationsTab from '../AcademicSummary/RegistrationsTab.vue';

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status'>;
    registrations: RegistrationsData;
}

const props = defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Registrations - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="registrations">
            <RegistrationsTab :registrations="registrations" :student-id="student.id" />
        </StudentLayout>
    </div>
</template>
