<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { getRoomStatusOptions, getRoomTypeOptions } from '@/schemas/room';
import type { Room } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Building2, Calendar, CheckCircle, Clock, Edit, Info, MapPin, Settings, Trash2, Users, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    room: Room | { data: Room };
    stats?: {
        total_bookings: number;
        active_bookings: number;
        upcoming_bookings: number;
        utilization_rate: number;
    };
}>();

// Extract room data (handle both direct Room object and wrapped { data: Room } structure)
const roomData = computed(() => {
    return 'data' in props.room ? props.room.data : props.room;
});

// Helper functions
const getRoomTypeLabel = (type: string) => {
    const option = getRoomTypeOptions().find((opt) => opt.value === type);
    return option?.label || type;
};

const getRoomStatusLabel = (status: string) => {
    const option = getRoomStatusOptions().find((opt) => opt.value === status);
    return option?.label || status;
};

const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'available':
            return 'default';
        case 'occupied':
            return 'secondary';
        case 'maintenance':
            return 'outline';
        case 'out_of_service':
            return 'destructive';
        case 'reserved':
            return 'secondary';
        default:
            return 'outline';
    }
};

const formatTime = (time: string | null) => {
    if (!time) return 'Not specified';

    try {
        // Parse time and format to HH:MM
        const [hours, minutes] = time.split(':');
        const date = new Date();
        date.setHours(parseInt(hours, 10), parseInt(minutes, 10));
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        });
    } catch {
        return time;
    }
};

const formatBlockedDays = (days: string[]) => {
    if (!days || days.length === 0) return 'None';

    return days.join(', ');
};

// Navigation functions
const goBack = () => {
    router.visit(systemRoutes.rooms.index());
};

const editRoom = () => {
    const id = roomData.value.id;
    if (!id) {
        console.error('Room ID is missing', roomData.value);
        return;
    }
    const roomId = Number(id);
    if (isNaN(roomId) || roomId <= 0) {
        console.error('Invalid room ID', id);
        return;
    }
    router.visit(systemRoutes.rooms.edit(roomId));
};

const deleteRoom = () => {
    if (confirm(`Are you sure you want to delete room "${roomData.value.name}"?`)) {
        router.delete(systemRoutes.rooms.destroy(roomData.value.id), {
            onSuccess: () => {
                router.visit(systemRoutes.rooms.index());
            },
        });
    }
};

// Computed properties
const roomDisplayName = computed(() => {
    return roomData.value.building ? `${roomData.value.building.name} - ${roomData.value.name}` : roomData.value.name;
});

const hasAvailabilityRestrictions = computed(() => {
    return roomData.value.available_from || roomData.value.available_until || (roomData.value.blocked_days && roomData.value.blocked_days.length > 0);
});

const hasAdditionalInfo = computed(() => {
    return roomData.value.description || roomData.value.usage_guidelines || roomData.value.booking_notes;
});
</script>

