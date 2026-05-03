<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import RoomSelectionModal from '@/components/RoomSelectionModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { createSelectionColumn } from '@/lib/table-utils';
import type { ClassSession, CourseOffering, Room } from '@/types/models';
import { attendanceRoutes } from '@/utils/routes';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ModalLink, visitModal } from '@inertiaui/modal-vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { format } from 'date-fns';
import { BookOpen, Calendar, Clock, Edit2, Eye, Settings, Trash2 } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Props {
    courseOffering: CourseOffering;
    availableRooms: Room[];
}

const props = defineProps<Props>();
const { showConfirmDialog } = useGlobalConfirmDialog();

const classSessionsTable = ref();
const isAllSessionsCompleted = computed(() => props.courseOffering.class_sessions?.every((s) => s.status === 'completed') ?? true);
const selectedSessions = ref<ClassSession[]>([]);

// ---- Session limit ----
const canAddSession = computed(() => {
    const limit = props.courseOffering.syllabus_template?.total_sessions;
    return !limit || (props.courseOffering.class_sessions?.length ?? 0) < limit;
});

const addSessionButtonText = computed(() => {
    const tmpl = props.courseOffering.syllabus_template;
    if (!tmpl?.total_sessions) return 'Add Session';
    const cur = props.courseOffering.class_sessions?.length ?? 0;
    return canAddSession.value ? `Add Session (${cur}/${tmpl.total_sessions})` : `Limit Reached (${cur}/${tmpl.total_sessions})`;
});

// ---- Generate sessions — useForm → Inertia web route ----
const generateForm = useForm<{
    room_id: number | null;
    start_date: string;
    weekly_schedule: Record<string, any>;
    excluded_dates: { start: string; end: string }[];
}>({
    room_id: null,
    start_date: '',
    weekly_schedule: {},
    excluded_dates: [],
});

const isGenerating = ref(false);

const generateClassSessions = (payload: { roomId: number; startDate: string; weeklySchedule: Record<string, any>; excludedDates: { start: string; end: string }[] }) => {
    generateForm.room_id = payload.roomId;
    generateForm.start_date = payload.startDate;
    generateForm.weekly_schedule = payload.weeklySchedule;
    generateForm.excluded_dates = payload.excludedDates;

    isGenerating.value = true;
    generateForm.post(route('class-sessions.generate', props.courseOffering.id), {
        preserveScroll: true,
        only: ['courseOffering'],
        onFinish: () => {
            isGenerating.value = false;
        },
    });
};

// ---- Add session — ModalLink ----
const openAddModal = () => {
    if (!canAddSession.value) {
        toast.error(`Session limit reached (${props.courseOffering.syllabus_template?.total_sessions ?? 0} max)`);
        return;
    }
    visitModal(route('class-sessions.add-for-offering', props.courseOffering.id));
};

// ---- Delete single — router.delete (1 request) ----
const deleteSession = (session: ClassSession) => {
    showConfirmDialog(
        {
            title: 'Delete Session',
            message: `Delete "${session.session_title || 'Unnamed'}" on ${new Date(session.session_date).toLocaleDateString()}? This cannot be undone.`,
            confirmText: 'Delete',
        },
        {
            onConfirm: () =>
                new Promise((resolve, reject) => {
                    router.delete(route('class-sessions.destroy', session.id), {
                        preserveScroll: true,
                        only: ['courseOffering'],
                        onSuccess: () => {
                            classSessionsTable.value?.clearSelection();
                            resolve();
                        },
                        onError: () => reject(new Error('Delete failed')),
                    });
                }),
        },
    );
};

// ---- Bulk delete — router.delete (1 request) ----
const bulkDelete = () => {
    if (!selectedSessions.value.length) return;
    const count = selectedSessions.value.length;
    showConfirmDialog(
        {
            title: 'Delete Sessions',
            message: `Delete ${count} selected session(s)? This cannot be undone.`,
            confirmText: 'Delete',
        },
        {
            onConfirm: () =>
                new Promise((resolve, reject) => {
                    router.delete(route('class-sessions.bulk-destroy'), {
                        data: { ids: selectedSessions.value.map((s) => s.id) },
                        preserveScroll: true,
                        only: ['courseOffering'],
                        onSuccess: () => {
                            selectedSessions.value = [];
                            classSessionsTable.value?.clearSelection();
                            resolve();
                        },
                        onError: () => reject(new Error('Bulk delete failed')),
                    });
                }),
        },
    );
};

