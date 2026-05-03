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
import { ColumnDef } from '@tanstack/vue-table';
import { format } from 'date-fns';
import { BookOpen, Calendar, Clock, Edit2, Eye, Settings, Trash2 } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    courseOffering: CourseOffering;
    availableRooms: Room[];
}

const props = defineProps<Props>();
const api = useApi();
const { showConfirmDialog } = useGlobalConfirmDialog();

const isGenerating = ref(false);
const classSessionsTable = ref();

const isAllClassSessionsCompleted = computed(() => props.courseOffering.class_sessions?.every((s) => s.status === 'completed') ?? true);

// Quick edit modal state
const quickEditOpen = ref(false);
const selectedSession = ref<ClassSession | null>(null);

// Bulk edit modal state
const bulkEditOpen = ref(false);
const selectedSessions = ref<ClassSession[]>([]);

// Add session modal state
const addSessionOpen = ref(false);

const canAddSession = computed(() => {
    if (!props.courseOffering.syllabus_template?.total_sessions) return true;
    const currentCount = props.courseOffering.class_sessions?.length || 0;
    return currentCount < props.courseOffering.syllabus_template.total_sessions;
});

const getAddSessionButtonText = computed(() => {
    if (!props.courseOffering.syllabus_template?.total_sessions) return 'Add Class Session';
    const currentCount = props.courseOffering.class_sessions?.length || 0;
    const maxSessions = props.courseOffering.syllabus_template.total_sessions;
    if (canAddSession.value) return `Add Class Session (${currentCount}/${maxSessions})`;
    return `Session Limit Reached (${currentCount}/${maxSessions})`;
});

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    return format(dateString, 'EEEE, dd/MM/yyyy');
};

const formatAttendancePercentage = (percentage: number | string | null | undefined): string => {
    if (percentage === null || percentage === undefined || percentage === '') return '0.00%';
    const num = typeof percentage === 'string' ? parseFloat(percentage) : percentage;
    if (isNaN(num)) return '0.00%';
    return `${num.toFixed(2)}%`;
};

const getSessionTypeIcon = (type: string) => {
    switch (type) {
        case 'lecture':
            return BookOpen;
        case 'assessment':
            return Clock;
        default:
            return BookOpen;
    }
};

const getSessionStatusVariant = (status: string) => {
    switch (status) {
        case 'scheduled':
            return 'default';
        case 'completed':
            return 'outline';
        case 'cancelled':
            return 'destructive';
        default:
            return 'secondary';
    }
};

// Generate sessions
const generateClassSessions = async ({ roomId, startDate, weeklySchedule, excludedDates }: { roomId: number; startDate: string; weeklySchedule: any; excludedDates: { start: string; end: string }[] }) => {
    try {
        isGenerating.value = true;
        const result = await api.post(`/api/course-offerings/${props.courseOffering.id}/class-sessions/generate`, {
            room_id: roomId,
            start_date: startDate,
            weekly_schedule: weeklySchedule,
            excluded_dates: excludedDates,
        });
        if (result.data?.value?.success) {
            toast.success(`${result.data.value.data.sessions_count} class sessions generated successfully`);
            router.reload({ only: ['courseOffering'] });
        } else {
            toast.error(result.data?.value?.message || 'Failed to generate class sessions');
        }
    } catch {
        toast.error('Failed to generate class sessions');
    } finally {
        isGenerating.value = false;
    }
};

// Open add session modal
const openAddSessionModal = () => {
    if (!canAddSession.value) {
        const maxSessions = props.courseOffering.syllabus_template?.total_sessions || 0;
        toast.error(`Cannot add more sessions. Maximum of ${maxSessions} sessions allowed by syllabus template.`);
        return;
    }
    addSessionOpen.value = true;
};

// Session created
const handleSessionCreated = () => {
    router.reload({ only: ['courseOffering'] });
    toast.success('Class session created successfully');
};

// Quick edit
const openQuickEdit = (session: ClassSession) => {
    selectedSession.value = session;
    quickEditOpen.value = true;
};

const handleSessionUpdated = () => {
    router.reload({ only: ['courseOffering'] });
    toast.success('Class session updated successfully');
};

