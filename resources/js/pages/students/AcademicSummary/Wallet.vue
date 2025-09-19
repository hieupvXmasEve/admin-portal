<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { Student } from '@/types/models';
import type { StudentWalletTabData } from '@/types/wallet';
import { Head } from '@inertiajs/vue3';
import WalletTab from '../AcademicSummary/WalletTab.vue';

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status' | 'email'>;
    wallet: StudentWalletTabData;
}

const props = defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Wallet - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="wallet">
            <WalletTab :wallet="wallet" :student-id="student.id" :loading="false" />
        </StudentLayout>
    </div>
</template>
