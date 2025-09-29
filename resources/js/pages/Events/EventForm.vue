<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

const form = useForm({
    title: props.event?.title || '',
    description: props.event?.description || '',
    start_time: formatDateTimeLocal(props.event?.start_time || ''),
    end_time: formatDateTimeLocal(props.event?.end_time || ''),
    location: props.event?.location || '',
    gold_reward_amount: props.event?.gold_reward_amount || 0,
    max_participants: props.event?.max_participants || null,
    is_manual: props.isManual || false,
    is_historical: false,
    status: props.isManual ? 'completed' : 'draft',
});

const submitForm = () => {
    if (props.isEditing && props.event) {
        form.put(route('events.update', props.event.id));
    } else if (props.isManual) {
        form.post(route('events.store-manual'));
    } else {
        form.post(route('events.store'));
    }
};
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

            <form class="space-y-8" @submit.prevent="submitForm">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Title -->
                    <div class="space-y-2 md:col-span-2">
                        <Label for="title">Event Title *</Label>
                        <Input id="title" v-model="form.title" type="text" required :disabled="form.processing" :aria-invalid="!!form.errors.title" aria-describedby="title-error" />
                        <p v-if="form.errors.title" id="title-error" class="text-destructive text-sm">
                            {{ form.errors.title }}
                        </p>
                    </div>

                    <!-- Description -->
                    <div class="space-y-2 md:col-span-2">
                        <Label for="description">Description</Label>
                        <Textarea id="description" v-model="form.description" rows="4" :disabled="form.processing" :aria-invalid="!!form.errors.description" aria-describedby="description-error" placeholder="Share what this event is about" />
                        <p v-if="form.errors.description" id="description-error" class="text-destructive text-sm">
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <!-- Start Time -->
                    <div class="space-y-2">
                        <Label for="start_time">Start Time *</Label>
                        <Input id="start_time" v-model="form.start_time" type="datetime-local" required :disabled="form.processing" :aria-invalid="!!form.errors.start_time" aria-describedby="start-time-error" />
                        <p v-if="form.errors.start_time" id="start-time-error" class="text-destructive text-sm">
                            {{ form.errors.start_time }}
                        </p>
                    </div>

                    <!-- End Time -->
                    <div class="space-y-2">
                        <Label for="end_time">End Time *</Label>
                        <Input id="end_time" v-model="form.end_time" type="datetime-local" required :disabled="form.processing" :aria-invalid="!!form.errors.end_time" aria-describedby="end-time-error" />
                        <p v-if="form.errors.end_time" id="end-time-error" class="text-destructive text-sm">
                            {{ form.errors.end_time }}
                        </p>
                    </div>

                    <!-- Location -->
                    <div class="space-y-2">
                        <Label for="location">Location *</Label>
                        <Input id="location" v-model="form.location" type="text" required :disabled="form.processing" :aria-invalid="!!form.errors.location" aria-describedby="location-error" />
                        <p v-if="form.errors.location" id="location-error" class="text-destructive text-sm">
                            {{ form.errors.location }}
                        </p>
                    </div>

                    <!-- Gold Reward Amount -->
                    <div class="space-y-2">
                        <Label for="gold_reward_amount">Gold Reward Amount *</Label>
                        <Input id="gold_reward_amount" v-model="form.gold_reward_amount" type="number" step="0.01" min="0" required :disabled="form.processing" :aria-invalid="!!form.errors.gold_reward_amount" aria-describedby="gold-reward-error" />
                        <p v-if="form.errors.gold_reward_amount" id="gold-reward-error" class="text-destructive text-sm">
                            {{ form.errors.gold_reward_amount }}
                        </p>
                    </div>

                    <!-- Max Participants -->
                    <div class="space-y-2 md:col-span-2">
                        <Label for="max_participants">Maximum Participants</Label>
                        <Input
                            id="max_participants"
                            v-model="form.max_participants"
                            type="number"
                            min="1"
                            :disabled="form.processing"
                            :aria-invalid="!!form.errors.max_participants"
                            aria-describedby="max-participants-error max-participants-help"
                            placeholder="Leave empty for unlimited"
                        />
                        <p v-if="form.errors.max_participants" id="max-participants-error" class="text-destructive text-sm">
                            {{ form.errors.max_participants }}
                        </p>
                        <p id="max-participants-help" class="text-muted-foreground text-sm">Leave empty for unlimited participants.</p>
                    </div>

                    <!-- Historical Event Checkbox (only for manual events) -->
                    <div v-if="isManual" class="space-y-2 md:col-span-2">
                        <div class="flex items-center space-x-2">
                            <Checkbox id="is_historical" v-model:checked="form.is_historical" :disabled="form.processing" />
                            <Label for="is_historical" class="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"> This is a historical event (occurred before system implementation) </Label>
                        </div>
                        <p class="text-muted-foreground text-sm">Check this if the event occurred before the system was implemented. This allows past dates and provides better audit tracking.</p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Button type="button" variant="outline" as-child :disabled="form.processing">
                        <Link :href="route('events.index')"> Cancel </Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="gap-2">
                        <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                        {{ isEditing ? 'Update Event' : isManual ? 'Create Manual Event' : 'Create Event' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
