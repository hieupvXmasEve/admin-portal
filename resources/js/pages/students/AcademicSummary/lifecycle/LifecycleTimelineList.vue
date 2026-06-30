<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { LifecycleTimelineRow } from '@/types/models';
import { AlertTriangle, ArrowRightLeft, CircleCheck, FileText, TrendingUp } from 'lucide-vue-next';

interface Props {
    timeline: LifecycleTimelineRow[];
    canChangeStatus?: boolean;
}

withDefaults(defineProps<Props>(), { canChangeStatus: false });
const emit = defineEmits<{ 'attach-decision': [row: LifecycleTimelineRow] }>();

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const sourceIcon = (row: LifecycleTimelineRow) => (row.source === 'progression' ? TrendingUp : ArrowRightLeft);
</script>

<template>
    <div v-if="timeline.length === 0" class="rounded-lg border border-dashed p-10 text-center">
        <FileText class="text-muted-foreground mx-auto h-8 w-8" />
        <p class="text-muted-foreground mt-3 text-sm">No lifecycle events recorded yet.</p>
    </div>

    <ol v-else class="relative space-y-4 before:absolute before:top-2 before:bottom-2 before:left-[18px] before:w-px before:bg-border">
        <li v-for="row in timeline" :key="row.id" class="relative flex gap-4">
            <span
                :class="[
                    'relative z-10 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ring-4 ring-background',
                    row.missing_decision ? 'bg-amber-100 text-amber-700' : 'bg-primary/10 text-primary',
                ]"
            >
                <component :is="sourceIcon(row)" class="h-4 w-4" />
            </span>

            <div class="bg-card flex-1 rounded-lg border p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ row.label }}</span>
                            <Badge variant="outline" class="text-[10px] uppercase tracking-wide">{{ row.source }}</Badge>
                        </div>
                        <p class="text-muted-foreground mt-0.5 text-sm">{{ formatDateTime(row.occurred_at) }}</p>
                    </div>

                    <!-- Authorizing Decision rendered inline on the transition it authorizes (ADR-0008/0009) -->
                    <div class="flex flex-col items-end gap-1">
                        <Badge v-if="row.decision" class="flex items-center gap-1 bg-emerald-100 text-emerald-800 hover:bg-emerald-100">
                            <CircleCheck class="h-3 w-3" />
                            {{ row.decision.decision_number || row.decision.decision_name || `Decision #${row.decision.id}` }}
                        </Badge>
                        <Badge v-else-if="row.missing_decision" variant="outline" class="flex items-center gap-1 border-amber-300 text-amber-700">
                            <AlertTriangle class="h-3 w-3" />
                            Decision missing
                        </Badge>
                        <a
                            v-if="row.decision?.upload_record"
                            :href="row.decision.upload_record.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-primary inline-flex items-center gap-1 text-xs hover:underline"
                        >
                            <FileText class="h-3 w-3" />
                            View document
                        </a>
                    </div>
                </div>

                <p v-if="row.detail.reason" class="mt-2 text-sm">
                    {{ row.detail.reason }}
                </p>

                <div class="text-muted-foreground mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                    <span v-if="row.actor">By {{ row.actor.name }}</span>
                    <span v-if="row.detail.previous_status && row.detail.new_status">
                        {{ row.detail.previous_status }} → {{ row.detail.new_status }}
                    </span>
                </div>

                <div v-if="row.missing_decision && canChangeStatus" class="mt-3">
                    <Button size="sm" variant="outline" @click="emit('attach-decision', row)">Attach decision</Button>
                </div>
            </div>
        </li>
    </ol>
</template>
