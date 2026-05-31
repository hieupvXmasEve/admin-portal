<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import TimePicker from '@/components/ui/TimePicker.vue';
import { useApi, type ApiResponse } from '@/composables/useApiRequest';
import type { Room } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { AlertTriangle, CheckCircle, Copy, RefreshCw, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

type ScheduleMode = 'single' | 'range';

interface RoomOption {
    id: number;
    value: string;
    label: string;
    building_id: number;
    building_name?: string;
    capacity: number;
    is_bookable?: boolean;
    status?: string;
}

interface BookingOccurrence {
    booking_date: string;
    start_time: string;
    end_time: string;
}

interface Conflict {
    type: 'room_booking' | 'class_session' | 'validation';
    id: number | string;
    title: string;
    booking_date?: string;
    start_time: string;
    end_time: string;
    status: string;
}

interface PreviewOccurrence extends BookingOccurrence {
    available: boolean;
    conflicts: Conflict[];
}

interface PreviewPayload {
    occurrences: PreviewOccurrence[];
    has_conflicts: boolean;
    conflicts: Conflict[];
    summary: {
        total: number;
        available: number;
        conflicting: number;
    };
}

interface CloneSource {
    source_booking_id: number;
    room_id: number;
    title: string;
    description?: string | null;
    booking_date: string;
    date_from: string;
    date_to: string;
    start_time: string;
    end_time: string;
    booking_type: string;
    priority?: string | null;
    contact_person?: string | null;
    contact_phone?: string | null;
    contact_email?: string | null;
    special_requirements?: string | null;
}

const props = defineProps<{
    selected_room?: Room;
    clone_source?: CloneSource | null;
    initial_schedule?: {
        booking_date: string;
        date_from: string;
        date_to: string;
        start_time: string;
        end_time: string;
    };
    booking_types?: Array<{ value: string; label: string }>;
    priorities?: Array<{ value: string; label: string }>;
    buildings?: Array<{ id: number; value: string; label: string }>;
    rooms?: RoomOption[];
}>();

const api = useApi();
const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

const today = (() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
})();

const cloneSource = computed(() => props.clone_source ?? null);
const initialSchedule = computed(
    () =>
        props.initial_schedule ?? {
            booking_date: today,
            date_from: today,
            date_to: today,
            start_time: '09:00',
            end_time: '10:00',
        },
);

const form = useForm({
    room_id: cloneSource.value?.room_id ?? props.selected_room?.id ?? 0,
    title: cloneSource.value?.title ?? '',
    description: cloneSource.value?.description ?? '',
    schedule_mode: 'single' as ScheduleMode,
    booking_date: cloneSource.value?.booking_date ?? initialSchedule.value.booking_date,
    date_from: cloneSource.value?.date_from ?? initialSchedule.value.date_from,
    date_to: cloneSource.value?.date_to ?? initialSchedule.value.date_to,
    all_days: true,
    selected_weekdays: [] as string[],
    start_time: cloneSource.value?.start_time ?? initialSchedule.value.start_time,
    end_time: cloneSource.value?.end_time ?? initialSchedule.value.end_time,
    occurrences: [
        {
            booking_date: cloneSource.value?.booking_date ?? initialSchedule.value.booking_date,
            start_time: cloneSource.value?.start_time ?? initialSchedule.value.start_time,
            end_time: cloneSource.value?.end_time ?? initialSchedule.value.end_time,
        },
    ] as BookingOccurrence[],
    booking_type: cloneSource.value?.booking_type ?? 'meeting',
    priority: cloneSource.value?.priority ?? 'normal',
    contact_person: cloneSource.value?.contact_person ?? '',
    contact_phone: cloneSource.value?.contact_phone ?? '',
    contact_email: cloneSource.value?.contact_email ?? '',
    special_requirements: cloneSource.value?.special_requirements ?? '',
});

const selectedBuildingId = ref<number | null>(props.selected_room?.building?.id ?? null);
const preview = ref<PreviewPayload | null>(null);
const previewStale = ref(true);
const isPreviewing = ref(false);
const formErrors = computed(() => form.errors as Record<string, string | undefined>);

const filteredRooms = computed(() => {
    if (!selectedBuildingId.value) return props.rooms || [];
    return (props.rooms || []).filter((room) => room.building_id === selectedBuildingId.value);
});

