<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { EgcPanel, LifecycleDecisionOption, LifecycleTimelineRow, StudentHubContext } from '@/types/models';
import { Activity } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AttachDecisionDialog from './lifecycle/AttachDecisionDialog.vue';
import EgcControls from './lifecycle/EgcControls.vue';
import EgcSubPanel from './lifecycle/EgcSubPanel.vue';
import LifecycleTimelineList from './lifecycle/LifecycleTimelineList.vue';
import RecordActionDialog from './lifecycle/RecordActionDialog.vue';

interface Props {
    student: StudentHubContext;
    timeline: LifecycleTimelineRow[];
    egc: EgcPanel;
    canAct?: boolean;
    canChangeStatus?: boolean;
    options: {
        action: Record<string, unknown>;
        placement: Record<string, unknown>;
        decisions: LifecycleDecisionOption[];
    };
}

const props = withDefaults(defineProps<Props>(), {
    canAct: false,
    canChangeStatus: false,
});

// EGC placement/progression controls apply only to students currently in the
// pre-uni GC stage (PRD user story 25 / ADR-0009).
const isEgcStudent = computed(() => props.student.status === 'intake_pre_uni_gc');

const missingDecisionCount = computed(() => props.timeline.filter((row) => row.missing_decision).length);

const attachOpen = ref(false);
const attachTarget = ref<LifecycleTimelineRow | null>(null);

const openAttachDecision = (row: LifecycleTimelineRow) => {
    attachTarget.value = row;
    attachOpen.value = true;
};
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <Activity class="h-5 w-5" />
                    Lifecycle
                </h2>
                <p class="text-muted-foreground text-sm">
                    One chronological timeline of status actions and EGC progression, with authorizing decisions inline.
                </p>
            </div>
            <RecordActionDialog v-if="canChangeStatus" :student="student" :options="options.action" />
        </div>

        <div v-if="missingDecisionCount > 0" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-800">
            {{ missingDecisionCount }} transition{{ missingDecisionCount === 1 ? '' : 's' }} still need an authorizing decision.
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="text-base">Timeline</CardTitle>
                    <CardDescription>Merged from Student Actions and Academic Progression, newest first.</CardDescription>
                </CardHeader>
                <CardContent>
                    <LifecycleTimelineList :timeline="timeline" :can-change-status="canChangeStatus" @attach-decision="openAttachDecision" />
                </CardContent>
            </Card>

            <div class="space-y-6">
                <EgcControls v-if="isEgcStudent && canChangeStatus" :student="student" :options="options.placement" :egc="egc" />
                <EgcSubPanel :egc="egc" :can-change-status="canChangeStatus" />
            </div>
        </div>

        <AttachDecisionDialog
            v-model:open="attachOpen"
            :student="student"
            :transition="attachTarget"
            :decisions="options.decisions"
        />
    </div>
</template>
