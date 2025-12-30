<script setup lang="ts">
import AddClassSessionModal from '@/components/AddClassSessionModal.vue';
import BulkEditClassSessionModal from '@/components/BulkEditClassSessionModal.vue';
import DataTable from '@/components/DataTable.vue';
import QuickEditClassSessionModal from '@/components/QuickEditClassSessionModal.vue';
import RoomSelectionModal from '@/components/RoomSelectionModal.vue';
import StudentSearchModal from '@/components/StudentSearchModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useApi } from '@/composables/useApiRequest';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { usePermission } from '@/composables/usePermission';
import { createSelectionColumn } from '@/lib/table-utils';
import type { AcademicRecord, ClassSession, CourseOffering, CourseRegistration, Room } from '@/types/models';
import { formatDateTimeToShort } from '@/utils/date';
import { classSessionRoutes, curriculumRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { format } from 'date-fns';
import { ArrowLeft, BookOpen, Calendar, ChevronDown, Clock, Edit, Edit2, ExternalLink, Eye, MapPin, Settings, Trash2, UserCheck, Users } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    courseOffering: CourseOffering & {
        course_registrations: CourseRegistration[];
        class_sessions?: ClassSession[];
        academic_records?: AcademicRecord[];
    };
    availableRooms: Room[];
    // canGenerateClassSessions: boolean;
}

const props = defineProps<Props>();
const api = useApi();
const { showConfirmDialog } = useGlobalConfirmDialog();
const { can } = usePermission();
// const showStatusModal = ref(false);
const isGenerating = ref(false);
const classSessionsTable = ref();
const isAllClassSessionsCompleted = computed(() => {
    return props.courseOffering.class_sessions?.every((session) => session.status === 'completed') ?? true;
});
const isStartedCourse = computed(() => {
    return props.courseOffering.class_sessions?.some((session) => session.status === 'in_progress' || session.status === 'completed') ?? false;
});
const getStatusVariant = (status: string) => {
    switch (status) {
        case 'open':
            return 'default';
        case 'waitlist_only':
            return 'secondary';
        case 'cancelled':
            return 'destructive';
        case 'closed':
            return 'outline';
        default:
            return 'outline';
    }
};

const getRegistrationStatusVariant = (status: string) => {
    switch (status) {
        case 'registered':
            return 'default';
        case 'confirmed':
            return 'default';
        case 'dropped':
            return 'destructive';
        case 'withdrawn':
            return 'secondary';
        case 'completed':
            return 'outline';
        default:
            return 'outline';
    }
};

const getDeliveryModeLabel = (mode: string) => {
    switch (mode) {
        case 'in_person':
            return 'In Person';
        case 'online':
            return 'Online';
        case 'hybrid':
            return 'Hybrid';
        default:
            return mode;
    }
};

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    //format short day - mm/dd/yyyy
    return format(dateString, 'EEEE, dd/MM/yyyy');
};
const formatAttendancePercentage = (percentage: number | string | null | undefined): string => {
    if (percentage === null || percentage === undefined || percentage === '') return '0.00%';
    const numPercentage = typeof percentage === 'string' ? parseFloat(percentage) : percentage;
    if (isNaN(numPercentage)) return '0.00%';
    return `${numPercentage.toFixed(2)}%`;
};

const enrollmentPercentage = props.courseOffering.max_capacity > 0 ? Math.round((props.courseOffering.current_enrollment / props.courseOffering.max_capacity) * 100) : 0;

// const handleStatusUpdate = () => {
//     // Refresh the page to get updated registration data
//     router.reload({
//         only: ['courseOffering'],
//     });
//     toast.success('Registration status updated successfully');
// };

// Class sessions management functions
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
            router.reload({
                only: ['courseOffering'],
            });
        } else {
            toast.error(result.data?.value?.message || 'Failed to generate class sessions');
        }
    } catch (error) {
        console.error('Error generating class sessions:', error);
        toast.error('Failed to generate class sessions');
    } finally {
        isGenerating.value = false;
    }
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
const editCourseOffering = () => {
    router.visit(`/course-offerings/${props.courseOffering.id}/edit`);
};
// Quick edit modal state
const quickEditOpen = ref(false);
const selectedSession = ref<ClassSession | null>(null);