const selectedRoom = computed(() => (props.rooms || []).find((room) => room.id === Number(form.room_id)));
const hasConflicts = computed(() => Boolean(preview.value?.has_conflicts));
const canSubmit = computed(() => form.room_id && form.title && form.occurrences.length > 0 && preview.value && !previewStale.value && !hasConflicts.value && !isPreviewing.value);

function parseDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function formatDate(value: string): string {
    return parseDate(value).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
}

function dateKey(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function dayName(value: string): string {
    return parseDate(value).toLocaleDateString('en-US', { weekday: 'long' });
}

function generatedDates(): string[] {
    if (form.schedule_mode === 'single') return form.booking_date ? [form.booking_date] : [];
    if (!form.date_from || !form.date_to) return [];

    const start = parseDate(form.date_from);
    const end = parseDate(form.date_to);
    if (start > end) return [];

    const selected = new Set(form.selected_weekdays);
    const dates: string[] = [];
    const cursor = new Date(start);

    while (cursor <= end && dates.length < 366) {
        const key = dateKey(cursor);
        if (form.all_days || selected.has(dayName(key))) {
            dates.push(key);
        }
        cursor.setDate(cursor.getDate() + 1);
    }

    return dates;
}

function syncOccurrences() {
    const existingByDate = new Map(form.occurrences.map((occurrence) => [occurrence.booking_date, occurrence]));
    form.occurrences = generatedDates().map((bookingDate) => {
        const existing = existingByDate.get(bookingDate);
        return {
            booking_date: bookingDate,
            start_time: existing?.start_time ?? form.start_time,
            end_time: existing?.end_time ?? form.end_time,
        };
    });
}

function markPreviewStale() {
    previewStale.value = true;
    preview.value = null;
}

async function previewAvailability() {
    if (!form.room_id || form.occurrences.length === 0) {
        preview.value = null;
        previewStale.value = true;
        return;
    }

    isPreviewing.value = true;
    try {
        const response = await api.post<PreviewPayload>(systemRoutes.roomBookings.apiPreviewSeries(), {
            room_id: form.room_id,
            occurrences: form.occurrences,
        });
        const body = response.data.value as ApiResponse<PreviewPayload> | null;
        preview.value = body?.success ? body.data : null;
        previewStale.value = false;
    } catch {
        preview.value = null;
        previewStale.value = true;
    } finally {
        isPreviewing.value = false;
    }
}

const debouncedPreviewAvailability = useDebounceFn(previewAvailability, 450);

function setScheduleMode(mode: ScheduleMode) {
    form.schedule_mode = mode;
    syncOccurrences();
    markPreviewStale();
}

function setWeekday(weekday: string, checked: boolean) {
    form.selected_weekdays = checked ? [...form.selected_weekdays, weekday] : form.selected_weekdays.filter((value) => value !== weekday);
    syncOccurrences();
    markPreviewStale();
}

function setWeekdayChecked(weekday: string, checked: boolean | 'indeterminate') {
    setWeekday(weekday, checked === true);
}

function applySameTimeToAll() {
    form.occurrences = form.occurrences.map((occurrence) => ({
        ...occurrence,
        start_time: form.start_time,
        end_time: form.end_time,
    }));
    markPreviewStale();
    debouncedPreviewAvailability();
}

watch(
    () => form.room_id,
    (roomId) => {
        const room = (props.rooms || []).find((option) => option.id === Number(roomId));
        if (room) selectedBuildingId.value = room.building_id;
        markPreviewStale();
        debouncedPreviewAvailability();
    },
);

watch(
    () => [form.schedule_mode, form.booking_date, form.date_from, form.date_to, form.all_days, form.selected_weekdays.join('|')],
    () => {
        syncOccurrences();
        markPreviewStale();
        debouncedPreviewAvailability();
    },
);

watch(
    () => [form.start_time, form.end_time],
    () => {
        if (form.schedule_mode === 'single') {
            syncOccurrences();
        }
        markPreviewStale();
        debouncedPreviewAvailability();
    },
);

watch(
    () => form.occurrences,
    () => {
        markPreviewStale();
        debouncedPreviewAvailability();
    },
    { deep: true },
);

function submit() {
    if (!preview.value || previewStale.value) {
        toast.error('Preview availability before creating this booking.');
        return;
    }

    if (hasConflicts.value) {
        toast.error('Resolve all conflicting dates before creating this booking.');
        return;
    }

    form.post(systemRoutes.roomBookings.store(), {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.general) toast.error(errors.general);
            if (errors.occurrences) toast.error(errors.occurrences);
        },
    });
}

