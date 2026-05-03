<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApiRequest';
import type { Lecture, Room } from '@/types/models';
import { useForm } from '@inertiajs/vue3';
import { BookOpen, Calendar, Clock, MapPin, Plus, User, Video } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    open: boolean;
    courseOfferingId: number;
    campusId: number;
    /** Pre-filled defaults (e.g. from course offering schedule) */
    defaultDate?: string;
    defaultStartTime?: string;
    defaultEndTime?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'session-created': [];
}>();

const api = useApi();

// ---- Form ----
const form = useForm({
    course_offering_id: props.courseOfferingId,
    session_title: '',
    session_description: '',
    session_date: props.defaultDate ?? '',
    start_time: props.defaultStartTime ?? '',
    end_time: props.defaultEndTime ?? '',
    session_type: 'lecture' as 'lecture' | 'tutorial' | 'practical' | 'workshop' | 'seminar' | 'exam',
    delivery_mode: 'in_person' as 'in_person' | 'online' | 'hybrid',
    status: 'scheduled' as 'scheduled' | 'in_progress' | 'completed' | 'cancelled',
    lecture_id: null as number | null,
    room_id: null as number | null,
    attendance_required: true,
    attendance_tracking_enabled: true,
    online_meeting_url: '',
    instructor_notes: '',
});

// ---- Dropdown data ----
const availableRooms = ref<Room[]>([]);
const availableLecturers = ref<Lecture[]>([]);
const isLoadingDropdowns = ref(false);

const loadDropdowns = async () => {
    isLoadingDropdowns.value = true;
    try {
        const [roomsRes, lecturersRes] = await Promise.all([api.get('/api/rooms', { campus_id: props.campusId, status: 'available' }), api.get('/api/lectures', { campus_id: props.campusId, is_active: true })]);
        availableRooms.value = roomsRes.data?.value?.data ?? [];
        availableLecturers.value = lecturersRes.data?.value?.data ?? [];
    } finally {
        isLoadingDropdowns.value = false;
    }
};

// ---- Computed ----
const isOpen = computed({
    get: () => props.open,
    set: (v) => emit('update:open', v),
});

const selectedRoom = computed(() => availableRooms.value.find((r) => r.id === form.room_id));
const selectedLecturer = computed(() => availableLecturers.value.find((l) => l.id === form.lecture_id));

const sessionTypeOptions = [
    { value: 'lecture', label: 'Lecture', icon: BookOpen },
    { value: 'tutorial', label: 'Tutorial', icon: BookOpen },
    { value: 'practical', label: 'Practical', icon: BookOpen },
    { value: 'workshop', label: 'Workshop', icon: BookOpen },
    { value: 'seminar', label: 'Seminar', icon: BookOpen },
    { value: 'exam', label: 'Exam', icon: BookOpen },
];

const deliveryModeOptions = [
    { value: 'in_person', label: 'In Person', icon: MapPin },
    { value: 'online', label: 'Online', icon: Video },
    { value: 'hybrid', label: 'Hybrid', icon: MapPin },
];

// ---- Actions ----
const submit = () => {
    form.post(route('class-sessions.store'), {
        preserveScroll: true,
        only: ['courseOffering'],
        onSuccess: () => {
            emit('session-created');
            handleClose();
        },
    });
};

const handleClose = () => {
    form.reset();
    form.clearErrors();
    emit('update:open', false);
};

