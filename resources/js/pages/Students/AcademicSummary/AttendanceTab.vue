<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';
import { Progress } from '@/components/ui/progress';
import type { AttendanceData, UnitAttendance } from '@/types/models';
import { AlertTriangle, Calendar, CheckCircle, ChevronDown, ChevronRight, Clock, Eye, Users, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    attendance: AttendanceData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

// Reactive state
const expandedUnits = ref<Set<number>>(new Set());
const selectedAttendanceDetails = ref<UnitAttendance | null>(null);
const isDetailsOpen = ref(false);

// Toggle unit expansion
const toggleUnitExpansion = (unitId: number) => {
    if (expandedUnits.value.has(unitId)) {
        expandedUnits.value.delete(unitId);
    } else {
        expandedUnits.value.add(unitId);
    }
};

// Attendance status styling
const getAttendanceStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        excellent: 'text-green-600',
        good: 'text-blue-600',
        warning: 'text-yellow-600',
        critical: 'text-red-600',
    };
    return colors[status] || 'text-gray-600';
};

const getAttendanceStatusBadge = (status: string) => {
    const variants: Record<string, string> = {
        excellent: 'default',
        good: 'secondary',
        warning: 'outline',
        critical: 'destructive',
    };
    return variants[status] || 'outline';
};

const getSessionStatusIcon = (status: string) => {
    const icons: Record<string, any> = {
        present: CheckCircle,
        late: Clock,
        absent: XCircle,
        excused: AlertTriangle,
    };
    return icons[status] || XCircle;
};

const getSessionStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        present: 'text-green-600',
        late: 'text-yellow-600',
        absent: 'text-red-600',
        excused: 'text-blue-600',
    };
    return colors[status] || 'text-gray-600';
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatTime = (time: string) => {
    return new Date(`2000-01-01T${time}`).toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
};

const formatStatus = (status: string) => {
    return status.replace(/_/g, ' ').toUpperCase();
};

const shouldShowAttemptLabel = (unit: UnitAttendance) => {
    return unit.is_retake || (unit.attempt_number ?? 1) > 1;
};

// Get units at risk
const unitsAtRisk = computed(() => {
    return props.attendance.data.filter((unit) => unit.attendance_status === 'critical' || unit.attendance_status === 'warning');
});

