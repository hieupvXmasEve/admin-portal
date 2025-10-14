<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { Loader2 } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import { z } from 'zod';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

interface Event {
    id: number;
    title: string;
    description: string | null;
    start_time: string;
    end_time: string;
    location: string;
    gold_reward_amount: number;
    max_participants: number | null;
}

interface Props {
    event: Event | null;
    isEditing: boolean;
    isManual?: boolean;
}

const props = defineProps<Props>();

// Convert ISO strings to datetime-local format
const formatDateTimeLocal = (isoString: string | null) => {
    if (!isoString) return '';
    const date = new Date(isoString);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

// Custom Zod refinement for datetime comparison
const createEventSchema = (isManual: boolean, isHistorical: boolean) => {
    return z
        .object({
            title: z.string({ required_error: 'Event title is required' }).min(1, 'Event title is required').max(255, 'Event title cannot exceed 255 characters'),
            description: z.string().max(5000, 'Description cannot exceed 5000 characters').nullable().optional(),
            start_time: z.string({ required_error: 'Start time is required' }).min(1, 'Start time is required'),
            end_time: z.string({ required_error: 'End time is required' }).min(1, 'End time is required'),
            location: z.string({ required_error: 'Location is required' }).min(1, 'Location is required').max(255, 'Location cannot exceed 255 characters'),
            gold_reward_amount: z.number({ required_error: 'Gold reward amount is required' }).min(0, 'Gold reward amount cannot be negative').max(999999.99, 'Gold reward amount is too large'),
            max_participants: z.number().int('Maximum participants must be a whole number').min(1, 'Maximum participants must be at least 1').max(10000, 'Maximum participants cannot exceed 10,000').nullable().optional(),
            is_manual: z.boolean().default(false),
            is_historical: z.boolean().default(false),
            status: z.enum(['draft', 'published', 'completed', 'cancelled']).optional(),
        })
        .refine(
            (data) => {
                // Check if end_time is after start_time
                if (data.start_time && data.end_time) {
                    return new Date(data.end_time) > new Date(data.start_time);
                }
                return true;
            },
            {
                message: 'End time must be after start time',
                path: ['end_time'],
            },
        )
        .refine(
            (data) => {
                // Check if start_time is in the future (only for non-manual, non-historical events)
                if (!isManual && !isHistorical && data.start_time) {
                    return new Date(data.start_time) > new Date();
                }
                return true;
            },
            {
                message: 'Start time must be in the future',
                path: ['start_time'],
            },
        );
};

type EventFormData = z.infer<ReturnType<typeof createEventSchema>>;

// Initial form values
const initialValues: EventFormData = {
    title: props.event?.title || '',
    description: props.event?.description || '',
    start_time: formatDateTimeLocal(props.event?.start_time || ''),
    end_time: formatDateTimeLocal(props.event?.end_time || ''),
    location: props.event?.location || '',
    gold_reward_amount: Number(props.event?.gold_reward_amount) || 0,
    max_participants: props.event?.max_participants || null,
    is_manual: props.isManual || false,
    is_historical: false,
    status: props.isManual ? 'completed' : 'draft',
};

const validationSchema = toTypedSchema(createEventSchema(props.isManual || false, initialValues.is_historical));

// vee-validate form
const { handleSubmit, setFieldValue } = useForm({
    validationSchema,
    initialValues,
});

const isSubmitting = ref(false);

// Update validation schema when is_historical changes
const updateValidationSchema = (isHistorical: boolean) => {
    setFieldValue('is_historical', isHistorical);
};

// Form submission handler using vee-validate actions pattern
const onSubmit = handleSubmit(async (formValues, actions) => {
    const submitData = {
        ...formValues,
        title: String(formValues.title),
        description: formValues.description || null,
        start_time: String(formValues.start_time),
        end_time: String(formValues.end_time),
        location: String(formValues.location),
        gold_reward_amount: Number(formValues.gold_reward_amount),
        max_participants: formValues.max_participants ? Number(formValues.max_participants) : null,
        is_manual: Boolean(formValues.is_manual),
        is_historical: Boolean(formValues.is_historical),
        status: formValues.status,
    };

    isSubmitting.value = true;

    const routeName = props.isEditing && props.event ? route('events.update', props.event.id) : props.isManual ? route('events.store-manual') : route('events.store');

    const method = props.isEditing && props.event ? 'put' : 'post';

    router[method](routeName, submitData, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            toast.success(props.isEditing ? 'Event updated successfully' : props.isManual ? 'Manual event created successfully' : 'Event created successfully');
        },
        onError: (serverErrors) => {
            isSubmitting.value = false;
            toast.error('Failed to save event. Please check the form for errors.');
            console.error('Validation errors:', serverErrors);

            // Use vee-validate actions to set errors
            actions.setErrors(serverErrors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
});
</script>
<template>
    <Head :title="isEditing ? 'Edit Event' : 'Create Event'" />
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">
                {{ isEditing ? 'Edit Event' : isManual ? 'Create Manual Event' : 'Create Event' }}
            </h2>
            <p class="text-muted-foreground mt-1 text-sm">
                {{ isManual ? 'Create a manual event for historical activities or past events.' : 'Manage event information and rewards.' }}
            </p>
        </div>
        <Button variant="outline" as-child class="gap-2">
            <Link :href="route('events.index')"> Back to Events </Link>
        </Button>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>{{ isManual ? 'Manual Event Details' : 'Event Details' }}</CardTitle>
            <CardDescription>
                {{ isManual ? 'Create a manual event for activities that occurred before the system was implemented.' : 'Provide the key information about the event, including schedule and reward.' }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <!-- Manual Event Warning -->
            <Alert v-if="isManual" class="mb-6">
                <AlertDescription> <strong>Manual Event:</strong> This event will be created as a completed event. You can set past dates and manually add participants after creation. </AlertDescription>
            </Alert>

            <form class="space-y-8" @submit.prevent="onSubmit">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Title -->
                    <FormField v-slot="{ componentField }" name="title" class="md:col-span-2">
                        <FormItem>
                            <FormLabel>Event Title <span class="text-destructive">*</span></FormLabel>
                            <FormControl>
                                <Input type="text" placeholder="Enter event title" :disabled="isSubmitting" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Description -->
                    <FormField v-slot="{ componentField }" name="description" class="md:col-span-2">
                        <FormItem>
                            <FormLabel>Description</FormLabel>
                            <FormControl>
                                <Textarea rows="4" placeholder="Share what this event is about" :disabled="isSubmitting" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Start Time -->
                    <FormField v-slot="{ componentField }" name="start_time">
                        <FormItem>
                            <FormLabel>Start Time <span class="text-destructive">*</span></FormLabel>
                            <FormControl>
                                <Input type="datetime-local" :disabled="isSubmitting" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- End Time -->
                    <FormField v-slot="{ componentField }" name="end_time">
                        <FormItem>
                            <FormLabel>End Time <span class="text-destructive">*</span></FormLabel>
                            <FormControl>
                                <Input type="datetime-local" :disabled="isSubmitting" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Location -->
                    <FormField v-slot="{ componentField }" name="location">
                        <FormItem>
                            <FormLabel>Location <span class="text-destructive">*</span></FormLabel>
                            <FormControl>
                                <Input type="text" placeholder="Enter event location" :disabled="isSubmitting" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Gold Reward Amount -->
                    <FormField v-slot="{ componentField }" name="gold_reward_amount">
                        <FormItem>
                            <FormLabel>Gold Reward Amount <span class="text-destructive">*</span></FormLabel>
                            <FormControl>
                                <Input type="number" step="1" min="0" placeholder="0.00" :disabled="isSubmitting" v-bind="componentField" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Max Participants -->
                    <FormField v-slot="{ componentField }" name="max_participants" class="md:col-span-2">
                        <FormItem>
                            <FormLabel>Maximum Participants</FormLabel>
                            <FormControl>
                                <Input type="number" min="1" placeholder="Leave empty for unlimited" :disabled="isSubmitting" v-bind="componentField" />
                            </FormControl>
                            <FormDescription>Leave empty for unlimited participants.</FormDescription>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Historical Event Checkbox (only for manual events) -->
                    <FormField v-if="isManual" v-slot="{ value, handleChange }" name="is_historical" class="md:col-span-2">
                        <FormItem class="flex flex-row items-start space-y-0 space-x-3">
                            <FormControl>
                                <Checkbox
                                    :model-value="value"
                                    :disabled="isSubmitting"
                                    @update:model-value="
                                        (checked) => {
                                            handleChange(checked);
                                            updateValidationSchema(checked as boolean);
                                        }
                                    "
                                />
                            </FormControl>
                            <div class="space-y-1 leading-none">
                                <FormLabel>This is a historical event (occurred before system implementation)</FormLabel>
                                <FormDescription> Check this if the event occurred before the system was implemented. This allows past dates and provides better audit tracking. </FormDescription>
                            </div>
                        </FormItem>
                    </FormField>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Button type="button" variant="outline" as-child :disabled="isSubmitting">
                        <Link :href="route('events.index')"> Cancel </Link>
                    </Button>
                    <Button type="submit" :disabled="isSubmitting" class="gap-2">
                        <Loader2 v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                        {{ isEditing ? 'Update Event' : isManual ? 'Create Manual Event' : 'Create Event' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
