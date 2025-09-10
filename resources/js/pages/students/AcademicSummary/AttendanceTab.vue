<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { AttendanceData, UnitAttendance, AttendanceSession } from '@/types/models';
import {
    AlertTriangle,
    Calendar,
    CheckCircle,
    ChevronDown,
    ChevronRight,
    Clock,
    Eye,
    Filter,
    Search,
    Users,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    attendance: AttendanceData;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

// Reactive filters
const searchQuery = ref('');
const selectedSemester = ref<string>('');
const selectedStatus = ref<string>('');
const expandedUnits = ref<Set<number>>(new Set());
const selectedAttendanceDetails = ref<UnitAttendance | null>(null);

// Computed filtered data
const filteredAttendance = computed(() => {
    let filtered = props.attendance.data;

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(unit =>
            unit.unit_name.toLowerCase().includes(query) ||
            unit.unit_code.toLowerCase().includes(query) ||
            unit.semester.toLowerCase().includes(query)
        );
    }

    // Semester filter
    if (selectedSemester.value) {
        filtered = filtered.filter(unit => unit.semester === selectedSemester.value);
    }

    // Status filter
    if (selectedStatus.value) {
        filtered = filtered.filter(unit => unit.attendance_status === selectedStatus.value);
    }

    return filtered;
});

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

const clearFilters = () => {
    searchQuery.value = '';
    selectedSemester.value = '';
    selectedStatus.value = '';
};

const hasActiveFilters = computed(() => {
    return searchQuery.value || selectedSemester.value || selectedStatus.value;
});

// Get unique semesters for filter dropdown
const availableSemesters = computed(() => {
    const semesters = new Set(props.attendance.data.map(unit => unit.semester));
    return Array.from(semesters).sort();
});

// Get units at risk
const unitsAtRisk = computed(() => {
    return props.attendance.data.filter(unit => unit.attendance_status === 'critical' || unit.attendance_status === 'warning');
});

