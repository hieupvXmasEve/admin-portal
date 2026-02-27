<script setup lang="ts">
import ServerPaginatedDataTable from '@/components/tables/ServerPaginatedDataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useServerTableQuery } from '@/composables/useServerTableQuery';
import type { PaginatedResponse } from '@/types';
import type { StudentActionLog } from '@/types/student-action';
import type { StudentDecision } from '@/types/student-decision';
import { studentRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ExternalLink } from 'lucide-vue-next';

interface LinkedActionFilters {
    per_page?: number;
    page?: number;
}

interface Props {
    decision: StudentDecision;
    linkedActions: PaginatedResponse<StudentActionLog>;
    filters: LinkedActionFilters;
}

const props = defineProps<Props>();

const { handlePageChange, handlePageSizeChange } = useServerTableQuery<LinkedActionFilters>({
    baseUrl: studentRoutes.studentDecisionsShow(props.decision.id),
    initialFilters: {
        page: props.filters.page ?? 1,
        per_page: props.filters.per_page ?? 10,
    },
    emptyFilters: {
        page: 1,
        per_page: 10,
    },
    defaultValues: {
        page: 1,
        per_page: 10,
    },
    only: ['linkedActions', 'filters'],
});

const formatDateOnly = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('vi-VN');
};

const columns: ColumnDef<StudentActionLog>[] = [
    {
        header: 'Student',
        id: 'student',
        cell: ({ row }) => `${row.original.student?.student_id || '-'} - ${row.original.student?.full_name || '-'}`,
    },
    {
        header: 'Action Type',
        accessorKey: 'action_type',
    },
    {
        header: 'Changed At',
        accessorKey: 'created_at',
        cell: ({ row }) => formatDateOnly(row.original.created_at),
    },
    {
        header: 'Reason',
        accessorKey: 'reason',
    },
    {
        header: 'Details',
        id: 'details',
        cell: 'details',
    },
];
</script>

<template>
    <Head :title="`Decision ${decision.decision_number}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Decision Detail</h1>
                <p class="text-muted-foreground mt-1">{{ decision.decision_number }} - {{ decision.decision_name }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="studentRoutes.studentDecisionsIndex()">
                    <Button variant="outline">Back to List</Button>
                </Link>
                <a v-if="decision.upload_record?.url" :href="decision.upload_record.url" target="_blank" rel="noopener noreferrer">
                    <Button variant="outline">
                        <ExternalLink class="mr-2 h-4 w-4" />
                        View File
                    </Button>
                </a>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Decision Information</CardTitle>
                <CardDescription>Metadata and linked statistics.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <p class="text-muted-foreground text-sm">Signer</p>
                        <p class="font-medium">{{ decision.decision_signer }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Issued</p>
                        <p class="font-medium">{{ formatDateOnly(decision.issued_at) }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Expires</p>
                        <p class="font-medium">{{ formatDateOnly(decision.expires_at) }}</p>
                    </div>
                </div>
                <div class="mt-4 flex gap-3">
                    <Badge variant="outline">Linked Actions: {{ decision.total_linked_actions ?? 0 }}</Badge>
                    <Badge variant="outline">Linked Students: {{ decision.total_linked_students ?? 0 }}</Badge>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Linked Student Actions</CardTitle>
                <CardDescription>Open action detail from each row.</CardDescription>
            </CardHeader>
            <CardContent>
                <ServerPaginatedDataTable :data="linkedActions.data" :columns="columns" :pagination-data="linkedActions" item-name="actions" @page-change="handlePageChange" @page-size-change="handlePageSizeChange">
                    <template #cell-details="{ row }">
                        <Link :href="studentRoutes.studentStatusActionShow(row.original.id)">
                            <Button size="sm" variant="outline">View Action</Button>
                        </Link>
                    </template>
                </ServerPaginatedDataTable>
            </CardContent>
        </Card>
    </div>
</template>
