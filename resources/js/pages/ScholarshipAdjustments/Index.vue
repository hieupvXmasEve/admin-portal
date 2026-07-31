<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

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
    dossiers: {
        data: DossierRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: Record<string, string | number | null>;
}

defineProps<Props>();

function statusVariant(status: string) {
    if (['applied', 'no_adjustment'].includes(status)) return 'default';
    if (['cancelled', 'finance_review_required', 'student_disputed'].includes(status)) return 'destructive';
    return 'secondary';
}

function goTo(url: string | null) {
    if (url) router.visit(url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Scholarship Adjustments" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Scholarship Adjustments</h2>
            <p class="text-muted-foreground mt-1 text-sm">Per-semester scholarship adjustment dossiers — identification, interview, and maker-checker decision.</p>
        </div>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Dossiers</CardTitle>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Student</TableHead>
                        <TableHead>Source → Target</TableHead>
                        <TableHead>Campus</TableHead>
                        <TableHead>Source</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-if="dossiers.data.length === 0">
                        <TableCell colspan="6" class="text-muted-foreground text-center">No dossiers found.</TableCell>
                    </TableRow>
                    <TableRow v-for="row in dossiers.data" :key="row.id">
                        <TableCell>
                            <div class="font-medium">{{ row.student?.full_name }}</div>
                            <div class="text-muted-foreground text-xs">{{ row.student?.student_id }}</div>
                        </TableCell>
                        <TableCell>{{ row.source_semester?.name }} → {{ row.target_semester?.name }}</TableCell>
                        <TableCell>{{ row.campus?.name }}</TableCell>
                        <TableCell class="capitalize">{{ row.source }}</TableCell>
                        <TableCell>
                            <Badge :variant="statusVariant(row.status)">{{ row.status }}</Badge>
                            <Badge v-if="row.needs_data_review" variant="destructive" class="ml-1">needs review</Badge>
                        </TableCell>
                        <TableCell>
                            <Link :href="route('scholarship-adjustments.show', row.id)" class="text-primary text-sm underline">View</Link>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div class="mt-4 flex justify-center gap-2">
                <button
                    v-for="link in dossiers.links"
                    :key="link.label"
                    class="rounded px-3 py-1 text-sm"
                    :class="link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
                    :disabled="!link.url"
                    v-html="link.label"
                    @click="goTo(link.url)"
                />
            </div>
        </CardContent>
    </Card>
</template>
