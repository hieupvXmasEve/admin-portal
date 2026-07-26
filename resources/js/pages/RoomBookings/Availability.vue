<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import TimePicker from '@/components/ui/TimePicker.vue';
import { useDataTable } from '@/composables/useDataTable';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { CalendarPlus, RotateCcw } from 'lucide-vue-next';
import { computed } from 'vue';

interface AvailabilityFilters {
    building_id: string;
    room_id: string;
    start_date: string;
    end_date: string;
    start_time: string;
    end_time: string;
}

interface AvailabilityEvent {
    type: 'room_booking' | 'class_session' | 'exam_room_slot';
    id: number;
    title: string;
    start_time: string;
    end_time: string;
    status: string;
    booking_id?: number;
    class_session_id?: number;
    exam_room_slot_id?: number;
    session_count?: number;
}

interface FreeWindow {
    start_time: string;
    end_time: string;
}

interface AvailabilityDay {
    date: string;
    events: AvailabilityEvent[];
    free_windows: FreeWindow[];
    is_bookable: boolean;
    blocked_reason?: string | null;
}

interface AvailabilityRoom {
    id: number;
    name: string;
    code: string;
    capacity: number;
    status: string;
    is_bookable: boolean;
    building?: {
        id: number;
        name: string;
        code: string;
    } | null;
    days: AvailabilityDay[];
}

type AvailabilityTimelineItem =
    | {
          kind: 'free';
          key: string;
          start_time: string;
          end_time: string;
          window: FreeWindow;
      }
    | {
          kind: 'event';
          key: string;
          start_time: string;
          end_time: string;
          event: AvailabilityEvent;
      };

const props = defineProps<{
    availability: {
        dates: string[];
        rooms: AvailabilityRoom[];
        summary: {
            rooms: number;
            dates: number;
            busy_events: number;
        };
    };
    filters: Partial<AvailabilityFilters>;
    buildings?: Array<{ id: number; value: string; label: string }>;
    rooms?: Array<{ id: number; value: string; label: string; building_id: number; capacity: number }>;
}>();

const { filters, setFilter, clearAllFilters, hasActiveFilters, isLoading } = useDataTable<AvailabilityFilters>({
    baseUrl: systemRoutes.roomBookings.availability(),
    initialFilters: {
        building_id: props.filters.building_id ? String(props.filters.building_id) : '',
        room_id: props.filters.room_id ? String(props.filters.room_id) : '',
        start_date: props.filters.start_date ?? '',
        end_date: props.filters.end_date ?? '',
        start_time: props.filters.start_time ?? '07:00',
        end_time: props.filters.end_time ?? '20:00',
    },
    defaultValues: {
        building_id: '',
        room_id: '',
        start_time: '07:00',
        end_time: '20:00',
    },
    only: ['availability', 'filters'],
    immediateFields: ['building_id', 'room_id', 'start_date', 'end_date', 'start_time', 'end_time'],
});

const filteredRoomOptions = computed(() => {
    if (!filters.building_id) return props.rooms || [];
    return (props.rooms || []).filter((room) => String(room.building_id) === String(filters.building_id));
});

