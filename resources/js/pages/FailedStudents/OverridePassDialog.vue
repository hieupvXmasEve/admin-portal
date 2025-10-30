<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { FailedStudent } from '@/types';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    open: boolean;
    student: FailedStudent | null;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const overrideReason = ref('');
const isSubmitting = ref(false);
const errors = ref<{ override_reason?: string }>({});

const isEgcUnit = computed(() => props.student?.unit_type === 'egc');
const unitLevel = computed(() => props.student?.unit_level ?? null);
const willIncrementLevel = computed(() => {
    // Only show increment if unit level matches student's current GC level
    const studentLevel = props.student?.student_gc_level;
    return isEgcUnit.value && unitLevel.value !== null && studentLevel !== null && unitLevel.value === studentLevel;
});

const schema = z.object({
    override_reason: z.string().min(10, 'Reason must be at least 10 characters').max(1000, 'Reason must not exceed 1000 characters'),
});

const handleClose = () => {
    emit('update:open', false);
    overrideReason.value = '';
    errors.value = {};
};

const handleSubmit = async () => {
    if (!props.student) return;

    errors.value = {};

    // Validate with Zod
    const result = schema.safeParse({ override_reason: overrideReason.value });
    if (!result.success) {
        const fieldErrors = result.error.flatten().fieldErrors;
        if (fieldErrors.override_reason) {
            errors.value.override_reason = fieldErrors.override_reason[0];
        }
        return;
    }

    isSubmitting.value = true;

    try {
        const response = await fetch('/failed-students/override-pass', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                academic_record_id: props.student.id,
                override_reason: overrideReason.value,
            }),
        });

        const data = await response.json();

        if (data.success) {
            toast.success('Pass status overridden successfully.');

            // Reload the page to refresh data
            router.reload({ only: ['failed_students'] });

            emit('success');
            handleClose();
        } else {
            toast.error(data.message || 'Failed to override pass status.');
        }
    } catch {
        toast.error('An unexpected error occurred. Please try again.');
    } finally {
        isSubmitting.value = false;
    }
};

// Reset form when dialog closes
watch(
    () => props.open,
    (newValue) => {
        if (!newValue) {
            overrideReason.value = '';
            errors.value = {};
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle>Override Pass Status</DialogTitle>
                <DialogDescription>Override the fail status for this student with a special pass. A reason is required for audit purposes.</DialogDescription>
            </DialogHeader>

            <div v-if="student" class="space-y-4 py-4">
                <div class="bg-muted rounded-lg p-3 text-sm">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="font-medium">Student:</div>
                        <div>{{ student.student_id }} - {{ student.student_name }}</div>
                        <div class="font-medium">Unit:</div>
                        <div>{{ student.unit_code }} - {{ student.unit_name }}</div>
                        <div class="font-medium">Grade:</div>
                        <div>{{ student.final_letter_grade }} ({{ student.final_percentage.toFixed(2) }}%)</div>
                    </div>
                </div>

                <!-- EGC Level Information -->
                <div v-if="isEgcUnit && unitLevel !== null" class="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                    <p class="mb-1 text-sm font-medium text-blue-900 dark:text-blue-100">EGC Level Information</p>
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        This is an <strong>EGC Level {{ unitLevel }}</strong> unit.
                        <span v-if="willIncrementLevel">
                            By overriding this fail status, the student will successfully pass <strong>GC Level {{ unitLevel }}</strong> and progress to the next level.
                        </span>
                        <span v-else> Overriding this fail status will mark the unit as passed. </span>
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="override_reason" class="text-sm font-medium"> Override Reason <span class="text-destructive">*</span> </Label>
                    <Textarea
                        id="override_reason"
                        v-model="overrideReason"
                        placeholder="Enter detailed reason for overriding pass status (minimum 10 characters)..."
                        rows="5"
                        :disabled="isSubmitting"
                        :class="{ 'border-destructive': errors.override_reason }"
                    />
                    <p v-if="errors.override_reason" class="text-destructive text-sm">{{ errors.override_reason }}</p>
                    <p class="text-muted-foreground text-xs">This reason will be logged and visible to administrators.</p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleClose" :disabled="isSubmitting">Cancel</Button>
                <Button @click="handleSubmit" :disabled="isSubmitting || !overrideReason.trim()">
                    {{ isSubmitting ? 'Submitting...' : 'Override Pass' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