// ---- Bulk edit — ModalLink with session IDs in query ----
const openBulkEditModal = () => {
    if (!selectedSessions.value.length) {
        toast.error('Select at least one session');
        return;
    }
    const ids = selectedSessions.value.map((s) => s.id).join(',');
    visitModal(route('class-sessions.bulk-edit', props.courseOffering.id) + `?ids=${ids}`);
};

const handleSelectionChange = (sessions: ClassSession[]) => {
    selectedSessions.value = sessions;
};

// ---- Table helpers ----
const formatDate = (d?: string | null) => (d ? format(d, 'EEE, dd/MM/yyyy') : 'N/A');
const formatAttendance = (p?: number | string | null) => {
    if (p == null || p === '') return '—';
    const n = Number(p);
    return isNaN(n) ? '—' : `${n.toFixed(1)}%`;
};
const sessionTypeIcon = (t: string) => (t === 'assessment' ? Clock : BookOpen);
const sessionStatusVariant = (s: string): 'default' | 'outline' | 'destructive' | 'secondary' => {
    if (s === 'scheduled') return 'default';
    if (s === 'completed') return 'outline';
    if (s === 'cancelled') return 'destructive';
    return 'secondary';
};

// ---- Table columns ----
const columns: ColumnDef<ClassSession>[] = [
    createSelectionColumn<ClassSession>(),
    {
        header: 'Session',
        id: 'session',
        enableSorting: false,
        cell: ({ row }) => {
            const s = row.original;
            return h('div', { class: 'flex items-center gap-2' }, [
                h(sessionTypeIcon(s.session_type), { class: 'h-4 w-4 shrink-0 text-muted-foreground' }),
                h('div', [h('p', { class: 'font-medium leading-tight' }, s.session_title), s.session_description && h('p', { class: 'text-muted-foreground max-w-[200px] truncate text-xs' }, s.session_description)]),
            ]);
        },
    },
    {
        header: 'Date',
        id: 'session_date',
        enableSorting: false,
        cell: ({ row }) => h('span', { class: 'text-sm' }, formatDate(row.original.session_date)),
    },
    {
        header: 'Time',
        id: 'time',
        enableSorting: false,
        cell: ({ row }) => {
            const s = row.original;
            return h('span', { class: 'tabular-nums text-sm' }, `${s.start_time} – ${s.end_time}`);
        },
    },
    {
        header: 'Room',
        id: 'room',
        enableSorting: false,
        cell: ({ row }) => {
            const room = row.original?.room;
            return room ? h('div', { class: 'text-sm' }, [h('span', room.name), room.building && h('span', { class: 'text-muted-foreground ml-1 text-xs' }, room.building.name)]) : h('span', { class: 'text-muted-foreground text-sm' }, '—');
        },
    },
    {
        header: 'Lecturer',
        id: 'lecturer',
        enableSorting: false,
        cell: ({ row }) => (row.original.lecture?.display_name ? h('span', { class: 'text-sm' }, row.original.lecture.display_name) : h('span', { class: 'text-muted-foreground text-sm' }, '—')),
    },
    { header: 'Status', id: 'status', enableSorting: false },
    {
        header: 'Attendance',
        id: 'attendance',
        enableSorting: false,
        cell: ({ row }) => {
            const pct = row.original.attendance_percentage;
            const val = pct != null ? Number(pct) : null;
            const color = val == null ? 'text-muted-foreground' : val >= 80 ? 'text-green-600' : val >= 60 ? 'text-yellow-600' : 'text-red-600';
            return h('span', { class: `tabular-nums text-sm font-medium ${color}` }, formatAttendance(pct));
        },
    },
    { header: 'Actions', id: 'actions', enableSorting: false, enableHiding: false },
];
</script>

