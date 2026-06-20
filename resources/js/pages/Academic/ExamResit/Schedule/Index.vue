<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import TimePicker from '@/components/ui/TimePicker.vue';
import type { PaginatedResponse } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, DoorOpen, Plus, UserPlus, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import { route } from 'ziggy-js';

interface SlotSession {
    id: number;
    unit: { id: number; code: string; name: string } | null;
    status: string;
    expected_candidates: number;
    actual_candidates: number;
}

interface SlotInvigilator {
    id: number;
    role: string;
    lecture: { id: number; name: string } | null;
}

interface SlotRow {
    id: number;
    exam_date: string | null;
    start_time: string | null;
    end_time: string | null;
    status: string;
    room: { id: number; name: string; code: string } | null;
    seats_total: number;
    seats_used: number;
    seats_assigned: number;
    sessions: SlotSession[];
    invigilators: SlotInvigilator[];
}

interface Props {
    campus_id: number | null;
    slots: PaginatedResponse<SlotRow>;
    rooms: { id: number; name: string; code: string; capacity: number }[];
    units: { id: number; code: string; name: string }[];
    lecturers: { id: number; first_name: string; last_name: string }[];
    semesters: { id: number; name: string; code: string }[];
}

const props = defineProps<Props>();

const showSlotForm = ref(false);
const openSessionSlotId = ref<number | null>(null);
const openInvigilatorSlotId = ref<number | null>(null);

const lecturerName = (l: { first_name: string; last_name: string }): string => `${l.first_name} ${l.last_name}`.trim();
const formatDate = (value: string | null): string => (value ? new Date(value).toLocaleDateString('vi-VN') : '—');

// --- Room slot ---
const slotForm = useForm({
    campus_id: props.campus_id,
    room_id: null as number | null,
    exam_date: '',
    start_time: '',
    end_time: '',
    capacity: null as number | null,
    notes: '',
});
const submitSlot = () => {
    slotForm.post(route('academic.exam-schedule.room-slots.store'), {
        preserveScroll: true,
        onSuccess: () => {
            slotForm.reset();
            showSlotForm.value = false;
        },
    });
};

// --- Session ---
const sessionForm = useForm({
    exam_room_slot_id: null as number | null,
    unit_id: null as number | null,
    semester_id: null as number | null,
    expected_candidates: 1 as number | null,
    notes: '',
});
const openSessionForm = (slot: SlotRow) => {
    openSessionSlotId.value = openSessionSlotId.value === slot.id ? null : slot.id;
    openInvigilatorSlotId.value = null;
    sessionForm.reset();
    sessionForm.exam_room_slot_id = slot.id;
};
const submitSession = () => {
    sessionForm.post(route('academic.exam-schedule.sessions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            sessionForm.reset();
            openSessionSlotId.value = null;
        },
    });
};

// --- Invigilator ---
const invigilatorForm = useForm({
    exam_room_slot_id: null as number | null,
    lecture_id: null as number | null,
    role: 'assistant',
    notes: '',
});
const openInvigilatorForm = (slot: SlotRow) => {
    openInvigilatorSlotId.value = openInvigilatorSlotId.value === slot.id ? null : slot.id;
    openSessionSlotId.value = null;
    invigilatorForm.reset();
    invigilatorForm.exam_room_slot_id = slot.id;
};
const submitInvigilator = () => {
    invigilatorForm.post(route('academic.exam-schedule.invigilators.store'), {
        preserveScroll: true,
        onSuccess: () => {
            invigilatorForm.reset();
            openInvigilatorSlotId.value = null;
        },
    });
};
</script>