// Delete single session
const deleteClassSession = (session: ClassSession) => {
    const sessionTitle = session.session_title || 'Unnamed Session';
    const sessionDate = new Date(session.session_date).toLocaleDateString();
    showConfirmDialog(
        { title: 'Delete Class Session', message: `Are you sure you want to delete "${sessionTitle}" scheduled on ${sessionDate}? This action cannot be undone.`, confirmText: 'Delete Session' },
        {
            onConfirm: async () => {
                try {
                    const result = await api.delete(`/api/class-sessions/${session.id}`);
                    if (result.data?.value?.success) {
                        classSessionsTable.value?.clearSelection();
                        router.reload({ only: ['courseOffering'] });
                        toast.success('Class session deleted successfully');
                    } else {
                        toast.error(result.data?.value?.message || 'Failed to delete class session');
                    }
                } catch {
                    toast.error('Failed to delete class session');
                    throw new Error('Failed to delete class session');
                }
            },
        },
    );
};

// Selection change
const handleSelectionChange = (sessions: ClassSession[]) => {
    selectedSessions.value = sessions;
};

// Bulk edit
const openBulkEdit = () => {
    if (selectedSessions.value.length === 0) {
        toast.error('Please select at least one session');
        return;
    }
    bulkEditOpen.value = true;
};

const handleBulkEditSuccess = () => {
    router.reload({ only: ['courseOffering'] });
};

// Bulk delete
const bulkDeleteSessions = () => {
    if (selectedSessions.value.length === 0) {
        toast.error('Please select sessions to delete');
        return;
    }
    const count = selectedSessions.value.length;
    showConfirmDialog(
        { title: 'Bulk Delete Class Sessions', message: `Are you sure you want to delete ${count} selected class sessions? This action cannot be undone.`, confirmText: 'Delete Sessions' },
        {
            onConfirm: async () => {
                try {
                    const result = await api.delete('/api/class-sessions/bulk', {
                        ids: selectedSessions.value.map((s) => s.id),
                    });
                    if (result.data?.value?.success) {
                        selectedSessions.value = [];
                        classSessionsTable.value?.clearSelection();
                        router.reload({ only: ['courseOffering'] });
                        toast.success(result.data.value.message || `${count} class sessions deleted successfully`);
                    } else {
                        toast.error(result.data?.value?.message || 'Failed to delete class sessions');
                    }
                } catch {
                    toast.error('Failed to delete class sessions');
                    throw new Error('Failed to delete class sessions');
                }
            },
        },
    );
};

// Table columns
const classSessionColumns: ColumnDef<ClassSession>[] = [
    createSelectionColumn<ClassSession>(),
    {
        header: 'Session',
        id: 'session',
        enableSorting: false,
        cell: ({ row }) => {
            const session = row.original;
            return h('div', { class: 'flex items-center gap-2' }, [
                h(getSessionTypeIcon(session.session_type), { class: 'h-4 w-4' }),
                h('div', [h('p', { class: 'font-medium' }, session.session_title), session.session_description && h('p', { class: 'text-muted-foreground text-sm' }, session.session_description)]),
            ]);
        },
    },
    {
        header: 'Date',
        id: 'session_date',
        enableSorting: false,
        cell: ({ row }) => formatDate(row.original.session_date),
    },
    {
        header: 'Time',
        id: 'time',
        enableSorting: false,
        cell: ({ row }) => {
            const session = row.original;
            return `${session.start_time} - ${session.end_time}`;
        },
    },
    {
        header: 'Room',
        id: 'room',
        enableSorting: false,
        cell: ({ row }) => row.original?.room?.name || 'N/A',
    },
    {
        header: 'Lecturer',
        id: 'lecturer',
        enableSorting: false,
        cell: ({ row }) => row.original.lecture?.display_name?.toUpperCase() || 'N/A',
    },
    {
        header: 'Status',
        id: 'status',
        enableSorting: false,
        cell: ({ row }) => {
            const session = row.original;
            return h(Badge, { variant: getSessionStatusVariant(session.status) }, () => session.status.toUpperCase());
        },
    },
    {
        header: 'Attendance',
        id: 'attendance',
        enableSorting: false,
        cell: ({ row }) => {
            return h('div', { class: 'text-sm font-medium' }, formatAttendancePercentage(row.original.attendance_percentage));
        },
    },
    {
        header: 'Actions',
        id: 'actions',
        enableSorting: false,
        enableHiding: false,
    },
];
</script>

