<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { Student } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import FeeTab from './FeeTab.vue';

interface FeeSummary {
    student_info: {
        full_name: string;
        student_id: string;
        program: string;
        intake: string;
    };
    summary: {
        total_charged: number;
        total_discount: number;
        total_paid: number;
        remaining: number;
        progress: number;
    };
    billing_by_semester: Array<{
        semester_id: number;
        semester_name: string;
        semester_code: string;
        status: string;
        totals: {
            total: number;
            paid: number;
            remaining: number;
        };
        invoices: Array<{
            id: number;
            invoice_number: string;
            created_at: string;
            due_date: string;
            status: string;
            total: number;
            paid: number;
            remaining: number;
            lines: Array<{
                id: number;
                item: string;
                category: string;
                type: string;
                amount: number;
            }>;
            payments: Array<{
                id: number;
                paid_at: string;
                method: string;
                amount: number;
                ref: string | null;
            }>;
        }>;
    }>;
    tuition_plan_checklist: {
        plan_name: string;
        terms: Array<{
            term_number: number;
            semester_name: string;
            required_amount: number;
            generated: boolean;
            charge_id: number | null;
            payment_status: string;
            linked_invoices: string[];
        }>;
    } | null;
}

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status' | 'email' | 'intake'>;
    feeSummary: FeeSummary;
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Fee - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="fees">
            <FeeTab :fee-summary="feeSummary" :loading="false" />
        </StudentLayout>
    </div>
</template>