<template>
    <Head title="Lịch thi lại" />
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Lịch thi lại</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Tạo ca phòng thi, ca thi theo môn và phân công cán bộ coi thi.</p>
        </div>
        <Button class="gap-2" @click="showSlotForm = !showSlotForm"> <Plus class="h-4 w-4" /> Tạo ca phòng thi </Button>
    </div>

    <!-- Create room slot (inline) -->
    <Card v-if="showSlotForm" class="mt-4">
        <CardHeader><CardTitle class="text-base">Ca phòng thi mới</CardTitle></CardHeader>
        <CardContent>
            <form @submit.prevent="submitSlot" class="grid gap-4 md:grid-cols-3">
                <div class="space-y-1.5">
                    <Label>Phòng <span class="text-destructive">*</span></Label>
                    <Select :model-value="slotForm.room_id?.toString() ?? undefined" @update:model-value="(v) => (slotForm.room_id = v ? Number(v) : null)">
                        <SelectTrigger><SelectValue placeholder="Chọn phòng" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="r in rooms" :key="r.id" :value="r.id.toString()">{{ r.name }} ({{ r.capacity }} chỗ)</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="slotForm.errors.room_id" class="text-destructive text-xs">{{ slotForm.errors.room_id }}</p>
                </div>
                <div class="space-y-1.5">
                    <Label>Ngày thi <span class="text-destructive">*</span></Label>
                    <DatePicker v-model="slotForm.exam_date" placeholder="Chọn ngày" />
                    <p v-if="slotForm.errors.exam_date" class="text-destructive text-xs">{{ slotForm.errors.exam_date }}</p>
                </div>
                <div class="space-y-1.5">
                    <Label>Sức chứa (mặc định = phòng)</Label>
                    <Input v-model.number="slotForm.capacity" type="number" min="1" placeholder="Theo phòng" />
                </div>
                <div class="space-y-1.5">
                    <Label>Giờ bắt đầu <span class="text-destructive">*</span></Label>
                    <TimePicker v-model="slotForm.start_time" />
                    <p v-if="slotForm.errors.start_time" class="text-destructive text-xs">{{ slotForm.errors.start_time }}</p>
                </div>
                <div class="space-y-1.5">
                    <Label>Giờ kết thúc <span class="text-destructive">*</span></Label>
                    <TimePicker v-model="slotForm.end_time" />
                    <p v-if="slotForm.errors.end_time" class="text-destructive text-xs">{{ slotForm.errors.end_time }}</p>
                </div>
                <div class="flex items-end justify-end gap-2">
                    <Button type="button" variant="outline" @click="showSlotForm = false">Đóng</Button>
                    <Button type="submit" :disabled="slotForm.processing || !slotForm.room_id">Tạo ca</Button>
                </div>
            </form>
        </CardContent>
    </Card>

    <!-- Slot list -->
    <div class="mt-6 space-y-4">
        <div v-if="slots.data.length === 0" class="text-muted-foreground rounded-md border border-dashed py-12 text-center text-sm">Chưa có ca phòng thi nào.</div>

        <Card v-for="slot in slots.data" :key="slot.id">
            <CardHeader>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <DoorOpen class="h-4 w-4" />
                        {{ slot.room?.name ?? 'Phòng?' }} · {{ formatDate(slot.exam_date) }} · {{ slot.start_time }}–{{ slot.end_time }}
                    </CardTitle>
                    <div class="flex items-center gap-2">
                        <Badge variant="info">{{ slot.seats_used }}/{{ slot.seats_total }} chỗ xếp</Badge>
                        <Badge variant="secondary">{{ slot.seats_assigned }} đã gán</Badge>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Sessions -->
                <div>
                    <div class="text-muted-foreground mb-1 text-xs font-medium uppercase">Ca thi theo môn</div>
                    <div v-if="slot.sessions.length === 0" class="text-muted-foreground text-sm">Chưa có ca thi.</div>
                    <ul v-else class="divide-y rounded-md border">
                        <li v-for="s in slot.sessions" :key="s.id" class="flex items-center justify-between px-3 py-2 text-sm">
                            <span class="font-mono"
                                >{{ s.unit?.code ?? '—' }}<span class="text-muted-foreground ml-2 font-sans">{{ s.unit?.name }}</span></span
                            >
                            <Badge variant="outline">{{ s.actual_candidates }}/{{ s.expected_candidates }}</Badge>
                        </li>
                    </ul>
                </div>

                <!-- Invigilators -->
                <div>
                    <div class="text-muted-foreground mb-1 text-xs font-medium uppercase">Cán bộ coi thi</div>
                    <div v-if="slot.invigilators.length === 0" class="text-muted-foreground text-sm">Chưa phân công.</div>
                    <div v-else class="flex flex-wrap gap-2">
                        <Badge v-for="inv in slot.invigilators" :key="inv.id" variant="secondary" class="gap-1"> <Users class="h-3 w-3" /> {{ inv.lecture?.name ?? '—' }} · {{ inv.role }} </Badge>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" class="gap-1" @click="openSessionForm(slot)"><CalendarPlus class="h-4 w-4" /> Thêm ca thi</Button>
                    <Button variant="outline" size="sm" class="gap-1" @click="openInvigilatorForm(slot)"><UserPlus class="h-4 w-4" /> Phân công coi thi</Button>
                </div>

                <!-- Add session (inline) -->
                <form v-if="openSessionSlotId === slot.id" @submit.prevent="submitSession" class="bg-muted/30 grid gap-3 rounded-md border p-3 md:grid-cols-4">
                    <div class="space-y-1.5">
                        <Label>Môn học <span class="text-destructive">*</span></Label>
                        <Select :model-value="sessionForm.unit_id?.toString() ?? undefined" @update:model-value="(v) => (sessionForm.unit_id = v ? Number(v) : null)">
                            <SelectTrigger><SelectValue placeholder="Chọn môn" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="u in units" :key="u.id" :value="u.id.toString()">{{ u.code }} — {{ u.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="sessionForm.errors.unit_id" class="text-destructive text-xs">{{ sessionForm.errors.unit_id }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Học kỳ <span class="text-destructive">*</span></Label>
                        <Select :model-value="sessionForm.semester_id?.toString() ?? undefined" @update:model-value="(v) => (sessionForm.semester_id = v ? Number(v) : null)">
                            <SelectTrigger><SelectValue placeholder="Chọn học kỳ" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sem in semesters" :key="sem.id" :value="sem.id.toString()">{{ sem.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="sessionForm.errors.semester_id" class="text-destructive text-xs">{{ sessionForm.errors.semester_id }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Số chỗ dự kiến</Label>
                        <Input v-model.number="sessionForm.expected_candidates" type="number" min="1" />
                        <p v-if="sessionForm.errors.expected_candidates" class="text-destructive text-xs">{{ sessionForm.errors.expected_candidates }}</p>
                    </div>
                    <div class="flex items-end justify-end gap-2">
                        <Button type="submit" size="sm" :disabled="sessionForm.processing || !sessionForm.unit_id || !sessionForm.semester_id">Tạo ca thi</Button>
                    </div>
                    <p v-if="sessionForm.errors.exam_room_slot_id" class="text-destructive col-span-full text-xs">{{ sessionForm.errors.exam_room_slot_id }}</p>
                </form>

                <!-- Assign invigilator (inline) -->
                <form v-if="openInvigilatorSlotId === slot.id" @submit.prevent="submitInvigilator" class="bg-muted/30 grid gap-3 rounded-md border p-3 md:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label>Giảng viên <span class="text-destructive">*</span></Label>
                        <Select :model-value="invigilatorForm.lecture_id?.toString() ?? undefined" @update:model-value="(v) => (invigilatorForm.lecture_id = v ? Number(v) : null)">
                            <SelectTrigger><SelectValue placeholder="Chọn giảng viên" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="l in lecturers" :key="l.id" :value="l.id.toString()">{{ lecturerName(l) }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="invigilatorForm.errors.lecture_id" class="text-destructive text-xs">{{ invigilatorForm.errors.lecture_id }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Vai trò</Label>
                        <Select :model-value="invigilatorForm.role" @update:model-value="(v) => (invigilatorForm.role = typeof v === 'string' ? v : 'assistant')">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="lead">Chính (lead)</SelectItem>
                                <SelectItem value="assistant">Phụ (assistant)</SelectItem>
                                <SelectItem value="backup">Dự phòng (backup)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex items-end justify-end gap-2">
                        <Button type="submit" size="sm" :disabled="invigilatorForm.processing || !invigilatorForm.lecture_id">Phân công</Button>
                    </div>
                    <p v-if="invigilatorForm.errors.exam_room_slot_id" class="text-destructive col-span-full text-xs">{{ invigilatorForm.errors.exam_room_slot_id }}</p>
                </form>
            </CardContent>
        </Card>
    </div>

    <DataPagination :pagination-data="slots" @navigate="(page) => router.visit(route('academic.exam-schedule.index', { page }))" />
</template>