// Bulk edit modal state
const bulkEditOpen = ref(false);
const selectedSessions = ref<ClassSession[]>([]);

// Add class session modal state
const addSessionOpen = ref(false);

// Student addition success handler
const handleStudentAddSuccess = () => {
    router.reload({ only: ['courseOffering'] });
};

// Delete student registration
const deleteStudentRegistration = (registration: CourseRegistration) => {
    const studentName = registration.student?.full_name || 'Unknown Student';
    const studentId = registration.student?.student_id || 'Unknown ID';

    showConfirmDialog(
        {
            title: 'Remove Student from Course',
            message: `Are you sure you want to remove ${studentName} (${studentId}) from this course? This action cannot be undone.`,
            confirmText: 'Remove Student',
        },
        {
            onConfirm: async () => {
                try {
                    const result = await api.post(`/course-offerings/${props.courseOffering.id}/delete-student-registration`, {
                        registration_id: registration.id,
                    });

                    if (result.data?.value?.success) {
                        toast.success(result.data.value.message || `Successfully removed ${studentName} from the course`);
                        router.reload({ only: ['courseOffering'] });
                    } else {
                        toast.error(result.data?.value?.message || 'Failed to remove student from course');
                    }
                } catch (error) {
                    console.error('Error removing student:', error);
                    toast.error('Failed to remove student from course');
                    throw error; // Keep dialog open on error
                }
            },
        },
    );
};

// Quick edit session functions
const openQuickEdit = (session: ClassSession) => {
    selectedSession.value = session;
    quickEditOpen.value = true;
};

const handleSessionUpdated = () => {
    // Refresh the page to show updated data
    router.reload({ only: ['courseOffering'] });
    toast.success('Class session updated successfully');
};

// Delete single class session
const deleteClassSession = (session: ClassSession) => {
    const sessionTitle = session.session_title || 'Unnamed Session';
    const sessionDate = new Date(session.session_date).toLocaleDateString();

    showConfirmDialog(
        {
            title: 'Delete Class Session',
            message: `Are you sure you want to delete "${sessionTitle}" scheduled on ${sessionDate}? This action cannot be undone.`,
            confirmText: 'Delete Session',
        },
        {
            onConfirm: async () => {
                try {
                    const result = await api.delete(`/api/class-sessions/${session.id}`);

                    if (result.data?.value?.success) {
                        classSessionsTable.value?.clearSelection();
                        router.reload({
                            only: ['courseOffering'],
                        });
                        toast.success('Class session deleted successfully');
                    } else {
                        toast.error(result.data?.value?.message || 'Failed to delete class session');
                    }
                } catch (error) {
                    console.error('Error deleting class session:', error);
                    toast.error('Failed to delete class session');
                    throw error; // Keep dialog open on error
                }
            },
        },
    );
};

// Add class session functions
const canAddSession = computed(() => {
    // Check if we have a syllabus template with total_sessions limit
    if (!props.courseOffering.syllabus_template?.total_sessions) {
        return true; // No limit, can always add
    }

    const currentSessionCount = props.courseOffering.class_sessions?.length || 0;
    return currentSessionCount < props.courseOffering.syllabus_template.total_sessions;
});

const openAddSessionModal = () => {
    if (!canAddSession.value) {
        const maxSessions = props.courseOffering.syllabus_template?.total_sessions || 0;
        toast.error(`Cannot add more sessions. Maximum of ${maxSessions} sessions allowed by syllabus template.`);
        return;
    }
    addSessionOpen.value = true;
};

const handleSessionCreated = () => {
    // Refresh the page to show updated data
    router.reload({ only: ['courseOffering'] });
    toast.success('Class session created successfully');
};

const getAddSessionButtonText = computed(() => {
    if (!props.courseOffering.syllabus_template?.total_sessions) {
        return 'Add Class Session';
    }

    const currentCount = props.courseOffering.class_sessions?.length || 0;
    const maxSessions = props.courseOffering.syllabus_template.total_sessions;

    if (canAddSession.value) {
        return `Add Class Session (${currentCount}/${maxSessions})`;
    }

    return `Session Limit Reached (${currentCount}/${maxSessions})`;
});