const openAttendanceDetails = (unit: UnitAttendance) => {
    selectedAttendanceDetails.value = unit;
    isDetailsOpen.value = true;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Loading State -->
        <div v-if="loading" class="space-y-4">
            <div class="animate-pulse">
                <div class="mb-4 h-8 w-1/3 rounded bg-gray-200"></div>
                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div v-for="i in 4" :key="i" class="h-24 rounded bg-gray-200"></div>
                </div>
                <div class="space-y-4">
                    <div v-for="i in 3" :key="i" class="h-32 rounded bg-gray-200"></div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div v-else>
            <!-- Risk Alert -->
            <Alert v-if="unitsAtRisk.length > 0" variant="destructive" class="mb-6">
                <AlertTriangle class="h-4 w-4" />
                <AlertDescription> <strong>Attendance Warning:</strong> {{ unitsAtRisk.length }} course attempt(s) have concerning attendance rates. Please review and take appropriate action. </AlertDescription>
            </Alert>

            <!-- Summary Cards -->
            <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Course Attempts</p>
                                <p class="text-2xl font-bold">{{ attendance.summary.total_attempts ?? attendance.summary.total_units }}</p>
                            </div>
                            <Users class="h-8 w-8 text-blue-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Total Sessions</p>
                                <p class="text-2xl font-bold">{{ attendance.summary.total_sessions }}</p>
                            </div>
                            <Calendar class="h-8 w-8 text-purple-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Attended</p>
                                <p class="text-2xl font-bold text-green-600">{{ attendance.summary.total_attended }}</p>
                            </div>
                            <CheckCircle class="h-8 w-8 text-green-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Overall Rate</p>
                                <p class="text-2xl font-bold" :class="attendance.summary.overall_percentage >= 80 ? 'text-green-600' : attendance.summary.overall_percentage >= 70 ? 'text-yellow-600' : 'text-red-600'">
                                    {{ attendance.summary.overall_percentage.toFixed(1) }}%
                                </p>
                            </div>
                            <AlertTriangle :class="attendance.summary.overall_percentage >= 80 ? 'text-green-600' : attendance.summary.overall_percentage >= 70 ? 'text-yellow-600' : 'text-red-600'" class="h-8 w-8" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Attendance List -->
            <div class="space-y-4">
                <div v-if="attendance.data.length === 0" class="py-12 text-center">
                    <Users class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                    <h3 class="mb-2 text-lg font-semibold">No Attendance Records Found</h3>
                    <p class="text-muted-foreground">No attendance records found for this student.</p>
                </div>

                <Card v-for="unit in attendance.data" :key="unit.course_offering_id">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <CardTitle class="flex items-center gap-3">
                                    <Users class="h-5 w-5" />
                                    <div>
                                        <h3 class="text-lg font-semibold">{{ unit.unit_name }}</h3>
                                        <p class="text-muted-foreground text-sm font-normal">
                                            {{ unit.unit_code }} • {{ unit.semester }}
                                            <span v-if="unit.section_code"> • Section {{ unit.section_code }}</span>
                                            <span v-if="shouldShowAttemptLabel(unit)"> • {{ unit.attempt_label }}</span>
                                        </p>
                                    </div>
                                </CardTitle>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <p class="text-muted-foreground text-sm">Attendance Rate</p>
                                    <div class="flex items-center gap-2">
                                        <p class="text-xl font-bold" :class="getAttendanceStatusColor(unit.attendance_status)">{{ unit.attendance_percentage.toFixed(1) }}%</p>
                                        <Badge :variant="getAttendanceStatusBadge(unit.attendance_status) as any">
                                            {{ formatStatus(unit.attendance_status) }}
                                        </Badge>
                                    </div>
                                </div>
                                <Button variant="ghost" size="sm" @click="toggleUnitExpansion(unit.course_offering_id)" class="flex items-center gap-2">
                                    <ChevronDown v-if="expandedUnits.has(unit.course_offering_id)" class="h-4 w-4" />
                                    <ChevronRight v-else class="h-4 w-4" />
                                    {{ expandedUnits.has(unit.course_offering_id) ? 'Collapse' : 'Expand' }}
                                </Button>
                                <Button variant="outline" size="sm" @click="openAttendanceDetails(unit)" class="flex items-center gap-2">
                                    <Eye class="h-4 w-4" />
                                    Details
                                </Button>
                            </div>
                        </div>
                    </CardHeader>

                    <Collapsible :open="expandedUnits.has(unit.course_offering_id)">
                        <CollapsibleContent>
                            <CardContent>
                                <div class="space-y-4">
                                    <!-- Unit Statistics -->
                                    <div class="bg-muted grid grid-cols-2 gap-4 rounded-lg p-4 md:grid-cols-5">
                                        <div class="text-center">
                                            <p class="text-muted-foreground text-sm">Total Sessions</p>
                                            <p class="text-xl font-bold">{{ unit.total_sessions }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-muted-foreground text-sm">Present</p>
                                            <p class="text-xl font-bold text-green-600">{{ unit.present_count }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-muted-foreground text-sm">Late</p>
                                            <p class="text-xl font-bold text-yellow-600">{{ unit.late_count }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-muted-foreground text-sm">Absent</p>
                                            <p class="text-xl font-bold text-red-600">{{ unit.absent_count }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-muted-foreground text-sm">Excused</p>
                                            <p class="text-xl font-bold text-blue-600">{{ unit.excused_count }}</p>
                                        </div>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div>
                                        <div class="mb-2 flex items-center justify-between">
                                            <span class="text-sm font-medium">Attendance Progress</span>
                                            <span class="text-muted-foreground text-sm"> {{ unit.attended_count }}/{{ unit.total_sessions }} sessions </span>
                                        </div>
                                        <Progress :value="unit.attendance_percentage" class="h-2" :class="unit.attendance_percentage >= 80 ? 'text-green-600' : unit.attendance_percentage >= 70 ? 'text-yellow-600' : 'text-red-600'" />
                                    </div>

                                    <!-- Recent Sessions -->
                                    <div>
                                        <h4 class="mb-3 font-semibold">Recent Sessions</h4>
                                        <div class="space-y-2">
                                            <div v-for="session in unit.sessions.slice(0, 5)" :key="session.session_id" class="flex items-center justify-between rounded-lg border p-3">
                                                <div class="flex-1">
                                                    <p class="font-medium">{{ formatDate(session.session_date) }}</p>
                                                    <p class="text-muted-foreground text-sm">{{ formatTime(session.start_time) }} - {{ formatTime(session.end_time) }}</p>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <div v-if="session.check_in_time" class="text-right text-sm">
                                                        <p class="text-muted-foreground">Check-in</p>
                                                        <p class="font-medium">{{ formatTime(session.check_in_time) }}</p>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <component :is="getSessionStatusIcon(session.status)" :class="getSessionStatusColor(session.status)" class="h-5 w-5" />
                                                        <Badge :variant="session.status === 'present' ? 'default' : session.status === 'late' ? 'outline' : session.status === 'excused' ? 'secondary' : 'destructive'">
                                                            {{ formatStatus(session.status) }}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="unit.sessions.length > 5" class="mt-3 text-center">
                                            <Button variant="outline" size="sm" @click="openAttendanceDetails(unit)" class="flex items-center gap-2">
                                                <Eye class="h-4 w-4" />
                                                View All {{ unit.sessions.length }} Sessions
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>
            </div>
        </div>
    </div>

    <Dialog v-model:open="isDetailsOpen">
        <DialogContent class="max-h-[80vh] max-w-4xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle> {{ selectedAttendanceDetails?.unit_name }} - Attendance Details </DialogTitle>
                <DialogDescription />
            </DialogHeader>
            <div v-if="selectedAttendanceDetails" class="space-y-4">
                <!-- Unit Info -->
                <div class="bg-muted grid grid-cols-2 gap-4 rounded-lg p-4">
                    <div>
                        <p class="text-muted-foreground text-sm">Unit Code</p>
                        <p class="font-medium">{{ selectedAttendanceDetails.unit_code }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Semester</p>
                        <p class="font-medium">{{ selectedAttendanceDetails.semester }}</p>
                    </div>
                    <div v-if="selectedAttendanceDetails.section_code">
                        <p class="text-muted-foreground text-sm">Section</p>
                        <p class="font-medium">{{ selectedAttendanceDetails.section_code }}</p>
                    </div>
                    <div v-if="shouldShowAttemptLabel(selectedAttendanceDetails)">
                        <p class="text-muted-foreground text-sm">Course Attempt</p>
                        <p class="font-medium">{{ selectedAttendanceDetails.attempt_label }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Total Sessions</p>
                        <p class="font-medium">{{ selectedAttendanceDetails.total_sessions }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Attendance Rate</p>
                        <p class="text-lg font-medium" :class="getAttendanceStatusColor(selectedAttendanceDetails.attendance_status)">{{ selectedAttendanceDetails.attendance_percentage.toFixed(1) }}%</p>
                    </div>
                </div>

                <!-- Session Details -->
                <div class="space-y-3">
                    <h4 class="font-semibold">Session Details</h4>
                    <div class="max-h-96 space-y-2 overflow-y-auto">
                        <div v-for="session in selectedAttendanceDetails.sessions" :key="session.session_id" class="flex items-center justify-between rounded-lg border p-3">
                            <div class="flex-1">
                                <p class="font-medium">{{ formatDate(session.session_date) }}</p>
                                <p class="text-muted-foreground text-sm">{{ formatTime(session.start_time) }} - {{ formatTime(session.end_time) }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <div v-if="session.check_in_time" class="text-right text-sm">
                                    <p class="text-muted-foreground">Check-in</p>
                                    <p class="font-medium">{{ formatTime(session.check_in_time) }}</p>
                                    <p v-if="session.minutes_late && session.minutes_late > 0" class="text-red-600">{{ session.minutes_late }} min late</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <component :is="getSessionStatusIcon(session.status)" :class="getSessionStatusColor(session.status)" class="h-5 w-5" />
                                    <Badge :variant="session.status === 'present' ? 'default' : session.status === 'late' ? 'outline' : session.status === 'excused' ? 'secondary' : 'destructive'">
                                        {{ formatStatus(session.status) }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
