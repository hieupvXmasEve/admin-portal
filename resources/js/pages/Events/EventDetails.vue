<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { createColumns } from '@/lib/table-utils';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Event {
    id: number;
    title: string;
    description: string | null;
    start_time: string;
    end_time: string;
    location: string;
    status: 'draft' | 'published' | 'cancelled' | 'completed';
    gold_reward_amount: number;
    max_participants: number | null;
    qr_code: string;
    is_manual: boolean;
    is_historical: boolean;
    requires_registration: boolean;
    creator?: {
        id: number;
        name: string;
        email: string;
    };
    created_by_admin?: {
        id: number;
        name: string;
        email: string;
    };
}

interface Statistics {
    registered_count: number;
    checked_in_count: number;
    completed_count: number;
    cancelled_count: number;
    total_gold_distributed: number;
    participation_rate: number | null;
}

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    phone: string | null;
    program: {
        id: number;
        name: string;
        code: string;
    } | null;
    specialization: {
        id: number;
        name: string;
        code: string;
    } | null;
}

interface EventParticipant {
    id: number;
    event_id: number;
    student_id: number;
    status: 'registered' | 'checked_in' | 'completed' | 'cancelled';
    registered_at: string;
    checkin_time: string | null;
    gold_awarded: boolean;
    awarded_at: string | null;
    student: Student;
    checkin_staff: {
        id: number;
        name: string;
        email: string;
    } | null;
}

interface Props {
    event: Event;
    statistics: Statistics;
    participants: EventParticipant[];
    can: {
        update: boolean;
        delete: boolean;
        publish: boolean;
        cancel: boolean;
        complete: boolean;
    };
}

const props = defineProps<Props>();
console.log(props.event);
console.log('Participants:', props.participants);

const showCancelModal = ref(false);
const cancelReason = ref('');
const publishing = ref(false);
const cancelling = ref(false);
const completing = ref(false);

const isPastEndTime = computed(() => {
    return new Date(props.event.end_time) < new Date();
});

const formatDateTime = (dateTime: string) => {
    return new Date(dateTime).toLocaleString();
};

const getStatusVariant = (status: string): 'default' | 'destructive' | 'outline' | 'secondary' => {
    const variants = {
        draft: 'secondary' as const,
        published: 'default' as const,
        cancelled: 'destructive' as const,
        completed: 'outline' as const,
    };
    return variants[status as keyof typeof variants] || 'secondary';
};

const getParticipantStatusVariant = (status: string): 'default' | 'destructive' | 'outline' | 'secondary' => {
    const variants = {
        registered: 'secondary' as const,
        checked_in: 'default' as const,
        completed: 'outline' as const,
        cancelled: 'destructive' as const,
    };
    return variants[status as keyof typeof variants] || 'secondary';
};

// Participant table columns
const participantColumns: ColumnDef<EventParticipant>[] = createColumns<EventParticipant>([
    {
        accessorKey: 'student.student_id',
        header: 'Student ID',
    },
    {
        accessorKey: 'student.full_name',
        header: 'Full Name',
    },
    {
        accessorKey: 'student.email',
        header: 'Email',
    },
    {
        accessorKey: 'student.program.name',
        header: 'Program',
    },
    {
        accessorKey: 'status',
        header: 'Status',
    },
    {
        accessorKey: 'gold_awarded',
        header: 'Gold Awarded',
    },
    {
        accessorKey: 'registered_at',
        header: 'Registered At',
    },
    {
        accessorKey: 'checkin_time',
        header: 'Check-in Time',
    },
]);

const publishEvent = () => {
    publishing.value = true;
    router.post(
        route('events.publish', props.event.id),
        {},
        {
            onFinish: () => {
                publishing.value = false;
            },
        },
    );
};

const cancelEvent = () => {
    cancelling.value = true;
    router.post(
        route('events.cancel', props.event.id),
        {
            reason: cancelReason.value,
        },
        {
            onFinish: () => {
                cancelling.value = false;
                showCancelModal.value = false;
                cancelReason.value = '';
            },
        },
    );
};