<template>
    <Head :title="`Room Details - ${roomData.name}`" />

    <!-- Header -->
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="goBack" class="h-8 w-8">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div class="flex-1">
            <h1 class="text-2xl font-semibold">{{ roomDisplayName }}</h1>
            <p class="text-muted-foreground">Room Code: {{ roomData.code }}</p>
        </div>
        <div class="flex items-center gap-2">
            <Button variant="outline" @click="editRoom" class="flex items-center gap-2">
                <Edit class="h-4 w-4" />
                Edit
            </Button>
            <Button variant="destructive" @click="deleteRoom" class="flex items-center gap-2">
                <Trash2 class="h-4 w-4" />
                Delete
            </Button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Main Information (2/3 width) -->
        <div class="space-y-6 lg:col-span-2">
            <!-- Basic Information Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building2 class="h-5 w-5" />
                        Basic Information
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="space-y-1">
                            <p class="text-muted-foreground text-sm font-medium">Room Name</p>
                            <p class="text-base">{{ roomData.name }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-muted-foreground text-sm font-medium">Room Code</p>
                            <p class="font-mono text-base">{{ roomData.code }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-muted-foreground text-sm font-medium">Building</p>
                            <p class="flex items-center gap-1 text-base">
                                <MapPin class="text-muted-foreground h-4 w-4" />
                                {{ roomData.building?.name || 'N/A' }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-muted-foreground text-sm font-medium">Floor</p>
                            <p class="text-base">{{ roomData.floor }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-muted-foreground text-sm font-medium">Type</p>
                            <p class="text-base">{{ getRoomTypeLabel(roomData.type) }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-muted-foreground text-sm font-medium">Capacity</p>
                            <p class="flex items-center gap-1 text-base">
                                <Users class="text-muted-foreground h-4 w-4" />
                                {{ roomData.capacity }} people
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
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="space-y-1">
                            <p class="text-muted-foreground text-sm font-medium">Status</p>
                            <Badge :variant="getStatusBadgeVariant(roomData.status)">
                                {{ getRoomStatusLabel(roomData.status) }}
                            </Badge>
                        </div>
                        <div class="space-y-1">
                            <p class="text-muted-foreground text-sm font-medium">Booking Settings</p>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-1">
                                    <CheckCircle v-if="roomData.is_bookable" class="h-4 w-4 text-green-500" />
                                    <XCircle v-else class="h-4 w-4 text-red-500" />
                                    <span class="text-sm">{{ roomData.is_bookable ? 'Bookable' : 'Not Bookable' }}</span>
                                </div>
                                <div v-if="roomData.is_bookable" class="flex items-center gap-1">
                                    <CheckCircle v-if="roomData.requires_approval" class="h-4 w-4 text-orange-500" />
                                    <XCircle v-else class="h-4 w-4 text-green-500" />
                                    <span class="text-sm">{{ roomData.requires_approval ? 'Needs Approval' : 'Auto Approve' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Availability Settings Card -->
            <Card v-if="hasAvailabilityRestrictions">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Clock class="h-5 w-5" />
                        Availability Settings
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-6">
                        <div class="space-y-1">
                            <p class="text-muted-foreground text-sm font-medium">Available From</p>
                            <p class="text-base">{{ formatTime(roomData.available_from) }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-muted-foreground text-sm font-medium">Available Until</p>
                            <p class="text-base">{{ formatTime(roomData.available_until) }}</p>
                        </div>
                    </div>

                    <div v-if="roomData.blocked_days && roomData.blocked_days.length > 0" class="space-y-1">
                        <p class="text-muted-foreground text-sm font-medium">Blocked Days</p>
                        <div class="flex items-center gap-1">
                            <Calendar class="text-muted-foreground h-4 w-4" />
                            <p class="text-base">{{ formatBlockedDays(roomData.blocked_days) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Additional Information Card -->
            <Card v-if="hasAdditionalInfo">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Info class="h-5 w-5" />
                        Additional Information
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="roomData.description" class="space-y-2">
                        <p class="text-muted-foreground text-sm font-medium">Description</p>
                        <p class="text-base leading-relaxed">{{ roomData.description }}</p>
                    </div>

                    <Separator v-if="roomData.description && (roomData.usage_guidelines || roomData.booking_notes)" />

                    <div v-if="roomData.usage_guidelines" class="space-y-2">
                        <p class="text-muted-foreground text-sm font-medium">Usage Guidelines</p>
                        <p class="text-base leading-relaxed">{{ roomData.usage_guidelines }}</p>
                    </div>

                    <Separator v-if="roomData.usage_guidelines && roomData.booking_notes" />

                    <div v-if="roomData.booking_notes" class="space-y-2">
                        <p class="text-muted-foreground text-sm font-medium">Booking Notes</p>
                        <p class="text-base leading-relaxed">{{ roomData.booking_notes }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Statistics Sidebar (1/3 width) -->
        <div class="space-y-6">
            <!-- Room Statistics Card -->
            <Card v-if="stats">
                <CardHeader>
                    <CardTitle class="text-lg">Usage Statistics</CardTitle>
                    <CardDescription> Room booking and utilization data </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground text-sm">Total Bookings</span>
                            <span class="font-semibold">{{ stats.total_bookings }}</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground text-sm">Active Bookings</span>
                            <Badge variant="secondary">{{ stats.active_bookings }}</Badge>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground text-sm">Upcoming</span>
                            <Badge variant="outline">{{ stats.upcoming_bookings }}</Badge>
                        </div>

                        <Separator />

                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground text-sm">Utilization Rate</span>
                            <div class="text-right">
                                <p class="font-semibold">{{ stats.utilization_rate }}%</p>
                                <div class="bg-muted mt-1 h-2 w-20 rounded-full">
                                    <div class="bg-primary h-2 rounded-full" :style="{ width: `${Math.min(stats.utilization_rate, 100)}%` }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Room Properties Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Properties</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm">Bookable</span>
                        <div class="flex items-center gap-1">
                            <CheckCircle v-if="roomData.is_bookable" class="h-4 w-4 text-green-500" />
                            <XCircle v-else class="h-4 w-4 text-red-500" />
                            <span class="text-sm">{{ roomData.is_bookable ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm">Requires Approval</span>
                        <div class="flex items-center gap-1">
                            <CheckCircle v-if="roomData.requires_approval" class="h-4 w-4 text-orange-500" />
                            <XCircle v-else class="h-4 w-4 text-green-500" />
                            <span class="text-sm">{{ roomData.requires_approval ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm">Time Restrictions</span>
                        <div class="flex items-center gap-1">
                            <CheckCircle v-if="roomData.available_from || roomData.available_until" class="h-4 w-4 text-orange-500" />
                            <XCircle v-else class="h-4 w-4 text-green-500" />
                            <span class="text-sm">{{ roomData.available_from || roomData.available_until ? 'Yes' : 'None' }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm">Blocked Days</span>
                        <div class="flex items-center gap-1">
                            <CheckCircle v-if="roomData.blocked_days && roomData.blocked_days.length > 0" class="h-4 w-4 text-orange-500" />
                            <XCircle v-else class="h-4 w-4 text-green-500" />
                            <span class="text-sm">{{ roomData.blocked_days && roomData.blocked_days.length > 0 ? roomData.blocked_days.length : 'None' }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
