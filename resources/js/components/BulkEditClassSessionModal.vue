<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { useApi } from '@/composables/useApiRequest';
import type { ClassSession, Lecture, Room } from '@/types/models';
import { router } from '@inertiajs/vue3';
import { Clock, Edit2, MapPin, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    open: boolean;
    selectedSessions: ClassSession[];
    campusId: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'sessions-updated': [];
}>();

const api = useApi();

// ---- Simple reactive form state (no vee-validate needed for optional partial updates) ----
const fields = ref({
    start_time: '',
    end_time: '',
    lecture_id: null as number | null,
    room_id: null as number | null,
});
const isSubmitting = ref(false);

// ---- Dropdowns ----
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

// ---- Helpers ----
const isOpen = computed({
    get: () => props.open,
    set: (v) => emit('update:open', v),
});

const selectedCount = computed(() => props.selectedSessions.length);

const hasChanges = computed(() => {
    const f = fields.value;
    return f.start_time || f.end_time || f.lecture_id || f.room_id;
});

const selectedRoom = computed(() => availableRooms.value.find((r) => r.id === fields.value.room_id));
const selectedLecturer = computed(() => availableLecturers.value.find((l) => l.id === fields.value.lecture_id));

// ---- Submit ----
const submit = async () => {
    if (!hasChanges.value) {
        toast.error('Please fill in at least one field to update');
        return;
    }

    const courseOfferingId = props.selectedSessions[0]?.course_offering_id;
    if (!courseOfferingId) return;

    const updateData: Record<string, any> = {
        session_ids: props.selectedSessions.map((s) => s.id),
    };
    if (fields.value.start_time) updateData.start_time = fields.value.start_time;
    if (fields.value.end_time) updateData.end_time = fields.value.end_time;
    if (fields.value.lecture_id) updateData.lecture_id = fields.value.lecture_id;
    if (fields.value.room_id) updateData.room_id = fields.value.room_id;

    isSubmitting.value = true;
    try {
        const response = await api.post(`/api/course-offerings/${courseOfferingId}/class-sessions/bulk-update`, updateData);

        if (response.data?.value?.success) {
            emit('sessions-updated');
            router.reload({ only: ['courseOffering'] });
            handleClose();
        } else {
            toast.error(response.data?.value?.message || 'Failed to update sessions');
        }
    } catch {
        toast.error('Failed to update sessions');
    } finally {
        isSubmitting.value = false;
    }
};

const handleClose = () => {
    fields.value = { start_time: '', end_time: '', lecture_id: null, room_id: null };
    emit('update:open', false);
};

watch(
    () => props.open,
    (opened) => {
        if (opened) {
            fields.value = { start_time: '', end_time: '', lecture_id: null, room_id: null };
            loadDropdowns();
        }
    },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Edit2 class="h-4 w-4" />
                    Bulk Edit Sessions
                </DialogTitle>
                <DialogDescription>
                    Update
                    <Badge variant="secondary" class="mx-1">{{ selectedCount }}</Badge>
                    selected session(s). Only filled fields will be updated.
                </DialogDescription>
            </DialogHeader>

            <!-- Loading -->
            <div v-if="isLoadingDropdowns" class="flex items-center justify-center py-8">
                <div class="border-primary h-4 w-4 animate-spin rounded-full border-2 border-t-transparent" />
                <span class="text-muted-foreground ml-2 text-sm">Loading...</span>
            </div>

            <div v-else class="space-y-4">
                <!-- Time range -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5"> <Clock class="h-3.5 w-3.5" /> Start Time </Label>
                        <Input v-model="fields.start_time" type="time" placeholder="Leave blank to keep" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>End Time</Label>
                        <Input v-model="fields.end_time" type="time" placeholder="Leave blank to keep" />
                    </div>
                </div>

                <Separator />

                <!-- Lecturer -->
                <div class="space-y-1.5">
                    <Label class="flex items-center gap-1.5">
                        <User class="h-3.5 w-3.5" /> Lecturer
                        <span class="text-muted-foreground text-xs font-normal">(optional — leave blank to keep)</span>
                    </Label>
                    <Select :model-value="fields.lecture_id?.toString() ?? ''" @update:model-value="(v) => (fields.lecture_id = v ? Number(v) : null)">
                        <SelectTrigger>
                            <SelectValue placeholder="No change" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="lec in availableLecturers" :key="lec.id" :value="lec.id.toString()">
                                {{ lec.display_name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div v-if="selectedLecturer" class="text-muted-foreground text-xs">→ {{ selectedLecturer.display_name }}</div>
                </div>

                <!-- Room -->
                <div class="space-y-1.5">
                    <Label class="flex items-center gap-1.5">
                        <MapPin class="h-3.5 w-3.5" /> Room
                        <span class="text-muted-foreground text-xs font-normal">(optional)</span>
                    </Label>
                    <Select :model-value="fields.room_id?.toString() ?? ''" @update:model-value="(v) => (fields.room_id = v ? Number(v) : null)">
                        <SelectTrigger>
                            <SelectValue placeholder="No change" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="room in availableRooms" :key="room.id" :value="room.id.toString()">
                                {{ room.name }}
                                <span v-if="room.building" class="text-muted-foreground ml-1 text-xs">{{ room.building.name }}</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div v-if="selectedRoom" class="text-muted-foreground flex items-center gap-1 text-xs">
                        <MapPin class="h-3 w-3" /> {{ selectedRoom.name }}
                        <span v-if="selectedRoom.capacity">· {{ selectedRoom.capacity }} seats</span>
                    </div>
                </div>

                <!-- Summary of what will change -->
                <div v-if="hasChanges" class="bg-muted/50 space-y-1 rounded-md p-3 text-xs">
                    <p class="font-medium">Will update {{ selectedCount }} session(s):</p>
                    <p v-if="fields.start_time">• Start time → {{ fields.start_time }}</p>
                    <p v-if="fields.end_time">• End time → {{ fields.end_time }}</p>
                    <p v-if="selectedLecturer">• Lecturer → {{ selectedLecturer.display_name }}</p>
                    <p v-if="selectedRoom">• Room → {{ selectedRoom.name }}</p>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" :disabled="isSubmitting" @click="handleClose">Cancel</Button>
                <Button :disabled="isSubmitting || isLoadingDropdowns || !hasChanges" @click="submit">
                    {{ isSubmitting ? 'Updating...' : `Update ${selectedCount} Session(s)` }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
