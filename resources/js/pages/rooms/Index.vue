<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useGlobalConfirmDialog } from '@/composables';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { getRoomStatusOptions, getRoomTypeOptions } from '@/schemas/room';
import type { PaginatedResponse } from '@/types';
import type { Building, Room } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { useDebounceFn } from '@vueuse/core';
import { Building2, Plus, Users, X } from 'lucide-vue-next';
import { computed, h } from 'vue';
import { toast } from 'vue-sonner';

interface RoomFilters {
    search: string;
    type: string;
    status: string;
    building_id: string;
    floor: string;
    is_bookable?: boolean;
    requires_approval?: boolean;
    min_capacity: string;
    max_capacity: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
    per_page: number;
}

const props = defineProps<{
    rooms: PaginatedResponse<Room>;
    filters?: Partial<RoomFilters>;
    statistics?: {
        total: number;
        available: number;
        occupied: number;
        maintenance: number;
        bookable: number;
    };
    room_types?: Array<{ value: string; label: string }>;
    room_statuses?: Array<{ value: string; label: string }>;
    buildings?: Building[];
    floors?: string[];
    permissions?: {
        can_create: boolean;
        can_edit: boolean;
        can_delete: boolean;
    };
}>();

// Confirm dialog composable
const confirmDialog = useGlobalConfirmDialog();

// Reactive data
const data = computed(() => props.rooms.data);

// Use useInertiaFilters composable
const { filters, hasActiveFilters, clearFilters, handleSearch, handleSelectFilter, handleSortChange, handlePaginationNavigate, handlePageSizeChange, currentSort, currentDirection } = useInertiaFilters<RoomFilters>({
    baseUrl: systemRoutes.rooms.index(),
    initialFilters: {
        search: props.filters?.search || '',
        type: props.filters?.type || 'all',
        status: props.filters?.status || 'all',
        building_id: props.filters?.building_id || 'all',
        floor: props.filters?.floor || 'all',
        is_bookable: props.filters?.is_bookable,
        requires_approval: props.filters?.requires_approval,
        min_capacity: props.filters?.min_capacity?.toString() || '',
        max_capacity: props.filters?.max_capacity?.toString() || '',
        sort: props.filters?.sort || null,
        direction: (props.filters?.direction as 'asc' | 'desc') || null,
        per_page: props.filters?.per_page || 15,
    },
    defaultValues: {
        per_page: 15,
        direction: 'asc',
        type: 'all',
        status: 'all',
        building_id: 'all',
        floor: 'all',
    },
    only: ['rooms', 'filters'],
    debounce: 400,
    transform: (filters) => {
        // Transform capacity strings to numbers for server
        const transformed: Record<string, any> = { ...filters };
        if (transformed.min_capacity && transformed.min_capacity !== '') {
            transformed.min_capacity = parseInt(transformed.min_capacity as string, 10);
        } else {
            delete transformed.min_capacity;
        }
        if (transformed.max_capacity && transformed.max_capacity !== '') {
            transformed.max_capacity = parseInt(transformed.max_capacity as string, 10);
        } else {
            delete transformed.max_capacity;
        }
        // Remove empty string values
        Object.keys(transformed).forEach((key) => {
            if (transformed[key] === '' || transformed[key] === 'all' || transformed[key] === null) {
                delete transformed[key];
            }
        });
        return transformed;
    },
});

// Room type and status options - Use props if available, fallback to schema
const roomTypeOptions = props.room_types || getRoomTypeOptions();
const roomStatusOptions = props.room_statuses || getRoomStatusOptions();

// Get unique buildings and floors from props, fallback to data calculation
const buildingOptions = computed(() => {
    if (props.buildings) {
        return props.buildings.map((building) => ({
            value: building.id.toString(),
            label: building.name,
        }));
    }

    const buildings = new Set<string>();
    data.value.forEach((room) => {
        if (room.building?.name) buildings.add(room.building.name);
    });
    return Array.from(buildings)
        .sort()
        .map((building) => ({
            value: building,
            label: building,
        }));
});

const floorOptions = computed(() => {
    if (props.floors) {
        return props.floors.map((floor) => ({
            value: floor,
            label: floor,
        }));
    }

    const floors = new Set<string>();
    data.value.forEach((room) => {
        if (room.floor) floors.add(room.floor);
    });
    return Array.from(floors)
        .sort()
        .map((floor) => ({
            value: floor,
            label: floor,
        }));
});

// Debounced handlers for capacity filters (to avoid too many requests)
const debouncedApplyFilters = useDebounceFn(() => {
    // Filters will auto-sync via useInertiaFilters watchDebounced
}, 500);

const updateMinCapacityFilter = (value: string | number) => {
    filters.min_capacity = String(value);
    debouncedApplyFilters();
};

