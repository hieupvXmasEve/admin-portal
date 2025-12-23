<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import type { Room, SyllabusTemplate } from '@/types/models';
import { toTypedSchema } from '@vee-validate/zod';
import { useVirtualList } from '@vueuse/core';
import { CheckCircle, Clock, MapPin, Users } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref, shallowRef, watch } from 'vue';
import * as z from 'zod';

interface Props {
    availableRooms?: Room[];
    isGenerating: boolean;
    // Optional semester bounds for client-side limits
    semesterStart?: string;
    semesterEnd?: string;
    syllabusTemplate?: SyllabusTemplate;
    disableGenerate: boolean;
}

interface WeeklySchedule {
    [key: string]: {
        enabled: boolean;
        startTime: string;
        endTime: string;
    };
}

interface Emits {
    (e: 'generate', payload: { roomId: number; startDate: string; weeklySchedule: WeeklySchedule }): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
const open = ref(false);
const search = shallowRef('');

// Days of the week
const daysOfWeek = [
    { key: 'monday', label: 'Monday' },
    { key: 'tuesday', label: 'Tuesday' },
    { key: 'wednesday', label: 'Wednesday' },
    { key: 'thursday', label: 'Thursday' },
    { key: 'friday', label: 'Friday' },
    { key: 'saturday', label: 'Saturday' },
    { key: 'sunday', label: 'Sunday' },
];

// Form schema
const formSchema = toTypedSchema(
    z.object({
        roomId: z.number({ message: 'Please select a room' }),
        startDate: z.string().min(1, 'Start date is required'),
        selectedDays: z.array(z.string()).min(1, 'Please select at least one day'),
        schedule: z.record(
            z.object({
                startTime: z.string(),
                endTime: z.string(),
            }),
        ),
    }),
);

// Initialize form
const { handleSubmit, setFieldValue, values } = useForm({
    validationSchema: formSchema,
    initialValues: {
        roomId: undefined,
        startDate: '',
        selectedDays: [] as string[],
        schedule: daysOfWeek.reduce(
            (acc, day) => {
                acc[day.key] = {
                    startTime: '09:00',
                    endTime: '10:00',
                };
                return acc;
            },
            {} as Record<string, { startTime: string; endTime: string }>,
        ),
    },
});

// Generate time options from 07:00 to 19:00
const timeOptions = computed(() => {
    const options = [];
    for (let hour = 6; hour <= 23; hour++) {
        for (let minute = 0; minute < 60; minute += 30) {
            const time = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
            const displayTime = formatTimeDisplay(time);
            options.push({ value: time, label: displayTime });
        }
    }
    return options;
});

const formatTimeDisplay = (time: string) => {
    const [hours, minutes] = time.split(':').map(Number);
    const period = hours >= 12 ? 'PM' : 'AM';
    const displayHours = hours === 0 ? 12 : hours > 12 ? hours - 12 : hours;
    return `${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`;
};

const filteredItems = computed(() => {
    return props?.availableRooms?.filter((i) => i.name.toLowerCase().includes(search.value.toLowerCase())) || [];
});

// virtual list
const { list, containerProps, wrapperProps } = useVirtualList(filteredItems, {
    itemHeight: 90, // height of one Card (px)
});

// Check if at least one day is selected
const hasSelectedDays = computed(() => {
    return values.selectedDays?.length && values.selectedDays.length > 0;
});

// Calculate total sessions that will be generated
const estimatedSessions = computed(() => {
    if (!props.syllabusTemplate?.total_sessions) return 0;
    const selectedDaysCount = values.selectedDays?.length || 0;
    if (selectedDaysCount === 0) return 0;
    // This is just an estimate - actual calculation will be done server-side
    return props.syllabusTemplate.total_sessions;
});

// Reset selection when modal opens
watch(open, (newValue) => {
    if (newValue) {
        setFieldValue('roomId', undefined);
        setFieldValue('selectedDays', []);
        // Default start date to semester start if provided
        if (!values.startDate && props.semesterStart) {
            setFieldValue('startDate', props.semesterStart.split('T')[0] || props.semesterStart);
        }
        // Reset schedule times
        daysOfWeek.forEach((day) => {
            setFieldValue(`schedule.${day.key}.startTime`, '09:00');
            setFieldValue(`schedule.${day.key}.endTime`, '10:00');
        });
    }
});

const onSubmit = handleSubmit((formValues) => {
    // Build weekly schedule from form values
    const weeklySchedule: WeeklySchedule = {};
    daysOfWeek.forEach((day) => {
        weeklySchedule[day.key] = {
            enabled: formValues.selectedDays.includes(day.key),
            startTime: formValues.schedule[day.key].startTime,
            endTime: formValues.schedule[day.key].endTime,
        };
    });
    emit('generate', {
        roomId: formValues.roomId!,
        startDate: formValues.startDate,
        weeklySchedule,
    });
    open.value = false;
});

const getRoomTypeLabel = (type: string) => {
    const typeMap: Record<string, string> = {
        classroom: 'Classroom',
        laboratory: 'Laboratory',
        computer_lab: 'Computer Lab',
        auditorium: 'Auditorium',
        meeting_room: 'Meeting Room',
        library: 'Library',
        study_room: 'Study Room',
        workshop: 'Workshop',
        office: 'Office',
        other: 'Other',
    };
    return typeMap[type] || type;
};

const getRoomDisplayName = (room: Room) => {
    return room.building ? `${room.building} - ${room.name}` : room.name;
};

const isEndOptionDisabled = (dayKey: string, optionValue: string): boolean => {
    const startTime: string | undefined = values.schedule?.[dayKey]?.startTime;
    if (!startTime) return false;
    return optionValue <= startTime;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button :disabled="isGenerating || disableGenerate" size="sm">
                <Users class="mr-2 h-4 w-4" />
                {{ isGenerating ? 'Generating...' : 'Generate Class Sessions' }}
            </Button>
        </DialogTrigger>
        <DialogContent class="flex max-h-[90vh] !max-w-4xl flex-col">
            <DialogHeader>
                <DialogTitle>Generate Class Sessions</DialogTitle>
                <DialogDescription>
                    Configure the weekly schedule for class sessions. Sessions will be generated based on the syllabus template's total sessions requirement
                    <span v-if="syllabusTemplate?.total_sessions" class="font-medium">({{ syllabusTemplate.total_sessions }} sessions)</span>.
                </DialogDescription>
            </DialogHeader>

            <div class="flex-1 space-y-4 overflow-y-auto px-1">
                <!-- Start Date -->
                <FormField v-slot="{ componentField }" name="startDate">
                    <FormItem>
                        <FormLabel>Start Date</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" type="date" class="w-full" />
                        </FormControl>
                        <p class="text-muted-foreground mt-1 text-xs">First day of classes within the semester</p>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <Separator />

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <!-- Weekly Schedule -->
                    <div>
                        <div class="mb-4 flex items-center justify-between">
                            <Label class="text-base">Weekly Schedule</Label>
                            <div class="text-muted-foreground flex items-center gap-2 text-sm">
                                <Clock class="h-4 w-4" />
                                <span>Select days and times for classes</span>
                            </div>
                        </div>

                        <FormField name="selectedDays">
                            <FormItem>
                                <div class="space-y-3">
                                    <FormField v-for="day in daysOfWeek" v-slot="{ value, handleChange }" :key="day.key" type="checkbox" :value="day.key" :unchecked-value="false" name="selectedDays">
                                        <FormItem
                                            class="flex items-center gap-4 rounded-lg border p-3"
                                            :class="{
                                                'bg-primary/5 border-primary': value.includes(day.key),
                                                'bg-background': !value.includes(day.key),
                                            }"
                                        >
                                            <FormControl>
                                                <Checkbox :model-value="value.includes(day.key)" @update:model-value="handleChange" />
                                            </FormControl>
                                            <FormLabel class="flex-1 cursor-pointer font-medium">
                                                {{ day.label }}
                                            </FormLabel>

