<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { useTableFilters } from '@/composables/useFilters';
import type { PaginatedResponse } from '@/types';
import { studentRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, FileSignature, Filter, RefreshCw, Search } from 'lucide-vue-next';

interface Campus {
    id: number;
    name: string | null;
    code: string | null;
}

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    status: string;
    campus: Campus | null;
}

interface MissingDecisionRow {
    id: string;
    source: 'action' | 'progression';
    source_id: number;
    transition_type: string;
    transition_label: string;
    occurred_at: string | null;
    student: Student;
}

interface MissingDecisionFilters {
    search: string | null;
    per_page: number;
}

interface Props {
    transitions: PaginatedResponse<MissingDecisionRow>;
    filters: MissingDecisionFilters;
}

const props = defineProps<Props>();

const { filters, clearFilters, handlePaginationNavigate, handlePageSizeChange, updateFieldDebounced } = useTableFilters<MissingDecisionFilters>(
    studentRoutes.academicProgressionMissingDecisions(),
    props.filters,
    ['transitions', 'filters'],
);

// One-way deep link into the student's Hub Lifecycle tab, where the authorizing
// Decision is backfilled onto the transition (ADR-0007, ADR-0008).
const goToStudentLifecycle = (studentId: number) => {
    router.visit(studentRoutes.hub.lifecycle(studentId));
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return value.slice(0, 10);
};

const sourceLabel = (source: MissingDecisionRow['source']): string => (source === 'action' ? 'Status action' : 'EGC progression');
</script>

<template>
    <Head title="Missing Decisions" />

    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Missing Decisions</h1>
                <p class="text-muted-foreground">Transitions that require an authorizing decision (quyết định) but still lack one</p>
            </div>

            <div>
                <Badge variant="destructive" class="text-lg"> {{ transitions.total }} records </Badge>
            </div>
        </div>

        <!-- Alert Banner -->
        <Card class="border-orange-200 bg-orange-50 dark:border-orange-900 dark:bg-orange-950">
            <CardContent class="flex items-start gap-4 pt-6">
                <AlertCircle class="h-6 w-6 text-orange-500" />
                <div>
                    <h3 class="font-semibold text-orange-800 dark:text-orange-200">Backfill Required</h3>
                    <p class="text-sm text-orange-700 dark:text-orange-300">
                        These transitions were recorded without an authorizing decision. Attach the signed quyết định in the Decisions area to complete the
                        record — each row disappears once a decision is linked.
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Filters -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Filter class="h-5 w-5" />
                    Filters
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="w-full space-y-2 sm:w-64">
                        <Label>Search Student</Label>
                        <div class="relative">
                            <Search class="text-muted-foreground absolute left-2 top-2.5 h-4 w-4" />
                            <Input
                                :model-value="filters.search ?? ''"
                                @update:model-value="(val) => updateFieldDebounced('search', String(val))"
                                placeholder="Search by name or ID..."
                                class="pl-8"
                            />
                        </div>
                    </div>

                    <Button variant="outline" @click="clearFilters">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Reset
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Results -->
        <Card>
            <CardHeader>
                <CardTitle>Transitions Missing a Decision</CardTitle>
                <CardDescription> Drawn from both the status-action and EGC-progression streams </CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="transitions.data.length === 0" class="py-12 text-center">
                    <FileSignature class="mx-auto h-12 w-12 text-green-500" />
                    <p class="mt-4 font-medium text-green-600">Every required transition has a decision!</p>
                    <p class="text-muted-foreground text-sm">No transitions are missing an authorizing decision.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-3 text-left font-medium">Student</th>
                                <th class="px-4 py-3 text-left font-medium">Campus</th>
                                <th class="px-4 py-3 text-left font-medium">Transition</th>
                                <th class="px-4 py-3 text-left font-medium">Stream</th>
                                <th class="px-4 py-3 text-left font-medium">Occurred</th>
                                <th class="px-4 py-3 text-left font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in transitions.data" :key="row.id" class="hover:bg-muted/50 cursor-pointer border-b" @click="goToStudentLifecycle(row.student.id)">
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium text-blue-600 hover:underline">{{ row.student.full_name }}</p>
                                        <p class="text-muted-foreground text-xs">{{ row.student.student_id }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ row.student.campus?.name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ row.transition_label }}</td>
                                <td class="px-4 py-3">
                                    <Badge variant="outline">{{ sourceLabel(row.source) }}</Badge>
                                </td>
                                <td class="px-4 py-3">{{ formatDate(row.occurred_at) }}</td>
                                <td class="px-4 py-3">
                                    <Button size="sm" variant="outline" @click.stop="goToStudentLifecycle(row.student.id)">
                                        <FileSignature class="mr-1 h-4 w-4" />
                                        Attach decision
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Separator class="my-4" />

                <DataPagination :pagination-data="transitions" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
