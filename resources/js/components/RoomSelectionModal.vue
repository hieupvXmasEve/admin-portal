<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Room } from '@/types/models';
import { CheckCircle, MapPin, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    availableRooms: Room[];
    isGenerating: boolean;
}

interface Emits {
    (e: 'generate', roomId: number): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const open = ref(false);
const selectedRoomId = ref<number | null>(null);

// Reset selection when modal opens
watch(open, (newValue) => {
    if (newValue) {
        selectedRoomId.value = null;
    }
});

const handleGenerate = () => {
    if (selectedRoomId.value) {
        emit('generate', selectedRoomId.value);
        open.value = false;
    }
};

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
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button :disabled="isGenerating" size="sm">
                <Users class="mr-2 h-4 w-4" />
                {{ isGenerating ? 'Generating...' : 'Generate Class Sessions' }}
            </Button>
        </DialogTrigger>
        <DialogContent class="max-w-2xl">
            <div class="flex flex-col space-y-1.5 text-center sm:text-left">
                <DialogTitle class="text-lg font-semibold leading-none tracking-tight">
                    Select Room for Class Sessions
                </DialogTitle>
                <DialogDescription class="text-sm text-muted-foreground">
                    Choose a room where the class sessions will be held. All generated sessions will be assigned to the selected room.
                </DialogDescription>
            </div>

                <div class="max-h-96 overflow-y-auto">
                    <RadioGroup v-model="selectedRoomId" class="grid gap-4">
                        <div v-for="room in availableRooms" :key="room.id">
                            <Label
                                :for="`room-${room.id}`"
                                class="cursor-pointer"
                            >
                                <Card
                                    class="border-2 transition-colors hover:border-primary"
                                    :class="{
                                        'border-primary bg-primary/5': selectedRoomId === room.id,
                                        'border-border': selectedRoomId !== room.id,
                                    }"
                                >
                                    <CardHeader class="pb-3">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <RadioGroupItem
                                                    :id="`room-${room.id}`"
                                                    :value="room.id"
                                                    class="mt-0.5"
                                                />
                                                <div>
                                                    <CardTitle class="text-base">
                                                        {{ getRoomDisplayName(room) }}
                                                    </CardTitle>
                                                    <CardDescription class="text-sm">
                                                        {{ room.code }} • {{ getRoomTypeLabel(room.type) }}
                                                    </CardDescription>
                                                </div>
                                            </div>
                                            <CheckCircle
                                                v-if="selectedRoomId === room.id"
                                                class="h-5 w-5 text-primary"
                                            />
                                        </div>
                                    </CardHeader>
                                    <CardContent class="pt-0">
                                        <div class="flex items-center justify-between text-sm">
                                            <div class="flex items-center text-muted-foreground">
                                                <Users class="mr-1 h-4 w-4" />
                                                <span>Capacity: {{ room.capacity }}</span>
                                            </div>
                                            <div v-if="room.building" class="flex items-center text-muted-foreground">
                                                <MapPin class="mr-1 h-4 w-4" />
                                                <span>{{ room.building }}</span>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Label>
                        </div>
                    </RadioGroup>

                    <div v-if="availableRooms.length === 0" class="py-8 text-center">
                        <Users class="mx-auto h-12 w-12 text-muted-foreground" />
                        <h3 class="mt-2 text-sm font-semibold text-gray-900">No available rooms</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            There are no bookable rooms available at the moment.
                        </p>
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                    <DialogClose as-child>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button
                        @click="handleGenerate"
                        :disabled="!selectedRoomId || isGenerating"
                        class="mb-2 sm:mb-0"
                    >
                        {{ isGenerating ? 'Generating...' : 'Generate Sessions' }}
                    </Button>
                </div>
            </DialogContent>
    </Dialog>
</template>