                                            <div class="flex items-center gap-2">
                                                <FormField v-slot="{ componentField }" :name="`schedule.${day.key}.startTime`">
                                                    <FormItem>
                                                        <FormControl>
                                                            <Select v-bind="componentField" :disabled="!value.includes(day.key)">
                                                                <SelectTrigger class="">
                                                                    <SelectValue placeholder="Start time" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem v-for="option in timeOptions" :key="option.value" :value="option.value">
                                                                        {{ option.label }}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </FormControl>
                                                    </FormItem>
                                                </FormField>

                                                <span class="text-muted-foreground">to</span>

                                                <FormField v-slot="{ componentField }" :name="`schedule.${day.key}.endTime`">
                                                    <FormItem>
                                                        <FormControl>
                                                            <Select v-bind="componentField" :disabled="!value.includes(day.key)">
                                                                <SelectTrigger class="">
                                                                    <SelectValue placeholder="End time" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem v-for="option in timeOptions" :key="option.value" :value="option.value" :disabled="isEndOptionDisabled(day.key, option.value)">
                                                                        {{ option.label }}
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </FormControl>
                                                    </FormItem>
                                                </FormField>
                                            </div>
                                        </FormItem>
                                    </FormField>
                                </div>

                                <div v-if="!hasSelectedDays" class="bg-muted/50 mt-3 rounded-lg p-3">
                                    <p class="text-muted-foreground text-center text-sm">Please select at least one day</p>
                                </div>

