<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useGlobalDeleteDialog } from '@/composables';
import { getRoomStatusOptions, getRoomTypeOptions } from '@/schemas/room';
import type { PaginatedResponse } from '@/types';
import type { Building, Room } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { useDebounceFn } from '@vueuse/core';
import { Building2, Plus, Users, X } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    rooms: PaginatedResponse<Room>;
    filters?: {
        search?: string;
        type?: string;
        status?: string;
        building_id?: string;
        floor?: string;
        is_bookable?: boolean;
        requires_approval?: boolean;
        min_capacity?: number;
        max_capacity?: number;
    };
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

// Delete dialog composable
const deleteDialog = useGlobalDeleteDialog();

// Reactive data
const data = computed(() => props.rooms.data);

// Filter state - Initialize with props or defaults
const filters = ref({
    search: props.filters?.search || '',
    type: props.filters?.type || '',
    status: props.filters?.status || '',
    building_id: props.filters?.building_id || '',
    floor: props.filters?.floor || '',
    is_bookable: props.filters?.is_bookable,
    requires_approval: props.filters?.requires_approval,
    min_capacity: props.filters?.min_capacity?.toString() || '',
    max_capacity: props.filters?.max_capacity?.toString() || '',
    sort: '',
    direction: 'asc',
    per_page: 15,
});

// Room type and status options - Use props if available, fallback to schema
const roomTypeOptions = props.room_types || getRoomTypeOptions();
const roomStatusOptions = props.room_statuses || getRoomStatusOptions();