const completeEvent = () => {
    completing.value = true;
    router.post(
        route('events.complete', props.event.id),
        {},
        {
            onFinish: () => {
                completing.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Event Details" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h2 class="text-xl leading-tight font-semibold text-gray-800">Event Details</h2>
            <div class="flex items-center space-x-2">
                <Link :href="route('events.index')">
                    <Button variant="outline">Back to Events</Button>
                </Link>
                <Link v-if="event.status === 'draft'" :href="route('events.edit', event.id)">
                    <Button>Edit Event</Button>
                </Link>
                <Link v-if="event.status === 'published' && !isPastEndTime" :href="route('events.scanner', event.id)">
                    <Button>Check-in</Button>
                </Link>
                <Link :href="route('events.manage-participants', event.id)">
                    <Button variant="outline">Manage Participants</Button>
                </Link>
            </div>
        </div>

        <!-- Event Header Card -->
        <Card>
            <CardHeader>
                <div class="flex items-start justify-between">
                    <div>
                        <CardTitle class="mb-2 text-2xl font-bold text-gray-900">
                            {{ event.title }}
                        </CardTitle>
                        <div class="flex gap-2">
                            <Badge :variant="getStatusVariant(event.status)">
                                {{ event.status }}
                            </Badge>
                            <Badge v-if="event.is_manual" variant="secondary">Manual Event</Badge>
                            <Badge v-if="event.is_historical" variant="outline">Historical</Badge>
                            <Badge v-if="event.requires_registration" variant="default">Registration Required</Badge>
                            <Badge v-else variant="secondary">Walk-in Allowed</Badge>
                        </div>
                    </div>
                    <div class="flex flex-col space-y-2">
                        <Button v-if="event.status === 'draft'" @click="publishEvent" :disabled="publishing" variant="default" class="bg-green-600 hover:bg-green-700">
                            <span v-if="publishing" class="mr-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            Publish Event
                        </Button>
                        <Button v-if="event.status === 'published'" @click="showCancelModal = true" variant="destructive"> Cancel Event </Button>
                        <Button v-if="event.status === 'published' && isPastEndTime" @click="completeEvent" :disabled="completing">
                            <span v-if="completing" class="mr-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                            Complete Event
                        </Button>
                    </div>
                </div>
            </CardHeader>
        </Card>

        <!-- Event Information Card -->
        <Card>
            <CardHeader>
                <CardTitle>Event Information</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Description</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ event.description || 'No description provided' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Start Time</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ formatDateTime(event.start_time) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">End Time</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ formatDateTime(event.end_time) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Location</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ event.location }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Gold Reward</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ event.gold_reward_amount }} gold per participant</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Maximum Participants</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ event.max_participants || 'Unlimited' }}
                                </dd>
                            </div>
                            <div v-if="event.creator">
                                <dt class="text-sm font-medium text-gray-500">Created By</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ event.creator.name }} ({{ event.creator.email }})</dd>
                            </div>
                            <div v-if="event.is_manual && event.created_by_admin">
                                <dt class="text-sm font-medium text-gray-500">Manual Event Created By</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ event.created_by_admin.name }} ({{ event.created_by_admin.email }})</dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h3 class="mb-4 text-lg font-medium text-gray-900">Participation Statistics</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Registered Participants</dt>
                                <dd class="mt-1 text-2xl font-semibold text-blue-600">
                                    {{ statistics.registered_count }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Checked In</dt>
                                <dd class="mt-1 text-2xl font-semibold text-green-600">
                                    {{ statistics.checked_in_count }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Completed</dt>
                                <dd class="mt-1 text-2xl font-semibold text-purple-600">
                                    {{ statistics.completed_count }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Cancelled</dt>
                                <dd class="mt-1 text-2xl font-semibold text-red-600">
                                    {{ statistics.cancelled_count }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Total Gold Distributed</dt>
                                <dd class="mt-1 text-2xl font-semibold text-yellow-600">{{ statistics.total_gold_distributed || 0 }} gold</dd>
                            </div>
                            <div v-if="statistics.participation_rate !== null">
                                <dt class="text-sm font-medium text-gray-500">Participation Rate</dt>
                                <dd class="mt-1 text-2xl font-semibold text-indigo-600">{{ statistics.participation_rate || 0 }}%</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- QR Code Card -->
        <Card v-if="event.qr_code">
            <CardHeader>
                <CardTitle>Event QR Code</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex items-center space-x-4">
                    <div class="rounded-lg bg-gray-100 p-4">
                        <div class="text-center">
                            <div class="mb-2 text-sm text-gray-600">QR Code for Check-in</div>
                            <div class="rounded border bg-white p-2 font-mono text-xs">
                                {{ event.qr_code }}
                            </div>
                        </div>
                    </div>
                    <div class="text-sm text-gray-600">
                        <p>Staff can use this QR code to check in participants during the event.</p>
                        <p class="mt-1">The QR code is automatically generated when the event is created.</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Participants List Card -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <CardTitle>Event Participants</CardTitle>
                    <div class="text-sm text-gray-500">Total: {{ participants.length }} participant{{ participants.length !== 1 ? 's' : '' }}</div>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="participants.length > 0">
                    <DataTable :data="participants" :columns="participantColumns" :show-column-toggle="true" empty-message="No participants found.">
                        <template #cell-student.student_id="{ row }">
                            {{ row.original.student.student_id }}
                        </template>

                        <template #cell-student.full_name="{ row }">
                            {{ row.original.student.full_name }}
                        </template>

                        <template #cell-student.email="{ row }">
                            {{ row.original.student.email }}
                        </template>

                        <template #cell-student.program.name="{ row }">
                            {{ row.original.student.program?.name || 'N/A' }}
                        </template>

                        <template #cell-status="{ row }">
                            <Badge :variant="getParticipantStatusVariant(row.original.status)">
                                {{ row.original.status.replace('_', ' ').toUpperCase() }}
                            </Badge>
                        </template>

                        <template #cell-gold_awarded="{ row }">
                            <span v-if="row.original.gold_awarded" class="text-green-600">✓ Yes</span>
                            <span v-else class="text-gray-400">✗ No</span>
                        </template>

                        <template #cell-registered_at="{ row }">
                            {{ new Date(row.original.registered_at).toLocaleString() }}
                        </template>

                        <template #cell-checkin_time="{ row }">
                            {{ row.original.checkin_time ? new Date(row.original.checkin_time).toLocaleString() : '-' }}
                        </template>
                    </DataTable>
                </div>
                <div v-else class="py-8 text-center text-sm text-gray-500">No participants registered for this event yet.</div>
            </CardContent>
        </Card>

        <!-- Cancel Event Modal -->
        <Dialog :open="showCancelModal" @update:open="showCancelModal = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel Event</DialogTitle>
                    <DialogDescription> Are you sure you want to cancel this event? This action cannot be undone and all registered participants will be notified. </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="cancel_reason">Reason for cancellation (optional)</Label>
                        <Textarea id="cancel_reason" v-model="cancelReason" rows="3" placeholder="Provide a reason for cancelling the event..." />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showCancelModal = false"> Keep Event </Button>
                    <Button variant="destructive" @click="cancelEvent" :disabled="cancelling">
                        <span v-if="cancelling" class="mr-2">
                            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                        Cancel Event
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
