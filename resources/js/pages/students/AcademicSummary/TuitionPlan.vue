<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { Student } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import TuitionPlanTab from './TuitionPlanTab.vue';

interface ScholarshipAward {
    id: number;
    scholarship_code: string;
    awarded_at: string | null;
    formatted_awarded_at: string | null;
    notes: string | null;
    scholarship: {
        id: number;
        code: string;
        name: string;
        description: string | null;
        type: 'percentage' | 'fixed_amount';
        amount: number;
        valid_from: string | null;
        valid_until: string | null;
        formatted_valid_from: string | null;
        formatted_valid_until: string | null;
        is_active: boolean;
        is_valid: boolean;
    } | null;
}

interface Props {
    student: Pick<Student, 'id' | 'student_id' | 'full_name' | 'status' | 'email' | 'intake'>;
    tuitionPlan: TuitionPlanData | null;
    scholarshipAward: ScholarshipAward | null;
}

interface TuitionPlanData {
    id: number;
    total_amount: number;
    currency: string;
    is_active: boolean;
    curriculum_version: {
        id: number;
        version_code: string;
        program: { id: number; name: string; code: string } | null;
        specialization: { id: number; name: string; code: string } | null;
    } | null;
    intake_semester: {
        id: number;
        code: string;
        name: string;
        start_date: string;
        end_date: string;
    } | null;
    terms: Array<{
        id: number;
        term_number: number;
        amount: number;
        discount_amount: number;
        amount_after_discount: number;
        due_date: string | null;
        formatted_due_date: string | null;
        semester: { id: number; code: string; name: string } | null;
        paid_amount: number;
        remaining_amount: number;
        payment_status: 'paid' | 'partial' | 'unpaid' | 'overdue';
        invoices: Array<{
            invoice_number: string;
            semester_id: number;
            due_date: string | null;
            status: string;
            item_total: number;
            item_discount: number;
            item_amount_after_discount: number;
            item_paid: number;
            voucher_discounts: Array<{
                code: string;
                name: string;
                amount: number;
                voucher: {
                    code: string;
                    name: string;
                    discount_type: string;
                    discount_value: number;
                } | null;
            }>;
        }>;
    }>;
    summary: {
        total_amount: number;
        total_discount: number;
        total_after_discount: number;
        total_paid: number;
        total_remaining: number;
        paid_count: number;
        partial_count: number;
        unpaid_count: number;
        overdue_count: number;
    };
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`Academic Summary - Tuition Plan - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="tuition-plan">
            <TuitionPlanTab :tuition-plan="tuitionPlan" :scholarship-award="scholarshipAward" :loading="false" />
        </StudentLayout>
    </div>
</template>
