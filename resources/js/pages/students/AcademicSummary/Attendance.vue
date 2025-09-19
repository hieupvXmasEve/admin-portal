<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { AttendanceData, Student } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import AttendanceTab from '../AcademicSummary/AttendanceTab.vue';

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status'>;
    attendance: AttendanceData;
}

const props = defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Attendance - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="attendance">
            <AttendanceTab :attendance="attendance" :loading="false" />
        </StudentLayout>
    </div>
</template>
