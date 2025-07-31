<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { format, startOfWeek, endOfWeek } from 'date-fns';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { X, Filter, RotateCcw, Search } from 'lucide-vue-next';
import { useAdminSchedule } from '@/composables/useAdminSchedule';
import type { ScheduleFilters } from '@/types/schedule';

const scheduleApi = useAdminSchedule();

// Local filter state
const localFilters = ref<ScheduleFilters>({
    semester_id: undefined,
    lecturer_id: undefined,
    room_id: undefined,
    date_range: undefined,
});

// Date range inputs
const dateRangeStart = ref('');
const dateRangeEnd = ref('');

// Search functionality
const searchTerm = ref('');
const isSearching = ref(false);

// Computed
const hasActiveFilters = computed(() => {
    return !!(localFilters.value.semester_id || localFilters.value.lecturer_id || localFilters.value.room_id || localFilters.value.date_range);
});

const activeFiltersCount = computed(() => {
    let count = 0;
    if (localFilters.value.semester_id) count++;
    if (localFilters.value.lecturer_id) count++;
    if (localFilters.value.room_id) count++;
    if (localFilters.value.date_range) count++;
    return count;
});

const selectedSemester = computed(() => {
    if (!localFilters.value.semester_id || !scheduleApi.filterOptions.value) return null;
    return scheduleApi.filterOptions.value.semesters.find((s) => s.id === localFilters.value.semester_id);
});

const selectedLecturer = computed(() => {
    if (!localFilters.value.lecturer_id || !scheduleApi.filterOptions.value) return null;
    return scheduleApi.filterOptions.value.lecturers.find((l) => l.id === localFilters.value.lecturer_id);
});

const selectedRoom = computed(() => {
    if (!localFilters.value.room_id || !scheduleApi.filterOptions.value) return null;
    return scheduleApi.filterOptions.value.rooms.find((r) => r.id === localFilters.value.room_id);
});

// Grouped rooms by campus for better UX
const roomsByCapmus = computed(() => {
    if (!scheduleApi.filterOptions.value) return {};

    const grouped: Record<string, typeof scheduleApi.filterOptions.value.rooms> = {};

    scheduleApi.filterOptions.value.rooms.forEach((room) => {
        const campusName = room.campus.name;
        if (!grouped[campusName]) {
            grouped[campusName] = [];
        }
        grouped[campusName].push(room);
    });

    return grouped;
});

// Methods
const setSemesterFilter = (semesterId: string) => {
    localFilters.value.semester_id = semesterId === 'none' ? undefined : parseInt(semesterId);
    applyFilters();
};

const setLecturerFilter = (lecturerId: string) => {
    localFilters.value.lecturer_id = lecturerId === 'none' ? undefined : parseInt(lecturerId);
    applyFilters();
};

const setRoomFilter = (roomId: string) => {
    localFilters.value.room_id = roomId === 'none' ? undefined : parseInt(roomId);
    applyFilters();
};

const updateDateRange = () => {
    if (dateRangeStart.value && dateRangeEnd.value) {
        localFilters.value.date_range = {
            start: dateRangeStart.value,
            end: dateRangeEnd.value,
        };
    } else {
        localFilters.value.date_range = undefined;
    }
    applyFilters();
};

const setDateRangeToCurrentWeek = () => {
    const currentDate = new Date();
    const startDate = startOfWeek(currentDate, { weekStartsOn: 1 });
    const endDate = endOfWeek(currentDate, { weekStartsOn: 1 });

    dateRangeStart.value = format(startDate, 'yyyy-MM-dd');
    dateRangeEnd.value = format(endDate, 'yyyy-MM-dd');

    localFilters.value.date_range = {
        start: dateRangeStart.value,
        end: dateRangeEnd.value,
    };

    applyFilters();
};

const clearAllFilters = () => {
    localFilters.value = {
        semester_id: undefined,
        lecturer_id: undefined,
        room_id: undefined,
        date_range: undefined,
    };

    dateRangeStart.value = '';
    dateRangeEnd.value = '';
    searchTerm.value = '';

    scheduleApi.clearFilters();
    scheduleApi.fetchSessions();
};

const clearFilter = (filterType: keyof ScheduleFilters) => {
    localFilters.value[filterType] = undefined;

    if (filterType === 'date_range') {
        dateRangeStart.value = '';
        dateRangeEnd.value = '';
    }

    applyFilters();
};

const applyFilters = async () => {
    console.log('%c localFilters.value', 'color: red', localFilters.value);
    scheduleApi.setFilters(localFilters.value);
    await scheduleApi.fetchSessions();
};

const performSearch = async () => {
    if (!searchTerm.value.trim()) return;

    isSearching.value = true;
    try {
        // Implement search logic here
        // This could be extended to search in session titles, lecturer names, etc.
        await scheduleApi.fetchSessions({
            // Add search parameter when backend supports it
            ...localFilters.value,
        });
    } finally {
        isSearching.value = false;
    }
};

// Initialize filters when component mounts
onMounted(async () => {
    // Load filter options if not already loaded
    if (!scheduleApi.filterOptions.value) {
        await scheduleApi.fetchFilterOptions();
    }

    // Sync local filters with composable
    localFilters.value = { ...scheduleApi.filters.value };

    // Initialize date range inputs if date_range is set
    if (scheduleApi.filters.value.date_range) {
        dateRangeStart.value = scheduleApi.filters.value.date_range.start;
        dateRangeEnd.value = scheduleApi.filters.value.date_range.end;
    }
});

