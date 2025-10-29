<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import type { Student } from '@/types/models';
import { STUDENT_STATUS_LABELS } from '@/types/student';

interface Props {
    open: boolean;
    student: Student;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const form = useForm({
    status: props.student.status,
    gc_current_level: props.student.gc_current_level,
    gc_starting_level: props.student.gc_starting_level,
    gc_total_levels: props.student.gc_total_levels,
    reason: '',
});

// Watch for changes in the open prop to reset form when dialog is opened
watch(() => props.open, (newValue) => {
    if (newValue) {
        form.status = props.student.status;
        form.gc_current_level = props.student.gc_current_level;
        form.gc_starting_level = props.student.gc_starting_level;
        form.gc_total_levels = props.student.gc_total_levels;
        form.reason = '';
    }
});

// Watch for status changes to set default GC values when switching to intake_pre_uni_gc
watch(() => form.status, (newStatus) => {
    if (newStatus === 'intake_pre_uni_gc') {
        // Set defaults if values are null
        if (form.gc_total_levels === null) {
            form.gc_total_levels = 6;
        }
        if (form.gc_starting_level === null) {
            form.gc_starting_level = 0;
        }
        // Set gc_current_level to starting level if null
        if (form.gc_current_level === null) {
            form.gc_current_level = form.gc_starting_level;
        }
    }
});

// Computed: Check if GC fields should be shown
const shouldShowGcFields = computed(() => {
    // Show if student already has GC data and not switching to intake_course
    const hasExistingGcData = props.student.gc_starting_level !== null;
    
    // Show if switching TO intake_pre_uni_gc (even if no existing GC data)
    const switchingToPreUniGc = form.status === 'intake_pre_uni_gc';
    
    // Hide if switching to intake_course
    const notSwitchingToIntakeCourse = form.status !== 'intake_course';
    
    return (hasExistingGcData || switchingToPreUniGc) && notSwitchingToIntakeCourse;
});

// Generate GC level options (0 to gc_total_levels)
const gcLevelOptions = computed(() => {
    const totalLevels = form.gc_total_levels ?? 6;
    return Array.from({ length: totalLevels + 1 }, (_, i) => i);
});

// Generate GC starting level options (0 to gc_total_levels)
const gcStartingLevelOptions = computed(() => {
    const totalLevels = form.gc_total_levels ?? 6;
    return Array.from({ length: totalLevels + 1 }, (_, i) => i);
});

const submit = () => {
    form.post(route('students.change-status-gc-level', props.student.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            emit('success');
            emit('close');
            form.reset();
        },
    });
};

const handleClose = () => {
    if (!form.processing) {
        form.reset();
        emit('close');
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="(val) => !val && handleClose()">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Change Student Status & GC Level</DialogTitle>
                <DialogDescription class="text-sm text-gray-600">
                    {{ student.full_name }} ({{ student.student_id }})
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Status Select -->
                <div>
                    <Label>Status <span class="text-red-500">*</span></Label>
                    <Select v-model="form.status">
                        <SelectTrigger :disabled="form.processing">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem 
                                v-for="(label, status) in STUDENT_STATUS_LABELS" 
                                :key="status" 
                                :value="status"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="form.errors.status" class="text-sm text-red-500 mt-1">
                        {{ form.errors.status }}
                    </p>
                </div>

                <!-- GC Fields - Only shown conditionally -->
                <div v-if="shouldShowGcFields" class="space-y-3 border-t pt-3">
                    <p class="text-sm font-medium text-gray-700">GC Information</p>
                    
                    <!-- GC Starting Level (editable when switching to intake_pre_uni_gc) -->
                    <div>
                        <Label>GC Starting Level <span v-if="form.status === 'intake_pre_uni_gc'" class="text-red-500">*</span></Label>
                        <Select v-model="form.gc_starting_level" :disabled="form.processing || form.status !== 'intake_pre_uni_gc'">
                            <SelectTrigger>
                                <SelectValue placeholder="Select starting level" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem 
                                    v-for="level in gcStartingLevelOptions" 
                                    :key="level" 
                                    :value="level"
                                >
                                    {{ level === 0 ? 'Foundation' : `Level ${level}` }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.gc_starting_level" class="text-sm text-red-500 mt-1">
                            {{ form.errors.gc_starting_level }}
                        </p>
                    </div>

                    <!-- GC Current Level (editable) -->
                    <div>
                        <Label>GC Current Level <span v-if="form.status === 'intake_pre_uni_gc'" class="text-red-500">*</span></Label>
                        <Select v-model="form.gc_current_level">
                            <SelectTrigger :disabled="form.processing">
                                <SelectValue placeholder="Select current level" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem 
                                    v-for="level in gcLevelOptions" 
                                    :key="level" 
                                    :value="level"
                                >
                                    {{ level === 0 ? 'Foundation' : `Level ${level}` }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.gc_current_level" class="text-sm text-red-500 mt-1">
                            {{ form.errors.gc_current_level }}
                        </p>
                    </div>

                    <!-- GC Total Levels (read-only, always 6) -->
                    <div>
                        <Label class="text-sm">GC Total Levels</Label>
                        <div class="px-3 py-2 bg-gray-100 rounded-md text-sm text-gray-700">
                            {{ form.gc_total_levels ?? 6 }}
                        </div>
                    </div>
                </div>

                <!-- Reason Textarea -->
                <div>
                    <Label>Reason <span class="text-red-500">*</span></Label>
                    <Textarea 
                        v-model="form.reason" 
                        placeholder="Enter reason for this change (minimum 10 characters)..."
                        rows="4"
                        :disabled="form.processing"
                        class="resize-none"
                    />
                    <p class="text-xs text-gray-500 mt-1">
                        Minimum 10 characters required
                    </p>
                    <p v-if="form.errors.reason" class="text-sm text-red-500 mt-1">
                        {{ form.errors.reason }}
                    </p>
                </div>

                <!-- General error message -->
                <div v-if="(form.errors as any).error" class="bg-red-50 border border-red-200 rounded-md p-3">
                    <p class="text-sm text-red-800">
                        {{ (form.errors as any).error }}
                    </p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleClose" :disabled="form.processing">
                    Cancel
                </Button>
                <Button @click="submit" :disabled="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