syncOccurrences();
debouncedPreviewAvailability();
</script>

<template>
    <Head title="New Room Booking" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">New Room Booking</h1>
            <p class="text-muted-foreground mt-1">Create one booking or a dated booking set</p>
        </div>
        <Button variant="outline" @click="router.visit(systemRoutes.roomBookings.availability())">
            <Search class="mr-2 h-4 w-4" />
            Find Available Rooms
        </Button>
    </div>

    <Alert v-if="formErrors.general || formErrors.occurrences" variant="destructive">
        <AlertTriangle class="h-4 w-4" />
        <AlertTitle>Booking blocked</AlertTitle>
        <AlertDescription>{{ formErrors.general || formErrors.occurrences }}</AlertDescription>
    </Alert>

    <Alert v-if="cloneSource" class="border-blue-200 bg-blue-50">
        <Copy class="h-4 w-4 text-blue-700" />
        <AlertTitle class="text-blue-900">Clone draft</AlertTitle>
        <AlertDescription class="text-blue-800">Source booking #{{ cloneSource.source_booking_id }} copied into a new draft.</AlertDescription>
    </Alert>

    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Room</CardTitle>
                <CardDescription>Campus-scoped room selection</CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Building</label>
                    <Select :model-value="selectedBuildingId?.toString() || 'all'" @update:model-value="(value) => (selectedBuildingId = value === 'all' ? null : Number(value))">
                        <SelectTrigger>
                            <SelectValue placeholder="All buildings" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All buildings</SelectItem>
                            <SelectItem v-for="building in buildings" :key="building.id" :value="building.value">
                                {{ building.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Room *</label>
                    <Select :model-value="form.room_id ? String(form.room_id) : undefined" @update:model-value="(value) => (form.room_id = Number(value))">
                        <SelectTrigger :class="form.errors.room_id && 'border-destructive'">
                            <SelectValue placeholder="Select a room" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="room in filteredRooms" :key="room.id" :value="room.value"> {{ room.label }} ({{ room.capacity }} seats) </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="selectedRoom" class="text-muted-foreground text-xs">{{ selectedRoom.building_name }} · {{ selectedRoom.capacity }} seats · {{ selectedRoom.status }}</p>
                    <p v-if="form.errors.room_id" class="text-destructive text-sm">{{ form.errors.room_id }}</p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Details</CardTitle>
                <CardDescription>Booking identity and contact information</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <label class="text-sm font-medium">Title *</label>
                    <Input v-model="form.title" :class="form.errors.title && 'border-destructive'" placeholder="Meeting, exam, workshop" />
                    <p v-if="form.errors.title" class="text-destructive text-sm">{{ form.errors.title }}</p>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Description</label>
                    <Textarea v-model="form.description" rows="3" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Booking type *</label>
                        <Select v-model="form.booking_type">
                            <SelectTrigger>
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="type in booking_types" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Priority</label>
                        <Select v-model="form.priority">
                            <SelectTrigger>
                                <SelectValue placeholder="Select priority" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="priority in priorities" :key="priority.value" :value="priority.value">
                                    {{ priority.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Schedule</CardTitle>
                <CardDescription>{{ form.occurrences.length }} occurrence{{ form.occurrences.length === 1 ? '' : 's' }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
                <div class="inline-flex rounded-md border p-1">
                    <Button type="button" size="sm" :variant="form.schedule_mode === 'single' ? 'default' : 'ghost'" @click="setScheduleMode('single')">Single day</Button>
                    <Button type="button" size="sm" :variant="form.schedule_mode === 'range' ? 'default' : 'ghost'" @click="setScheduleMode('range')">Date range</Button>
                </div>

                <div v-if="form.schedule_mode === 'single'" class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Date *</label>
                        <DatePicker v-model="form.booking_date" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Start time *</label>
                        <TimePicker v-model="form.start_time" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">End time *</label>
                        <TimePicker v-model="form.end_time" />
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="space-y-2">
                            <label class="text-sm font-medium">From *</label>
                            <DatePicker v-model="form.date_from" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">To *</label>
                            <DatePicker v-model="form.date_to" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Default start</label>
                            <TimePicker v-model="form.start_time" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Default end</label>
                            <TimePicker v-model="form.end_time" />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox v-model:checked="form.all_days" />
                            All days
                        </label>
                        <template v-if="!form.all_days">
                            <label v-for="weekday in weekdays" :key="weekday" class="flex items-center gap-2 text-sm">
                                <Checkbox :checked="form.selected_weekdays.includes(weekday)" @update:checked="setWeekdayChecked(weekday, $event)" />
                                {{ weekday.slice(0, 3) }}
                            </label>
                        </template>
                        <Button type="button" variant="outline" size="sm" @click="applySameTimeToAll">
                            <RefreshCw class="mr-2 h-4 w-4" />
                            Apply time
                        </Button>
                    </div>
                </div>

                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-14">#</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead class="w-40">Start</TableHead>
                                <TableHead class="w-40">End</TableHead>
                                <TableHead class="w-36">Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(occurrence, index) in form.occurrences" :key="occurrence.booking_date">
                                <TableCell>{{ index + 1 }}</TableCell>
                                <TableCell>
                                    <div class="font-medium">{{ formatDate(occurrence.booking_date) }}</div>
                                </TableCell>
                                <TableCell>
                                    <TimePicker v-model="occurrence.start_time" />
                                </TableCell>
                                <TableCell>
                                    <TimePicker v-model="occurrence.end_time" />
                                </TableCell>
                                <TableCell>
                                    <Badge v-if="preview?.occurrences[index]?.available" variant="default">Available</Badge>
                                    <Badge v-else-if="preview?.occurrences[index]" variant="destructive">Conflict</Badge>
                                    <Badge v-else variant="outline">Pending</Badge>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="form.occurrences.length === 0">
                                <TableCell colspan="5" class="text-muted-foreground py-8 text-center text-sm">No dates selected.</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <Alert v-if="preview?.has_conflicts" variant="destructive">
            <AlertTriangle class="h-4 w-4" />
            <AlertTitle>Conflicts found</AlertTitle>
            <AlertDescription>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="conflict in preview.conflicts" :key="`${conflict.type}-${conflict.id}-${conflict.booking_date}`">
                        <span class="font-medium">{{ conflict.booking_date ? formatDate(conflict.booking_date) : '' }}</span>
                        {{ conflict.start_time }}-{{ conflict.end_time }} · {{ conflict.title }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <Alert v-else-if="preview && !previewStale" class="border-green-200 bg-green-50">
            <CheckCircle class="h-4 w-4 text-green-700" />
            <AlertTitle class="text-green-900">Available</AlertTitle>
            <AlertDescription class="text-green-800">{{ preview.summary.available }} of {{ preview.summary.total }} occurrences are available.</AlertDescription>
        </Alert>

        <div v-else-if="isPreviewing" class="text-muted-foreground text-center text-sm">Checking availability...</div>

        <Card>
            <CardHeader>
                <CardTitle>Contact</CardTitle>
                <CardDescription>Optional request contact</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Contact person</label>
                        <Input v-model="form.contact_person" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Phone</label>
                        <Input v-model="form.contact_phone" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Email</label>
                        <Input v-model="form.contact_email" type="email" />
                    </div>
                </div>
                <div class="space-y-2">
                    <label class="text-sm font-medium">Special requirements</label>
                    <Textarea v-model="form.special_requirements" rows="2" />
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end gap-3">
            <Button type="button" variant="outline" @click="router.visit(systemRoutes.roomBookings.index())">Cancel</Button>
            <Button type="button" variant="outline" :disabled="isPreviewing || form.occurrences.length === 0" @click="previewAvailability">
                {{ isPreviewing ? 'Checking...' : 'Preview' }}
            </Button>
            <Button type="submit" :disabled="form.processing || !canSubmit">
                {{ form.processing ? 'Creating...' : form.occurrences.length > 1 ? `Create ${form.occurrences.length} Bookings` : 'Create Booking' }}
            </Button>
        </div>
    </form>
</template>
