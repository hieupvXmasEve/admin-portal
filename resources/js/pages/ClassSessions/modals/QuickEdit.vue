<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import type { ClassSession, Lecture, Room } from '@/types/models';
import { useForm } from '@inertiajs/vue3';
import { Modal } from '@inertiaui/modal-vue';
import { Calendar, Clock, MapPin, Pencil, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    session: ClassSession;
    rooms: Room[];
    lecturers: Lecture[];
}

const props = defineProps<Props>();
const modalRef = ref<InstanceType<typeof Modal> | null>(null);

// Seed form from session — pass ALL required fields so backend validation passes
const form = useForm({
    // Required passthrough (unchanged)
    course_offering_id: props.session.course_offering_id,
    session_type: props.session.session_type || 'lecture',
    delivery_mode: props.session.delivery_mode || 'in_person',
    status: props.session.status || 'scheduled',
    session_description: props.session.session_description || '',
    attendance_required: props.session.attendance_required ?? false,
    attendance_tracking_enabled: props.session.attendance_tracking_enabled ?? false,
    online_meeting_url: props.session.online_meeting_url || '',
    instructor_notes: props.session.instructor_notes || '',
    learning_objectives: (props.session.learning_objectives as any[]) ?? [],
    required_materials: (props.session.required_materials as any[]) ?? [],
    topics_covered: (props.session.topics_covered as any[]) ?? [],
    // Editable
    session_title: props.session.session_title || '',
    session_date: props.session.session_date || '',
    start_time: props.session.start_time || '',
    end_time: props.session.end_time || '',
    lecture_id: props.session.lecture_id ?? null,
    room_id: props.session.room_id ?? null,
});

const selectedRoom = computed(() => props.rooms.find((r) => r.id === form.room_id));
const selectedLecturer = computed(() => props.lecturers.find((l) => l.id === form.lecture_id));

const submit = () => {
    form.put(route('class-sessions.update', props.session.id), {
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
                    <Pencil class="h-4 w-4" />
                    Quick Edit Session
                </h2>
                <p class="text-muted-foreground mt-1 text-sm">{{ session.session_title }}</p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
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
                            <SelectItem v-for="lec in lecturers" :key="lec.id" :value="lec.id.toString()">
                                {{ lec.display_name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="selectedLecturer" class="text-muted-foreground text-xs">→ {{ selectedLecturer.display_name }}</p>
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
                            <SelectItem v-for="room in rooms" :key="room.id" :value="room.id.toString()">
                                {{ room.name }}
                                <span v-if="room.building" class="text-muted-foreground ml-1 text-xs">{{ room.building.name }}</span>
                                <Badge v-if="room.capacity" variant="outline" class="ml-1 px-1 py-0 text-[10px]">{{ room.capacity }}</Badge>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="selectedRoom" class="text-muted-foreground flex items-center gap-1 text-xs">
                        <MapPin class="h-3 w-3" /> {{ selectedRoom.name }}<span v-if="selectedRoom.capacity"> · {{ selectedRoom.capacity }} seats</span>
                    </p>
                    <InputError :message="form.errors.room_id" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" @click="modalRef?.close()">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </div>
    </Modal>
</template>
