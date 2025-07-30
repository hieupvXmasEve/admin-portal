<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { getRoomTypeOptions, getRoomStatusOptions } from '@/schemas/room';
import type { Room } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { 
    ArrowLeft, 
    Building2, 
    Users, 
    MapPin, 
    Clock, 
    Settings, 
    Info, 
    Edit, 
    Trash2,
    CheckCircle,
    XCircle,
    Calendar
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    room: Room;
    stats?: {
        total_bookings: number;
        active_bookings: number;
        upcoming_bookings: number;
        utilization_rate: number;
    };
}>();

// Helper functions
const getRoomTypeLabel = (type: string) => {
    const option = getRoomTypeOptions().find(opt => opt.value === type);
    return option?.label || type;
};

const getRoomStatusLabel = (status: string) => {
    const option = getRoomStatusOptions().find(opt => opt.value === status);
    return option?.label || status;
};

const getStatusBadgeVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'available': return 'default';
        case 'occupied': return 'secondary';
        case 'maintenance': return 'outline';
        case 'out_of_service': return 'destructive';
        case 'reserved': return 'secondary';
        default: return 'outline';
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
            hour12: false 
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
    router.visit(systemRoutes.rooms.edit(props.room.id));
};

const deleteRoom = () => {
    if (confirm(`Are you sure you want to delete room "${props.room.name}"?`)) {
        router.delete(systemRoutes.rooms.destroy(props.room.id), {
            onSuccess: () => {
                router.visit(systemRoutes.rooms.index());
            },
        });
    }
};

// Computed properties
const roomDisplayName = computed(() => {
    return props.room.building ? `${props.room.building} - ${props.room.name}` : props.room.name;
});

const hasAvailabilityRestrictions = computed(() => {
    return props.room.available_from || 
           props.room.available_until || 
           (props.room.blocked_days && props.room.blocked_days.length > 0);
});

const hasAdditionalInfo = computed(() => {
    return props.room.description || 
           props.room.usage_guidelines || 
           props.room.booking_notes;
});
</script>