// Helper function to get academic record for a registration
const getAcademicRecordForStudent = (studentId: number) => {
    return props.courseOffering.academic_records?.find((record) => record.student_id === studentId);
};

// Class sessions table columns
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
        cell: ({ row }) => row.original.lecture?.display_name.toUpperCase() || 'N/A',
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
            const session = row.original;
            return h('div', { class: 'text-sm font-medium' }, formatAttendancePercentage(session.attendance_percentage));
        },
    },
    {
        header: 'Actions',
        id: 'actions',
        enableSorting: false,
        enableHiding: false,
    },
];

// Handle selection change
const handleSelectionChange = (sessions: ClassSession[]) => {
    selectedSessions.value = sessions;
};

// Handle bulk edit success
const handleBulkEditSuccess = () => {
    router.reload({ only: ['courseOffering'] });
};

// Open bulk edit modal
const openBulkEdit = () => {
    if (selectedSessions.value.length === 0) {
        toast.error('Please select at least one session');
        return;
    }
    bulkEditOpen.value = true;
};

// Bulk delete class sessions
const bulkDeleteSessions = () => {
    if (selectedSessions.value.length === 0) {
        toast.error('Please select sessions to delete');
        return;
    }

    const count = selectedSessions.value.length;
    showConfirmDialog(
        {
            title: 'Bulk Delete Class Sessions',
            message: `Are you sure you want to delete ${count} selected class sessions? This action cannot be undone.`,
            confirmText: 'Delete Sessions',
        },
        {
            onConfirm: async () => {
                try {
                    const result = await api.delete('/api/class-sessions/bulk', {
                        ids: selectedSessions.value.map((s) => s.id),
                    });

                    if (result.data?.value?.success) {
                        selectedSessions.value = [];
                        classSessionsTable.value?.clearSelection();
                        router.reload({
                            only: ['courseOffering'],
                        });
                        toast.success(result.data.value.message || `${count} class sessions deleted successfully`);
                    } else {
                        toast.error(result.data?.value?.message || 'Failed to delete class sessions');
                    }
                } catch (error) {
                    console.error('Error deleting class sessions:', error);
                    toast.error('Failed to delete class sessions');
                    throw error;
                }
            },
        },
    );
};
</script>

