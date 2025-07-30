<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import type { Room } from '@/types/models';
import { useVirtualList } from '@vueuse/core';
import { CheckCircle, MapPin, Users } from 'lucide-vue-next';
import { computed, ref, shallowRef, watch } from 'vue';

interface Props {
    availableRooms?: Room[];
    isGenerating: boolean;
}

interface Emits {
    (e: 'generate', roomId: number): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
console.log('props', props.availableRooms);
const open = ref(false);
const selectedRoomId = ref<number | null>(null);
const search = shallowRef('');

const filteredItems = computed(() => {
    return props?.availableRooms?.filter((i) => i.name.toLowerCase().includes(search.value.toLowerCase())) || [];
});
// virtual list
const { list, containerProps, wrapperProps } = useVirtualList(filteredItems, {
    itemHeight: 120, // height of one Card (px)
});

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
            <DialogHeader>
                <DialogTitle> Select Room for Class Sessions</DialogTitle>
                <DialogDescription>
                    Choose a room where the class sessions will be held. All generated sessions will be assigned to the selected room.
                </DialogDescription>
            </DialogHeader>
            <div class="mr-4 inline-block">
                Filter list by size
                <Input v-model="search" placeholder="e.g..." type="search" />
            </div>
            <div class="h-96" v-bind="containerProps">
                <RadioGroup v-model="selectedRoomId" class="flex flex-col gap-4" v-bind="wrapperProps">
                    <div v-for="{ data: room } in list" :key="room.id" class="">
                        <Label :for="`room-${room.id}`" class="cursor-pointer">
                            <Card
                                class="hover:border-primary w-full border-2 transition-colors"
                                :class="{
                                    'border-primary bg-primary/5': selectedRoomId === room.id,
                                    'border-border': selectedRoomId !== room.id,
                                }"
                            >
                                <CardHeader class="pb-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <RadioGroupItem :id="`room-${room.id}`" :value="room.id" class="mt-0.5" />
                                            <div>
                                                <CardTitle class="text-base">
                                                    {{ getRoomDisplayName(room) }}
                                                </CardTitle>
                                                <CardDescription class="text-sm">
                                                    {{ room.code }} • {{ getRoomTypeLabel(room.type) }}
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <CheckCircle v-if="selectedRoomId === room.id" class="text-primary h-5 w-5" />
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

                <div v-if="!availableRooms || availableRooms.length === 0" class="py-8 text-center">
                    <Users class="text-muted-foreground mx-auto h-12 w-12" />
                    <h3 class="mt-2 text-sm font-semibold text-gray-900">No available rooms</h3>
                    <p class="text-muted-foreground mt-1 text-sm">There are no bookable rooms available at the moment.</p>
                </div>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancel</Button>
                </DialogClose>
                <Button @click="handleGenerate" :disabled="!selectedRoomId || isGenerating">
                    {{ isGenerating ? 'Generating...' : 'Generate Sessions' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
