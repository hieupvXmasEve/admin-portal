<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { debounce } from 'lodash-es';
import { Calendar, FileUp, Plus, Trash2 } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { route } from 'ziggy-js';

import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PaginatedResponse } from '@/types';

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
}

interface ScholarshipDefinition {
    id: number;
    code: string;
    name: string;
    type: 'percentage' | 'fixed_amount';
    amount: number | string;
    total_amount?: number | string | null;
    total_terms?: number | null;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
}

interface StudentScholarshipAward {
    id: number;
    student_id: number;
    scholarship_code: string;
    awarded_at: string;
    notes: string | null;
    student: Student;
    scholarship_definition: ScholarshipDefinition;
}

interface Scholarship {
    id: number;
    code: string;
    name: string;
}

interface Props {
    assignments: PaginatedResponse<StudentScholarshipAward>;
    scholarships: Scholarship[];
    filters: {
        search?: string;
        scholarship_code?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();

const searchForm = reactive({
    search: props.filters.search || '',
    scholarship_code: props.filters.scholarship_code || 'all',
    per_page: props.filters.per_page || 20,
});

const deleteDialogOpen = ref(false);
const assignmentToDelete = ref<StudentScholarshipAward | null>(null);

const deleteForm = useForm({});

const applyFilters = () => {
    const params = new URLSearchParams();

    // Only add params if they have meaningful values
    if (searchForm.search) params.set('search', searchForm.search);
    if (searchForm.scholarship_code && searchForm.scholarship_code !== 'all') params.set('scholarship_code', searchForm.scholarship_code);
    if (searchForm.per_page) params.set('per_page', searchForm.per_page.toString());

    const url = `/student-scholarships${params.toString() ? '?' + params.toString() : ''}`;

    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['assignments', 'filters'],
    });
};

const debouncedSearch = debounce(() => {
    applyFilters();
}, 300);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatAmount = (amount: number | string, type: string) => {
    const numericAmount = Number(amount);

    if (type === 'percentage') {
        return `${numericAmount}%`;
    }
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(numericAmount);
};

const isScholarshipExpired = (scholarship: ScholarshipDefinition): boolean => {
    return new Date() > new Date(scholarship.valid_until);
};

const getStatusVariant = (scholarship: ScholarshipDefinition): 'default' | 'destructive' | 'secondary' => {
    if (isScholarshipExpired(scholarship)) return 'destructive';
    if (!scholarship.is_active) return 'secondary';
    return 'default';
};

const getStatusLabel = (scholarship: ScholarshipDefinition): string => {
    if (isScholarshipExpired(scholarship)) return 'Expired';
    if (!scholarship.is_active) return 'Inactive';
    return 'Active';
};

const confirmDelete = (assignment: StudentScholarshipAward) => {
    assignmentToDelete.value = assignment;
    deleteDialogOpen.value = true;
};

const deleteAssignment = () => {
    if (!assignmentToDelete.value) return;

    deleteForm.delete(route('student-scholarships.destroy', assignmentToDelete.value.id), {
        onSuccess: () => {
            deleteDialogOpen.value = false;
            assignmentToDelete.value = null;
        },
    });
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['assignments'],
    });
};

const handlePageSizeChange = (pageSize: number) => {
    searchForm.per_page = pageSize;
    applyFilters();
};

// Table columns definition
const columns: ColumnDef<StudentScholarshipAward>[] = [
    {
        accessorKey: 'student',
        header: 'Student',
    },
    {
        accessorKey: 'scholarship',
        header: 'Scholarship',
    },
    {
        accessorKey: 'discount',
        header: 'Discount',
    },
    {
        accessorKey: 'awarded_at',
        header: 'Awarded Date',
    },
    {
        accessorKey: 'status',
        header: 'Status',
    },
    {
        accessorKey: 'actions',
        header: 'Actions',
    },
];
</script>

<template>
    <Head title="Student Scholarship Assignments" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Student Scholarship Assignments</h2>
            <p class="text-muted-foreground mt-1 text-sm">View and manage scholarship assignments to students</p>
        </div>
        <div class="flex gap-2">
            <Button variant="outline" as-child>
                <Link :href="route('student-scholarships.imports.index')">
                    <FileUp class="mr-2 h-4 w-4" />
                    Import
                </Link>
            </Button>
            <Button as-child>
                <Link :href="route('student-scholarships.create')">
                    <Plus class="mr-2 h-4 w-4" />
                    Assign Scholarship
                </Link>
            </Button>
        </div>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Filter Assignments</CardTitle>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="search">Search Student</Label>
                    <Input id="search" v-model="searchForm.search" type="text" placeholder="Search by name, ID, or email..." @input="debouncedSearch" />
                </div>

                <div class="space-y-2">
                    <Label for="scholarship">Scholarship</Label>
                    <Select v-model="searchForm.scholarship_code" @update:model-value="applyFilters">
                        <SelectTrigger id="scholarship">
                            <SelectValue placeholder="All Scholarships" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Scholarships</SelectItem>
                            <SelectItem v-for="scholarship in scholarships" :key="scholarship.id" :value="scholarship.code"> {{ scholarship.code }} - {{ scholarship.name }} </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </CardContent>
    </Card>

    <Card class="mt-6">
        <CardContent class="pt-6">
            <DataTable :columns="columns" :data="assignments.data">
                <template #cell-student="{ row }">
                    <Link :href="route('students.academic-summary.overview', row.original.student.id)" class="block transition-colors hover:text-blue-600">
                        <p class="font-medium">{{ row.original.student.full_name }}</p>
                        <p class="text-muted-foreground text-sm">
                            {{ row.original.student.student_id }}
                        </p>
                    </Link>
                </template>

                <template #cell-scholarship="{ row }">
                    <div>
                        <p class="font-mono font-medium">
                            {{ row.original.scholarship_definition.code }}
                        </p>
                        <p class="text-muted-foreground text-sm">
                            {{ row.original.scholarship_definition.name }}
                        </p>
                    </div>
                </template>

                <template #cell-discount="{ row }">
                    <span class="font-semibold">
                        {{ formatAmount(row.original.scholarship_definition.amount, row.original.scholarship_definition.type) }}
                    </span>
                </template>

                <template #cell-awarded_at="{ row }">
                    <div class="flex items-center gap-2">
                        <Calendar class="text-muted-foreground h-4 w-4" />
                        <span class="text-sm">{{ formatDate(row.original.awarded_at) }}</span>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <Badge :variant="getStatusVariant(row.original.scholarship_definition)">
                        {{ getStatusLabel(row.original.scholarship_definition) }}
                    </Badge>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex justify-start">
                        <Button variant="ghost" size="sm" @click="confirmDelete(row.original)">
                            <Trash2 class="text-destructive h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </DataTable>

            <DataPagination v-if="assignments.data.length > 0" :pagination-data="assignments" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" class="mt-4" />

            <div v-if="assignments.data.length === 0" class="text-muted-foreground py-8 text-center">
                <p>No scholarship assignments found.</p>
                <Button class="mt-4" as-child>
                    <Link :href="route('student-scholarships.create')"> Assign Your First Scholarship </Link>
                </Button>
            </div>
        </CardContent>
    </Card>

    <!-- Delete Confirmation Dialog -->
    <AlertDialog v-model:open="deleteDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Remove Scholarship Assignment?</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to remove the scholarship assignment for
                    <strong>{{ assignmentToDelete?.student.full_name }}</strong
                    >? This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="deleteAssignment" class="bg-destructive hover:bg-destructive/90"> Remove Assignment </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