// Get unique buildings and floors from props, fallback to data calculation
const buildingOptions = computed(() => {
    if (props.buildings) {
        return props?.buildings ? props.buildings.map((building) => ({
            value: building.id,
            label: building.name,
        })) : [];
    }

    const buildings = new Set<string>();
    data.value.forEach((room) => {
        if (room.building) buildings.add(room.building);
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

// Server-side filtering functions
const applyFilters = (newFilters: typeof filters.value) => {
    const params = new URLSearchParams();
    // Add filters to URL params
    if (newFilters.search) params.set('search', newFilters.search);
    if (newFilters.type) params.set('type', newFilters.type);
    if (newFilters.status) params.set('status', newFilters.status);
    if (newFilters.building_id) params.set('building_id', newFilters.building_id);
    if (newFilters.floor) params.set('floor', newFilters.floor);
    if (newFilters.is_bookable !== undefined && newFilters.is_bookable !== null) params.set('is_bookable', newFilters.is_bookable.toString());
    if (newFilters.requires_approval !== undefined && newFilters.requires_approval !== null) params.set('requires_approval', newFilters.requires_approval.toString());
    if (newFilters.min_capacity) params.set('min_capacity', newFilters.min_capacity);
    if (newFilters.max_capacity) params.set('max_capacity', newFilters.max_capacity);
    if (newFilters.sort) params.set('sort', newFilters.sort);
    if (newFilters.direction) params.set('direction', newFilters.direction);
    if (newFilters.per_page) params.set('per_page', newFilters.per_page.toString());

    const url = `${systemRoutes.rooms.index()}${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['rooms', 'filters'],
    });
};

// Search handler for DebouncedInput
const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    applyFilters(filters.value);
};

// Debounced filter functions
const debouncedApplyFilters = useDebounceFn((newFilters) => {
    applyFilters(newFilters);
}, 500);

const updateSearchFilter = (value: string | number) => {
    filters.value.search = String(value);
    debouncedApplyFilters(filters.value);
};

const updateTypeFilter = (value: any) => {
    const stringValue = String(value);
    filters.value.type = stringValue === 'all' || value === null ? '' : stringValue;
    applyFilters(filters.value);
};

const updateStatusFilter = (value: any) => {
    const stringValue = String(value);
    filters.value.status = stringValue === 'all' || value === null ? '' : stringValue;
    applyFilters(filters.value);
};

const updateBuildingFilter = (value: any) => {
    const stringValue = String(value);
    filters.value.building_id = stringValue === 'all' || value === null ? '' : stringValue;
    applyFilters(filters.value);
};

const updateFloorFilter = (value: any) => {
    const stringValue = String(value);
    filters.value.floor = stringValue === 'all' || value === null ? '' : stringValue;
    applyFilters(filters.value);
};

const updateBookableFilter = (value: boolean) => {
    filters.value.is_bookable = value;
    applyFilters(filters.value);
};

const updateApprovalFilter = (value: boolean) => {
    filters.value.requires_approval = value;
    applyFilters(filters.value);
};

const updateMinCapacityFilter = (value: string | number) => {
    filters.value.min_capacity = String(value);
    debouncedApplyFilters(filters.value);
};

const updateMaxCapacityFilter = (value: string | number) => {
    filters.value.max_capacity = String(value);
    debouncedApplyFilters(filters.value);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        type: '',
        status: '',
        building: '',
        floor: '',
        is_bookable: undefined,
        requires_approval: undefined,
        min_capacity: '',
        max_capacity: '',
        sort: '',
        direction: 'asc',
        per_page: 15,
    };
    router.visit(systemRoutes.rooms.index(), {
        preserveState: true,
        preserveScroll: true,
        only: ['rooms', 'filters'],
    });
};

const hasActiveFilters = computed(() => {
    return (
        filters.value.search ||
        filters.value.type ||
        filters.value.status ||
        filters.value.building ||
        filters.value.floor ||
        (filters.value.is_bookable !== undefined && filters.value.is_bookable !== null) ||
        (filters.value.requires_approval !== undefined && filters.value.requires_approval !== null) ||
        filters.value.min_capacity ||
        filters.value.max_capacity
    );
});

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

const viewRoom = (roomId: number) => {
    router.visit(systemRoutes.rooms.show(roomId));
};

const deleteRoom = (room: Room) => {
    deleteDialog.deleteItem(room.name, 'room', () => {
        router.delete(systemRoutes.rooms.destroy(room.id), {
            preserveState: true,
            preserveScroll: true,
            only: ['rooms', 'statistics'],
            onSuccess: () => {
                toast.success("Room deleted successfully")
                console.log('Room deleted successfully');
            },
            onError: () => {
                toast.error("Room deleted errorfully");
            }
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
        id: 'info',
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

// Pagination navigation
const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['rooms'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    applyFilters(filters.value);
};
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
    <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
        <div class="min-w-[200px] flex-1">
            <DebouncedInput placeholder="Search rooms..." v-model="filters.search" @debounced="handleSearch" />
        </div>

        <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters">
            <X class="mr-2 h-4 w-4" />
            Clear Filters
        </Button>
    </div>

    <!-- Advanced Filters Section (collapsible) -->
    <details class="group">
        <summary class="hover:bg-muted/50 cursor-pointer rounded-lg border p-4 select-none">
            <span class="font-medium">Advanced Filters</span>
            <span class="text-muted-foreground ml-2 text-sm">Type, Status, Location, Capacity</span>
        </summary>

        <div class="mt-4 space-y-4 rounded-lg border p-4">
            <!-- Row 1: Type and Status Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Type Filter -->
                <div class="flex flex-col gap-1">
                    <Label class="text-muted-foreground text-xs">Type</Label>
                    <Select :model-value="filters.type || 'all'" @update:model-value="updateTypeFilter">
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
                    <Select :model-value="filters.status || 'all'" @update:model-value="updateStatusFilter">
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
            </div>

            <!-- Row 2: Location and Capacity Filters -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Building Filter -->
                <div class="flex flex-col gap-1">
                    <Label class="text-muted-foreground text-xs">Building</Label>
                    <Select :model-value="filters.building_id || 'all'" @update:model-value="updateBuildingFilter">
                        <SelectTrigger class="w-48">
                            <SelectValue placeholder="All Buildings" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Buildings</SelectItem>
                            <SelectItem v-for="option in buildingOptions" :key="option.value" :value="option.value.toString()">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Floor Filter -->
                <div class="flex flex-col gap-1">
                    <Label class="text-muted-foreground text-xs">Floor</Label>
                    <Select :model-value="filters.floor || 'all'" @update:model-value="updateFloorFilter">
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

            <!-- Row 3: Boolean Filters -->
            <!--            <div class="flex flex-wrap items-center gap-4">-->
            <!--                &lt;!&ndash; Bookable Filter &ndash;&gt;-->
            <!--                <div class="flex items-center space-x-2">-->
            <!--                    <Checkbox id="bookable" :checked="filters.is_bookable === true" @update:checked="(checked: unknown) => updateBookableFilter(checked as boolean)" />-->
            <!--                    <Label for="bookable" class="text-sm font-medium">Bookable Only</Label>-->
            <!--                </div>-->

            <!--                &lt;!&ndash; Requires Approval Filter &ndash;&gt;-->
            <!--                <div class="flex items-center space-x-2">-->
            <!--                    <Checkbox id="approval" :checked="filters.requires_approval === true" @update:checked="(checked: unknown) => updateApprovalFilter(checked as boolean)" />-->
            <!--                    <Label for="approval" class="text-sm font-medium">Requires Approval</Label>-->
            <!--                </div>-->
            <!--            </div>-->
        </div>
    </details>

    <!-- Data Table -->
    <DataTable :data="data" :columns="columns" :show-column-toggle="false">
        <template #cell-info="{ row }">
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
