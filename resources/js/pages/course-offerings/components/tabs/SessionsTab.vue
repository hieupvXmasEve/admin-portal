<script setup lang="ts">
import AddClassSessionModal from '@/components/AddClassSessionModal.vue';
import BulkEditClassSessionModal from '@/components/BulkEditClassSessionModal.vue';
import DataTable from '@/components/DataTable.vue';
import QuickEditClassSessionModal from '@/components/QuickEditClassSessionModal.vue';
import RoomSelectionModal from '@/components/RoomSelectionModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useApi } from '@/composables/useApiRequest';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { createSelectionColumn } from '@/lib/table-utils';
import type { ClassSession, CourseOffering, Room } from '@/types/models';
import { attendanceRoutes } from '@/utils/routes';
import { Link, router } from '@inertiajs/vue3';
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

// Keep useApi only for JSON-only API endpoints (generate, bulk delete — no Inertia web route)
const api = useApi();
const { showConfirmDialog } = useGlobalConfirmDialog();

const isGenerating = ref(false);
const classSessionsTable = ref();

const isAllClassSessionsCompleted = computed(() => props.courseOffering.class_sessions?.every((s) => s.status === 'completed') ?? true);

// ---- Modal state ----
const quickEditOpen = ref(false);
const selectedSession = ref<ClassSession | null>(null);
const bulkEditOpen = ref(false);
const selectedSessions = ref<ClassSession[]>([]);
const addSessionOpen = ref(false);

// ---- Add session guards ----
const canAddSession = computed(() => {
    if (!props.courseOffering.syllabus_template?.total_sessions) return true;
    return (props.courseOffering.class_sessions?.length ?? 0) < props.courseOffering.syllabus_template.total_sessions;
});

const addSessionButtonText = computed(() => {
    const tmpl = props.courseOffering.syllabus_template;
    if (!tmpl?.total_sessions) return 'Add Session';
    const cur = props.courseOffering.class_sessions?.length ?? 0;
    return canAddSession.value ? `Add Session (${cur}/${tmpl.total_sessions})` : `Limit Reached (${cur}/${tmpl.total_sessions})`;
});

// ---- Formatting ----
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

// ---- Generate sessions (JSON API → keep useApi, 2 requests unavoidable) ----
const generateClassSessions = async (payload: { roomId: number; startDate: string; weeklySchedule: any; excludedDates: { start: string; end: string }[] }) => {
    try {
        isGenerating.value = true;
        const res = await api.post(`/api/course-offerings/${props.courseOffering.id}/class-sessions/generate`, {
            room_id: payload.roomId,
            start_date: payload.startDate,
            weekly_schedule: payload.weeklySchedule,
            excluded_dates: payload.excludedDates,
        });
        if (res.data?.value?.success) {
            toast.success(`${res.data.value.data.sessions_count} sessions generated`);
            router.reload({ only: ['courseOffering'] });
        } else {
            toast.error(res.data?.value?.message || 'Failed to generate sessions');
        }
    } catch {
        toast.error('Failed to generate sessions');
    } finally {
        isGenerating.value = false;
    }
};

// ---- Add session ----
const openAddSessionModal = () => {
    if (!canAddSession.value) {
        toast.error(`Session limit reached (${props.courseOffering.syllabus_template?.total_sessions ?? 0} max)`);
        return;
    }
    addSessionOpen.value = true;
};

// ---- Quick edit ----
const openQuickEdit = (session: ClassSession) => {
    selectedSession.value = session;
    quickEditOpen.value = true;
};

// ---- Delete single session — Inertia router.delete → 1 request ----
const deleteClassSession = (session: ClassSession) => {
    showConfirmDialog(
        {
            title: 'Delete Class Session',
            message: `Delete "${session.session_title || 'Unnamed Session'}" on ${new Date(session.session_date).toLocaleDateString()}? This cannot be undone.`,
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
                        onError: () => reject(new Error('Failed to delete session')),
                    });
                }),
        },
    );
};

// ---- Bulk edit ----
const openBulkEdit = () => {
    if (!selectedSessions.value.length) {
        toast.error('Select at least one session');
        return;
    }
    bulkEditOpen.value = true;
};

// ---- Bulk delete — JSON API (no Inertia route) ----
const bulkDeleteSessions = () => {
    if (!selectedSessions.value.length) {
        toast.error('Select sessions to delete');
        return;
    }
    const count = selectedSessions.value.length;
    showConfirmDialog(
        {
            title: 'Delete Sessions',
            message: `Delete ${count} selected session(s)? This cannot be undone.`,
            confirmText: 'Delete',
        },
        {
            onConfirm: async () => {
                const res = await api.delete('/api/class-sessions/bulk', {
                    ids: selectedSessions.value.map((s) => s.id),
                });
                if (res.data?.value?.success) {
                    selectedSessions.value = [];
                    classSessionsTable.value?.clearSelection();
                    router.reload({ only: ['courseOffering'] });
                    toast.success(`${count} session(s) deleted`);
                } else {
                    toast.error(res.data?.value?.message || 'Failed to delete sessions');
                    throw new Error('bulk delete failed');
                }
            },
        },
    );
};

const handleSelectionChange = (sessions: ClassSession[]) => {
    selectedSessions.value = sessions;
};

