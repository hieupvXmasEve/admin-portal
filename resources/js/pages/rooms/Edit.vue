<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { 
    roomFormSchema, 
    getRoomTypeOptions, 
    getRoomStatusOptions,
    getDaysOfWeekOptions,
    DAYS_OF_WEEK,
    type RoomFormData 
} from '@/schemas/room';
import type { Room } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Building2, Clock, Info, Settings } from 'lucide-vue-next';
import { computed } from 'vue';
import { useForm, useField } from 'vee-validate';
import { toast } from 'vue-sonner';

const props = defineProps<{
    room: Room;
}>();

// Schema for vee-validate
const validationSchema = toTypedSchema(roomFormSchema);

// Use vee-validate form
const { handleSubmit, meta, isSubmitting } = useForm({
    validationSchema,
    initialValues: {
        name: props.room.name,
        code: props.room.code,
        building: props.room.building,
        floor: props.room.floor,
        type: props.room.type,
        capacity: props.room.capacity,
        status: props.room.status,
        is_bookable: props.room.is_bookable,
        requires_approval: props.room.requires_approval,
        available_from: props.room.available_from || '',
        available_until: props.room.available_until || '',
        blocked_days: (props.room.blocked_days || []) as (typeof DAYS_OF_WEEK[number])[],
        description: props.room.description || '',
        usage_guidelines: props.room.usage_guidelines || '',
        booking_notes: props.room.booking_notes || '',
    } as RoomFormData,
});

// Define fields with vee-validate
const { value: name, errorMessage: nameError } = useField<string>('name');
const { value: code, errorMessage: codeError } = useField<string>('code');
const { value: building, errorMessage: buildingError } = useField<string>('building');
const { value: floor, errorMessage: floorError } = useField<string>('floor');
const { value: type, errorMessage: typeError } = useField<string>('type');
const { value: capacity, errorMessage: capacityError } = useField<number>('capacity');
const { value: status, errorMessage: statusError } = useField<string>('status');
const { value: isBookable, errorMessage: isBookableError } = useField<boolean>('is_bookable');
const { value: requiresApproval, errorMessage: requiresApprovalError } = useField<boolean>('requires_approval');
const { value: availableFrom, errorMessage: availableFromError } = useField<string>('available_from');
const { value: availableUntil, errorMessage: availableUntilError } = useField<string>('available_until');
const { value: blockedDays, errorMessage: blockedDaysError } = useField<(typeof DAYS_OF_WEEK[number])[]>('blocked_days');
const { value: description, errorMessage: descriptionError } = useField<string>('description');
const { value: usageGuidelines, errorMessage: usageGuidelinesError } = useField<string>('usage_guidelines');
const { value: bookingNotes, errorMessage: bookingNotesError } = useField<string>('booking_notes');

// Options
const roomTypeOptions = getRoomTypeOptions();
const roomStatusOptions = getRoomStatusOptions();
const daysOfWeekOptions = getDaysOfWeekOptions();

// Handle blocked days selection
const toggleBlockedDay = (day: string) => {
    const currentDays = [...(blockedDays.value || [])];
    const index = currentDays.indexOf(day as typeof DAYS_OF_WEEK[number]);
    
    if (index > -1) {
        currentDays.splice(index, 1);
    } else {
        currentDays.push(day as typeof DAYS_OF_WEEK[number]);
    }
    
    blockedDays.value = currentDays;
};

// Check if day is blocked
const isDayBlocked = (day: string) => {
    return (blockedDays.value || []).includes(day as typeof DAYS_OF_WEEK[number]);
};

// Form submission with vee-validate
const onSubmit = handleSubmit((values) => {
    // Submit to server using Inertia
    router.put(systemRoutes.rooms.update(props.room.id), values, {
        onSuccess: () => {
            toast.success('Room updated successfully!');
            router.visit(systemRoutes.rooms.index());
        },
        onError: (errors) => {
            // Display server-side validation errors
            const firstErrorKey = Object.keys(errors)[0];
            const firstError = errors[firstErrorKey];
            
            if (firstError) {
                toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
            } else {
                toast.error('Failed to update room. Please check the form for errors.');
            }
        },
    });
});

// Navigation
const goBack = () => {
    router.visit(systemRoutes.rooms.index());
};

const viewRoom = () => {
    router.visit(systemRoutes.rooms.show(props.room.id));
};

// Computed properties
const isFormValid = computed(() => {
    return meta.value.valid;
});

const hasChanges = computed(() => {
    return meta.value.dirty;
});
</script>