<template>
    <div class="space-y-4">
        <!-- Empty state -->
        <div v-if="!courseOffering.class_sessions?.length" class="flex flex-col items-center gap-4 py-12 text-center">
            <div class="bg-muted flex h-16 w-16 items-center justify-center rounded-full">
                <Calendar class="text-muted-foreground h-8 w-8" />
            </div>
            <div>
                <p class="font-semibold">No sessions yet</p>
                <p class="text-muted-foreground mt-1 text-sm">Auto-generate sessions from the syllabus, or add them one by one.</p>
            </div>
            <div class="flex items-center gap-2">
                <RoomSelectionModal
                    :disable-generate="!courseOffering.syllabus_template || !canAddSession"
                    :available-rooms="availableRooms"
                    :is-generating="isGenerating || generateForm.processing"
                    :semester-start="courseOffering.semester?.start_date"
                    :semester-end="courseOffering.semester?.end_date"
                    :syllabus-template="courseOffering.syllabus_template"
                    @generate="generateClassSessions"
                />
                <Button variant="outline" :disabled="!canAddSession" @click="openAddModal">
                    <Calendar class="mr-2 h-4 w-4" />
                    Add Session
                </Button>
            </div>
            <p v-if="!courseOffering.syllabus_template" class="text-muted-foreground text-xs">Assign a syllabus template to enable auto-generate.</p>
        </div>

        <!-- Sessions table -->
        <template v-else>
            <!-- Toolbar -->
            <div class="flex items-center justify-between gap-2">
                <span class="text-muted-foreground text-sm">
                    {{ courseOffering.class_sessions?.length }} session(s)
                    <span v-if="courseOffering.syllabus_template?.total_sessions" class="ml-1"> / {{ courseOffering.syllabus_template.total_sessions }} planned </span>
                </span>
                <div class="flex items-center gap-2">
                    <!-- Bulk actions — visible only when rows selected -->
                    <template v-if="selectedSessions.length">
                        <Button size="sm" variant="outline" @click="openBulkEditModal">
                            <Edit2 class="mr-1.5 h-3.5 w-3.5" />
                            Edit {{ selectedSessions.length }}
                        </Button>
                        <Button size="sm" variant="destructive" @click="bulkDelete">
                            <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                            Delete {{ selectedSessions.length }}
                        </Button>
                    </template>
                    <!-- Generate -->
                    <RoomSelectionModal
                        :disable-generate="!courseOffering.syllabus_template || !canAddSession"
                        :available-rooms="availableRooms"
                        :is-generating="isGenerating || generateForm.processing"
                        :semester-start="courseOffering.semester?.start_date"
                        :semester-end="courseOffering.semester?.end_date"
                        :syllabus-template="courseOffering.syllabus_template"
                        @generate="generateClassSessions"
                    />
                    <!-- Add session — opens route-based modal -->
                    <Button size="sm" :disabled="!canAddSession" :variant="canAddSession ? 'default' : 'outline'" @click="openAddModal">
                        <Calendar class="mr-1.5 h-3.5 w-3.5" />
                        {{ addSessionButtonText }}
                    </Button>
                </div>
            </div>

            <p v-if="!canAddSession && courseOffering.syllabus_template?.total_sessions" class="text-xs text-orange-600">Session limit reached ({{ courseOffering.syllabus_template.total_sessions }} max).</p>

            <DataTable
                ref="classSessionsTable"
                :data="courseOffering.class_sessions ?? []"
                :columns="columns"
                :enable-row-selection="!isAllSessionsCompleted"
                :enable-server-sorting="false"
                empty-message="No sessions found."
                @selection-change="handleSelectionChange"
            >
                <!-- Status cell -->
                <template #cell-status="{ row }">
                    <Badge :variant="sessionStatusVariant(row.original.status)" class="capitalize">
                        {{ row.original.status.replace('_', ' ') }}
                    </Badge>
                </template>

                <!-- Actions cell -->
                <template #cell-actions="{ row }">
                    <div class="flex items-center gap-1">
                        <!-- Quick edit — ModalLink, only for scheduled sessions -->
                        <ModalLink
                            v-if="row.original.status === 'scheduled'"
                            :href="route('class-sessions.quick-edit', row.original.id)"
                            as="button"
                            class="hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring inline-flex h-9 w-9 items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:ring-1 focus-visible:outline-none"
                            title="Quick edit"
                        >
                            <Settings class="h-4 w-4" />
                        </ModalLink>

                        <!-- View attendance detail -->
                        <Link :href="attendanceRoutes.classSessions.show(row.original.id)">
                            <Button variant="ghost" size="sm" title="View attendance">
                                <Eye class="h-4 w-4" />
                            </Button>
                        </Link>

                        <!-- Delete — only for scheduled sessions -->
                        <Button v-if="row.original.status === 'scheduled'" variant="ghost" size="sm" title="Delete session" class="text-destructive hover:bg-destructive/10 hover:text-destructive" @click="deleteSession(row.original)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </template>
    </div>
</template>