<template>
    <Head :title="`Room Details - ${room.name}`" />
    
    <!-- Header -->
    <div class="flex items-center gap-4">
        <Button variant="ghost" size="icon" @click="goBack" class="h-8 w-8">
            <ArrowLeft class="h-4 w-4" />
        </Button>
        <div class="flex-1">
            <h1 class="text-2xl font-semibold">{{ roomDisplayName }}</h1>
            <p class="text-muted-foreground">Room Code: {{ room.code }}</p>
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
        <div class="lg:col-span-2 space-y-6">
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
                            <p class="text-sm font-medium text-muted-foreground">Room Name</p>
                            <p class="text-base">{{ room.name }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-muted-foreground">Room Code</p>
                            <p class="text-base font-mono">{{ room.code }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-muted-foreground">Building</p>
                            <p class="text-base flex items-center gap-1">
                                <MapPin class="h-4 w-4 text-muted-foreground" />
                                {{ room.building }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-muted-foreground">Floor</p>
                            <p class="text-base">{{ room.floor }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-muted-foreground">Type</p>
                            <p class="text-base">{{ getRoomTypeLabel(room.type) }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-muted-foreground">Capacity</p>
                            <p class="text-base flex items-center gap-1">
                                <Users class="h-4 w-4 text-muted-foreground" />
                                {{ room.capacity }} people
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
                            <p class="text-sm font-medium text-muted-foreground">Status</p>
                            <Badge :variant="getStatusBadgeVariant(room.status)">
                                {{ getRoomStatusLabel(room.status) }}
                            </Badge>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-muted-foreground">Booking Settings</p>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-1">
                                    <CheckCircle v-if="room.is_bookable" class="h-4 w-4 text-green-500" />
                                    <XCircle v-else class="h-4 w-4 text-red-500" />
                                    <span class="text-sm">{{ room.is_bookable ? 'Bookable' : 'Not Bookable' }}</span>
                                </div>
                                <div v-if="room.is_bookable" class="flex items-center gap-1">
                                    <CheckCircle v-if="room.requires_approval" class="h-4 w-4 text-orange-500" />
                                    <XCircle v-else class="h-4 w-4 text-green-500" />
                                    <span class="text-sm">{{ room.requires_approval ? 'Needs Approval' : 'Auto Approve' }}</span>
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
                            <p class="text-sm font-medium text-muted-foreground">Available From</p>
                            <p class="text-base">{{ formatTime(room.available_from) }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-muted-foreground">Available Until</p>
                            <p class="text-base">{{ formatTime(room.available_until) }}</p>
                        </div>
                    </div>
                    
                    <div v-if="room.blocked_days && room.blocked_days.length > 0" class="space-y-1">
                        <p class="text-sm font-medium text-muted-foreground">Blocked Days</p>
                        <div class="flex items-center gap-1">
                            <Calendar class="h-4 w-4 text-muted-foreground" />
                            <p class="text-base">{{ formatBlockedDays(room.blocked_days) }}</p>
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
                    <div v-if="room.description" class="space-y-2">
                        <p class="text-sm font-medium text-muted-foreground">Description</p>
                        <p class="text-base leading-relaxed">{{ room.description }}</p>
                    </div>

                    <Separator v-if="room.description && (room.usage_guidelines || room.booking_notes)" />

                    <div v-if="room.usage_guidelines" class="space-y-2">
                        <p class="text-sm font-medium text-muted-foreground">Usage Guidelines</p>
                        <p class="text-base leading-relaxed">{{ room.usage_guidelines }}</p>
                    </div>

                    <Separator v-if="room.usage_guidelines && room.booking_notes" />

                    <div v-if="room.booking_notes" class="space-y-2">
                        <p class="text-sm font-medium text-muted-foreground">Booking Notes</p>
                        <p class="text-base leading-relaxed">{{ room.booking_notes }}</p>
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
                    <CardDescription>
                        Room booking and utilization data
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Total Bookings</span>
                            <span class="font-semibold">{{ stats.total_bookings }}</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Active Bookings</span>
                            <Badge variant="secondary">{{ stats.active_bookings }}</Badge>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Upcoming</span>
                            <Badge variant="outline">{{ stats.upcoming_bookings }}</Badge>
                        </div>
                        
                        <Separator />
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-muted-foreground">Utilization Rate</span>
                            <div class="text-right">
                                <p class="font-semibold">{{ stats.utilization_rate }}%</p>
                                <div class="w-20 h-2 bg-muted rounded-full mt-1">
                                    <div 
                                        class="h-2 bg-primary rounded-full" 
                                        :style="{ width: `${Math.min(stats.utilization_rate, 100)}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Quick Actions Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-lg">Quick Actions</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <Button @click="editRoom" class="w-full justify-start" variant="outline">
                        <Edit class="mr-2 h-4 w-4" />
                        Edit Room Details
                    </Button>
                    
                    <Button class="w-full justify-start" variant="outline" disabled>
                        <Calendar class="mr-2 h-4 w-4" />
                        View Bookings
                    </Button>
                    
                    <Button class="w-full justify-start" variant="outline" disabled>
                        <Clock class="mr-2 h-4 w-4" />
                        Room Schedule
                    </Button>
                    
                    <Separator />
                    
                    <Button @click="deleteRoom" class="w-full justify-start" variant="destructive">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete Room
                    </Button>
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
                            <CheckCircle v-if="room.is_bookable" class="h-4 w-4 text-green-500" />
                            <XCircle v-else class="h-4 w-4 text-red-500" />
                            <span class="text-sm">{{ room.is_bookable ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm">Requires Approval</span>
                        <div class="flex items-center gap-1">
                            <CheckCircle v-if="room.requires_approval" class="h-4 w-4 text-orange-500" />
                            <XCircle v-else class="h-4 w-4 text-green-500" />
                            <span class="text-sm">{{ room.requires_approval ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm">Time Restrictions</span>
                        <div class="flex items-center gap-1">
                            <CheckCircle v-if="room.available_from || room.available_until" class="h-4 w-4 text-orange-500" />
                            <XCircle v-else class="h-4 w-4 text-green-500" />
                            <span class="text-sm">{{ (room.available_from || room.available_until) ? 'Yes' : 'None' }}</span>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm">Blocked Days</span>
                        <div class="flex items-center gap-1">
                            <CheckCircle v-if="room.blocked_days && room.blocked_days.length > 0" class="h-4 w-4 text-orange-500" />
                            <XCircle v-else class="h-4 w-4 text-green-500" />
                            <span class="text-sm">{{ (room.blocked_days && room.blocked_days.length > 0) ? room.blocked_days.length : 'None' }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>