<script setup lang="ts">
import DebouncedInput from '@/components/DebouncedInput.vue';
import FilterPanel from '@/components/filters/FilterPanel.vue';
import ServerPaginatedDataTable from '@/components/tables/ServerPaginatedDataTable.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useDataTable } from '@/composables/useDataTable';
import { usePermissions } from '@/composables/usePermissions';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { AlertTriangle, Bell, CheckCircle2, Mail, Send, Settings } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { route } from 'ziggy-js';

interface AcademicWarningRow {
    id: number;
    dedupe_key: string;
    student: {
        id: number;
        name: string;
        student_id: string;
        email: string | null;
        program: string | null;
    };
    intake: string | null;
    semester: string | null;
    current_cumulative_gpa: number;
    total_credits_earned: number;
    total_credits_attempted: number;
    academic_standing: string;
    warning_sent_at: string | null;
    warning_status: string | null;
}

interface AttendanceWarningStudent {
    dedupe_key: string;
    warning_type: 'attendance_early_warning' | 'attendance_limit_exceeded';
    student: {
        id: number;
        name: string;
        student_id: string;
        email: string | null;
    };
    intake: string | null;
    absence_count: number;
    warning_absences: number;
    allowed_absences: number;
    total_sessions: number;
    attendance_rate: number;
    latest_absent_session: {
        id: number;
        date: string | null;
        sequence_number: number | null;
    } | null;
    status: 'warning' | 'limit_exceeded';
    warning_sent_at: string | null;
    warning_status: string | null;
}

interface AttendanceSection {
    id: number;
    section_code: string;
    course_code: string;
    course_name: string;
    lecture_name: string | null;
    total_sessions: number;
    min_attendance_threshold: number;
    warning_absences: number;
    allowed_absences: number;
    warning_count: number;
    students: AttendanceWarningStudent[];
}

interface AttendanceSubject {
    id: number;
    code: string;
    name: string;
    warning_count: number;
    sections: AttendanceSection[];
}

