<script setup lang="ts">
/**
 * Records attendance for a session's active roster from the Course Offering
 * Cockpit sessions view (ADR 0013 phase B), instead of the standalone staff
 * Attendance pages. Submits through useCockpitAction so operational_state
 * (readiness blockers + per-session attendance status) refreshes via the
 * same partial-reload contract as Finalize/Recalculate.
 */
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCockpitAction } from '@/composables/useCockpitAction';
import type { Attendance, ClassSession, CourseOffering, CourseRegistration } from '@/types/models';
import { computed, reactive, watch } from 'vue';
import { route } from 'ziggy-js';

type AttendanceStatus = Attendance['status'];

interface Props {
    open: boolean;
    courseOffering: CourseOffering;
    session: ClassSession | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { isPending, submit } = useCockpitAction();

const statusOptions: { value: AttendanceStatus; label: string }[] = [
    { value: 'present', label: 'Present' },
    { value: 'late', label: 'Late' },
    { value: 'absent', label: 'Absent' },
    { value: 'excused', label: 'Excused' },
];

// Matches CourseRegistration::CLASS_ROSTER_REGISTRATION_STATUSES — the same
// "active class roster" definition the backend validates student_id against
// (CourseOffering::activeClassRosterRegistrations, also used by the lecturer
// attendance flow), so a roster row shown here is never rejected server-side.
const classRosterRegistrationStatuses = ['confirmed'];

const activeRegistrations = computed<CourseRegistration[]>(() => (props.courseOffering.course_registrations ?? []).filter((registration) => classRosterRegistrationStatuses.includes(registration.registration_status)));

const drafts = reactive<Record<number, AttendanceStatus>>({});

// Reset drafts to "present" defaults whenever the modal opens for a
// (possibly different) session — staff adjust exceptions from there.
watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) return;
        Object.keys(drafts).forEach((key) => delete drafts[Number(key)]);
        activeRegistrations.value.forEach((registration) => {
            drafts[registration.student_id] = 'present';
        });
    },
);

const close = () => emit('update:open', false);

const onSubmit = () => {
    if (!props.session) return;

    submit(
        route('course-offerings.sessions.record-attendance', {
            courseOffering: props.courseOffering.id,
            classSession: props.session.id,
        }),
        {
            records: Object.entries(drafts).map(([studentId, status]) => ({
                student_id: Number(studentId),
                status,
            })),
        },
        ['operational_state', 'courseOffering'],
    );
    close();
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[80vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Record attendance</DialogTitle>
                <DialogDescription> {{ session?.session_title }} — {{ activeRegistrations.length }} student(s) on roster. Defaults to Present; adjust exceptions below. </DialogDescription>
            </DialogHeader>

            <div v-if="!activeRegistrations.length" class="text-muted-foreground py-6 text-center text-sm">No active roster students for this course offering.</div>

            <div v-else class="max-h-[50vh] space-y-2 overflow-y-auto">
                <div v-for="registration in activeRegistrations" :key="registration.student_id" class="flex items-center justify-between gap-3 rounded-md border p-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ registration.student?.full_name }}</p>
                        <Badge variant="outline" class="font-mono text-xs">{{ registration.student?.student_id }}</Badge>
                    </div>
                    <Select :model-value="drafts[registration.student_id]" @update:model-value="(value) => (drafts[registration.student_id] = value as AttendanceStatus)">
                        <SelectTrigger class="w-32 shrink-0">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="isPending" @click="close">Cancel</Button>
                <Button :disabled="isPending || !activeRegistrations.length" @click="onSubmit">
                    {{ isPending ? 'Recording…' : 'Record attendance' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
