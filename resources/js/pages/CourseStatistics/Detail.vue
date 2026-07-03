<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BarChart3, Calendar, CheckCircle, Clock, Download, User, Users, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Session {
    id: number;
    session_number: number;
    session_date: string;
    session_title: string;
    session_time_start: string;
    session_time_end: string;
}

interface AttendanceSession {
    session_id: number;
    session_number: number;
    session_date: string;
    status: string;
    check_in_time: string | null;
    minutes_late: number | null;
}

interface StudentAttendance {
    student_id: string;
    full_name: string;
    email: string;
    sessions: AttendanceSession[];
    total_present: number;
    total_absences: number;
    total_late: number;
    attendance_percentage: number;
    meets_attendance_requirement: boolean;
    allowed_absences: number;
    absences_remaining: number;
}

interface Statistics {
    course_code: string;
    course_name: string;
    section_code: string;
    semester: string;
    instructor_name: string | null;
    total_students: number;
    total_sessions: number;
    allowed_absences: number;
    students_absent_exceeded: number;
}

interface Props {
    statistics: Statistics;
    sessions: Session[];
    attendance_grid: StudentAttendance[];
    course_offering: {
        id: number;
        unit_id: number;
        semester_id: number;
    };
}

const props = defineProps<Props>();
const statusFilter = ref<string>('all');

const filteredAttendanceGrid = computed(() => {
    if (statusFilter.value === 'all') {
        return props.attendance_grid;
    }

    if (statusFilter.value === 'pass') {
        return props.attendance_grid.filter((student) => student.meets_attendance_requirement);
    }

    if (statusFilter.value === 'fail') {
        return props.attendance_grid.filter((student) => !student.meets_attendance_requirement);
    }

    return props.attendance_grid;
});

const handleExport = () => {
    window.location.href = `/course-statistics/${props.course_offering.id}/export`;
};

const getStatusBadgeVariant = (status: string): 'success' | 'destructive' | 'warning' | 'info' | 'outline' => {
    switch (status) {
        case 'present':
            return 'success';
        case 'absent':
            return 'destructive';
        case 'late':
            return 'warning';
        case 'excused':
            return 'info';
        default:
            return 'outline';
    }
};

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'present':
            return 'P';
        case 'absent':
            return 'A';
        case 'late':
            return 'L';
        case 'excused':
            return 'E';
        default:
            return '-';
    }
};
</script>