<template>
    <div class="space-y-4">
        <!-- Empty state -->
        <div v-if="!courseOffering.class_sessions || courseOffering.class_sessions.length === 0" class="space-y-2 py-8 text-center">
            <Calendar class="text-muted-foreground mx-auto h-12 w-12" />
            <h3 class="mt-2 text-sm font-semibold text-gray-900">No sessions scheduled yet</h3>
            <p class="text-muted-foreground mt-1 text-sm">Click "Auto-Generate Sessions" to create class sessions based on the syllabus.</p>
            <div class="mt-1 flex items-center justify-center gap-2">
                <RoomSelectionModal
                    :disable-generate="!courseOffering.syllabus_template || !canAddSession"
                    :available-rooms="availableRooms"
                    :is-generating="isGenerating"
                    :semester-start="courseOffering.semester?.start_date"
                    :semester-end="courseOffering.semester?.end_date"
                    :syllabus-template="courseOffering.syllabus_template"
                    @generate="generateClassSessions"
                />
            </div>
            <div v-if="!courseOffering.syllabus_template || !courseOffering.syllabus_template.total_sessions">
                <p class="text-sm text-yellow-600">Please ensure a syllabus template with total sessions is assigned to this course offering before generating class sessions.</p>
            </div>
        </div>

        <!-- Sessions table -->
        <div v-else class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="text-muted-foreground text-sm">{{ courseOffering.class_sessions.length }} session(s)</div>
                <div class="flex items-center gap-2">
                    <Button v-if="selectedSessions.length > 0" size="sm" variant="default" @click="openBulkEdit">
                        <Edit2 class="mr-2 h-4 w-4" />
                        Bulk Edit ({{ selectedSessions.length }})
                    </Button>
                    <Button v-if="selectedSessions.length > 0" size="sm" variant="destructive" @click="bulkDeleteSessions">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete ({{ selectedSessions.length }})
                    </Button>
                </div>
            </div>

            <DataTable
                ref="classSessionsTable"
                :data="courseOffering.class_sessions || []"
                :columns="classSessionColumns"
                :enable-row-selection="!isAllClassSessionsCompleted"
                :enable-server-sorting="false"
                empty-message="No class sessions found."
                @selection-change="handleSelectionChange"
            >
                <template #cell-actions="{ row }">
                    <div class="flex items-center gap-2">
                        <Button v-if="row.original.status !== 'completed' && row.original.status !== 'in_progress'" variant="ghost" size="sm" title="Quick Edit" @click="openQuickEdit(row.original)">
                            <Settings class="h-4 w-4" />
                        </Button>
                        <Link :href="attendanceRoutes.classSessions.show(row.original.id)">
                            <Button variant="ghost" size="sm" title="View Details">
                                <Eye class="h-4 w-4" />
                            </Button>
                        </Link>
                        <Button
                            v-if="row.original.status !== 'completed' && row.original.status !== 'in_progress'"
                            variant="ghost"
                            size="sm"
                            title="Delete Session"
                            class="text-destructive hover:text-destructive hover:bg-destructive/10"
                            @click="deleteClassSession(row.original)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
                <template #cell-status="{ row }">
                    <Badge :variant="getSessionStatusVariant(row.original.status)">
                        {{ row.original.status.toUpperCase() }}
                    </Badge>
                </template>
            </DataTable>

            <!-- Add session + generate buttons -->
            <div class="flex items-center justify-center gap-2">
                <RoomSelectionModal
                    :disable-generate="!courseOffering.syllabus_template || !canAddSession"
                    :available-rooms="availableRooms"
                    :is-generating="isGenerating"
                    :semester-start="courseOffering.semester?.start_date"
                    :semester-end="courseOffering.semester?.end_date"
                    :syllabus-template="courseOffering.syllabus_template"
                    @generate="generateClassSessions"
                />

                <Button @click="openAddSessionModal" :disabled="!canAddSession" :variant="canAddSession ? 'default' : 'outline'">
                    <Calendar class="mr-2 h-4 w-4" />
                    {{ getAddSessionButtonText }}
                </Button>
            </div>

            <div v-if="!canAddSession && courseOffering.syllabus_template?.total_sessions" class="text-center">
                <p class="text-sm text-orange-600">Session limit reached. Maximum of {{ courseOffering.syllabus_template.total_sessions }} sessions allowed by syllabus template.</p>
            </div>
        </div>

        <!-- Modals -->
        <QuickEditClassSessionModal :open="quickEditOpen" :session="selectedSession" @update:open="quickEditOpen = $event" @session-updated="handleSessionUpdated" :campus_id="courseOffering.campus_id" />

        <AddClassSessionModal :open="addSessionOpen" :course-offering-id="courseOffering.id" :campus_id="courseOffering.campus_id" @update:open="addSessionOpen = $event" @session-created="handleSessionCreated" />

        <BulkEditClassSessionModal :open="bulkEditOpen" :selected-sessions="selectedSessions" :campus_id="courseOffering.campus_id" @update:open="bulkEditOpen = $event" @sessions-updated="handleBulkEditSuccess" />
    </div>
</template>