<template>

    <Head title="Course Offering Details" />
    <!-- Header -->
    <div class="flex flex-col items-center md:flex-row md:justify-between">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">{{ courseOffering.course_code }} - {{
                    courseOffering.course_title }}</h1>
                <p class="text-muted-foreground">Course offering details and enrollment information</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <Link
                v-if="!courseOffering.section_code && courseOffering.current_enrollment > 0 && !courseOffering.class_sessions?.length"
                :href="`/course-offerings/${courseOffering.id}/split`">
                <Button variant="outline">
                    <Users class="mr-2 h-4 w-4" />
                    Split into Sections
                </Button>
            </Link>
            <Button variant="outline" size="sm" @click="editCourseOffering">
                <Edit class="mr-2 h-4 w-4" />
                Edit
            </Button>
            <Link href="/course-offerings">
                <Button variant="outline" size="sm">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back
                </Button>
            </Link>
        </div>
    </div>

    <!-- Course Information -->
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Basic Info -->
        <Card class="lg:col-span-2">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Course Information</CardTitle>
                    <Badge :variant="getStatusVariant(courseOffering.enrollment_status)">
                        {{ courseOffering.enrollment_status.toUpperCase() }}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Course Code</p>
                        <p class="text-lg font-semibold">{{ courseOffering.course_code }}</p>
                    </div>
                    <div v-if="courseOffering.section_code">
                        <p class="text-muted-foreground text-sm font-medium">Section</p>
                        <p class="text-lg font-semibold">{{ courseOffering.section_code }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-muted-foreground text-sm font-medium">Course Title</p>
                    <p class="text-lg font-semibold">{{ courseOffering.course_title }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- <div>
                        <p class="text-muted-foreground text-sm font-medium">Credit Hours</p>
                        <p class="text-lg font-semibold">{{ courseOffering.credit_hours }}</p>
                    </div> -->
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Delivery Mode</p>
                        <p class="text-lg font-semibold">{{ getDeliveryModeLabel(courseOffering.delivery_mode) }}</p>
                    </div>
                </div>

                <div v-if="courseOffering.location" class="flex items-center gap-2">
                    <MapPin class="text-muted-foreground h-4 w-4" />
                    <span>{{ courseOffering.location }}</span>
                </div>

                <div v-if="courseOffering.notes">
                    <p class="text-muted-foreground text-sm font-medium">Notes</p>
                    <p class="text-sm">{{ courseOffering.notes }}</p>
                </div>
            </CardContent>
        </Card>

        <!-- Enrollment Stats -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-4 w-4" />
                    Enrollment
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="text-center">
                    <p class="text-3xl font-bold">{{ courseOffering.current_enrollment }}/{{ courseOffering.max_capacity
                        }}</p>
                    <p class="text-muted-foreground text-sm">Students Enrolled</p>
                    <div class="mt-2 h-2 w-full rounded-full bg-gray-200">
                        <div class="h-2 rounded-full bg-blue-600" :style="{ width: `${enrollmentPercentage}%` }"></div>
                    </div>
                    <p class="text-muted-foreground mt-1 text-sm">{{ enrollmentPercentage }}% Full</p>
                </div>

                <Separator />

                <div>
                    <p class="text-muted-foreground text-sm font-medium">Waitlist</p>
                    <p class="text-lg font-semibold">{{ courseOffering.current_waitlist }}/{{
                        courseOffering.waitlist_capacity }}</p>
                </div>

                <div>
                    <p class="text-muted-foreground text-sm font-medium">Available Spots</p>
                    <p class="text-lg font-semibold">
                        {{ Math.max(0, courseOffering.max_capacity - courseOffering.current_enrollment) }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Academic Information -->
    <div class="grid gap-6 lg:grid-cols-2">
        <!-- Semester & Campus -->
        <Card>
            <CardHeader>
                <CardTitle>Academic Details</CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 gap-4">
                <div class="space-y-4">
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Semester</p>
                        <p class="text-lg font-semibold">{{ courseOffering.semester?.name }} ({{
                            courseOffering.semester?.code }})</p>
                    </div>

                    <div v-if="courseOffering.curriculum_unit">
                        <p class="text-muted-foreground text-sm font-medium">Unit</p>
                        <Link :href="`/units/${courseOffering.curriculum_unit.unit.id}`"
                            class="flex items-center gap-2 text-lg font-semibold text-green-400">
                            <span class="">{{ courseOffering.curriculum_unit.unit.code }} - {{
                                courseOffering.curriculum_unit.unit.name }}</span>
                            <ExternalLink class="h-4 w-4" />
                        </Link>
                    </div>

                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Lecturer</p>
                        <p class="text-muted-foreground text-lg font-semibold">{{ courseOffering.lecture?.display_name
                            || 'Not set' }}</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <!-- show syllabus link -->
                    <!-- <div>
                        <p class="text-muted-foreground text-sm font-medium">Syllabus</p>
                        <Link
                            v-if="courseOffering.curriculum_unit?.syllabus"
                            :href="curriculumRoutes.units.syllabusShow(courseOffering.curriculum_unit.unit.id, courseOffering.curriculum_unit.syllabus.id)"
                            class="flex items-center gap-2 text-lg font-semibold"
                        >
                            {{ courseOffering.curriculum_unit.syllabus.version }}
                            <ExternalLink class="h-4 w-4" />
                        </Link>
                        <p v-else class="font-semibold">No syllabus</p>
                    </div> -->

                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Syllabus</p>
                        <div v-if="courseOffering.syllabus_template" class="space-y-2">
                            <div class="flex items-start gap-2">
                                <div class="flex-1">
                                    <!-- <p class="text-lg font-semibold">{{ courseOffering.syllabus_template.title }} - {{ courseOffering.syllabus_template.version }}</p> -->
                                    <Link v-if="courseOffering.syllabus_template"
                                        :href="curriculumRoutes.syllabusTemplates.show(courseOffering.syllabus_template.id)"
                                        class="flex items-center gap-2 text-lg font-semibold">
                                        {{ courseOffering.syllabus_template.title }} - {{
                                            courseOffering.syllabus_template.version }}
                                        <ExternalLink class="h-4 w-4" />
                                    </Link>
                                    <p v-if="courseOffering.syllabus_template.description"
                                        class="text-muted-foreground mt-1 text-sm">
                                        {{ courseOffering.syllabus_template.description }}
                                    </p>
                                    <div class="text-muted-foreground mt-2 flex items-center gap-4 text-xs">
                                        <span v-if="courseOffering.syllabus_template.unit"> {{
                                            courseOffering.syllabus_template.unit.code }} - {{
                                                courseOffering.syllabus_template.unit.name }} </span>
                                        <span v-if="courseOffering.syllabus_template.delivery_mode">
                                            {{ getDeliveryModeLabel(courseOffering.syllabus_template.delivery_mode) }}
                                        </span>
                                        <span v-if="courseOffering.syllabus_template.applicable_campus">
                                            {{ courseOffering.syllabus_template.applicable_campus.name }}
                                        </span>
                                        <span v-if="courseOffering.syllabus_template.applicable_program">
                                            {{ courseOffering.syllabus_template.applicable_program.name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-muted-foreground font-semibold">No Syllabus assigned</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Dates & Deadlines -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Calendar class="h-4 w-4" />
                    Important Dates
                </CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 gap-4">
                <div class="space-y-4">
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Registration Period</p>
                        <p class="text-sm">
                            {{ formatDate(courseOffering.registration_start_date) }} -
                            {{ formatDate(courseOffering.registration_end_date) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Drop Deadline</p>
                        <p class="text-sm">{{ formatDate(courseOffering.drop_deadline) }}</p>
                    </div>

                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Withdrawal Deadline</p>
                        <p class="text-sm">{{ formatDate(courseOffering.withdrawal_deadline) }}</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <!-- schedule day -->
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Schedule Day</p>
                        <p class="text-muted-foreground text-sm font-semibold">
                            {{ courseOffering.schedule_days?.join(',') ?? 'Not set' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Schedule Time</p>
                        <p class="text-sm">{{ courseOffering.schedule_time_start }} - {{
                            courseOffering.schedule_time_end }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Class Sessions Management -->
    <Collapsible default-open>
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CollapsibleTrigger asChild>
                        <Button variant="ghost" class="w-full justify-start p-0 font-normal">
                            <div class="flex items-center gap-2">
                                <Calendar class="h-4 w-4" />
                                <CardTitle class="flex items-center gap-2"> Class Sessions Management </CardTitle>
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 transition-transform duration-200 [&[data-state=open]]:rotate-180" />
                            </div>
                        </Button>
                    </CollapsibleTrigger>
                </div>
            </CardHeader>
            <CollapsibleContent>
                <CardContent>
                    <div v-if="!courseOffering.class_sessions || courseOffering.class_sessions.length === 0"
                        class="space-y-2 text-center">
                        <Calendar class="text-muted-foreground mx-auto h-12 w-12" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No class sessions</h3>
                        <p class="text-muted-foreground mt-1 text-sm">Click "Auto-Generate Sessions" to create class
                            sessions based on the syllabus.</p>
                        <div class="mt-1 flex items-center justify-center gap-2">
                            <RoomSelectionModal :disable-generate="!courseOffering.syllabus_template || !canAddSession"
                                :available-rooms="availableRooms" :is-generating="isGenerating"
                                :semester-start="courseOffering.semester?.start_date"
                                :semester-end="courseOffering.semester?.end_date"
                                :syllabus-template="courseOffering.syllabus_template"
                                @generate="generateClassSessions" />
                        </div>
                        <div
                            v-if="!courseOffering.syllabus_template || !courseOffering.syllabus_template.total_sessions">
                            <!-- Notice for user about missing syllabus template -->
                            <p class="text-sm text-yellow-600">Please ensure a syllabus template with total sessions is
                                assigned to this course offering before generating class sessions.</p>
                        </div>
                    </div>
                    <div v-else class="space-y-4">
                        <!-- Change Room action -->
                        <div class="flex items-center justify-between">
                            <div class="text-muted-foreground text-sm">{{ courseOffering.class_sessions.length }}
                                session(s)</div>
                            <!-- Action buttons -->
                            <div class="flex items-center gap-2">
                                <!-- Bulk Edit Button (shown when sessions are selected) -->
                                <Button v-if="selectedSessions.length > 0" size="sm" variant="default"
                                    @click="openBulkEdit">
                                    <Edit2 class="mr-2 h-4 w-4" />
                                    Bulk Edit ({{ selectedSessions.length }})
                                </Button>
                                <!-- Bulk Delete Button -->
                                <Button v-if="selectedSessions.length > 0" size="sm" variant="destructive"
                                    @click="bulkDeleteSessions">
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Delete ({{ selectedSessions.length }})
                                </Button>
                            </div>
                        </div>

                        <DataTable ref="classSessionsTable" :data="courseOffering.class_sessions || []"
                            :columns="classSessionColumns" :enable-row-selection="!isAllClassSessionsCompleted"
                            :enable-server-sorting="false" empty-message="No class sessions found."
                            @selection-change="handleSelectionChange">
                            <template #cell-actions="{ row }">
                                <div class="flex items-center gap-2">
                                    <Button
                                        v-if="row.original.status !== 'completed' && row.original.status !== 'in_progress'"
                                        variant="ghost" size="sm" title="Quick Edit"
                                        @click="openQuickEdit(row.original)">
                                        <Settings class="h-4 w-4" />
                                    </Button>
                                    <Link :href="classSessionRoutes.show(row.original.id)">
                                        <Button variant="ghost" size="sm" title="View Details">
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </Link>
                                    <Button
                                        v-if="row.original.status !== 'completed' && row.original.status !== 'in_progress'"
                                        variant="ghost" size="sm" title="Delete Session"
                                        class="text-destructive hover:text-destructive hover:bg-destructive/10"
                                        @click="deleteClassSession(row.original)">
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
                        <!-- Add Class Session Button -->
                        <div class="flex items-center justify-center gap-2">
                            <RoomSelectionModal :disable-generate="!courseOffering.syllabus_template || !canAddSession"
                                :available-rooms="availableRooms" :is-generating="isGenerating"
                                :semester-start="courseOffering.semester?.start_date"
                                :semester-end="courseOffering.semester?.end_date"
                                :syllabus-template="courseOffering.syllabus_template"
                                @generate="generateClassSessions" />

                            <Button @click="openAddSessionModal" :disabled="!canAddSession"
                                :variant="canAddSession ? 'default' : 'outline'">
                                <Calendar class="mr-2 h-4 w-4" />
                                {{ getAddSessionButtonText }}
                            </Button>
                        </div>

                        <!-- Session limit warning -->
                        <div v-if="!canAddSession && courseOffering.syllabus_template?.total_sessions"
                            class="text-center">
                            <p class="text-sm text-orange-600">Session limit reached. Maximum of {{
                                courseOffering.syllabus_template.total_sessions }} sessions allowed by syllabus
                                template.</p>
                        </div>
                    </div>
                </CardContent>
            </CollapsibleContent>
        </Card>
    </Collapsible>

    <!-- Student Registration Status -->
    <Collapsible default-open>
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CollapsibleTrigger asChild>
                        <Button variant="ghost" class="w-full justify-start p-0 font-normal">
                            <div class="flex items-center gap-2">
                                <UserCheck class="h-4 w-4" />
                                <CardTitle class="flex items-center gap-2"> Student Registration Status </CardTitle>
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 transition-transform duration-200 [&[data-state=open]]:rotate-180" />
                            </div>
                        </Button>
                    </CollapsibleTrigger>

                    <!-- <Button
                        v-if="courseOffering.course_registrations && courseOffering.course_registrations.length > 0"
                        @click="showStatusModal = true"
                        variant="outline"
                    >
                        <UserCheck class="mr-2 h-4 w-4" />
                        Manage Status
                    </Button> -->
                </div>
            </CardHeader>
            <CollapsibleContent>
                <CardContent>
                    <StudentSearchModal :course-offering-id="courseOffering.id" :on-success="handleStudentAddSuccess" />
                    <div v-if="!courseOffering.course_registrations || courseOffering.course_registrations?.length === 0"
                        class="py-8 text-center">
                        <Users class="text-muted-foreground mx-auto h-12 w-12" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No registrations</h3>
                        <p class="text-muted-foreground mt-1 text-sm">No students have registered for this course
                            offering
                            yet.</p>
                    </div>
                    <div v-else>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>No</TableHead>
                                    <TableHead>Student ID</TableHead>
                                    <TableHead>Student Name</TableHead>
                                    <TableHead>Email</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Retake Info</TableHead>
                                    <TableHead>Registration Date</TableHead>
                                    <TableHead>Method</TableHead>
                                    <TableHead>Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(registration, index) in courseOffering.course_registrations"
                                    :key="registration.id">
                                    <!-- No column -->
                                    <TableCell>
                                        {{ index + 1 }}
                                    </TableCell>
                                    <TableCell class="font-medium">
                                        {{ registration.student?.student_id }}
                                    </TableCell>
                                    <TableCell>
                                        {{ registration.student?.full_name }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ registration.student?.email }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            :variant="getRegistrationStatusVariant(registration.registration_status)">
                                            {{ registration.registration_status.toUpperCase() }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <template v-if="registration.student">
                                            <div v-if="getAcademicRecordForStudent(registration.student.id)"
                                                class="space-y-1">
                                                <div v-if="getAcademicRecordForStudent(registration.student.id)?.is_repeat_course"
                                                    class="flex items-center gap-2">
                                                    <Badge variant="secondary" class="bg-orange-100 text-orange-800">
                                                        Retake (Attempt #{{
                                                            getAcademicRecordForStudent(registration.student.id)?.attempt_number
                                                        }})
                                                    </Badge>
                                                </div>
                                                <div v-else class="text-muted-foreground text-sm">First Attempt</div>
                                                <div v-if="getAcademicRecordForStudent(registration.student.id)?.is_repeat_course && getAcademicRecordForStudent(registration.student.id)?.original_record"
                                                    class="text-muted-foreground text-xs">
                                                    Previous: {{
                                                        getAcademicRecordForStudent(registration.student.id)?.original_record?.final_letter_grade
                                                        || 'N/A' }}
                                                    <template
                                                        v-if="getAcademicRecordForStudent(registration.student.id)?.original_record?.final_percentage">
                                                        ({{
                                                            Number(getAcademicRecordForStudent(registration.student.id)?.original_record?.final_percentage).toFixed(2)
                                                        }}%)
                                                    </template>
                                                    <template v-else>(N/A)</template>
                                                </div>
                                            </div>
                                            <span v-else class="text-muted-foreground text-sm">N/A</span>
                                        </template>
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ formatDateTimeToShort(registration.registration_date) }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ registration.registration_method }}
                                    </TableCell>
                                    <TableCell>
                                        <Button v-if="can('delete_student_registration') && !isStartedCourse"
                                            variant="ghost" size="sm" @click="deleteStudentRegistration(registration)"
                                            class="text-destructive hover:text-destructive hover:bg-destructive/10">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </CollapsibleContent>
        </Card>
    </Collapsible>

    <!-- Quick Edit Class Session Modal -->
    <QuickEditClassSessionModal :open="quickEditOpen" :session="selectedSession" @update:open="quickEditOpen = $event"
        @session-updated="handleSessionUpdated" :campus_id="courseOffering.campus_id" />

    <!-- Add Class Session Modal -->
    <AddClassSessionModal :open="addSessionOpen" :course-offering-id="courseOffering.id"
        :campus_id="courseOffering.campus_id" @update:open="addSessionOpen = $event"
        @session-created="handleSessionCreated" />

    <!-- Bulk Edit Class Session Modal -->
    <BulkEditClassSessionModal :open="bulkEditOpen" :selected-sessions="selectedSessions"
        :campus_id="courseOffering.campus_id" @update:open="bulkEditOpen = $event"
        @sessions-updated="handleBulkEditSuccess" />

    <!-- Registration Status Management Modal -->
    <!--    <RegistrationStatusModal-->
    <!--        v-if="showStatusModal"-->
    <!--        :course-offering="courseOffering"-->
    <!--        @close="showStatusModal = false"-->
    <!--        @updated="handleStatusUpdate"-->
    <!--    />-->
</template>