const updateMaxCapacityFilter = (value: string | number) => {
    filters.max_capacity = String(value);
    debouncedApplyFilters();
};

// Note: updateBookableFilter and updateApprovalFilter are commented out in template
// Uncomment if needed:
// const updateBookableFilter = (value: boolean) => {
//     filters.is_bookable = value;
// };
//
// const updateApprovalFilter = (value: boolean) => {
//     filters.requires_approval = value;
// };

// Helper functions
const getRoomTypeLabel = (type: string) => {
    const option = roomTypeOptions.find((opt) => opt.value === type);
    return option?.label || type;
};

const getRoomStatusLabel = (status: string) => {
    const option = roomStatusOptions.find((opt) => opt.value === status);
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

// CRUD actions
const editRoom = (roomId: number) => {
    router.visit(systemRoutes.rooms.edit(roomId));
};

const deleteRoom = (room: Room) => {
    confirmDialog.confirmDelete(room.name, 'room', () => {
        router.delete(systemRoutes.rooms.destroy(room.id), {
            preserveState: true,
            preserveScroll: true,
            only: ['rooms', 'statistics'],
            onSuccess: () => {
                toast.success('Room deleted successfully');
                console.log('Room deleted successfully');
            },
            onError: () => {
                toast.error('Room deleted errorfully');
            },
        });
    });
};

// Column definitions
const columns: ColumnDef<Room>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        enableHiding: false,
        cell: ({ row }) => {
            const currentPage = props.rooms.current_page;
            const perPage = props.rooms.per_page;
            const rowIndex = row.index;
            return (currentPage - 1) * perPage + rowIndex + 1;
        },
    },
    {
        header: 'Room',
        id: 'name',
        accessorKey: 'name',
        enableSorting: true,
        cell: ({ row }) => {
            const room = row.original;
            return {
                template: 'info',
                data: room,
            };
        },
    },
    {
        header: 'Location',
        id: 'location',
        accessorKey: 'building',
        enableSorting: true,
        cell: ({ row }) => {
            const room = row.original;
            return {
                template: 'location',
                data: room,
            };
        },
    },
    {
        header: 'Type',
        accessorKey: 'type',
        enableSorting: true,
        cell: ({ row }) => {
            const room = row.original;
            return getRoomTypeLabel(room.type);
        },
    },
    {
        header: 'Capacity',
        accessorKey: 'capacity',
        enableSorting: true,
        cell: ({ row }) => {
            const room = row.original;
            return {
                template: 'capacity',
                data: room,
            };
        },
    },
    {
        header: 'Status',
        accessorKey: 'status',
        enableSorting: true,
        cell: ({ row }) => {
            const room = row.original;
            return {
                template: 'status',
                data: room,
            };
        },
    },
    {
        header: 'Properties',
        id: 'properties',
        enableHiding: false,
        enableSorting: false,
        cell: ({ row }) => {
            const room = row.original;
            return {
                template: 'properties',
                data: room,
            };
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        enableHiding: false,
        enableSorting: false,
        cell: ({ row }) => {
            const room = row.original;
            return h('div', { class: 'flex items-center space-x-2' }, [
                // h(
                //     Button,
                //     {
                //         variant: 'ghost',
                //         size: 'sm',
                //         onClick: () => viewRoom(room.id),
                //     },
                //     () => [h(Icon, { name: 'eye', class: 'w-4 h-4 mr-1' })],
                // ),
                h(
                    Button,
                    {
                        variant: 'ghost',
                        size: 'sm',
                        onClick: () => editRoom(room.id),
                    },
                    () => [h(Icon, { name: 'edit', class: 'w-4 h-4 mr-1' })],
                ),
                h(
                    Button,
                    {
                        variant: 'ghost',
                        size: 'sm',
                        onClick: () => deleteRoom(room),
                    },
                    () => [h(Icon, { name: 'trash', class: 'w-4 h-4' })],
                ),
            ]);
        },
    },
];

// handlePaginationNavigate and handlePageSizeChange are now provided by useInertiaFilters
</script>