// ---- Table columns ----
const classSessionColumns: ColumnDef<ClassSession>[] = [
    createSelectionColumn<ClassSession>(),
    {
        header: 'Session',
        id: 'session',
        enableSorting: false,
        cell: ({ row }) => {
            const s = row.original;
            return h('div', { class: 'flex items-center gap-2' }, [
                h(sessionTypeIcon(s.session_type), { class: 'h-4 w-4 shrink-0 text-muted-foreground' }),
                h('div', [h('p', { class: 'font-medium leading-tight' }, s.session_title), s.session_description && h('p', { class: 'text-muted-foreground text-xs truncate max-w-[200px]' }, s.session_description)]),
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
            return h('span', { class: 'text-sm tabular-nums' }, `${s.start_time} – ${s.end_time}`);
        },
    },
    {
        header: 'Room',
        id: 'room',
        enableSorting: false,
        cell: ({ row }) => {
            const room = row.original?.room;
            return room ? h('div', { class: 'text-sm' }, [h('span', room.name), room.building && h('span', { class: 'text-muted-foreground text-xs ml-1' }, room.building.name)]) : h('span', { class: 'text-muted-foreground text-sm' }, '—');
        },
    },
    {
        header: 'Lecturer',
        id: 'lecturer',
        enableSorting: false,
        cell: ({ row }) => (row.original.lecture?.display_name ? h('span', { class: 'text-sm' }, row.original.lecture.display_name) : h('span', { class: 'text-muted-foreground text-sm' }, '—')),
    },
    {
        header: 'Status',
        id: 'status',
        enableSorting: false,
    },
    {
        header: 'Attendance',
        id: 'attendance',
        enableSorting: false,
        cell: ({ row }) => {
            const pct = row.original.attendance_percentage;
            const val = pct != null ? Number(pct) : null;
            const color = val == null ? 'text-muted-foreground' : val >= 80 ? 'text-green-600' : val >= 60 ? 'text-yellow-600' : 'text-red-600';
            return h('span', { class: `text-sm font-medium tabular-nums ${color}` }, formatAttendance(pct));
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
                <p class="text-muted-foreground mt-1 text-sm">
                    Use "Auto-Generate" to create sessions from the syllabus,<br />
                    or add them one by one.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <RoomSelectionModal
                    :disable-generate="!courseOffering.syllabus_template || !canAddSession"
                    :available-rooms="availableRooms"
                    :is-generating="isGenerating"
                    :semester-start="courseOffering.semester?.start_date"
                    :semester-end="courseOffering.semester?.end_date"
                    :syllabus-template="courseOffering.syllabus_template"
                    @generate="generateClassSessions"
                />
                <Button variant="outline" :disabled="!canAddSession" @click="openAddSessionModal">
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
                    <!-- Bulk actions — shown when rows selected -->
                    <template v-if="selectedSessions.length">
                        <Button size="sm" variant="outline" @click="openBulkEdit">
                            <Edit2 class="mr-1.5 h-3.5 w-3.5" />
                            Edit {{ selectedSessions.length }}
                        </Button>
                        <Button size="sm" variant="destructive" @click="bulkDeleteSessions">
                            <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                            Delete {{ selectedSessions.length }}
                        </Button>
                    </template>

                    <!-- Add + Generate (always visible) -->
                    <RoomSelectionModal
                        :disable-generate="!courseOffering.syllabus_template || !canAddSession"
                        :available-rooms="availableRooms"
                        :is-generating="isGenerating"
                        :semester-start="courseOffering.semester?.start_date"
                        :semester-end="courseOffering.semester?.end_date"
                        :syllabus-template="courseOffering.syllabus_template"
                        @generate="generateClassSessions"
                    />
                    <Button size="sm" :disabled="!canAddSession" :variant="canAddSession ? 'default' : 'outline'" @click="openAddSessionModal">
                        <Calendar class="mr-1.5 h-3.5 w-3.5" />
                        {{ addSessionButtonText }}
                    </Button>
                </div>
            </div>

            <!-- Session limit warning -->
            <p v-if="!canAddSession && courseOffering.syllabus_template?.total_sessions" class="text-xs text-orange-600">Session limit reached ({{ courseOffering.syllabus_template.total_sessions }} max per syllabus).</p>

            <DataTable
                ref="classSessionsTable"
                :data="courseOffering.class_sessions ?? []"
                :columns="classSessionColumns"
                :enable-row-selection="!isAllClassSessionsCompleted"
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
                        <!-- Quick edit — only for scheduled sessions -->
                        <Button v-if="row.original.status === 'scheduled'" variant="ghost" size="sm" title="Quick edit" @click="openQuickEdit(row.original)">
                            <Settings class="h-4 w-4" />
                        </Button>

                        <!-- View attendance detail -->
                        <Link :href="attendanceRoutes.classSessions.show(row.original.id)">
                            <Button variant="ghost" size="sm" title="View attendance">
                                <Eye class="h-4 w-4" />
                            </Button>
                        </Link>

                        <!-- Delete — only for scheduled sessions -->
                        <Button v-if="row.original.status === 'scheduled'" variant="ghost" size="sm" title="Delete session" class="text-destructive hover:bg-destructive/10 hover:text-destructive" @click="deleteClassSession(row.original)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>
        </template>

        <!-- Modals -->
        <AddClassSessionModal
            :open="addSessionOpen"
            :course-offering-id="courseOffering.id"
            :campus-id="courseOffering.campus_id"
            :default-start-time="courseOffering.schedule_time_start"
            :default-end-time="courseOffering.schedule_time_end"
            @update:open="addSessionOpen = $event"
            @session-created="addSessionOpen = false"
        />

        <QuickEditClassSessionModal :open="quickEditOpen" :session="selectedSession" :campus-id="courseOffering.campus_id" @update:open="quickEditOpen = $event" @session-updated="quickEditOpen = false" />

        <BulkEditClassSessionModal :open="bulkEditOpen" :selected-sessions="selectedSessions" :campus-id="courseOffering.campus_id" @update:open="bulkEditOpen = $event" @sessions-updated="bulkEditOpen = false" />
    </div>
</template>
