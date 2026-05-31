<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { usePermissions } from '@/composables/usePermissions';
import type { RoomBooking } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { Building2, Calendar, Check, Clock, Copy, Edit, Mail, Phone, User, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    booking: RoomBooking;
}>();

// Use permissions composable
const { can } = usePermissions();

// Helper to check if booking can be edited (must be pending or approved, not in the past)
const canBeEdited = computed(() => {
    const booking = props.booking;
    if (booking.status !== 'pending' && booking.status !== 'approved') {
        return false;
    }
    // Check if booking date is in the future or today
    const bookingDate = new Date(booking.booking_date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return bookingDate >= today;
});

// Helper to check if booking can be cancelled (must be pending or approved, not completed)
const canBeCancelled = computed(() => {
    const booking = props.booking;
    return booking.status === 'pending' || booking.status === 'approved';
});

// Compute permissions based on booking state and user permissions
const permissions = computed(() => ({
    can_edit: can('edit_room_booking') && canBeEdited.value,
    can_create: can('create_room_booking'),
    can_delete: can('delete_room_booking'),
    can_approve: can('approve_room_booking') && props.booking.status === 'pending',
    can_cancel: canBeCancelled.value,
}));

const showApproveDialog = ref(false);
const showRejectDialog = ref(false);
const showCancelDialog = ref(false);
const approvalNote = ref('');
const rejectionReason = ref('');
const cancelReason = ref('');
const processing = ref(false);

const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'approved':
            return 'default';
        case 'pending':
            return 'secondary';
        case 'rejected':
            return 'destructive';
        case 'cancelled':
            return 'outline';
        case 'completed':
            return 'default';
        default:
            return 'outline';
    }
};

const getPriorityBadgeVariant = (priority: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (priority) {
        case 'urgent':
            return 'destructive';
        case 'high':
            return 'secondary';
        case 'normal':
            return 'default';
        case 'low':
            return 'outline';
        default:
            return 'outline';
    }
};

const formatDate = (date: string) =>
    new Date(date).toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });

const formatTime = (time: string) => {
    const [hours, minutes] = time.split(':');
    const h = parseInt(hours);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour12 = h % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
};

const formatDateTime = (datetime: string) =>
    new Date(datetime).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

const handleApprove = () => {
    processing.value = true;
    router.post(
        systemRoutes.roomBookings.approve(props.booking.id),
        { note: approvalNote.value },
        {
            onSuccess: () => {
                toast.success('Booking approved successfully');
                showApproveDialog.value = false;
                approvalNote.value = '';
            },
            onError: () => toast.error('Failed to approve booking'),
            onFinish: () => (processing.value = false),
        },
    );
};

const handleReject = () => {
    if (!rejectionReason.value.trim()) {
        toast.error('Please provide a reason for rejection');
        return;
    }
    processing.value = true;
    router.post(
        systemRoutes.roomBookings.reject(props.booking.id),
        { reason: rejectionReason.value },
        {
            onSuccess: () => {
                toast.success('Booking rejected');
                showRejectDialog.value = false;
                rejectionReason.value = '';
            },
            onError: () => toast.error('Failed to reject booking'),
            onFinish: () => (processing.value = false),
        },
    );
};

const handleCancel = () => {
    processing.value = true;
    router.post(
        systemRoutes.roomBookings.cancel(props.booking.id),
        { reason: cancelReason.value },
        {
            onSuccess: () => {
                toast.success('Booking cancelled');
                showCancelDialog.value = false;
                cancelReason.value = '';
            },
            onError: () => toast.error('Failed to cancel booking'),
            onFinish: () => (processing.value = false),
        },
    );
};

const getActionBadgeVariant = (actionType: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (actionType) {
        case 'created':
            return 'outline';
        case 'approved':
            return 'default';
        case 'rejected':
            return 'destructive';
        case 'cancelled':
            return 'outline';
        case 'updated':
            return 'secondary';
        default:
            return 'outline';
    }
};