interface Filters {
    search: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

const props = defineProps<{
    academic_warnings: PaginatedResponse<AcademicWarningRow>;
    attendance_subjects: AttendanceSubject[];
    active_semester: { id: number; name: string; code: string } | null;
    settings: {
        attendance_warning_ratio: number;
        channels: string[];
    };
    filters?: Partial<Filters>;
}>();

const permission = usePermissions();
const sendingKey = ref<string | null>(null);

const canSend = computed(() => permission.can('send_manual_notification'));
const channelText = computed(() => props.settings.channels.map((channel) => (channel === 'realtime' ? 'in-app' : channel)).join(' + '));
const attendanceWarningTotal = computed(() => props.attendance_subjects.reduce((total, subject) => total + subject.warning_count, 0));

const {
    filters,
    hasActiveFilters,
    isLoading,
    currentSort,
    currentDirection,
    handleSearch,
    handleSortChange,
    handlePaginationNavigate,
    handlePageSizeChange,
    clearAllFilters,
} = useDataTable<Filters>({
    baseUrl: route('academic.warnings.index'),
    initialFilters: {
        search: props.filters?.search ?? '',
        sort: props.filters?.sort ?? null,
        direction: props.filters?.direction ?? null,
        per_page: props.filters?.per_page ?? 15,
    },
    defaultValues: {
        search: '',
        sort: null,
        direction: null,
        per_page: 15,
    },
    only: ['academic_warnings', 'attendance_subjects', 'filters'],
    debounce: 350,
});

const formatDate = (value: string | null) => {
    if (!value) return null;

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const isSending = (key: string) => sendingKey.value === key;

const sendAcademicWarning = (row: AcademicWarningRow) => {
    if (!canSend.value || isSending(row.dedupe_key) || row.warning_sent_at) return;

    sendingKey.value = row.dedupe_key;
    router.post(route('academic.warnings.academic-standing.send', row.id), {}, {
        preserveScroll: true,
        only: ['academic_warnings', 'attendance_subjects', 'flash'],
        onFinish: () => {
            sendingKey.value = null;
        },
    });
};

const sendAttendanceWarning = (section: AttendanceSection, studentWarning: AttendanceWarningStudent) => {
    if (!canSend.value || isSending(studentWarning.dedupe_key) || studentWarning.warning_sent_at) return;

    sendingKey.value = studentWarning.dedupe_key;
    router.post(route('academic.warnings.attendance.send', { courseOffering: section.id, student: studentWarning.student.id }), {}, {
        preserveScroll: true,
        only: ['academic_warnings', 'attendance_subjects', 'flash'],
        onFinish: () => {
            sendingKey.value = null;
        },
    });
};

const standingColumns: ColumnDef<AcademicWarningRow>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => (props.academic_warnings.current_page - 1) * props.academic_warnings.per_page + row.index + 1,
    },
    {
        header: 'Name',
        id: 'student',
        enableSorting: false,
        cell: ({ row }) =>
            h('div', [
                h('div', { class: 'font-medium' }, row.original.student.name),
                h('div', { class: 'text-xs text-muted-foreground' }, row.original.student.email ?? ''),
            ]),
    },
    {
        header: 'Student ID',
        id: 'student_id',
        enableSorting: false,
        cell: ({ row }) => h('span', { class: 'font-mono text-sm' }, row.original.student.student_id),
    },
    {
        header: 'Intake',
        id: 'intake',
        enableSorting: false,
        cell: ({ row }) => row.original.intake ?? '-',
    },
    {
        header: 'Current Cumulative GPA',
        accessorKey: 'current_cumulative_gpa',
        cell: ({ row }) => h(Badge, { variant: 'warning' }, () => `${row.original.current_cumulative_gpa.toFixed(2)} / 100`),
    },
    {
        header: 'Credits Earned',
        accessorKey: 'total_credits_earned',
        cell: ({ row }) => row.original.total_credits_earned.toFixed(2),
    },
    {
        header: 'Credits Attempted',
        accessorKey: 'total_credits_attempted',
        cell: ({ row }) => row.original.total_credits_attempted.toFixed(2),
    },
    {
        header: 'Action',
        id: 'actions',
        enableSorting: false,
        cell: ({ row }) => {
            if (row.original.warning_sent_at) {
                return h('div', { class: 'flex items-center gap-2 text-xs text-muted-foreground' }, [
                    h(CheckCircle2, { class: 'h-4 w-4 text-green-600' }),
                    h('span', formatDate(row.original.warning_sent_at) ?? 'Sent'),
                ]);
            }

            return h(
                Button,
                {
                    size: 'sm',
                    disabled: !canSend.value || isSending(row.original.dedupe_key),
                    onClick: (event: MouseEvent) => {
                        event.stopPropagation();
                        sendAcademicWarning(row.original);
                    },
                },
                () => [
                    h(Send, { class: 'h-4 w-4' }),
                    isSending(row.original.dedupe_key) ? 'Sending' : 'Send warning',
                ],
            );
        },
    },
];
</script>

