<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import DataPagination from '@/components/DataPagination.vue'
import DataTable from '@/components/DataTable.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { PaginatedResponse } from '@/types'
import type { ColumnDef } from '@tanstack/vue-table'
import { computed, h } from 'vue'

interface Student {
    id: number
    student_id: string
    full_name: string
    program?: {
        name: string
    }
    academic_status: string
    academic_status_label: string
    status_change_date: string
}

interface Props {
    statistics: {
        total_students: number
        active_students: number
        inactive_students: number
        graduated_students: number
        suspended_students: number
        withdrawn_students: number
    }
    students?: PaginatedResponse<Student>
    filters: any
}

const props = defineProps<Props>()

const data = computed(() => props.students?.data || [])

const columns: ColumnDef<Student>[] = [
    {
        header: 'Student ID',
        accessorKey: 'student_id',
        enableSorting: false,
    },
    {
        header: 'Student Name',
        accessorKey: 'full_name',
        enableSorting: false,
        cell: ({ row }) => {
            return h('div', { class: 'font-medium' }, row.original.full_name)
        },
    },
    {
        header: 'Program',
        accessorKey: 'program.name',
        enableSorting: false,
        cell: ({ row }) => {
            const program = row.original.program
            return program ? h('span', program.name) : h('span', { class: 'text-gray-400' }, 'No program')
        },
    },
    {
        header: 'Status',
        accessorKey: 'academic_status_label',
        enableSorting: false,
        cell: ({ row }) => {
            const status = row.original.academic_status
            const colors = {
                active: 'bg-green-100 text-green-800',
                inactive: 'bg-yellow-100 text-yellow-800',
                graduated: 'bg-blue-100 text-blue-800',
                suspended: 'bg-red-100 text-red-800',
                withdrawn: 'bg-gray-100 text-gray-800',
            }
            const colorClass = colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800'
            return h('span', { class: `inline-flex items-center px-2 py-1 rounded-full text-xs ${colorClass}` }, row.original.academic_status_label)
        },
    },
    {
        header: 'Status Changed',
        accessorKey: 'status_change_date',
        enableSorting: false,
    },
]

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['students'],
    })
}

const handlePageSizeChange = (pageSize: number) => {
    console.log('Page size changed to:', pageSize)
}
</script>

<template>
    <Head title="Student Status Tracking" />
    
    <div class="space-y-6">
        <div class="bg-white shadow">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Student Status Tracking
                </h2>
            </div>
        </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <Card>
                    <CardContent class="p-4">
                        <div class="text-2xl font-bold">{{ statistics.total_students }}</div>
                        <div class="text-sm text-gray-600">Total Students</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="text-2xl font-bold text-green-600">{{ statistics.active_students }}</div>
                        <div class="text-sm text-gray-600">Active</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="text-2xl font-bold text-yellow-600">{{ statistics.inactive_students }}</div>
                        <div class="text-sm text-gray-600">Inactive</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="text-2xl font-bold text-blue-600">{{ statistics.graduated_students }}</div>
                        <div class="text-sm text-gray-600">Graduated</div>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4">
                        <div class="text-2xl font-bold text-red-600">{{ statistics.suspended_students }}</div>
                        <div class="text-sm text-gray-600">Suspended</div>
                    </CardContent>
                </Card>
            </div>

            <!-- Students by Status -->
            <Card v-if="students">
                <CardHeader>
                    <CardTitle>Students - {{ filters.status ? filters.status.charAt(0).toUpperCase() + filters.status.slice(1) : 'All' }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <DataTable :data="data" :columns="columns" />

                    <div class="mt-4" v-if="students">
                        <DataPagination 
                            :pagination-data="students" 
                            @navigate="handlePaginationNavigate" 
                            @page-size-change="handlePageSizeChange" 
                        />
                    </div>
                </CardContent>
            </Card>
    </div>
</template>
