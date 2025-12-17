<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { Student } from '@/types/models';
import type { PaginatedTransactions, StudentCashWallet, TuitionPlan, WalletStats } from '@/types/wallet';
import { Head } from '@inertiajs/vue3';
import WalletTab from '../WalletTab.vue';

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status' | 'email' | 'intake'>;
    wallet: StudentCashWallet;
    transactions: PaginatedTransactions;
    stats: WalletStats;
    tuitionPlan?: TuitionPlan | null;
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Wallet - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="wallet">
            <WalletTab :wallet="wallet" :transactions="transactions" :stats="stats" :tuition-plan="tuitionPlan" :student-id="student.id" :loading="false" />
        </StudentLayout>
    </div>
</template>
