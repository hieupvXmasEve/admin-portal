<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { StudentHubContext, StudentOverview } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import OverviewTab from '../AcademicSummary/OverviewTab.vue';

interface Props {
    student: StudentHubContext;
    overview: StudentOverview;
    can_act?: boolean;
}

withDefaults(defineProps<Props>(), {
    can_act: true,
});
</script>

<template>
    <div>
        <Head :title="`Student Hub - Overview - ${student.full_name}`" />
        <StudentLayout :student="student" current-tab="overview">
            <OverviewTab :overview="overview" :can-act="can_act" :loading="false" />
        </StudentLayout>
    </div>
</template>