<template>
    <Head title="Room Management" />

    <!-- Header with Statistics and Add Room Button -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Room Management</h1>
            <div v-if="statistics" class="text-muted-foreground mt-2 flex items-center gap-4 text-sm">
                <div class="flex items-center gap-1">
                    <Building2 class="h-4 w-4" />
                    <span>{{ statistics.total }} Total</span>
                </div>
                <div class="flex items-center gap-1">
                    <Badge variant="default" class="px-2 py-0.5 text-xs">{{ statistics.available }} Available</Badge>
                </div>
                <div class="flex items-center gap-1">
                    <Badge variant="secondary" class="px-2 py-0.5 text-xs">{{ statistics.occupied }} Occupied</Badge>
                </div>
                <div class="flex items-center gap-1">
                    <Badge variant="outline" class="px-2 py-0.5 text-xs">{{ statistics.maintenance }} Maintenance</Badge>
                </div>
                <div class="flex items-center gap-1">
                    <Badge variant="default" class="px-2 py-0.5 text-xs">{{ statistics.bookable }} Bookable</Badge>
                </div>
            </div>
        </div>
        <Button @click="router.visit(systemRoutes.rooms.create())" class="flex items-center gap-2">
            <Plus class="h-4 w-4" />
            Add Room
        </Button>
    </div>

    <!-- Filters Section -->
    <div class="space-y-4 rounded-lg border p-4">
        <!-- Search and Clear -->
        <div class="flex flex-wrap items-center gap-4">
            <div class="min-w-[200px] flex-1">
                <DebouncedInput placeholder="Search rooms..." :model-value="filters.search" @update:model-value="handleSearch" />
            </div>

            <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
                <X class="mr-2 h-4 w-4" />
                Clear Filters
            </Button>
        </div>

        <!-- Row 1: Type and Status Filters -->
        <div class="flex flex-wrap items-center gap-4">
            <!-- Type Filter -->
            <div class="flex flex-col gap-1">
                <Label class="text-muted-foreground text-xs">Type</Label>
                <Select :model-value="filters.type || 'all'" @update:model-value="(v) => handleSelectFilter('type', v, 'all')">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="All Types" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Types</SelectItem>
                        <SelectItem v-for="option in roomTypeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Status Filter -->
            <div class="flex flex-col gap-1">
                <Label class="text-muted-foreground text-xs">Status</Label>
                <Select :model-value="filters.status || 'all'" @update:model-value="(v) => handleSelectFilter('status', v, 'all')">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="All Statuses" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem v-for="option in roomStatusOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Building Filter -->
            <div class="flex flex-col gap-1">
                <Label class="text-muted-foreground text-xs">Building</Label>
                <Select :model-value="filters.building_id || 'all'" @update:model-value="(v) => handleSelectFilter('building_id', v, 'all')">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="All Buildings" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Buildings</SelectItem>
                        <SelectItem v-for="option in buildingOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Floor Filter -->
            <div class="flex flex-col gap-1">
                <Label class="text-muted-foreground text-xs">Floor</Label>
                <Select :model-value="filters.floor || 'all'" @update:model-value="(v) => handleSelectFilter('floor', v, 'all')">
                    <SelectTrigger class="w-32">
                        <SelectValue placeholder="All Floors" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Floors</SelectItem>
                        <SelectItem v-for="option in floorOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Capacity Range -->
            <div class="flex flex-col gap-1">
                <Label class="text-muted-foreground text-xs">Min Capacity</Label>
                <Input :model-value="filters.min_capacity" @update:model-value="updateMinCapacityFilter" placeholder="Min" type="number" class="w-24" />
            </div>

            <div class="flex flex-col gap-1">
                <Label class="text-muted-foreground text-xs">Max Capacity</Label>
                <Input :model-value="filters.max_capacity" @update:model-value="updateMaxCapacityFilter" placeholder="Max" type="number" class="w-24" />
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns" :show-column-toggle="false" enable-server-sorting :initial-sort="currentSort" :initial-direction="currentDirection" @sort-change="handleSortChange">
        <template #cell-name="{ row }">
            <div class="font-medium">
                <div class="font-semibold">{{ row.original.name }}</div>
                <div class="text-muted-foreground text-sm">{{ row.original.code }}</div>
            </div>
        </template>

        <template #cell-location="{ row }">
            <div class="flex flex-col">
                <div class="flex items-center gap-1 font-medium">
                    <Building2 class="h-3 w-3" />
                    {{ row.original.building.name }}
                </div>
                <div class="text-muted-foreground text-sm">Floor {{ row.original.floor }}</div>
            </div>
        </template>

        <template #cell-capacity="{ row }">
            <div class="flex items-center gap-1">
                <Users class="text-muted-foreground h-4 w-4" />
                <span class="font-medium">{{ row.original.capacity }}</span>
            </div>
        </template>

        <template #cell-status="{ row }">
            <Badge :variant="getStatusBadgeVariant(row.original.status)">
                {{ getRoomStatusLabel(row.original.status) }}
            </Badge>
        </template>

        <template #cell-properties="{ row }">
            <div class="flex gap-1">
                <Badge v-if="row.original.is_bookable" variant="default" class="text-xs"> Bookable </Badge>
                <Badge v-if="row.original.requires_approval" variant="outline" class="text-xs"> Needs Approval </Badge>
            </div>
        </template>
    </DataTable>

    <!-- Pagination -->
    <DataPagination :pagination-data="rooms" item-name="rooms" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
</template>