                                <div v-else-if="estimatedSessions > 0" class="bg-primary/10 mt-3 rounded-lg p-3">
                                    <p class="text-center text-sm">
                                        Will generate <span class="font-medium">{{ estimatedSessions }} sessions</span> across selected days
                                    </p>
                                </div>

                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <!-- Room Selection -->
                    <div class="flex flex-col">
                        <Label class="mb-2 block text-base">Select Room</Label>
                        <div class="mb-3">
                            <Input v-model="search" placeholder="Search rooms..." type="search" class="w-full" />
                        </div>

                        <FormField name="roomId">
                            <FormItem class="flex-1">
                                <FormControl>
                                    <div class="h-[500px] overflow-y-auto pr-2" v-bind="containerProps">
                                        <FormField v-slot="{ value, handleChange }" name="roomId">
                                            <FormItem>
                                                <FormControl>
                                                    <RadioGroup :model-value="value" @update:model-value="handleChange" class="flex flex-col gap-4" v-bind="wrapperProps">
                                                        <div v-for="{ data: room } in list" :key="room.id" class="">
                                                            <Label :for="`room-${room.id}`" class="cursor-pointer">
                                                                <Card
                                                                    class="hover:border-primary w-full gap-0 border-2 py-2 transition-colors"
                                                                    :class="{
                                                                        'border-primary bg-primary/5': value === room.id,
                                                                        'border-border': value !== room.id,
                                                                    }"
                                                                >
                                                                    <CardHeader>
                                                                        <div class="flex items-center justify-between">
                                                                            <div class="flex items-center space-x-3">
                                                                                <RadioGroupItem :id="`room-${room.id}`" :value="room.id" class="mt-0.5" />
                                                                                <div>
                                                                                    <CardTitle class="text-base">
                                                                                        {{ getRoomDisplayName(room) }}
                                                                                    </CardTitle>
                                                                                    <CardDescription class="text-sm"> {{ room.code }} • {{ getRoomTypeLabel(room.type) }} </CardDescription>
                                                                                </div>
                                                                            </div>
                                                                            <CheckCircle v-if="value === room.id" class="text-primary h-5 w-5" />
                                                                        </div>
                                                                    </CardHeader>
                                                                    <CardContent class="pt-0">
                                                                        <div class="flex items-center justify-between text-sm">
                                                                            <div class="text-muted-foreground flex items-center">
                                                                                <Users class="mr-1 h-4 w-4" />
                                                                                <span>Capacity: {{ room.capacity }}</span>
                                                                            </div>
                                                                            <div v-if="room.building" class="text-muted-foreground flex items-center">
                                                                                <MapPin class="mr-1 h-4 w-4" />
                                                                                <span>{{ room.building }}</span>
                                                                            </div>
                                                                        </div>
                                                                    </CardContent>
                                                                </Card>
                                                            </Label>
                                                        </div>
                                                    </RadioGroup>
                                                </FormControl>
                                            </FormItem>
                                        </FormField>
                                    </div>
                                    <div v-if="!availableRooms || availableRooms.length === 0" class="py-8 text-center">
                                        <Users class="text-muted-foreground mx-auto h-12 w-12" />
                                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No available rooms</h3>
                                        <p class="text-muted-foreground mt-1 text-sm">There are no bookable rooms available at the moment.</p>
                                    </div>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </div>
            </div>

            <DialogFooter class="mt-4">
                <DialogClose as-child>
                    <Button variant="outline">Cancel</Button>
                </DialogClose>
                <Button @click="onSubmit" :disabled="!values.roomId || !values.startDate || !hasSelectedDays || isGenerating">
                    {{ isGenerating ? 'Generating...' : 'Generate Sessions' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
