<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { studentRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, Upload, Users } from 'lucide-vue-next';
import { ref } from 'vue';

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

interface Props {
    certificates: {
        data: IeltsCertificate[];
        links: any;
        meta: any;
    };
    filters: {
        campus_id: number | null;
        per_page: number;
    };
    options: {
        campuses: Campus[];
    };
}

const props = defineProps<Props>();

const localFilters = ref({
    campus_id: props.filters.campus_id ? String(props.filters.campus_id) : 'all',
});

// Helper to convert 'all' to undefined for API
const filterValue = (value: string) => (value === 'all' || value === '' ? undefined : value);

const applyFilters = () => {
    router.visit(studentRoutes.academicProgressionMissingDocuments(), {
        data: {
            campus_id: filterValue(localFilters.value.campus_id),
        },
        preserveState: true,
        preserveScroll: true,
    });
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, { preserveState: true, preserveScroll: true });
};

const goToStudentPlacement = (studentId: number) => {
    router.visit(studentRoutes.studentPlacement(studentId));
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
                <Badge variant="destructive" class="text-lg"> {{ certificates.meta?.total ?? certificates.data.length }} records </Badge>
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
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex items-end gap-4">
                    <div class="w-64 space-y-2">
                        <Label>Campus</Label>
                        <Select v-model="localFilters.campus_id" @update:model-value="applyFilters">
                            <SelectTrigger>
                                <SelectValue placeholder="All campuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All campuses</SelectItem>
                                <SelectItem v-for="campus in options.campuses" :key="campus.id" :value="String(campus.id)">
                                    {{ campus.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
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
                            <tr v-for="cert in certificates.data" :key="cert.id" class="hover:bg-muted/50 cursor-pointer border-b" @click="goToStudentPlacement(cert.student.id)">
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
                                    <Button size="sm" variant="outline" @click.stop="goToStudentPlacement(cert.student.id)">
                                        <Upload class="mr-1 h-4 w-4" />
                                        Upload
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Separator class="my-4" />

                <DataPagination :pagination-data="certificates" @navigate="handlePaginationNavigate" />
            </CardContent>
        </Card>
    </div>
</template>