// Watch for changes in date range inputs
watch([dateRangeStart, dateRangeEnd], () => {
    updateDateRange();
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <Filter class="h-5 w-5" />
                    <span>Filters</span>
                    <Badge v-if="activeFiltersCount > 0" variant="secondary">
                        {{ activeFiltersCount }}
                    </Badge>
                </div>
                <Button
                    v-if="hasActiveFilters"
                    variant="ghost"
                    size="sm"
                    @click="clearAllFilters"
                    class="text-muted-foreground hover:text-foreground"
                >
                    <RotateCcw class="mr-2 h-4 w-4" />
                    Clear All
                </Button>
            </CardTitle>
        </CardHeader>

        <CardContent class="space-y-4">
            <!-- Search -->
            <div class="space-y-2">
                <Label for="search">Search Sessions</Label>
                <div class="flex space-x-2">
                    <Input
                        id="search"
                        v-model="searchTerm"
                        placeholder="Search by unit code, lecturer, or session title..."
                        @keyup.enter="performSearch"
                    />
                    <Button size="sm" @click="performSearch" :disabled="isSearching || !searchTerm.trim()">
                        <Search class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <!-- Active Filters Display -->
            <div v-if="hasActiveFilters" class="space-y-2">
                <Label>Active Filters</Label>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-if="selectedSemester"
                        variant="outline"
                        class="hover:bg-destructive hover:text-destructive-foreground cursor-pointer"
                        @click="clearFilter('semester_id')"
                    >
                        {{ selectedSemester.name }} ({{ selectedSemester.academic_year }})
                        <X class="ml-1 h-3 w-3" />
                    </Badge>

                    <Badge
                        v-if="selectedLecturer"
                        variant="outline"
                        class="hover:bg-destructive hover:text-destructive-foreground cursor-pointer"
                        @click="clearFilter('lecturer_id')"
                    >
                        {{ selectedLecturer.name }}
                        <X class="ml-1 h-3 w-3" />
                    </Badge>

                    <Badge
                        v-if="selectedRoom"
                        variant="outline"
                        class="hover:bg-destructive hover:text-destructive-foreground cursor-pointer"
                        @click="clearFilter('room_id')"
                    >
                        {{ selectedRoom.name }} ({{ selectedRoom.building }})
                        <X class="ml-1 h-3 w-3" />
                    </Badge>

                    <Badge
                        v-if="localFilters.date_range"
                        variant="outline"
                        class="hover:bg-destructive hover:text-destructive-foreground cursor-pointer"
                        @click="clearFilter('date_range')"
                    >
                        {{ format(new Date(localFilters.date_range.start), 'MMM dd') }} -
                        {{ format(new Date(localFilters.date_range.end), 'MMM dd') }}
                        <X class="ml-1 h-3 w-3" />
                    </Badge>
                </div>
            </div>

            <!-- Semester Filter -->
            <div class="space-y-2">
                <Label for="semester">Semester</Label>
                <Select :value="localFilters.semester_id?.toString() || 'none'" onValueChange="setSemesterFilter">
                    <SelectTrigger id="semester">
                        <SelectValue placeholder="Select semester" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">All Semesters</SelectItem>
                        <SelectItem
                            v-for="semester in scheduleApi.filterOptions.value?.semesters || []"
                            :key="semester.id"
                            :value="semester.id.toString()"
                        >
                            {{ semester.name }} ({{ semester.academic_year }})
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Lecturer Filter -->
            <div class="space-y-2">
                <Label for="lecturer">Lecturer</Label>
                <Select :value="localFilters.lecturer_id?.toString() || 'none'" onValueChange="setLecturerFilter">
                    <SelectTrigger id="lecturer">
                        <SelectValue placeholder="Select lecturer" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">All Lecturers</SelectItem>
                        <SelectItem
                            v-for="lecturer in scheduleApi.filterOptions.value?.lecturers || []"
                            :key="lecturer.id"
                            :value="lecturer.id.toString()"
                        >
                            {{ lecturer.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Room Filter -->
            <div class="space-y-2">
                <Label for="room">Room</Label>
                <Select :value="localFilters.room_id?.toString() || 'none'" onValueChange="setRoomFilter">
                    <SelectTrigger id="room">
                        <SelectValue placeholder="Select room" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">All Rooms</SelectItem>
                        <template v-for="(rooms, campusName) in roomsByCapmus" :key="campusName">
                            <div class="text-muted-foreground px-2 py-1 text-sm font-semibold">
                                {{ campusName }}
                            </div>
                            <SelectItem v-for="room in rooms" :key="room.id" :value="room.id.toString()" class="pl-4">
                                {{ room.name }} ({{ room.building }})
                            </SelectItem>
                        </template>
                    </SelectContent>
                </Select>
            </div>

            <!-- Date Range Filter -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <Label>Date Range</Label>
                    <Button variant="ghost" size="sm" @click="setDateRangeToCurrentWeek" class="text-xs"> Current Week </Button>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <Label for="date-start" class="text-xs">From</Label>
                        <Input id="date-start" v-model="dateRangeStart" type="date" class="text-sm" />
                    </div>
                    <div>
                        <Label for="date-end" class="text-xs">To</Label>
                        <Input id="date-end" v-model="dateRangeEnd" type="date" class="text-sm" />
                    </div>
                </div>
            </div>

            <!-- Apply/Reset Actions -->
            <div class="flex space-x-2 pt-2">
                <Button @click="applyFilters" class="flex-1" :disabled="scheduleApi.isLoading.value">
                    <Filter class="mr-2 h-4 w-4" />
                    Apply Filters
                </Button>
                <Button variant="outline" @click="clearAllFilters" :disabled="!hasActiveFilters">
                    <RotateCcw class="h-4 w-4" />
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