<template>
    <Head title="Warning Center" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Warning Center</h1>
                <p class="text-sm text-muted-foreground">
                    {{ active_semester ? `${active_semester.name} (${active_semester.code})` : 'No active semester' }}
                </p>
            </div>
            <Button v-if="canSend" variant="outline" as-child>
                <Link :href="route('academic.warnings.settings')">
                    <Settings class="h-4 w-4" />
                    Settings
                </Link>
            </Button>
        </div>

        <Alert class="border-amber-200 bg-amber-50 text-amber-900">
            <AlertTriangle class="h-4 w-4" />
            <AlertTitle>Delivery</AlertTitle>
            <AlertDescription class="flex flex-wrap items-center gap-3">
                <span>Warnings are sent through {{ channelText }}.</span>
                <span class="inline-flex items-center gap-1"><Bell class="h-3.5 w-3.5" /> In-app</span>
                <span class="inline-flex items-center gap-1"><Mail class="h-3.5 w-3.5" /> Email</span>
            </AlertDescription>
        </Alert>

        <Card>
            <CardHeader>
                <CardTitle>Academic Standing Warnings</CardTitle>
                <CardDescription>Students with current cumulative GPA below 50.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <FilterPanel :has-active-filters="hasActiveFilters" :columns="2" @clear="clearAllFilters">
                    <DebouncedInput :model-value="filters.search" placeholder="Search name, student ID, email..." @update:model-value="handleSearch" />
                </FilterPanel>

                <ServerPaginatedDataTable
                    :data="academic_warnings.data"
                    :columns="standingColumns"
                    :pagination-data="academic_warnings"
                    :loading="isLoading"
                    :initial-sort="currentSort ?? undefined"
                    :initial-direction="currentDirection ?? undefined"
                    item-name="students"
                    empty-message="No students are currently in academic standing warning."
                    @sort-change="handleSortChange"
                    @page-change="(page) => handlePaginationNavigate(`${academic_warnings.path}?page=${page}`)"
                    @page-size-change="handlePageSizeChange"
                />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <CardTitle>Attendance Warnings</CardTitle>
                        <CardDescription>Active semester sections using syllabus attendance thresholds.</CardDescription>
                    </div>
                    <Badge :variant="attendanceWarningTotal > 0 ? 'warning' : 'secondary'">
                        {{ attendanceWarningTotal }} warning cases
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="!active_semester" class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                    No active semester is configured.
                </div>

                <div v-else-if="attendance_subjects.length === 0" class="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                    No active course offerings found for this semester.
                </div>

                <div v-else class="divide-y rounded-md border">
                    <details v-for="subject in attendance_subjects" :key="subject.id" class="group">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 hover:bg-muted/50">
                            <div>
                                <div class="font-medium">{{ subject.code }} - {{ subject.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ subject.sections.length }} section(s)</div>
                            </div>
                            <Badge :variant="subject.warning_count > 0 ? 'warning' : 'secondary'">
                                {{ subject.warning_count }}
                            </Badge>
                        </summary>

                        <div class="space-y-3 border-t bg-muted/20 p-3">
                            <details v-for="section in subject.sections" :key="section.id" class="rounded-md border bg-background">
                                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-4 py-3 hover:bg-muted/40">
                                    <div>
                                        <div class="font-medium">Section {{ section.section_code }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ section.total_sessions }} sessions · min attendance {{ section.min_attendance_threshold }}% · warning after {{ section.warning_absences }} absence(s)
                                        </div>
                                    </div>
                                    <Badge :variant="section.warning_count > 0 ? 'warning' : 'secondary'">
                                        {{ section.warning_count }} student(s)
                                    </Badge>
                                </summary>

                                <div class="overflow-x-auto border-t">
                                    <table class="w-full min-w-[860px] text-sm">
                                        <thead class="bg-muted/50 text-xs uppercase text-muted-foreground">
                                            <tr>
                                                <th class="px-4 py-3 text-left">Student</th>
                                                <th class="px-4 py-3 text-left">Student ID</th>
                                                <th class="px-4 py-3 text-left">Intake</th>
                                                <th class="px-4 py-3 text-left">Absences / Limit</th>
                                                <th class="px-4 py-3 text-left">Attendance</th>
                                                <th class="px-4 py-3 text-left">Latest Absence</th>
                                                <th class="px-4 py-3 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-if="section.students.length === 0">
                                                <td colspan="7" class="px-4 py-6 text-center text-muted-foreground">No students have crossed the warning threshold.</td>
                                            </tr>
                                            <tr v-for="studentWarning in section.students" :key="studentWarning.dedupe_key" class="border-t">
                                                <td class="px-4 py-3">
                                                    <div class="font-medium">{{ studentWarning.student.name }}</div>
                                                    <div class="text-xs text-muted-foreground">{{ studentWarning.student.email }}</div>
                                                </td>
                                                <td class="px-4 py-3 font-mono">{{ studentWarning.student.student_id }}</td>
                                                <td class="px-4 py-3">{{ studentWarning.intake ?? '-' }}</td>
                                                <td class="px-4 py-3">
                                                    <Badge :variant="studentWarning.status === 'limit_exceeded' ? 'destructive' : 'warning'">
                                                        {{ studentWarning.absence_count }}/{{ studentWarning.allowed_absences }}
                                                    </Badge>
                                                </td>
                                                <td class="px-4 py-3">{{ studentWarning.attendance_rate.toFixed(2) }}%</td>
                                                <td class="px-4 py-3">
                                                    <span v-if="studentWarning.latest_absent_session">
                                                        {{ studentWarning.latest_absent_session.date ?? '-' }}
                                                    </span>
                                                    <span v-else>-</span>
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <div v-if="studentWarning.warning_sent_at" class="inline-flex items-center gap-2 text-xs text-muted-foreground">
                                                        <CheckCircle2 class="h-4 w-4 text-green-600" />
                                                        {{ formatDate(studentWarning.warning_sent_at) ?? 'Sent' }}
                                                    </div>
                                                    <Button
                                                        v-else
                                                        size="sm"
                                                        :disabled="!canSend || isSending(studentWarning.dedupe_key)"
                                                        @click="sendAttendanceWarning(section, studentWarning)"
                                                    >
                                                        <Send class="h-4 w-4" />
                                                        {{ isSending(studentWarning.dedupe_key) ? 'Sending' : 'Send warning' }}
                                                    </Button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        </div>
                    </details>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
