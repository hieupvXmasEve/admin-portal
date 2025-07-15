<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import DataPagination from '@/components/DataPagination.vue'
import DataTable from '@/components/DataTable.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { PaginatedResponse } from '@/types'
import type { ColumnDef } from '@tanstack/vue-table'
import { computed, h } from 'vue'

interface Hold {
    id: number
    student: {
        id: number
        student_id: string
        full_name: string
    }
    hold_type: string
    reason: string
    status: string
    created_at: string
}

interface Props {
    holds: PaginatedResponse<Hold>
    filters: any
}

const props = defineProps<Props>()

const data = computed(() => props.holds.data)

const columns: ColumnDef<Hold>[] = [
    {
        header: 'Student ID',
        accessorKey: 'student.student_id',
        enableSorting: false,
    },
    {
        header: 'Student Name',
        accessorKey: 'student.full_name',
        enableSorting: false,
        cell: ({ row }) => {
            const student = row.original.student
            return h('div', { class: 'font-medium' }, student.full_name)
        },
    },
    {
        header: 'Hold Type',
        accessorKey: 'hold_type',
        enableSorting: false,
    },
    {
        header: 'Reason',
        accessorKey: 'reason',
        enableSorting: false,
    },
    {
        header: 'Status',
        accessorKey: 'status',
        enableSorting: false,
        cell: ({ row }) => {
            const status = row.original.status
            const colors = {
                active: 'bg-red-100 text-red-800',
                resolved: 'bg-green-100 text-green-800',
            }
            const colorClass = colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800'
            return h('span', { class: `inline-flex items-center px-2 py-1 rounded-full text-xs ${colorClass}` }, status)
        },
    },
    {
        header: 'Created At',
        accessorKey: 'created_at',
        enableSorting: false,
    },
]

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['holds'],
    })
}

const handlePageSizeChange = (pageSize: number) => {
    console.log('Page size changed to:', pageSize)
}
</script>

<template>
    <Head title="Academic Holds" />
    
    <div class="space-y-6">
        <div class="bg-white shadow">
            <div class="px-4 py-5 sm:p-6">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Academic Holds Management
                </h2>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Academic Holds</CardTitle>
                <CardDescription>
                    Track and manage student academic holds
                </CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable :data="data" :columns="columns" />
                
                <div class="mt-4">
                    <DataPagination 
                        :pagination-data="holds" 
                        @navigate="handlePaginationNavigate" 
                        @page-size-change="handlePageSizeChange" 
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>