watch(
    () => props.open,
    (opened) => {
        if (opened) {
            form.course_offering_id = props.courseOfferingId;
            form.session_date = props.defaultDate ?? '';
            form.start_time = props.defaultStartTime ?? '';
            form.end_time = props.defaultEndTime ?? '';
            loadDropdowns();
        } else {
            form.reset();
            form.clearErrors();
        }
    },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Plus class="h-5 w-5" />
                    Add Class Session
                </DialogTitle>
                <DialogDescription>Create a new session for this course offering.</DialogDescription>
            </DialogHeader>

            <!-- Loading dropdowns -->
            <div v-if="isLoadingDropdowns" class="flex items-center justify-center py-10">
                <div class="border-primary h-5 w-5 animate-spin rounded-full border-2 border-t-transparent" />
                <span class="text-muted-foreground ml-3 text-sm">Loading...</span>
            </div>

            <form v-else class="space-y-5" @submit.prevent="submit">
                <!-- Title + Description -->
                <div class="space-y-3">
                    <div class="space-y-1.5">
                        <Label for="add-title">Session Title <span class="text-destructive">*</span></Label>
                        <Input id="add-title" v-model="form.session_title" placeholder="e.g. Week 1 — Introduction" :class="{ 'border-destructive': form.errors.session_title }" />
                        <InputError :message="form.errors.session_title" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="add-desc">Description</Label>
                        <Textarea id="add-desc" v-model="form.session_description" placeholder="Optional" rows="2" />
                    </div>
                </div>

                <Separator />

                <!-- Date + Time -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5"> <Calendar class="h-3.5 w-3.5" /> Date <span class="text-destructive">*</span> </Label>
                        <Input v-model="form.session_date" type="date" :class="{ 'border-destructive': form.errors.session_date }" />
                        <InputError :message="form.errors.session_date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5"> <Clock class="h-3.5 w-3.5" /> Start <span class="text-destructive">*</span> </Label>
                        <Input v-model="form.start_time" type="time" :class="{ 'border-destructive': form.errors.start_time }" />
                        <InputError :message="form.errors.start_time" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>End <span class="text-destructive">*</span></Label>
                        <Input v-model="form.end_time" type="time" :class="{ 'border-destructive': form.errors.end_time }" />
                        <InputError :message="form.errors.end_time" />
                    </div>
                </div>

                <!-- Type + Delivery + Status -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="space-y-1.5">
                        <Label>Session Type <span class="text-destructive">*</span></Label>
                        <Select v-model="form.session_type">
                            <SelectTrigger :class="{ 'border-destructive': form.errors.session_type }">
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in sessionTypeOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.session_type" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Delivery Mode <span class="text-destructive">*</span></Label>
                        <Select v-model="form.delivery_mode">
                            <SelectTrigger :class="{ 'border-destructive': form.errors.delivery_mode }">
                                <SelectValue placeholder="Mode" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in deliveryModeOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.delivery_mode" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Status</Label>
                        <Select v-model="form.status">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="scheduled">Scheduled</SelectItem>
                                <SelectItem value="in_progress">In Progress</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <Separator />

                <!-- Lecturer + Room -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5"> <User class="h-3.5 w-3.5" /> Lecturer </Label>
                        <Select :model-value="form.lecture_id?.toString() ?? ''" @update:model-value="(v) => (form.lecture_id = v ? Number(v) : null)">
                            <SelectTrigger>
                                <SelectValue placeholder="Select lecturer" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="lec in availableLecturers" :key="lec.id" :value="lec.id.toString()">
                                    {{ lec.display_name }}
                                    <span v-if="lec.department" class="text-muted-foreground ml-1 text-xs">({{ lec.department }})</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="selectedLecturer" class="text-muted-foreground flex items-center gap-1 text-xs">
                            <User class="h-3 w-3" />
                            {{ selectedLecturer.display_name }}
                        </div>
                        <InputError :message="form.errors.lecture_id" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5"> <MapPin class="h-3.5 w-3.5" /> Room </Label>
                        <Select :model-value="form.room_id?.toString() ?? ''" @update:model-value="(v) => (form.room_id = v ? Number(v) : null)">
                            <SelectTrigger>
                                <SelectValue placeholder="Select room" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="room in availableRooms" :key="room.id" :value="room.id.toString()">
                                    <span class="font-medium">{{ room.name }}</span>
                                    <span v-if="room.building" class="text-muted-foreground ml-1.5 text-xs">{{ room.building.name }}</span>
                                    <Badge v-if="room.capacity" variant="outline" class="ml-1.5 px-1 py-0 text-[10px]">
                                        {{ room.capacity }}
                                    </Badge>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="selectedRoom" class="text-muted-foreground flex items-center gap-1 text-xs">
                            <MapPin class="h-3 w-3" />
                            {{ selectedRoom.name }}
                            <span v-if="selectedRoom.capacity">· {{ selectedRoom.capacity }} seats</span>
                        </div>
                        <InputError :message="form.errors.room_id" />
                    </div>
                </div>

                <!-- Online URL (show only if online/hybrid) -->
                <div v-if="form.delivery_mode === 'online' || form.delivery_mode === 'hybrid'" class="space-y-1.5">
                    <Label class="flex items-center gap-1.5"> <Video class="h-3.5 w-3.5" /> Online Meeting URL </Label>
                    <Input v-model="form.online_meeting_url" placeholder="https://..." :class="{ 'border-destructive': form.errors.online_meeting_url }" />
                    <InputError :message="form.errors.online_meeting_url" />
                </div>

                <!-- Attendance toggles -->
                <div class="flex flex-wrap gap-5 pt-1">
                    <div class="flex items-center gap-2">
                        <Checkbox id="add-att-req" v-model:checked="form.attendance_required" />
                        <Label for="add-att-req" class="cursor-pointer font-normal">Attendance required</Label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox id="add-att-track" v-model:checked="form.attendance_tracking_enabled" />
                        <Label for="add-att-track" class="cursor-pointer font-normal">Enable attendance tracking</Label>
                    </div>
                </div>

                <!-- Notes -->
                <div class="space-y-1.5">
                    <Label>Instructor Notes</Label>
                    <Textarea v-model="form.instructor_notes" placeholder="Optional notes" rows="2" />
                </div>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" :disabled="form.processing" @click="handleClose">Cancel</Button>
                <Button :disabled="form.processing || isLoadingDropdowns" @click="submit">
                    <Plus v-if="!form.processing" class="mr-2 h-4 w-4" />
                    <span v-if="form.processing" class="mr-2 h-4 w-4 animate-spin">⟳</span>
                    {{ form.processing ? 'Creating...' : 'Create Session' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
