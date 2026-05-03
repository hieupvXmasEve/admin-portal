<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { useApi } from '@/composables/useApiRequest';
import type { ClassSession, Lecture, Room } from '@/types/models';
import { useForm } from '@inertiajs/vue3';
import { Calendar, Clock, MapPin, Pencil, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    open: boolean;
    session: ClassSession | null;
    campusId: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'session-updated': [];
}>();

const api = useApi();

// ---- Form — seeded from current session when modal opens ----
const form = useForm({
    // required by web update validation
    course_offering_id: 0,
    session_type: 'lecture',
    delivery_mode: 'in_person',
    status: 'scheduled',
    // editable
    session_title: '',
    session_date: '',
    start_time: '',
    end_time: '',
    lecture_id: null as number | null,
    room_id: null as number | null,
    // passthrough (unchanged)
    session_description: '',
    attendance_required: false,
    attendance_tracking_enabled: false,
    online_meeting_url: '',
    instructor_notes: '',
    learning_objectives: [] as any[],
    required_materials: [] as any[],
    topics_covered: [] as any[],
});

// ---- Dropdown data ----
const availableRooms = ref<Room[]>([]);
const availableLecturers = ref<Lecture[]>([]);
const isLoadingDropdowns = ref(false);

const loadDropdowns = async () => {
    if (!props.session) return;
    isLoadingDropdowns.value = true;
    try {
        const [roomsRes, lecturersRes] = await Promise.all([api.get('/api/rooms', { campus_id: props.campusId, status: 'available' }), api.get('/api/lectures', { campus_id: props.campusId, is_active: true })]);
        availableRooms.value = roomsRes.data?.value?.data ?? [];
        availableLecturers.value = lecturersRes.data?.value?.data ?? [];
    } finally {
        isLoadingDropdowns.value = false;
    }
};

// ---- Helpers ----
const isOpen = computed({
    get: () => props.open,
    set: (v) => emit('update:open', v),
});

const selectedRoom = computed(() => availableRooms.value.find((r) => r.id === form.room_id));
const selectedLecturer = computed(() => availableLecturers.value.find((l) => l.id === form.lecture_id));

const seedForm = (s: ClassSession) => {
    form.course_offering_id = s.course_offering_id;
    form.session_type = s.session_type || 'lecture';
    form.delivery_mode = s.delivery_mode || 'in_person';
    form.status = s.status || 'scheduled';
    form.session_title = s.session_title || '';
    form.session_date = s.session_date || '';
    form.start_time = s.start_time || '';
    form.end_time = s.end_time || '';
    form.lecture_id = s.lecture_id ?? null;
    form.room_id = s.room_id ?? null;
    form.session_description = s.session_description || '';
    form.attendance_required = s.attendance_required ?? false;
    form.attendance_tracking_enabled = s.attendance_tracking_enabled ?? false;
    form.online_meeting_url = s.online_meeting_url || '';
    form.instructor_notes = s.instructor_notes || '';
    form.learning_objectives = s.learning_objectives ?? [];
    form.required_materials = s.required_materials ?? [];
    form.topics_covered = s.topics_covered ?? [];
};

// ---- Submit ----
const submit = () => {
    if (!props.session) return;
    form.put(route('class-sessions.update', props.session.id), {
        preserveScroll: true,
        only: ['courseOffering'],
        onSuccess: () => {
            emit('session-updated');
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
    () => [props.open, props.session] as const,
    ([opened, session]) => {
        if (opened && session) {
            seedForm(session);
            loadDropdowns();
        } else if (!opened) {
            form.reset();
            form.clearErrors();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Pencil class="h-4 w-4" />
                    Quick Edit Session
                </DialogTitle>
                <DialogDescription v-if="session">
                    <span class="font-medium">{{ session.session_title }}</span>
                </DialogDescription>
            </DialogHeader>

            <!-- Loading -->
            <div v-if="isLoadingDropdowns" class="flex items-center justify-center py-8">
                <div class="border-primary h-4 w-4 animate-spin rounded-full border-2 border-t-transparent" />
                <span class="text-muted-foreground ml-2 text-sm">Loading...</span>
            </div>

            <form v-else-if="session" class="space-y-4" @submit.prevent="submit">
                <!-- Title -->
                <div class="space-y-1.5">
                    <Label>Session Title <span class="text-destructive">*</span></Label>
                    <Input v-model="form.session_title" placeholder="Session title" :class="{ 'border-destructive': form.errors.session_title }" />
                    <InputError :message="form.errors.session_title" />
                </div>

                <Separator />

                <!-- Date + Time -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5 text-xs"> <Calendar class="h-3 w-3" /> Date </Label>
                        <Input v-model="form.session_date" type="date" class="text-sm" :class="{ 'border-destructive': form.errors.session_date }" />
                        <InputError :message="form.errors.session_date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5 text-xs"> <Clock class="h-3 w-3" /> Start </Label>
                        <Input v-model="form.start_time" type="time" class="text-sm" :class="{ 'border-destructive': form.errors.start_time }" />
                        <InputError :message="form.errors.start_time" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs">End</Label>
                        <Input v-model="form.end_time" type="time" class="text-sm" :class="{ 'border-destructive': form.errors.end_time }" />
                        <InputError :message="form.errors.end_time" />
                    </div>
                </div>

                <Separator />

                <!-- Lecturer -->
                <div class="space-y-1.5">
                    <Label class="flex items-center gap-1.5"> <User class="h-3.5 w-3.5" /> Lecturer </Label>
                    <Select :model-value="form.lecture_id?.toString() ?? ''" @update:model-value="(v) => (form.lecture_id = v ? Number(v) : null)">
                        <SelectTrigger>
                            <SelectValue placeholder="Select lecturer" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="lec in availableLecturers" :key="lec.id" :value="lec.id.toString()">
                                {{ lec.display_name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div v-if="selectedLecturer" class="text-muted-foreground text-xs">{{ selectedLecturer.display_name }}</div>
                    <InputError :message="form.errors.lecture_id" />
                </div>

                <!-- Room -->
                <div class="space-y-1.5">
                    <Label class="flex items-center gap-1.5"> <MapPin class="h-3.5 w-3.5" /> Room </Label>
                    <Select :model-value="form.room_id?.toString() ?? ''" @update:model-value="(v) => (form.room_id = v ? Number(v) : null)">
                        <SelectTrigger>
                            <SelectValue placeholder="Select room" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="room in availableRooms" :key="room.id" :value="room.id.toString()">
                                {{ room.name }}
                                <span v-if="room.building" class="text-muted-foreground ml-1 text-xs">{{ room.building.name }}</span>
                                <Badge v-if="room.capacity" variant="outline" class="ml-1 px-1 py-0 text-[10px]">{{ room.capacity }}</Badge>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div v-if="selectedRoom" class="text-muted-foreground flex items-center gap-1 text-xs">
                        <MapPin class="h-3 w-3" /> {{ selectedRoom.name }}
                        <span v-if="selectedRoom.capacity">· {{ selectedRoom.capacity }} seats</span>
                    </div>
                    <InputError :message="form.errors.room_id" />
                </div>
            </form>

            <DialogFooter class="gap-2">
                <Button variant="outline" :disabled="form.processing" @click="handleClose">Cancel</Button>
                <Button :disabled="form.processing || isLoadingDropdowns || !session" @click="submit">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
