<script setup lang="ts">
import StudentLayout from '@/layouts/StudentLayout.vue';
import type { EgcPanel, LifecycleDecisionOption, LifecycleTimelineRow, StudentHubContext } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import LifecycleTab from './LifecycleTab.vue';

interface Props {
    student: StudentHubContext;
    timeline: LifecycleTimelineRow[];
    egc: EgcPanel;
    can_act?: boolean;
    can_change_status?: boolean;
    options: {
        action: Record<string, unknown>;
        placement: Record<string, unknown>;
        decisions: LifecycleDecisionOption[];
    };
}

withDefaults(defineProps<Props>(), {
    can_act: false,
    can_change_status: false,
});
</script>

<template>
    <div>
        <Head :title="`Student Hub - Lifecycle - ${student.full_name}`" />

        <StudentLayout :student="student" current-tab="lifecycle">
            <LifecycleTab :student="student" :timeline="timeline" :egc="egc" :can-act="can_act" :can-change-status="can_change_status" :options="options" />
        </StudentLayout>
    </div>
</template>