function formatDate(value: string): string {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

function eventVariant(type: string): 'default' | 'secondary' | 'outline' {
    if (type === 'class_session') return 'secondary';
    if (type === 'exam_room_slot') return 'outline';
    return 'default';
}

function eventLabel(type: string): string {
    if (type === 'class_session') return 'Class';
    if (type === 'exam_room_slot') return 'Exam';
    return 'Booking';
}

// Exam blocks get an amber treatment so they read as a distinct occupancy source
// from room bookings (primary) and class sessions (secondary).
function eventChipClass(type: string): string {
    return type === 'exam_room_slot' ? 'border-amber-300 bg-amber-50' : '';
}

function eventBadgeClass(type: string): string {
    return type === 'exam_room_slot' ? 'border-amber-400 bg-amber-100 text-amber-800' : '';
}

function timelineItems(day: AvailabilityDay): AvailabilityTimelineItem[] {
    return [
        ...day.free_windows.map((window, index) => ({
            kind: 'free' as const,
            key: `free-${index}-${window.start_time}-${window.end_time}`,
            start_time: window.start_time,
            end_time: window.end_time,
            window,
        })),
        ...day.events.map((event) => ({
            kind: 'event' as const,
            key: `${event.type}-${event.id}`,
            start_time: event.start_time,
            end_time: event.end_time,
            event,
        })),
    ].sort((first, second) => first.start_time.localeCompare(second.start_time) || first.end_time.localeCompare(second.end_time));
}

function createFromRoom(roomId: number, date: string, window?: FreeWindow) {
    router.visit(
        systemRoutes.roomBookings.create({
            room_id: roomId,
            date,
            start_time: window?.start_time,
            end_time: window?.end_time,
        }),
    );
}
</script>

<template>
    <Head title="Find Available Rooms" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Find Available Rooms</h1>
            <p class="text-muted-foreground mt-1">{{ availability.summary.rooms }} rooms · {{ availability.summary.dates }} dates · {{ availability.summary.busy_events }} busy blocks</p>
            <div class="text-muted-foreground mt-2 flex flex-wrap items-center gap-3 text-xs">
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm border border-green-200 bg-green-50"></span>Free</span>
                <span class="flex items-center gap-1"><span class="bg-primary inline-block h-2.5 w-2.5 rounded-sm"></span>Booking</span>
                <span class="flex items-center gap-1"><span class="bg-secondary inline-block h-2.5 w-2.5 rounded-sm border"></span>Class</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm border border-amber-400 bg-amber-100"></span>Exam (thi lại)</span>
            </div>
        </div>
        <Button @click="router.visit(systemRoutes.roomBookings.create())">
            <CalendarPlus class="mr-2 h-4 w-4" />
            New Booking
        </Button>
    </div>

    <Card>
        <CardHeader>
            <CardTitle>Filters</CardTitle>
            <CardDescription>Building, room, date range, and daily window</CardDescription>
        </CardHeader>
        <CardContent class="grid gap-4 lg:grid-cols-6">
            <Select :model-value="filters.building_id || 'all'" @update:model-value="(value) => setFilter('building_id', value === 'all' ? '' : String(value))">
                <SelectTrigger>
                    <SelectValue placeholder="Building" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All buildings</SelectItem>
                    <SelectItem v-for="building in buildings" :key="building.id" :value="building.value">
                        {{ building.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select :model-value="filters.room_id || 'all'" @update:model-value="(value) => setFilter('room_id', value === 'all' ? '' : String(value))">
                <SelectTrigger>
                    <SelectValue placeholder="Room" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All rooms</SelectItem>
                    <SelectItem v-for="room in filteredRoomOptions" :key="room.id" :value="room.value">
                        {{ room.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <DatePicker :model-value="filters.start_date" placeholder="From" @update:model-value="(value) => setFilter('start_date', value)" />
            <DatePicker :model-value="filters.end_date" placeholder="To" @update:model-value="(value) => setFilter('end_date', value)" />
            <TimePicker :model-value="filters.start_time" @update:model-value="(value) => setFilter('start_time', value)" />
            <div class="flex gap-2">
                <TimePicker :model-value="filters.end_time" @update:model-value="(value) => setFilter('end_time', value)" />
                <Button v-if="hasActiveFilters" variant="ghost" size="icon" :disabled="isLoading" @click="clearAllFilters">
                    <RotateCcw class="h-4 w-4" />
                </Button>
            </div>
        </CardContent>
    </Card>

    <div class="overflow-x-auto rounded-md border">
        <Table class="w-max min-w-full table-fixed">
            <TableHeader>
                <TableRow>
                    <TableHead class="bg-background sticky left-0 z-10 w-64 max-w-64 min-w-64">Room</TableHead>
                    <TableHead v-for="date in availability.dates" :key="date" class="w-72 max-w-72 min-w-72">
                        {{ formatDate(date) }}
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="room in availability.rooms" :key="room.id">
                    <TableCell class="bg-background sticky left-0 z-10 w-64 max-w-64 min-w-64 align-top whitespace-normal">
                        <div class="font-medium">{{ room.name }}</div>
                        <div class="text-muted-foreground text-xs">{{ room.code }} · {{ room.building?.name }} · {{ room.capacity }} seats</div>
                        <Badge class="mt-2" :variant="room.is_bookable && room.status === 'available' ? 'outline' : 'secondary'">{{ room.status }}</Badge>
                    </TableCell>
                    <TableCell v-for="day in room.days" :key="`${room.id}-${day.date}`" class="w-72 max-w-72 min-w-72 align-top whitespace-normal">
                        <div v-if="day.blocked_reason" class="text-muted-foreground rounded border border-dashed p-2 text-xs">
                            {{ day.blocked_reason }}
                        </div>
                        <div v-else class="flex min-w-0 flex-col gap-1">
                            <template v-if="timelineItems(day).length">
                                <template v-for="item in timelineItems(day)" :key="item.key">
                                    <button
                                        v-if="item.kind === 'free'"
                                        type="button"
                                        class="block w-full min-w-0 rounded border border-green-200 bg-green-50 px-2 py-1 text-left text-xs text-green-800 hover:bg-green-100"
                                        @click="createFromRoom(room.id, day.date, item.window)"
                                    >
                                        Free {{ item.start_time }}-{{ item.end_time }}
                                    </button>
                                    <div v-else class="min-w-0 rounded border px-2 py-1 text-xs" :class="eventChipClass(item.event.type)">
                                        <div class="flex min-w-0 items-center justify-between gap-2">
                                            <span class="font-medium">{{ item.event.start_time }}-{{ item.event.end_time }}</span>
                                            <Badge :variant="eventVariant(item.event.type)" class="shrink-0 text-[10px]" :class="eventBadgeClass(item.event.type)">{{ eventLabel(item.event.type) }}</Badge>
                                        </div>
                                        <div class="text-muted-foreground mt-1 line-clamp-2 min-w-0">
                                            {{ item.event.title }}
                                            <span v-if="item.event.type === 'exam_room_slot' && (item.event.session_count ?? 0) > 1"> · {{ item.event.session_count }} môn</span>
                                        </div>
                                    </div>
                                </template>
                            </template>
                            <div v-else class="text-muted-foreground text-xs">No window in filter.</div>
                        </div>
                    </TableCell>
                </TableRow>
                <TableRow v-if="availability.rooms.length === 0">
                    <TableCell :colspan="availability.dates.length + 1" class="text-muted-foreground py-8 text-center">No rooms match the selected filters.</TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
