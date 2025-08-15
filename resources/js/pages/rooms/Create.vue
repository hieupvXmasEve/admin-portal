<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    DAYS_OF_WEEK,
    getDaysOfWeekOptions,
    getRoomStatusOptions,
    getRoomTypeOptions,
    roomFormSchema,
    type RoomFormData,
} from '@/schemas/room';
import { systemRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Building2, Clock, Info, Settings } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

// Validation schema
const validationSchema = toTypedSchema(roomFormSchema);

// Initial form values
const initialValues: RoomFormData = {
    name: '',
    code: '',
    building: '',
    floor: '',
    type: 'classroom',
    capacity: 30,
    status: 'available',
    is_bookable: true,
    requires_approval: false,
    available_from: '',
    available_until: '',
    blocked_days: [] as (typeof DAYS_OF_WEEK[number])[],
    description: '',
    usage_guidelines: '',
    booking_notes: '',
};

// Inertia form for submission
const inertiaForm = useForm(initialValues);

// Options
const roomTypeOptions = getRoomTypeOptions();
const roomStatusOptions = getRoomStatusOptions();
const daysOfWeekOptions = getDaysOfWeekOptions();

// Handle blocked days selection
const blockedDays = ref<(typeof DAYS_OF_WEEK[number])[]>([]);

const toggleBlockedDay = (day: string) => {
    const currentDays = [...blockedDays.value];
    const index = currentDays.indexOf(day as (typeof DAYS_OF_WEEK[number]));

    if (index > -1) {
        currentDays.splice(index, 1);
    } else {
        currentDays.push(day as (typeof DAYS_OF_WEEK[number]));
    }

    blockedDays.value = currentDays;
};

const isDayBlocked = (day: string) => {
    return blockedDays.value.includes(day as (typeof DAYS_OF_WEEK)[number]);
};

// Form submission
const onSubmit = (values: any) => {
    // Add blocked days to the form data
    const formData = { ...values, blocked_days: blockedDays.value };
    Object.assign(inertiaForm, formData);

    inertiaForm.post(systemRoutes.rooms.store(), {
        onSuccess: () => {
            toast.success('Room created successfully!');
            router.visit(systemRoutes.rooms.index());
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
            const firstErrorKey = Object.keys(errors)[0];
            const firstError = errors[firstErrorKey];

            if (firstError) {
                toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
            } else {
                toast.error('Failed to create room. Please check the form for errors.');
            }
        },
    });
};

// Navigation
const goBack = () => {
    router.visit(systemRoutes.rooms.index());
};
</script>

<template>
    <Head title="Create Room" />

    <!-- Header -->
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="goBack" class="h-8 w-8">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div>
            <h1 class="text-2xl font-semibold">Create New Room</h1>
            <p class="text-muted-foreground">Add a new room to the system</p>
        </div>
    </div>

    <Form v-slot="{ meta }" :validation-schema="validationSchema" :initial-values="initialValues" class="space-y-6" @submit="onSubmit">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Basic Information Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-5 w-5" />
                        Basic Information
                    </CardTitle>
                    <CardDescription>Enter the basic details for the room</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <FormField v-slot="{ componentField }" name="name">
                        <FormItem>
                            <FormLabel>Room Name</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., Lecture Theatre 1, Lab A" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="code">
                        <FormItem>
                            <FormLabel>Room Code</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., LT1, LAB-A, CR101" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField v-slot="{ componentField }" name="building">
                            <FormItem>
                                <FormLabel>Building</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="e.g., Main Building" :disabled="inertiaForm.processing" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="floor">
                            <FormItem>
                                <FormLabel>Floor</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="e.g., Ground, 1st, 2nd" :disabled="inertiaForm.processing" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField v-slot="{ componentField }" name="type">
                            <FormItem>
                                <FormLabel>Room Type</FormLabel>
                                <FormControl>
                                    <Select v-bind="componentField">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select room type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="option in roomTypeOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="capacity">
                            <FormItem>
                                <FormLabel>Capacity</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="number" min="1" max="10000" placeholder="30" :disabled="inertiaForm.processing" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
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
                    <CardDescription>Configure room status and booking settings</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <FormField v-slot="{ componentField }" name="status">
                        <FormItem>
                            <FormLabel>Status</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="option in roomStatusOptions" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="space-y-4">
                        <FormField v-slot="{ componentField }" name="is_bookable">
                            <FormItem class="flex items-center space-x-2">
                                <FormControl>
                                    <Checkbox v-bind="componentField" />
                                </FormControl>
                                <FormLabel>Room is bookable</FormLabel>
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="requires_approval">
                            <FormItem class="flex items-center space-x-2">
                                <FormControl>
                                    <Checkbox v-bind="componentField" />
                                </FormControl>
                                <FormLabel>Requires approval for booking</FormLabel>
                            </FormItem>
                        </FormField>
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
                <CardDescription>Configure when the room is available for booking</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <FormField v-slot="{ componentField }" name="available_from">
                        <FormItem>
                            <FormLabel>Available From (Time)</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="time" step="1" placeholder="08:00:00" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <p class="text-muted-foreground text-xs">Leave empty for no time restriction</p>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="available_until">
                        <FormItem>
                            <FormLabel>Available Until (Time)</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="time" step="1" placeholder="18:00:00" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <p class="text-muted-foreground text-xs">Leave empty for no time restriction</p>
                            <FormMessage />
                        </FormItem>
                    </FormField>
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
                    <p class="text-muted-foreground text-xs">Select days when the room is not available for booking</p>
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
                <CardDescription>Optional details about the room</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <FormField v-slot="{ componentField }" name="description">
                    <FormItem>
                        <FormLabel>Description</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Brief description of the room..." rows="3" :disabled="inertiaForm.processing" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ componentField }" name="usage_guidelines">
                    <FormItem>
                        <FormLabel>Usage Guidelines</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Guidelines for using this room..." rows="3" :disabled="inertiaForm.processing" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ componentField }" name="booking_notes">
                    <FormItem>
                        <FormLabel>Booking Notes</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Important notes for booking this room..." rows="3" :disabled="inertiaForm.processing" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </CardContent>
        </Card>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-4">
            <Button type="button" variant="outline" @click="goBack" :disabled="inertiaForm.processing">Cancel</Button>
            <Button type="submit" :disabled="inertiaForm.processing" class="min-w-[120px]">
                <span v-if="inertiaForm.processing">Creating...</span>
                <span v-else>Create Room</span>
            </Button>
        </div>
    </Form>
</template>
