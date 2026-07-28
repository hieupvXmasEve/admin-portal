<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import type { PaginatedResponse } from '@/types';
import { getStudentStatusBadgeClass, getStudentStatusLabel } from '@/types/student';
import { studentRoutes } from '@/utils/routes';
import { router } from '@inertiajs/vue3';
import type { StatusRow } from './types';

defineProps<{
    statusTable: PaginatedResponse<StatusRow> | null;
    emptyMessage?: string;
}>();

const emit = defineEmits<{
    navigate: [url: string];
    pageSizeChange: [size: number];
}>();

const formatDateTime = (value: string | null): string => (value ? new Date(value).toLocaleString('vi-VN') : '-');
const formatActionType = (value: string | null): string => (value ? value.replaceAll('_', ' ') : '-');
</script>

<template>
    <div v-if="!statusTable || statusTable.data.length === 0" class="text-muted-foreground py-8 text-center">
        {{ emptyMessage ?? 'Không tìm thấy sinh viên nào khớp bộ lọc.' }}
    </div>

    <div v-else class="space-y-3">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1600px] border-collapse text-sm">
                <thead>
                    <tr class="bg-muted/40 border-b">
                        <th class="px-3 py-2 text-left font-semibold">Mã SV</th>
                        <th class="px-3 py-2 text-left font-semibold">Họ tên</th>
                        <th class="px-3 py-2 text-left font-semibold">Chương trình</th>
                        <th class="px-3 py-2 text-left font-semibold">Kỳ nhập học</th>
                        <th class="px-3 py-2 text-right font-semibold">Năm nhập học</th>
                        <th class="px-3 py-2 text-left font-semibold">Trạng thái trong kỳ</th>
                        <th class="px-3 py-2 text-left font-semibold">Trạng thái hiện tại</th>
                        <th class="px-3 py-2 text-left font-semibold">Quyết định gần nhất</th>
                        <th class="px-3 py-2 text-left font-semibold">Kỳ hiệu lực</th>
                        <th class="px-3 py-2 text-left font-semibold">Kỳ bắt đầu bảo lưu</th>
                        <th class="px-3 py-2 text-left font-semibold">Kỳ thôi học</th>
                        <th class="px-3 py-2 text-left font-semibold">Cơ sở</th>
                        <th class="px-3 py-2 text-center font-semibold text-blue-500">NE</th>
                        <th class="px-3 py-2 text-left font-semibold">Cập nhật lúc</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in statusTable.data" :key="`${row.student_id}-${row.intake_semester}`" class="hover:bg-muted/50 cursor-pointer border-b" @click="router.visit(studentRoutes.hub.lifecycle(row.id))">
                        <td class="px-3 py-2 font-medium text-blue-600 hover:underline">{{ row.student_id }}</td>
                        <td class="px-3 py-2">{{ row.full_name }}</td>
                        <td class="px-3 py-2">{{ row.program_name ?? '-' }}</td>
                        <td class="px-3 py-2">{{ row.intake_semester ?? '-' }}</td>
                        <td class="px-3 py-2 text-right">{{ row.intake_year ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <span class="rounded px-2 py-1 text-xs font-medium" :class="getStudentStatusBadgeClass(row.status_at_selected_semester)">
                                {{ getStudentStatusLabel(row.status_at_selected_semester) }}
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="rounded px-2 py-1 text-xs font-medium" :class="getStudentStatusBadgeClass(row.current_status)">
                                {{ getStudentStatusLabel(row.current_status) }}
                            </span>
                        </td>
                        <td class="px-3 py-2">{{ formatActionType(row.latest_action_type) }}</td>
                        <td class="px-3 py-2">{{ row.latest_action_effective_semester ?? '-' }}</td>
                        <td class="px-3 py-2">{{ row.defer_start_semester ?? '-' }}</td>
                        <td class="px-3 py-2">{{ row.dropout_semester ?? '-' }}</td>
                        <td class="px-3 py-2">{{ row.current_campus ?? '-' }}</td>
                        <td class="px-3 py-2 text-center font-semibold text-blue-500">{{ row.ne ? 'Yes' : 'No' }}</td>
                        <td class="px-3 py-2">{{ formatDateTime(row.updated_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <DataPagination :pagination-data="statusTable" @navigate="(url: string) => emit('navigate', url)" @page-size-change="(size: number) => emit('pageSizeChange', size)" />
    </div>
</template>
