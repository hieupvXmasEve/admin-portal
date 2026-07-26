<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { LifecycleDecisionOption, LifecycleTimelineRow, StudentHubContext } from '@/types/models';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Props {
    open: boolean;
    student: StudentHubContext;
    transition: LifecycleTimelineRow | null;
    decisions: LifecycleDecisionOption[];
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const form = useForm({
    source: '',
    source_id: 0,
    decision_id: '',
});

const decisionLabel = (decision: LifecycleDecisionOption): string => [decision.decision_number, decision.decision_name].filter(Boolean).join(' — ') || `Decision #${decision.id}`;

const canSubmit = computed(() => props.transition !== null && form.decision_id !== '');

const submit = () => {
    if (!props.transition) {
        return;
    }

    form.source = props.transition.source;
    form.source_id = props.transition.source_id;

    form.post(route('students.academic-summary.lifecycle.attach-decision', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Decision attached to the transition.');
            form.reset('decision_id');
            emit('update:open', false);
        },
        onError: () => toast.error('Could not attach the decision. Please review and try again.'),
    });
};
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Attach authorizing Decision</DialogTitle>
                <DialogDescription>
                    Backfill the signed quyết định for
                    <span class="font-medium">{{ transition?.label }}</span>
                    . The transition was recorded without a Decision; attaching one clears its missing-decision flag.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2 py-2">
                <Label for="attach-decision-select">Decision</Label>
                <Select v-model="form.decision_id">
                    <SelectTrigger id="attach-decision-select">
                        <SelectValue placeholder="Select a decision to attach" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="decision in decisions" :key="decision.id" :value="String(decision.id)">
                            {{ decisionLabel(decision) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="decisions.length === 0" class="text-muted-foreground text-sm">No decisions are available yet. Create a Decision in the management area first.</p>
                <p v-if="form.errors.decision_id" class="text-destructive text-sm">{{ form.errors.decision_id }}</p>
            </div>

            <DialogFooter>
                <Button variant="outline" type="button" @click="emit('update:open', false)">Cancel</Button>
                <Button type="button" :disabled="!canSubmit || form.processing" @click="submit">Attach decision</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
