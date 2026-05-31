<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, List, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CalendarItem {
    id: string;
    type: 'room_booking' | 'class_session';
    room_id: number;
    room?: {
        id: number;
        name: string;
        code: string;
        building?: { name: string };
    };
    title: string;
    description?: string;
    booking_date: string;
    start_time: string;
    end_time: string;
    status: string;
    booking_type: string;
    is_editable: boolean;
    booker_name?: string;
    instructor?: string;
    booking_id?: number;
    course_offering_id?: number;
}

const props = defineProps<{
    calendar_data: CalendarItem[];
    filters?: {
        room_id?: string;
        building_id?: string;
        start_date?: string;
        end_date?: string;
    };
    buildings?: Array<{ id: number; value: string; label: string }>;
    rooms?: Array<{ id: number; value: string; label: string }>;
}>();

// Current week state
const currentDate = ref(new Date(props.filters?.start_date || new Date()));
const selectedBuildingId = ref(props.filters?.building_id || '');
const selectedRoomId = ref(props.filters?.room_id || '');

// Calculate week dates
const weekDates = computed(() => {
    const dates = [];
    const startOfWeek = new Date(currentDate.value);
    startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay() + 1); // Monday

    for (let i = 0; i < 7; i++) {
        const date = new Date(startOfWeek);
        date.setDate(date.getDate() + i);
        dates.push(date);
    }
    return dates;
});

