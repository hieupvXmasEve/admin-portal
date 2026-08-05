<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

import DataPagination from '@/components/DataPagination.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { PaginatedResponse } from '@/types';
import { DOSSIER_SOURCE_LABELS, DOSSIER_STATUS_LABELS, labelFor } from './dossier-labels';

interface DossierRow {
    id: number;
    status: string;
    source: string;
    needs_data_review: boolean;
    student: { student_id: string; full_name: string } | null;
    source_semester: { name: string } | null;
    target_semester: { name: string } | null;
    campus: { name: string } | null;
}

interface Props {
    dossiers: PaginatedResponse<DossierRow>;
    filters: Record<string, string | number | null>;
    campusId: number;
}

const props = defineProps<Props>();

function statusVariant(status: string) {
    if (['applied', 'no_adjustment', 'not_applicable'].includes(status)) return 'default';
    if (['cancelled', 'finance_review_required', 'student_disputed'].includes(status)) return 'destructive';
    return 'secondary';
}

function handlePaginationNavigate(url: string) {
    router.visit(url, { preserveState: true, preserveScroll: true, only: ['dossiers'] });
}

function handlePageSizeChange(pageSize: number) {
    router.visit(route('scholarship-adjustments.index'), {
        data: { ...props.filters, per_page: pageSize },
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Điều chỉnh học bổng" />

    <div class="flex items-center justify-between">
        <Heading title="Điều chỉnh học bổng" description="Xét những sinh viên bị trượt môn, ghi nhận buổi phỏng vấn và quyết định học bổng của họ có thay đổi ở học kỳ sau hay không." />
        <Link :href="route('scholarship-adjustments.candidates.preview', { campus_id: props.campusId })">
            <Button>Tìm sinh viên cần xét</Button>
        </Link>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Sinh viên đang được xét</CardTitle>
            <CardDescription>{{ dossiers.total }} sinh viên tại cơ sở này.</CardDescription>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Sinh viên</TableHead>
                        <TableHead>Trượt ở kỳ → Áp dụng cho kỳ</TableHead>
                        <TableHead>Cơ sở</TableHead>
                        <TableHead>Nguồn</TableHead>
                        <TableHead>Giai đoạn</TableHead>
                        <TableHead />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="dossiers.data.length === 0">
                        <TableCell colspan="6" class="text-muted-foreground text-center"> Chưa có sinh viên nào đang được xét. Bấm “Tìm sinh viên cần xét” để quét một học kỳ. </TableCell>
                    </TableRow>
                    <TableRow v-for="row in dossiers.data" :key="row.id">
                        <TableCell>
                            <div class="font-medium">{{ row.student?.full_name }}</div>
                            <div class="text-muted-foreground text-xs">{{ row.student?.student_id }}</div>
                        </TableCell>
                        <TableCell>{{ row.source_semester?.name }} → {{ row.target_semester?.name }}</TableCell>
                        <TableCell>{{ row.campus?.name }}</TableCell>
                        <TableCell>{{ labelFor(DOSSIER_SOURCE_LABELS, row.source) }}</TableCell>
                        <TableCell>
                            <Badge :variant="statusVariant(row.status)">{{ labelFor(DOSSIER_STATUS_LABELS, row.status) }}</Badge>
                            <Badge v-if="row.needs_data_review" variant="destructive" class="ml-1">Cần kiểm tra điểm thủ công</Badge>
                        </TableCell>
                        <TableCell>
                            <Link :href="route('scholarship-adjustments.show', row.id)" class="text-primary text-sm underline">Mở</Link>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <DataPagination class="mt-4" :pagination-data="dossiers" item-name="students" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
        </CardContent>
    </Card>
</template>
