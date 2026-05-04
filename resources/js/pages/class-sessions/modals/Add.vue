<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import TimePicker from '@/components/ui/TimePicker.vue';
import type { Lecture, Room } from '@/types/models';
import { useForm } from '@inertiajs/vue3';
import { Modal } from '@inertiaui/modal-vue';
import { BookOpen, Calendar, Clock, MapPin, User, Video } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Props {
    courseOffering: {
        id: number;
        course_code: string | null;
        course_title: string | null;
        campus_id: number;
        schedule_time_start: string | null;
        schedule_time_end: string | null;
        syllabus_template: { total_sessions: number } | null;
        class_sessions_count: number;
    };
    rooms: Room[];
    lecturers: Lecture[];
}

const props = defineProps<Props>();
const modalRef = ref<InstanceType<typeof Modal> | null>(null);
const modalContentRef = ref<HTMLElement | null>(null);

const form = useForm({
    course_offering_id: props.courseOffering.id,
    session_title: '',
    session_description: '',
    session_date: '',
    start_time: props.courseOffering.schedule_time_start ?? '',
    end_time: props.courseOffering.schedule_time_end ?? '',
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

const selectedRoom = computed(() => props.rooms.find((r) => r.id === form.room_id));
const selectedLecturer = computed(() => props.lecturers.find((l) => l.id === form.lecture_id));
const atLimit = computed(() => {
    const limit = props.courseOffering.syllabus_template?.total_sessions;
    return limit ? props.courseOffering.class_sessions_count >= limit : false;
});

const sessionTypeOptions = [
    { value: 'lecture', label: 'Lecture' },
    { value: 'tutorial', label: 'Tutorial' },
    { value: 'practical', label: 'Practical' },
    { value: 'workshop', label: 'Workshop' },
    { value: 'seminar', label: 'Seminar' },
    { value: 'exam', label: 'Exam' },
];

const submit = () => {
    form.post(route('class-sessions.store'), {
        preserveScroll: true,
        only: ['courseOffering'],
        onSuccess: () => {
            modalRef.value?.close();
            form.reset();
        },
    });
};
</script>

<template>
    <Modal ref="modalRef" max-width="2xl">
        <div ref="modalContentRef" class="p-6">
            <div class="mb-5">
                <h2 class="text-lg font-semibold">Add Class Session</h2>
                <p class="text-muted-foreground text-sm">
                    {{ courseOffering.course_code }} — {{ courseOffering.course_title }}
                    <span v-if="courseOffering.syllabus_template?.total_sessions" class="ml-2">
                        <Badge variant="outline" class="text-xs"> {{ courseOffering.class_sessions_count }}/{{ courseOffering.syllabus_template.total_sessions }} sessions </Badge>
                    </span>
                </p>
                <p v-if="atLimit" class="mt-2 text-sm text-orange-600">Session limit reached. Cannot add more sessions.</p>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <!-- Title + Description -->
                <div class="space-y-3">
                    <div class="space-y-1.5">
                        <Label for="add-title">Session Title <span class="text-destructive">*</span></Label>
                        <Input id="add-title" v-model="form.session_title" placeholder="e.g. Week 1 — Introduction" :class="{ 'border-destructive': form.errors.session_title }" :disabled="atLimit" />
                        <InputError :message="form.errors.session_title" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Description</Label>
                        <Textarea v-model="form.session_description" placeholder="Optional" rows="2" :disabled="atLimit" />
                    </div>
                </div>

                <Separator />

                <!-- Date + Time -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5">
                            <Calendar class="h-3.5 w-3.5" />
                            Date <span class="text-destructive">*</span>
                        </Label>
                        <DatePicker
                            v-model="form.session_date"
                            :disabled="atLimit"
                            :class="{ 'border-destructive': form.errors.session_date }"
                            :portal-to="modalContentRef ?? undefined"
                        />
                        <InputError :message="form.errors.session_date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5">
                            <Clock class="h-3.5 w-3.5" />
                            Start <span class="text-destructive">*</span>
                        </Label>
                        <TimePicker
                            v-model="form.start_time"
                            :disabled="atLimit"
                            :class="{ 'border-destructive': form.errors.start_time }"
                        />
                        <InputError :message="form.errors.start_time" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5">
                            <Clock class="h-3.5 w-3.5" />
                            End <span class="text-destructive">*</span>
                        </Label>
                        <TimePicker
                            v-model="form.end_time"
                            :disabled="atLimit"
                            :class="{ 'border-destructive': form.errors.end_time }"
                        />
                        <InputError :message="form.errors.end_time" />
                    </div>
                </div>

                <!-- Type + Mode + Status -->
                <div class="grid grid-cols-3 gap-3">
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5">
                            <BookOpen class="h-3.5 w-3.5" />
                            Type <span class="text-destructive">*</span>
                        </Label>
                        <Select v-model="form.session_type" :disabled="atLimit">
                            <SelectTrigger :class="{ 'border-destructive': form.errors.session_type }">
                                <SelectValue />
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
                        <Label>Delivery <span class="text-destructive">*</span></Label>
                        <Select v-model="form.delivery_mode" :disabled="atLimit">
                            <SelectTrigger :class="{ 'border-destructive': form.errors.delivery_mode }">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="in_person">
                                    <span class="flex items-center gap-1.5"><MapPin class="h-3.5 w-3.5" /> In Person</span>
                                </SelectItem>
                                <SelectItem value="online">
                                    <span class="flex items-center gap-1.5"><Video class="h-3.5 w-3.5" /> Online</span>
                                </SelectItem>
                                <SelectItem value="hybrid">
                                    <span class="flex items-center gap-1.5"><MapPin class="h-3.5 w-3.5" /> Hybrid</span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.delivery_mode" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Status</Label>
                        <Select v-model="form.status" :disabled="atLimit">
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
                        <Label class="flex items-center gap-1.5">
                            <User class="h-3.5 w-3.5" />
                            Lecturer
                        </Label>
                        <Select :model-value="form.lecture_id?.toString() ?? ''" :disabled="atLimit" @update:model-value="(v) => (form.lecture_id = v ? Number(v) : null)">
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
                    <div class="space-y-1.5">
                        <Label class="flex items-center gap-1.5">
                            <MapPin class="h-3.5 w-3.5" />
                            Room
                        </Label>
                        <Select :model-value="form.room_id?.toString() ?? ''" :disabled="atLimit" @update:model-value="(v) => (form.room_id = v ? Number(v) : null)">
                            <SelectTrigger>
                                <SelectValue placeholder="Select room" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="room in rooms" :key="room.id" :value="room.id.toString()">
                                    <span class="font-medium">{{ room.name }}</span>
                                    <span v-if="room.building" class="text-muted-foreground ml-1.5 text-xs">{{ room.building.name }}</span>
                                    <Badge v-if="room.capacity" variant="outline" class="ml-1.5 px-1 py-0 text-[10px]">{{ room.capacity }}</Badge>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="selectedRoom" class="text-muted-foreground text-xs">
                            → {{ selectedRoom.name }}<span v-if="selectedRoom.capacity"> · {{ selectedRoom.capacity }} seats</span>
                        </p>
                        <InputError :message="form.errors.room_id" />
                    </div>
                </div>

                <!-- Online URL (only for online/hybrid) -->
                <div v-if="form.delivery_mode === 'online' || form.delivery_mode === 'hybrid'" class="space-y-1.5">
                    <Label class="flex items-center gap-1.5">
                        <Video class="h-3.5 w-3.5" />
                        Online Meeting URL
                    </Label>
                    <Input v-model="form.online_meeting_url" placeholder="https://..." :class="{ 'border-destructive': form.errors.online_meeting_url }" />
                    <InputError :message="form.errors.online_meeting_url" />
                </div>

                <!-- Attendance -->
                <div class="flex flex-wrap gap-5 pt-1">
                    <div class="flex items-center gap-2">
                        <Checkbox id="att-req" v-model:checked="form.attendance_required" :disabled="atLimit" />
                        <Label for="att-req" class="cursor-pointer font-normal">Attendance required</Label>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox id="att-track" v-model:checked="form.attendance_tracking_enabled" :disabled="atLimit" />
                        <Label for="att-track" class="cursor-pointer font-normal">Enable attendance tracking</Label>
                    </div>
                </div>

                <!-- Notes -->
                <div class="space-y-1.5">
                    <Label>Instructor Notes</Label>
                    <Textarea v-model="form.instructor_notes" placeholder="Optional" rows="2" :disabled="atLimit" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" @click="modalRef?.close()">Cancel</Button>
                    <Button type="submit" :disabled="form.processing || atLimit">
                        {{ form.processing ? 'Creating...' : 'Create Session' }}
                    </Button>
                </div>
            </form>
        </div>
    </Modal>
</template>
