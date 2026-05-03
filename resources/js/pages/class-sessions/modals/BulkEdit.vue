<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import type { ClassSession, Lecture, Room } from '@/types/models';
import { useForm } from '@inertiajs/vue3';
import { Modal } from '@inertiaui/modal-vue';
import { Clock, Edit2, MapPin, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    courseOffering: { id: number };
    sessions: ClassSession[];
    rooms: Room[];
    lecturers: Lecture[];
}

const props = defineProps<Props>();
const modalRef = ref<InstanceType<typeof Modal> | null>(null);

// Partial update form — only filled fields are sent
// Backend skips null/empty values
const form = useForm({
    session_ids: props.sessions.map((s) => s.id),
    start_time: '',
    end_time: '',
    lecture_id: null as number | null,
    room_id: null as number | null,
});

const hasChanges = computed(() => form.start_time || form.end_time || form.lecture_id || form.room_id);
const selectedRoom = computed(() => props.rooms.find((r) => r.id === form.room_id));
const selectedLecturer = computed(() => props.lecturers.find((l) => l.id === form.lecture_id));

const submit = () => {
    // Build payload with only non-empty values — backend skips undefined fields
    form.transform((data) => {
        const payload: Record<string, any> = { session_ids: data.session_ids };
        if (data.start_time) payload.start_time = data.start_time;
        if (data.end_time) payload.end_time = data.end_time;
        if (data.lecture_id) payload.lecture_id = data.lecture_id;
        if (data.room_id) payload.room_id = data.room_id;
        return payload;
    }).post(route('course-offerings.bulk-update-class-sessions', props.courseOffering.id), {
        preserveScroll: true,
        only: ['courseOffering'],
        onSuccess: () => {
            modalRef.value?.close();
        },
    });
};
</script>

<template>
    <Modal ref="modalRef" max-width="md">
        <div class="p-6">
            <div class="mb-5">
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <Edit2 class="h-4 w-4" />
                    Bulk Edit Sessions
                </h2>
                <p class="text-muted-foreground mt-1 text-sm">
                    Update
                    <Badge variant="secondary" class="mx-1">{{ sessions.length }}</Badge>
                    selected session(s). Only filled fields will change.
                </p>
            </div>

            <!-- Session list preview -->
            <div class="bg-muted/40 mb-5 max-h-32 overflow-y-auto rounded-md p-3 text-xs">
                <div v-for="s in sessions" :key="s.id" class="py-0.5">{{ s.session_title }} — {{ s.session_date }}</div>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- Time range -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5">
                            <Clock class="h-3.5 w-3.5" /> Start Time
                            <span class="text-muted-foreground text-xs font-normal">(optional)</span>
                        </Label>
                        <Input v-model="form.start_time" type="time" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>End Time <span class="text-muted-foreground text-xs font-normal">(optional)</span></Label>
                        <Input v-model="form.end_time" type="time" />
                    </div>
                </div>

                <Separator />

                <!-- Lecturer -->
                <div class="space-y-1.5">
                    <Label class="flex items-center gap-1.5">
                        <User class="h-3.5 w-3.5" /> Lecturer
                        <span class="text-muted-foreground text-xs font-normal">(leave blank = no change)</span>
                    </Label>
                    <Select :model-value="form.lecture_id?.toString() ?? ''" @update:model-value="(v) => (form.lecture_id = v ? Number(v) : null)">
                        <SelectTrigger>
                            <SelectValue placeholder="No change" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="lec in lecturers" :key="lec.id" :value="lec.id.toString()">
                                {{ lec.display_name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="selectedLecturer" class="text-muted-foreground text-xs">→ {{ selectedLecturer.display_name }}</p>
                </div>

                <!-- Room -->
                <div class="space-y-1.5">
                    <Label class="flex items-center gap-1.5">
                        <MapPin class="h-3.5 w-3.5" /> Room
                        <span class="text-muted-foreground text-xs font-normal">(leave blank = no change)</span>
                    </Label>
                    <Select :model-value="form.room_id?.toString() ?? ''" @update:model-value="(v) => (form.room_id = v ? Number(v) : null)">
                        <SelectTrigger>
                            <SelectValue placeholder="No change" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="room in rooms" :key="room.id" :value="room.id.toString()">
                                {{ room.name }}
                                <span v-if="room.building" class="text-muted-foreground ml-1 text-xs">{{ room.building.name }}</span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="selectedRoom" class="text-muted-foreground flex items-center gap-1 text-xs">
                        <MapPin class="h-3 w-3" /> {{ selectedRoom.name }}<span v-if="selectedRoom.capacity"> · {{ selectedRoom.capacity }} seats</span>
                    </p>
                </div>

                <!-- Change summary -->
                <div v-if="hasChanges" class="bg-muted/50 space-y-1 rounded-md p-3 text-xs">
                    <p class="font-medium">Will update {{ sessions.length }} session(s):</p>
                    <p v-if="form.start_time">• Start → {{ form.start_time }}</p>
                    <p v-if="form.end_time">• End → {{ form.end_time }}</p>
                    <p v-if="selectedLecturer">• Lecturer → {{ selectedLecturer.display_name }}</p>
                    <p v-if="selectedRoom">• Room → {{ selectedRoom.name }}</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" @click="modalRef?.close()">Cancel</Button>
                    <Button type="submit" :disabled="form.processing || !hasChanges">
                        {{ form.processing ? 'Updating...' : `Update ${sessions.length} Session(s)` }}
                    </Button>
                </div>
            </form>
        </div>
    </Modal>
</template>