const weekLabel = computed(() => {
    const start = weekDates.value[0];
    const end = weekDates.value[6];
    return `${start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
});

// Time slots (7 AM to 8 PM)
const timeSlots = computed(() => {
    const slots = [];
    for (let hour = 7; hour <= 20; hour++) {
        slots.push(`${hour.toString().padStart(2, '0')}:00`);
    }
    return slots;
});

// Group items by date
const itemsByDate = computed(() => {
    const grouped: Record<string, CalendarItem[]> = {};
    props.calendar_data.forEach((item) => {
        const dateKey = item.booking_date;
        if (!grouped[dateKey]) grouped[dateKey] = [];
        grouped[dateKey].push(item);
    });
    return grouped;
});

// Get items for a specific date and time
const getItemsForSlot = (date: Date, timeSlot: string) => {
    const dateKey = date.toISOString().split('T')[0];
    const items = itemsByDate.value[dateKey] || [];

    return items.filter((item) => {
        const startTime = item.start_time.substring(0, 5);
        const endTime = item.end_time.substring(0, 5);
        return startTime <= timeSlot && endTime > timeSlot;
    });
};

// Navigation
const previousWeek = () => {
    const newDate = new Date(currentDate.value);
    newDate.setDate(newDate.getDate() - 7);
    currentDate.value = newDate;
    applyFilters();
};

const nextWeek = () => {
    const newDate = new Date(currentDate.value);
    newDate.setDate(newDate.getDate() + 7);
    currentDate.value = newDate;
    applyFilters();
};

const goToToday = () => {
    currentDate.value = new Date();
    applyFilters();
};

const applyFilters = () => {
    const startDate = weekDates.value[0].toISOString().split('T')[0];
    const endDate = weekDates.value[6].toISOString().split('T')[0];

    const params = new URLSearchParams();
    params.set('start_date', startDate);
    params.set('end_date', endDate);
    if (selectedBuildingId.value) params.set('building_id', selectedBuildingId.value);
    if (selectedRoomId.value) params.set('room_id', selectedRoomId.value);

    router.visit(systemRoutes.roomBookings.calendar(Object.fromEntries(params.entries())), {
        preserveState: true,
        preserveScroll: true,
        only: ['calendar_data', 'filters'],
    });
};

const updateBuildingFilter = (value: unknown) => {
    const selectedValue = String(value ?? '');
    selectedBuildingId.value = selectedValue === 'all' ? '' : selectedValue;
    selectedRoomId.value = '';
    applyFilters();
};

const updateRoomFilter = (value: unknown) => {
    const selectedValue = String(value ?? '');
    selectedRoomId.value = selectedValue === 'all' ? '' : selectedValue;
    applyFilters();
};

// Color schemes for different types
const getItemColor = (item: CalendarItem) => {
    // Class sessions - blue/indigo tones (not editable)
    if (item.type === 'class_session') {
        return 'bg-indigo-100 text-indigo-800 border-indigo-300 hover:bg-indigo-150';
    }

    // Room bookings - based on status
    switch (item.status) {
        case 'approved':
            return 'bg-emerald-100 text-emerald-800 border-emerald-300 hover:bg-emerald-150';
        case 'pending':
            return 'bg-amber-100 text-amber-800 border-amber-300 hover:bg-amber-150';
        case 'rejected':
            return 'bg-red-100 text-red-800 border-red-300';
        case 'cancelled':
            return 'bg-gray-100 text-gray-500 border-gray-300 line-through';
        case 'completed':
            return 'bg-slate-100 text-slate-700 border-slate-300';
        default:
            return 'bg-blue-100 text-blue-800 border-blue-300';
    }
};

const formatTime = (time: string) => {
    const [hours, minutes] = time.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
};

const handleItemClick = (item: CalendarItem) => {
    // Only allow clicking on editable room bookings
    if (item.type === 'room_booking' && item.booking_id) {
        router.visit(systemRoutes.roomBookings.show(item.booking_id));
    }
};

const isToday = (date: Date) => {
    const today = new Date();
    return date.toDateString() === today.toDateString();
};

const getItemTooltip = (item: CalendarItem) => {
    if (item.type === 'class_session') {
        return `${item.title}\n${item.description || ''}\nInstructor: ${item.instructor || 'N/A'}\n(Class Session - Not editable)`;
    }
    return `${item.title}\n${item.description || ''}\nBooked by: ${item.booker_name || 'N/A'}\nStatus: ${item.status}`;
};
</script>

<template>
    <Head title="Room Usage Calendar" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Room Usage Calendar</h1>
                <p class="text-muted-foreground mt-1">View occupied room bookings and class schedules by week</p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="router.visit(systemRoutes.roomBookings.index())">
                    <List class="mr-2 h-4 w-4" />
                    List View
                </Button>
                <Button @click="router.visit(systemRoutes.roomBookings.create())">
                    <Plus class="mr-2 h-4 w-4" />
                    New Booking
                </Button>
            </div>
        </div>

        <!-- Controls -->
        <Card>
            <CardContent class="p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <Button variant="outline" size="icon" @click="previousWeek">
                                <ChevronLeft class="h-4 w-4" />
                            </Button>
                            <Button variant="outline" @click="goToToday">Today</Button>
                            <Button variant="outline" size="icon" @click="nextWeek">
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                        <span class="text-lg font-semibold">{{ weekLabel }}</span>
                    </div>

                    <div class="flex items-center gap-4">
                        <Select :model-value="selectedBuildingId || 'all'" @update:model-value="updateBuildingFilter">
                            <SelectTrigger class="w-48">
                                <SelectValue placeholder="All Buildings" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Buildings</SelectItem>
                                <SelectItem v-for="building in buildings" :key="building.id" :value="building.value">
                                    {{ building.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Select :model-value="selectedRoomId || 'all'" @update:model-value="updateRoomFilter">
                            <SelectTrigger class="w-48">
                                <SelectValue placeholder="All Rooms" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Rooms</SelectItem>
                                <SelectItem v-for="room in rooms" :key="room.id" :value="room.value">
                                    {{ room.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Calendar Grid -->
        <Card>
            <CardContent class="p-0">
                <div class="overflow-auto">
                    <table class="w-full min-w-[900px] border-collapse">
                        <thead>
                            <tr>
                                <th class="bg-muted sticky left-0 z-10 w-20 border-r border-b p-2 text-left text-sm font-medium">Time</th>
                                <th v-for="date in weekDates" :key="date.toISOString()" class="min-w-[120px] border-b p-2 text-center" :class="{ 'bg-primary/10': isToday(date) }">
                                    <div class="text-sm font-medium">
                                        {{ date.toLocaleDateString('en-US', { weekday: 'short' }) }}
                                    </div>
                                    <div class="text-muted-foreground text-xs">
                                        {{ date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="timeSlot in timeSlots" :key="timeSlot" class="h-20">
                                <td class="bg-muted sticky left-0 z-10 border-r border-b p-2 text-xs font-medium">
                                    {{ formatTime(timeSlot) }}
                                </td>
                                <td v-for="date in weekDates" :key="`${date.toISOString()}-${timeSlot}`" class="relative border-r border-b p-1 align-top" :class="{ 'bg-primary/5': isToday(date) }">
                                    <div
                                        v-for="item in getItemsForSlot(date, timeSlot)"
                                        :key="item.id"
                                        class="mb-1 rounded border px-1.5 py-1 text-xs transition-all"
                                        :class="[getItemColor(item), item.is_editable ? 'cursor-pointer hover:shadow-md' : 'cursor-default opacity-90']"
                                        :title="getItemTooltip(item)"
                                        @click="handleItemClick(item)"
                                    >
                                        <div class="flex items-center gap-1">
                                            <!-- Icon indicator for type -->
                                            <span v-if="item.type === 'class_session'" class="text-[10px]">📚</span>
                                            <span v-else class="text-[10px]">📅</span>
                                            <span class="max-w-[100px] flex-1 truncate font-medium">{{ item.title }}</span>
                                        </div>
                                        <div class="truncate text-[10px] opacity-80">
                                            {{ item.room?.name }}
                                        </div>
                                        <div v-if="item.type === 'class_session'" class="truncate text-[10px] opacity-70">
                                            {{ item.instructor }}
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Legend -->
        <div class="flex flex-wrap items-center gap-6 text-sm">
            <div class="flex items-center gap-2">
                <span class="text-muted-foreground font-medium">Legend:</span>
            </div>

            <!-- Class Sessions -->
            <div class="flex items-center gap-2">
                <div class="flex h-4 w-4 items-center justify-center rounded border border-indigo-300 bg-indigo-100 text-[10px]">📚</div>
                <span>Class Session (Fixed)</span>
            </div>

            <!-- Booking Statuses -->
            <div class="flex items-center gap-2">
                <div class="flex h-4 w-4 items-center justify-center rounded border border-emerald-300 bg-emerald-100 text-[10px]">📅</div>
                <span>Approved Booking</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-4 rounded border border-amber-300 bg-amber-100"></div>
                <span>Pending</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-4 rounded border border-red-300 bg-red-100"></div>
                <span>Rejected</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-4 rounded border border-gray-300 bg-gray-100"></div>
                <span>Cancelled</span>
            </div>
        </div>

        <!-- Info Note -->
        <p class="text-muted-foreground text-sm"><strong>Note:</strong> Class sessions (📚) are scheduled classes and cannot be modified here. Only room bookings (📅) can be edited by clicking on them.</p>
    </div>
</template>