<template>
    <Head :title="`Attendance Detail - ${statistics.course_code}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Link :href="`/course-statistics/units/${course_offering.unit_id}?semester_id=${course_offering.semester_id}`">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back
                    </Button>
                </Link>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">Course Attendance Detail</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ statistics.course_code }} - {{ statistics.course_name }}
                        <span v-if="statistics.section_code"> ({{ statistics.section_code }})</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="route('course-offerings.show', course_offering.id) + '?tab=sessions'">
                    <Button variant="outline" size="sm">
                        <CheckCircle class="mr-2 h-4 w-4" />
                        View in Course Offering
                    </Button>
                </Link>
                <Link :href="route('course-offerings.show', course_offering.id) + '?tab=scores'">
                    <Button variant="outline" size="sm">
                        <BarChart3 class="mr-2 h-4 w-4" />
                        Assessment Scores
                    </Button>
                </Link>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
            <Card>
                <CardContent class="p-4">
                    <div class="flex items-center gap-3">
                        <Calendar class="h-8 w-8 text-blue-600" />
                        <div>
                            <p class="text-muted-foreground text-sm">Semester</p>
                            <p class="text-lg font-bold">{{ statistics.semester }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-4">
                    <div class="flex items-center gap-3">
                        <User class="h-8 w-8 text-purple-600" />
                        <div>
                            <p class="text-muted-foreground text-sm">Instructor</p>
                            <p class="text-lg font-bold">{{ statistics.instructor_name || 'N/A' }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-4">
                    <div class="flex items-center gap-3">
                        <Users class="h-8 w-8 text-green-600" />
                        <div>
                            <p class="text-muted-foreground text-sm">Total Students</p>
                            <p class="text-lg font-bold">{{ statistics.total_students }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-4">
                    <div class="flex items-center gap-3">
                        <Clock class="h-8 w-8 text-orange-600" />
                        <div>
                            <p class="text-muted-foreground text-sm">Total Sessions</p>
                            <p class="text-lg font-bold">{{ statistics.total_sessions }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="p-4">
                    <div class="flex items-center gap-3">
                        <XCircle class="h-8 w-8 text-red-600" />
                        <div>
                            <p class="text-muted-foreground text-sm">Absent Exceeded</p>
                            <p class="text-lg font-bold text-red-600">{{ statistics.students_absent_exceeded }}</p>
                            <p class="text-muted-foreground text-xs">Max {{ statistics.allowed_absences }} absences allowed</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Legend -->
        <Card>
            <CardHeader>
                <CardTitle class="text-sm">Legend</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div class="flex items-center gap-2">
                        <CheckCircle class="h-4 w-4 text-green-600" />
                        <span>Present (P)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <XCircle class="h-4 w-4 text-red-600" />
                        <span>Absent (A)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <Clock class="h-4 w-4 text-yellow-600" />
                        <span>Late (L)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <CheckCircle class="h-4 w-4 text-blue-600" />
                        <span>Excused (E)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-4 w-4 rounded bg-gray-200"></span>
                        <span>Not Recorded (-)</span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Filters and Actions -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Filters</CardTitle>
                    <Button @click="handleExport" variant="outline">
                        <Download class="mr-2 h-4 w-4" />
                        Export to Excel
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="space-y-2">
                        <Label>Student Status</Label>
                        <Select v-model="statusFilter">
                            <SelectTrigger>
                                <SelectValue placeholder="All students" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Students ({{ attendance_grid.length }})</SelectItem>
                                <SelectItem value="pass"> Pass ({{ attendance_grid.filter((s) => s.meets_attendance_requirement).length }}) </SelectItem>
                                <SelectItem value="fail"> Fail ({{ attendance_grid.filter((s) => !s.meets_attendance_requirement).length }}) </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Attendance Grid -->
        <Card>
            <CardHeader>
                <CardTitle>
                    Attendance Grid
                    <span class="text-muted-foreground ml-2 text-sm font-normal"> (Showing {{ filteredAttendanceGrid.length }} of {{ attendance_grid.length }} students) </span>
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="sticky left-0 z-10 min-w-[200px] bg-white dark:bg-gray-950">Student</TableHead>
                                <TableHead v-for="session in sessions" :key="session.id" class="min-w-[80px] text-center">
                                    <div class="text-xs">
                                        <div class="font-bold">S{{ session.session_number }}</div>
                                        <div class="text-muted-foreground">{{ session.session_date }}</div>
                                    </div>
                                </TableHead>
                                <TableHead class="sticky right-0 z-10 min-w-[100px] bg-white text-center dark:bg-gray-950">Summary</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="student in filteredAttendanceGrid" :key="student.student_id" :class="{ 'bg-red-50 dark:bg-red-950/20': !student.meets_attendance_requirement }">
                                <TableCell class="sticky left-0 z-10 bg-white dark:bg-gray-950">
                                    <div class="space-y-1">
                                        <div class="font-medium">{{ student.full_name }}</div>
                                        <div class="text-muted-foreground text-xs">{{ student.student_id }}</div>
                                        <Badge v-if="!student.meets_attendance_requirement" variant="destructive" class="text-xs"> Exceeded </Badge>
                                    </div>
                                </TableCell>
                                <TableCell v-for="sessionData in student.sessions" :key="sessionData.session_id" class="text-center">
                                    <div class="flex items-center justify-center">
                                        <Badge :variant="getStatusBadgeVariant(sessionData.status)">
                                            {{ getStatusLabel(sessionData.status) }}
                                        </Badge>
                                    </div>
                                    <div v-if="sessionData.check_in_time" class="text-muted-foreground mt-1 text-xs">{{ sessionData.check_in_time }}</div>
                                    <div v-if="sessionData.minutes_late" class="text-muted-foreground text-xs">+{{ sessionData.minutes_late }}m</div>
                                </TableCell>
                                <TableCell class="sticky right-0 z-10 bg-white text-center dark:bg-gray-950">
                                    <div class="space-y-1 text-xs">
                                        <div class="flex items-center justify-center gap-1">
                                            <CheckCircle class="h-3 w-3 text-green-600" />
                                            <span>{{ student.total_present }}</span>
                                        </div>
                                        <div class="flex items-center justify-center gap-1">
                                            <XCircle class="h-3 w-3 text-red-600" />
                                            <span>{{ student.total_absences }}/{{ student.allowed_absences }}</span>
                                        </div>
                                        <div class="flex items-center justify-center gap-1">
                                            <Clock class="h-3 w-3 text-yellow-600" />
                                            <span>{{ student.total_late }}</span>
                                        </div>
                                        <div class="font-bold" :class="Number(student.attendance_percentage) >= 80 ? 'text-green-600' : 'text-red-600'">{{ Number(student.attendance_percentage).toFixed(1) }}%</div>
                                        <div v-if="student.absences_remaining === 0 && student.meets_attendance_requirement" class="font-semibold text-orange-600">⚠ At Limit</div>
                                        <div v-else-if="!student.meets_attendance_requirement" class="font-semibold text-red-600">✗ Failed</div>
                                        <div v-else class="text-green-600">✓ {{ student.absences_remaining }} left</div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
