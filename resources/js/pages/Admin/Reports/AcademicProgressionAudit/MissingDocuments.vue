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
import { AlertCircle, Filter, RefreshCw, Search, Upload, Users } from 'lucide-vue-next';

interface Campus {
    id: number;
    name: string;
    code: string;
}

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    status: string;
    campus: Campus;
}

interface IeltsCertificate {
    id: number;
    overall_score: number;
    submitted_at: string;
    formatted_submitted_at: string;
    missing_documents: boolean;
    issue_date: string | null;
    notes: string | null;
    student: Student;
    upload_record: { id: number; url: string } | null;
}

interface MissingDocsFilters {
    search: string | null;
    per_page: number;
}

interface Props {
    certificates: PaginatedResponse<IeltsCertificate>;
    filters: MissingDocsFilters;
}

const props = defineProps<Props>();

const {
    filters,
    clearFilters,
    handlePaginationNavigate,
    handlePageSizeChange,
    updateFieldDebounced,
} = useTableFilters<MissingDocsFilters>(
    studentRoutes.academicProgressionMissingDocuments(),
    props.filters,
    ['certificates', 'filters']
);

// One-way deep link into the student's Hub Lifecycle tab, where EGC placement /
// IELTS documents are now managed (ADR-0007; placement page retired into the Hub).
const goToStudentLifecycle = (studentId: number) => {
    router.visit(studentRoutes.hub.lifecycle(studentId));
};
</script>

<template>
    <Head title="Missing IELTS Documents" />

    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Missing IELTS Documents</h1>
                <p class="text-muted-foreground">Students in Intake Course with missing IELTS file scans</p>
            </div>

            <div>
                <Badge variant="destructive" class="text-lg"> {{ certificates.total }} records </Badge>
            </div>
        </div>

        <!-- Alert Banner -->
        <Card class="border-orange-200 bg-orange-50 dark:border-orange-900 dark:bg-orange-950">
            <CardContent class="flex items-start gap-4 pt-6">
                <AlertCircle class="h-6 w-6 text-orange-500" />
                <div>
                    <h3 class="font-semibold text-orange-800 dark:text-orange-200">Action Required</h3>
                    <p class="text-sm text-orange-700 dark:text-orange-300">These students are in Intake Course but are missing their IELTS file scans. Please follow up to ensure documents are uploaded for audit compliance.</p>
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
                <CardTitle>Students with Missing Documents</CardTitle>
                <CardDescription> Click on a row to view the student's placement details and upload documents </CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="certificates.data.length === 0" class="py-12 text-center">
                    <Users class="mx-auto h-12 w-12 text-green-500" />
                    <p class="mt-4 font-medium text-green-600">All documents are complete!</p>
                    <p class="text-muted-foreground text-sm">No students are missing IELTS file scans.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-3 text-left font-medium">Student</th>
                                <th class="px-4 py-3 text-left font-medium">Campus</th>
                                <th class="px-4 py-3 text-left font-medium">IELTS Score</th>
                                <th class="px-4 py-3 text-left font-medium">Submitted At</th>
                                <th class="px-4 py-3 text-left font-medium">Issue Date</th>
                                <th class="px-4 py-3 text-left font-medium">Notes</th>
                                <th class="px-4 py-3 text-left font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cert in certificates.data" :key="cert.id" class="hover:bg-muted/50 cursor-pointer border-b" @click="goToStudentLifecycle(cert.student.id)">
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium">{{ cert.student.full_name }}</p>
                                        <p class="text-muted-foreground text-xs">{{ cert.student.student_id }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3">{{ cert.student.campus.name }}</td>
                                <td class="px-4 py-3">
                                    <Badge variant="success">{{ cert.overall_score }}</Badge>
                                </td>
                                <td class="px-4 py-3">{{ cert.formatted_submitted_at }}</td>
                                <td class="px-4 py-3">{{ cert.issue_date ?? '-' }}</td>
                                <td class="max-w-xs truncate px-4 py-3">{{ cert.notes ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <Button size="sm" variant="outline" @click.stop="goToStudentLifecycle(cert.student.id)">
                                        <Upload class="mr-1 h-4 w-4" />
                                        Open Hub
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Separator class="my-4" />

                <DataPagination :pagination-data="certificates" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