// Helper function to get booker display name
const getBookerDisplayName = (bookedBy: any, bookedByType?: string): string => {
    if (!bookedBy) return 'Unknown';

    const type = bookedByType || bookedBy.type || 'user';

    switch (type) {
        case 'user':
            return bookedBy.name || 'Unknown User';
        case 'student':
            return bookedBy.full_name || bookedBy.name || 'Unknown Student';
        case 'lecturer':
        case 'lecture':
            return bookedBy.full_name || bookedBy.name || 'Unknown Lecturer';
        default:
            return bookedBy.name || bookedBy.full_name || 'Unknown';
    }
};
</script>

<template>
    <Head :title="booking.title" />

    <!-- Header -->
    <div class="flex items-start justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-semibold">{{ booking.title }}</h1>
                <Badge :variant="getStatusBadgeVariant(booking.status)" class="capitalize">
                    {{ booking.status }}
                </Badge>
                <Badge :variant="getPriorityBadgeVariant(booking.priority)" class="capitalize"> {{ booking.priority }} Priority </Badge>
            </div>
            <p class="text-muted-foreground mt-1">Booking #{{ booking.id }}</p>
        </div>
        <div class="flex gap-2">
            <Button v-if="permissions?.can_create" variant="outline" @click="router.visit(systemRoutes.roomBookings.create({ source_booking_id: booking.id }))">
                <Copy class="mr-2 h-4 w-4" />
                Clone
            </Button>
            <Button v-if="permissions?.can_edit" variant="outline" @click="router.visit(systemRoutes.roomBookings.edit(booking.id))">
                <Edit class="mr-2 h-4 w-4" />
                Edit
            </Button>
            <Button v-if="permissions?.can_approve" variant="default" @click="showApproveDialog = true">
                <Check class="mr-2 h-4 w-4" />
                Approve
            </Button>
            <Button v-if="permissions?.can_approve" variant="destructive" @click="showRejectDialog = true">
                <X class="mr-2 h-4 w-4" />
                Reject
            </Button>
            <Button v-if="permissions?.can_cancel" variant="outline" @click="showCancelDialog = true"> Cancel Booking </Button>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Main Content -->
        <div class="space-y-6 lg:col-span-2">
            <!-- Room Details -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-5 w-5" />
                        Room Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label class="text-muted-foreground text-xs">Room Name</Label>
                            <p class="font-medium">{{ booking.room?.name }}</p>
                        </div>
                        <div>
                            <Label class="text-muted-foreground text-xs">Room Code</Label>
                            <p class="font-medium">{{ booking.room?.code }}</p>
                        </div>
                        <div>
                            <Label class="text-muted-foreground text-xs">Building</Label>
                            <p class="font-medium">{{ booking.room?.building?.name }}</p>
                        </div>
                        <div>
                            <Label class="text-muted-foreground text-xs">Capacity</Label>
                            <p class="font-medium">{{ booking.room?.capacity }} seats</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Date & Time -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Calendar class="h-5 w-5" />
                        Date & Time
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <Label class="text-muted-foreground text-xs">Date</Label>
                            <p class="font-medium">{{ formatDate(booking.booking_date) }}</p>
                        </div>
                        <div>
                            <Label class="text-muted-foreground text-xs">Start Time</Label>
                            <p class="font-medium">{{ formatTime(booking.start_time) }}</p>
                        </div>
                        <div>
                            <Label class="text-muted-foreground text-xs">End Time</Label>
                            <p class="font-medium">{{ formatTime(booking.end_time) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Description -->
            <Card v-if="booking.description || booking.special_requirements">
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="booking.description">
                        <Label class="text-muted-foreground text-xs">Description</Label>
                        <p class="mt-1">{{ booking.description }}</p>
                    </div>
                    <div v-if="booking.special_requirements">
                        <Label class="text-muted-foreground text-xs">Special Requirements</Label>
                        <p class="mt-1">{{ booking.special_requirements }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Rejection Reason -->
            <Card v-if="booking.status === 'rejected' && booking.rejection_reason" class="border-destructive">
                <CardHeader>
                    <CardTitle class="text-destructive">Rejection Reason</CardTitle>
                </CardHeader>
                <CardContent>
                    <p>{{ booking.rejection_reason }}</p>
                </CardContent>
            </Card>

            <!-- Activity Log -->
            <Card v-if="booking.actions?.length">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Clock class="h-5 w-5" />
                        Activity History
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="action in booking.actions" :key="action.id" class="flex items-start gap-3 border-l-2 border-gray-200 pl-4">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <Badge :variant="getActionBadgeVariant(action.action_type)" class="text-xs capitalize">
                                        {{ action.action_type }}
                                    </Badge>
                                    <span class="text-muted-foreground text-sm">by {{ action.actor_name || 'System' }}</span>
                                </div>
                                <p v-if="action.note" class="text-muted-foreground mt-1 text-sm">{{ action.note }}</p>
                                <p class="text-muted-foreground mt-1 text-xs">{{ formatDateTime(action.created_at) }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Booking Info -->
            <Card>
                <CardHeader>
                    <CardTitle>Booking Information</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <Label class="text-muted-foreground text-xs">Booking Type</Label>
                        <p class="font-medium capitalize">{{ booking.booking_type.replace('_', ' ') }}</p>
                    </div>
                    <Separator />
                    <div>
                        <Label class="text-muted-foreground text-xs">Booked By</Label>
                        <p class="font-medium">
                            {{ booking.booker_name || (booking.booked_by ? getBookerDisplayName(booking.booked_by, booking.booked_by_type) : 'Unknown') }}
                        </p>
                    </div>
                    <div v-if="booking.approved_by">
                        <Label class="text-muted-foreground text-xs">Approved By</Label>
                        <p class="font-medium">{{ booking.approved_by.name }}</p>
                        <p class="text-muted-foreground text-xs">{{ booking.approved_at ? formatDateTime(booking.approved_at) : '' }}</p>
                    </div>
                    <Separator />
                    <div>
                        <Label class="text-muted-foreground text-xs">Created</Label>
                        <p class="text-sm">{{ formatDateTime(booking.created_at) }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Contact Info -->
            <Card v-if="booking.contact_person || booking.contact_phone || booking.contact_email">
                <CardHeader>
                    <CardTitle>Contact Information</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-if="booking.contact_person" class="flex items-center gap-2">
                        <User class="text-muted-foreground h-4 w-4" />
                        <span>{{ booking.contact_person }}</span>
                    </div>
                    <div v-if="booking.contact_phone" class="flex items-center gap-2">
                        <Phone class="text-muted-foreground h-4 w-4" />
                        <span>{{ booking.contact_phone }}</span>
                    </div>
                    <div v-if="booking.contact_email" class="flex items-center gap-2">
                        <Mail class="text-muted-foreground h-4 w-4" />
                        <span>{{ booking.contact_email }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Approve Dialog -->
    <Dialog v-model:open="showApproveDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Approve Booking</DialogTitle>
                <DialogDescription>Are you sure you want to approve this booking?</DialogDescription>
            </DialogHeader>
            <div class="py-4">
                <Label>Note (Optional)</Label>
                <Textarea v-model="approvalNote" placeholder="Add a note..." class="mt-2" />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showApproveDialog = false">Cancel</Button>
                <Button @click="handleApprove" :disabled="processing">
                    {{ processing ? 'Approving...' : 'Approve' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Reject Dialog -->
    <Dialog v-model:open="showRejectDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Reject Booking</DialogTitle>
                <DialogDescription>Please provide a reason for rejecting this booking.</DialogDescription>
            </DialogHeader>
            <div class="py-4">
                <Label>Reason *</Label>
                <Textarea v-model="rejectionReason" placeholder="Enter rejection reason..." class="mt-2" />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showRejectDialog = false">Cancel</Button>
                <Button variant="destructive" @click="handleReject" :disabled="processing || !rejectionReason.trim()">
                    {{ processing ? 'Rejecting...' : 'Reject' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Cancel Dialog -->
    <Dialog v-model:open="showCancelDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Cancel Booking</DialogTitle>
                <DialogDescription>Are you sure you want to cancel this booking?</DialogDescription>
            </DialogHeader>
            <div class="py-4">
                <Label>Reason (Optional)</Label>
                <Textarea v-model="cancelReason" placeholder="Enter cancellation reason..." class="mt-2" />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showCancelDialog = false">Back</Button>
                <Button variant="destructive" @click="handleCancel" :disabled="processing">
                    {{ processing ? 'Cancelling...' : 'Cancel Booking' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