<template>
    <Head :title="`Edit Room - ${room.name}`" />
    
    <!-- Header -->
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="goBack" class="h-8 w-8">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div class="flex-1">
            <h1 class="text-2xl font-semibold">Edit Room</h1>
            <p class="text-muted-foreground">Modify room details for {{ room.name }}</p>
        </div>
        <Button variant="outline" @click="viewRoom">
            View Room
        </Button>
    </div>

    <form @submit.prevent="onSubmit" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Basic Information Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-5 w-5" />
                        Basic Information
                    </CardTitle>
                    <CardDescription>
                        Update the basic details for the room
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="name">Room Name</Label>
                        <Input 
                            id="name" 
                            v-model="name" 
                            placeholder="e.g., Lecture Theatre 1, Lab A"
                            required 
                            :class="{ 'border-red-500': nameError }" 
                        />
                        <p v-if="nameError" class="text-sm text-red-500">
                            {{ nameError }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="code">Room Code</Label>
                        <Input 
                            id="code" 
                            v-model="code" 
                            placeholder="e.g., LT1, LAB-A, CR101"
                            required 
                            :class="{ 'border-red-500': codeError }" 
                        />
                        <p v-if="codeError" class="text-sm text-red-500">
                            {{ codeError }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="building">Building</Label>
                            <Input 
                                id="building" 
                                v-model="building" 
                                placeholder="e.g., Main Building"
                                required 
                                :class="{ 'border-red-500': buildingError }" 
                            />
                            <p v-if="buildingError" class="text-sm text-red-500">
                                {{ buildingError }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="floor">Floor</Label>
                            <Input 
                                id="floor" 
                                v-model="floor" 
                                placeholder="e.g., Ground, 1st, 2nd"
                                required 
                                :class="{ 'border-red-500': floorError }" 
                            />
                            <p v-if="floorError" class="text-sm text-red-500">
                                {{ floorError }}
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="type">Room Type</Label>
                            <Select v-model="type">
                                <SelectTrigger :class="{ 'border-red-500': typeError }">
                                    <SelectValue placeholder="Select room type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in roomTypeOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="typeError" class="text-sm text-red-500">
                                {{ typeError }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="capacity">Capacity</Label>
                            <Input 
                                id="capacity" 
                                v-model.number="capacity" 
                                type="number"
                                min="1"
                                max="10000"
                                placeholder="30"
                                required 
                                :class="{ 'border-red-500': capacityError }" 
                            />
                            <p v-if="capacityError" class="text-sm text-red-500">
                                {{ capacityError }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Status & Settings Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        Status & Settings
                    </CardTitle>
                    <CardDescription>
                        Configure room status and booking settings
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="status">Status</Label>
                        <Select v-model="status">
                            <SelectTrigger :class="{ 'border-red-500': statusError }">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in roomStatusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="statusError" class="text-sm text-red-500">
                            {{ statusError }}
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center space-x-2">
                            <Checkbox 
                                id="is_bookable"
                                v-model:checked="isBookable"
                            />
                            <Label for="is_bookable" class="text-sm font-medium">
                                Room is bookable
                            </Label>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Checkbox 
                                id="requires_approval"
                                v-model:checked="requiresApproval"
                            />
                            <Label for="requires_approval" class="text-sm font-medium">
                                Requires approval for booking
                            </Label>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Availability Settings Card -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Clock class="h-5 w-5" />
                    Availability Settings
                </CardTitle>
                <CardDescription>
                    Configure when the room is available for booking
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="available_from">Available From (Time)</Label>
                        <Input 
                            id="available_from" 
                            v-model="availableFrom" 
                            type="time"
                            step="1"
                            placeholder="08:00:00"
                            :class="{ 'border-red-500': availableFromError }" 
                        />
                        <p class="text-xs text-muted-foreground">
                            Leave empty for no time restriction
                        </p>
                        <p v-if="availableFromError" class="text-sm text-red-500">
                            {{ availableFromError }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="available_until">Available Until (Time)</Label>
                        <Input 
                            id="available_until" 
                            v-model="availableUntil" 
                            type="time"
                            step="1"
                            placeholder="18:00:00"
                            :class="{ 'border-red-500': availableUntilError }" 
                        />
                        <p class="text-xs text-muted-foreground">
                            Leave empty for no time restriction
                        </p>
                        <p v-if="availableUntilError" class="text-sm text-red-500">
                            {{ availableUntilError }}
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label>Blocked Days</Label>
                    <div class="grid grid-cols-7 gap-2">
                        <div v-for="day in daysOfWeekOptions" :key="day.value" class="flex items-center space-x-2">
                            <Checkbox 
                                :id="`day-${day.value}`"
                                :checked="isDayBlocked(day.value)"
                                @update:checked="() => toggleBlockedDay(day.value)"
                            />
                            <Label :for="`day-${day.value}`" class="text-sm">
                                {{ day.label.substring(0, 3) }}
                            </Label>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Select days when the room is not available for booking
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Additional Information Card -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Info class="h-5 w-5" />
                    Additional Information
                </CardTitle>
                <CardDescription>
                    Optional details about the room
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea 
                        id="description" 
                        v-model="description" 
                        placeholder="Brief description of the room..."
                        rows="3"
                        :class="{ 'border-red-500': descriptionError }" 
                    />
                    <p v-if="descriptionError" class="text-sm text-red-500">
                        {{ descriptionError }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="usage_guidelines">Usage Guidelines</Label>
                    <Textarea 
                        id="usage_guidelines" 
                        v-model="usageGuidelines" 
                        placeholder="Guidelines for using this room..."
                        rows="3"
                        :class="{ 'border-red-500': usageGuidelinesError }" 
                    />
                    <p v-if="usageGuidelinesError" class="text-sm text-red-500">
                        {{ usageGuidelinesError }}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="booking_notes">Booking Notes</Label>
                    <Textarea 
                        id="booking_notes" 
                        v-model="bookingNotes" 
                        placeholder="Important notes for booking this room..."
                        rows="3"
                        :class="{ 'border-red-500': bookingNotesError }" 
                    />
                    <p v-if="bookingNotesError" class="text-sm text-red-500">
                        {{ bookingNotesError }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-4">
            <Button type="button" variant="outline" @click="goBack">
                Cancel
            </Button>
            <Button 
                type="submit" 
                :disabled="isSubmitting || !isFormValid || !hasChanges" 
                class="min-w-[120px]"
            >
                <span v-if="isSubmitting">Updating...</span>
                <span v-else>Update Room</span>
            </Button>
        </div>
    </form>
</template>