const openAttendanceDetails = (unit: UnitAttendance) => {
    selectedAttendanceDetails.value = unit;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Loading State -->
        <div v-if="loading" class="space-y-4">
            <div class="animate-pulse">
                <div class="h-8 bg-gray-200 rounded w-1/3 mb-4"></div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div v-for="i in 4" :key="i" class="h-24 bg-gray-200 rounded"></div>
                </div>
                <div class="space-y-4">
                    <div v-for="i in 3" :key="i" class="h-32 bg-gray-200 rounded"></div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div v-else>
            <!-- Risk Alert -->
            <Alert v-if="unitsAtRisk.length > 0" variant="destructive" class="mb-6">
                <AlertTriangle class="h-4 w-4" />
                <AlertDescription>
                    <strong>Attendance Warning:</strong> {{ unitsAtRisk.length }} unit(s) have concerning attendance rates.
                    Please review and take appropriate action.
                </AlertDescription>
            </Alert>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <Card>
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-muted-foreground text-sm">Total Units</p>
                                <p class="text-2xl font-bold">{{ attendance.summary.total_units }}</p>
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
                                <p
                                    class="text-2xl font-bold"
                                    :class="attendance.summary.overall_percentage >= 80 ? 'text-green-600' :
                                           attendance.summary.overall_percentage >= 70 ? 'text-yellow-600' : 'text-red-600'"
                                >
                                    {{ attendance.summary.overall_percentage.toFixed(1) }}%
                                </p>
                            </div>
                            <AlertTriangle
                                :class="attendance.summary.overall_percentage >= 80 ? 'text-green-600' :
                                       attendance.summary.overall_percentage >= 70 ? 'text-yellow-600' : 'text-red-600'"
                                class="h-8 w-8"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle class="flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <Filter class="h-5 w-5" />
                            Filters
                        </span>
                        <Button
                            v-if="hasActiveFilters"
                            variant="outline"
                            size="sm"
                            @click="clearFilters"
                            class="flex items-center gap-2"
                        >
                            <X class="h-4 w-4" />
                            Clear
                        </Button>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Search -->
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search units..."
                                class="pl-10"
                            />
                        </div>

                        <!-- Semester Filter -->
                        <Select v-model="selectedSemester">
                            <SelectTrigger>
                                <SelectValue placeholder="All Semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Semesters</SelectItem>
                                <SelectItem
                                    v-for="semester in availableSemesters"
                                    :key="semester"
                                    :value="semester"
                                >
                                    {{ semester }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <!-- Status Filter -->
                        <Select v-model="selectedStatus">
                            <SelectTrigger>
                                <SelectValue placeholder="All Statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="excellent">Excellent (90%+)</SelectItem>
                                <SelectItem value="good">Good (80-89%)</SelectItem>
                                <SelectItem value="warning">Warning (70-79%)</SelectItem>
                                <SelectItem value="critical">Critical (<70%)</SelectItem>
                            </SelectContent>
                        </Select>

                        <!-- Results Count -->
                        <div class="flex items-center text-sm text-muted-foreground">
                            {{ filteredAttendance.length }} of {{ attendance.data.length }} units
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Attendance List -->
            <div class="space-y-4">
                <div v-if="filteredAttendance.length === 0" class="text-center py-12">
                    <Users class="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                    <h3 class="text-lg font-semibold mb-2">No Attendance Records Found</h3>
                    <p class="text-muted-foreground">No attendance records match your current filters.</p>
                </div>

                <Card v-for="unit in filteredAttendance" :key="unit.unit_id">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <CardTitle class="flex items-center gap-3">
                                    <Users class="h-5 w-5" />
                                    <div>
                                        <h3 class="text-lg font-semibold">{{ unit.unit_name }}</h3>
                                        <p class="text-sm text-muted-foreground font-normal">
                                            {{ unit.unit_code }} • {{ unit.semester }}
                                        </p>
                                    </div>
                                </CardTitle>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-right">
                                    <p class="text-sm text-muted-foreground">Attendance Rate</p>
                                    <div class="flex items-center gap-2">
                                        <p
                                            class="text-xl font-bold"
                                            :class="getAttendanceStatusColor(unit.attendance_status)"
                                        >
                                            {{ unit.attendance_percentage.toFixed(1) }}%
                                        </p>
                                        <Badge :variant="getAttendanceStatusBadge(unit.attendance_status) as any">
                                            {{ formatStatus(unit.attendance_status) }}
                                        </Badge>
                                    </div>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="toggleUnitExpansion(unit.unit_id)"
                                    class="flex items-center gap-2"
                                >
                                    <ChevronDown
                                        v-if="expandedUnits.has(unit.unit_id)"
                                        class="h-4 w-4"
                                    />
                                    <ChevronRight
                                        v-else
                                        class="h-4 w-4"
                                    />
                                    {{ expandedUnits.has(unit.unit_id) ? 'Collapse' : 'Expand' }}
                                </Button>
                                <Dialog>
                                    <DialogTrigger as-child>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="openAttendanceDetails(unit)"
                                            class="flex items-center gap-2"
                                        >
                                            <Eye class="h-4 w-4" />
                                            Details
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent class="max-w-4xl max-h-[80vh] overflow-y-auto">
                                        <DialogHeader>
                                            <DialogTitle>
                                                {{ unit.unit_name }} - Attendance Details
                                            </DialogTitle>
                                        </DialogHeader>
                                        <div v-if="selectedAttendanceDetails" class="space-y-4">
                                            <!-- Unit Info -->
                                            <div class="grid grid-cols-2 gap-4 p-4 bg-muted rounded-lg">
                                                <div>
                                                    <p class="text-sm text-muted-foreground">Unit Code</p>
                                                    <p class="font-medium">{{ selectedAttendanceDetails.unit_code }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-sm text-muted-foreground">Semester</p>
                                                    <p class="font-medium">{{ selectedAttendanceDetails.semester }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-sm text-muted-foreground">Total Sessions</p>
                                                    <p class="font-medium">{{ selectedAttendanceDetails.total_sessions }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-sm text-muted-foreground">Attendance Rate</p>
                                                    <p
                                                        class="font-medium text-lg"
                                                        :class="getAttendanceStatusColor(selectedAttendanceDetails.attendance_status)"
                                                    >
                                                        {{ selectedAttendanceDetails.attendance_percentage.toFixed(1) }}%
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Session Details -->
                                            <div class="space-y-3">
                                                <h4 class="font-semibold">Session Details</h4>
                                                <div class="space-y-2 max-h-96 overflow-y-auto">
                                                    <div
                                                        v-for="session in selectedAttendanceDetails.sessions"
                                                        :key="`${session.session_date}-${session.start_time}`"
                                                        class="flex items-center justify-between p-3 border rounded-lg"
                                                    >
                                                        <div class="flex-1">
                                                            <p class="font-medium">{{ formatDate(session.session_date) }}</p>
                                                            <p class="text-sm text-muted-foreground">
                                                                {{ formatTime(session.start_time) }} - {{ formatTime(session.end_time) }}
                                                            </p>
                                                        </div>
                                                        <div class="flex items-center gap-3">
                                                            <div v-if="session.check_in_time" class="text-right text-sm">
                                                                <p class="text-muted-foreground">Check-in</p>
                                                                <p class="font-medium">{{ formatTime(session.check_in_time) }}</p>
                                                                <p v-if="session.minutes_late && session.minutes_late > 0" class="text-red-600">
                                                                    {{ session.minutes_late }} min late
                                                                </p>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <component
                                                                    :is="getSessionStatusIcon(session.status)"
                                                                    :class="getSessionStatusColor(session.status)"
                                                                    class="h-5 w-5"
                                                                />
                                                                <Badge :variant="session.status === 'present' ? 'default' :
                                                                                session.status === 'late' ? 'outline' :
                                                                                session.status === 'excused' ? 'secondary' : 'destructive'">
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
                            </div>
                        </div>
                    </CardHeader>

                    <Collapsible :open="expandedUnits.has(unit.unit_id)">
                        <CollapsibleContent>
                            <CardContent>
                                <div class="space-y-4">
                                    <!-- Unit Statistics -->
                                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 p-4 bg-muted rounded-lg">
                                        <div class="text-center">
                                            <p class="text-sm text-muted-foreground">Total Sessions</p>
                                            <p class="text-xl font-bold">{{ unit.total_sessions }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-sm text-muted-foreground">Present</p>
                                            <p class="text-xl font-bold text-green-600">{{ unit.present_count }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-sm text-muted-foreground">Late</p>
                                            <p class="text-xl font-bold text-yellow-600">{{ unit.late_count }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-sm text-muted-foreground">Absent</p>
                                            <p class="text-xl font-bold text-red-600">{{ unit.absent_count }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-sm text-muted-foreground">Excused</p>
                                            <p class="text-xl font-bold text-blue-600">{{ unit.excused_count }}</p>
                                        </div>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div>
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium">Attendance Progress</span>
                                            <span class="text-sm text-muted-foreground">
                                                {{ unit.attended_count }}/{{ unit.total_sessions }} sessions
                                            </span>
                                        </div>
                                        <Progress
                                            :value="unit.attendance_percentage"
                                            class="h-2"
                                            :class="unit.attendance_percentage >= 80 ? 'text-green-600' :
                                                   unit.attendance_percentage >= 70 ? 'text-yellow-600' : 'text-red-600'"
                                        />
                                    </div>

                                    <!-- Recent Sessions -->
                                    <div>
                                        <h4 class="font-semibold mb-3">Recent Sessions</h4>
                                        <div class="space-y-2">
                                            <div
                                                v-for="session in unit.sessions.slice(0, 5)"
                                                :key="`${session.session_date}-${session.start_time}`"
                                                class="flex items-center justify-between p-3 border rounded-lg"
                                            >
                                                <div class="flex-1">
                                                    <p class="font-medium">{{ formatDate(session.session_date) }}</p>
                                                    <p class="text-sm text-muted-foreground">
                                                        {{ formatTime(session.start_time) }} - {{ formatTime(session.end_time) }}
                                                    </p>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <div v-if="session.check_in_time" class="text-right text-sm">
                                                        <p class="text-muted-foreground">Check-in</p>
                                                        <p class="font-medium">{{ formatTime(session.check_in_time) }}</p>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <component
                                                            :is="getSessionStatusIcon(session.status)"
                                                            :class="getSessionStatusColor(session.status)"
                                                            class="h-5 w-5"
                                                        />
                                                        <Badge :variant="session.status === 'present' ? 'default' :
                                                                        session.status === 'late' ? 'outline' :
                                                                        session.status === 'excused' ? 'secondary' : 'destructive'">
                                                            {{ formatStatus(session.status) }}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="unit.sessions.length > 5" class="text-center mt-3">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                @click="openAttendanceDetails(unit)"
                                                class="flex items-center gap-2"
                                            >
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
</template>
