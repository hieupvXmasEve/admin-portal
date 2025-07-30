<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import Icon from '@/components/Icon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { PaginatedResponse, TeachingAssignment } from '@/types/TeachingAssignment';
import type { ColumnDef } from '@tanstack/vue-table';
import { h, ref } from 'vue';

// Props
interface Props {
    assignments: TeachingAssignment[];
    meta?: PaginatedResponse<TeachingAssignment>['meta'];
    loading?: boolean;
    totalAssignments?: number;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    totalAssignments: 0,
});

// Emits
const emit = defineEmits<{
    'assign-lecturer': [assignment: TeachingAssignment];
    'unassign-lecturer': [assignment: TeachingAssignment];
    'view-details': [assignment: TeachingAssignment];
    'page-change': [page: number];
}>();

// State
const selectedAssignments = ref<TeachingAssignment[]>([]);

// Methods
const handleSelectionChange = (selected: TeachingAssignment[]) => {
    selectedAssignments.value = selected;
};

const clearSelection = () => {
    selectedAssignments.value = [];
};

const handlePageChange = (page: number) => {
    emit('page-change', page);
};

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'assigned':
            return 'default';
        case 'unassigned':
            return 'secondary';
        case 'urgent':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getPriorityBadgeVariant = (priority: string) => {
    switch (priority) {
        case 'urgent':
            return 'destructive';
        case 'high':
            return 'destructive';
        case 'normal':
            return 'default';
        case 'assigned':
            return 'default';
        default:
            return 'outline';
    }
};

const formatSchedule = (assignment: TeachingAssignment) => {
    const { schedule } = assignment;
    if (!schedule.days.length || !schedule.time_start || !schedule.time_end) {
        return 'Not scheduled';
    }

    const days = schedule.days.join(', ');
    const time = `${schedule.time_start} - ${schedule.time_end}`;
    return `${days}\n${time}`;
};

const formatEnrollment = (assignment: TeachingAssignment) => {
    const { enrollment } = assignment;
    const percentage = enrollment.maximum > 0 ? Math.round((enrollment.current / enrollment.maximum) * 100) : 0;

    return `${enrollment.current}/${enrollment.maximum} (${percentage}%)`;
};

// Table columns
const columns: ColumnDef<TeachingAssignment>[] = [
    {
        id: 'unit',
        header: 'Unit',
        cell: ({ row }) => {
            const assignment = row.original;
            return `${assignment.unit.code}\n${assignment.unit.name}`;
        },
        size: 200,
    },
    {
        id: 'section',
        header: 'Section',
        accessorKey: 'section_code',
        cell: ({ row }) => row.original.section_code || 'N/A',
        size: 80,
    },
    {
        id: 'semester',
        header: 'Semester',
        cell: ({ row }) => row.original.semester.name,
        size: 120,
    },
    {
        id: 'lecturer',
        header: 'Lecturer',
        cell: ({ row }) => {
            const assignment = row.original;
            if (!assignment.lecturer) {
                return 'Unassigned';
            }
            return `${assignment.lecturer.display_name}\n${assignment.lecturer.employee_id}`;
        },
        size: 180,
    },
    {
        id: 'schedule',
        header: 'Schedule',
        cell: ({ row }) => formatSchedule(row.original),
        size: 150,
    },
    {
        id: 'enrollment',
        header: 'Enrollment',
        cell: ({ row }) => formatEnrollment(row.original),
        size: 120,
    },
    {
        id: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const assignment = row.original;
            return h(
                Badge,
                {
                    variant: getStatusBadgeVariant(assignment.assignment_status),
                },
                () => assignment.assignment_status_label,
            );
        },
        size: 120,
    },
    {
        id: 'priority',
        header: 'Priority',
        cell: ({ row }) => {
            const assignment = row.original;
            return h(
                Badge,
                {
                    variant: getPriorityBadgeVariant(assignment.assignment_priority),
                },
                () => assignment.assignment_priority.charAt(0).toUpperCase() + assignment.assignment_priority.slice(1),
            );
        },
        size: 100,
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const assignment = row.original;
            return h('div', { class: 'flex items-center space-x-2' }, [
                // Assign/Unassign button
                assignment.lecturer
                    ? h(
                          Button,
                          {
                              variant: 'outline',
                              size: 'sm',
                              onClick: () => emit('unassign-lecturer', assignment),
                          },
                          () => [h(Icon, { name: 'user-x', class: 'w-4 h-4 mr-1' }), 'Unassign'],
                      )
                    : h(
                          Button,
                          {
                              variant: 'default',
                              size: 'sm',
                              onClick: () => emit('assign-lecturer', assignment),
                          },
                          () => [h(Icon, { name: 'user-plus', class: 'w-4 h-4 mr-1' }), 'Assign'],
                      ),

                // View details button
                h(
                    Button,
                    {
                        variant: 'ghost',
                        size: 'sm',
                        onClick: () => emit('view-details', assignment),
                    },
                    () => [h(Icon, { name: 'eye', class: 'w-4 h-4' })],
                ),
            ]);
        },
        size: 150,
    },
];
</script>

<template>
    <div class="space-y-4">
        <!-- Table Actions -->
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-700"> Showing {{ assignments.length }} of {{ totalAssignments }} assignments </span>
            </div>

            <div class="flex items-center space-x-2">
                <!-- Bulk Actions -->
                <div v-if="selectedAssignments.length > 0" class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600"> {{ selectedAssignments.length }} selected </span>
                    <Button variant="outline" size="sm" @click="clearSelection"> Clear </Button>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <DataTable
            :data="assignments"
            :columns="columns"
            :loading="loading"
            :enable-row-selection="true"
            empty-message="No teaching assignments found. Try adjusting your filters."
            @selection-change="handleSelectionChange"
        />

        <!-- Pagination -->
        <DataPagination
            v-if="meta"
            :current-page="meta.current_page"
            :last-page="meta.last_page"
            :per-page="meta.per_page"
            :total="meta.total"
            @page-change="handlePageChange"
        />
    </div>
</template>
