<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTableFilters } from '@/composables/useFilters';
import type { PaginatedResponse } from '@/types';
import { Head } from '@inertiajs/vue3';
import { GraduationCap, RefreshCw, Search } from 'lucide-vue-next';
import { ref } from 'vue';
import { route } from 'ziggy-js';
import ClassifyStudentDialog from './ClassifyStudentDialog.vue';
import type { PlacementOptions, WorklistProgram, WorklistStudent } from './worklist-types';

interface WorklistFilters {
    search: string | null;
    program_id: number | null;
    per_page: number;
}

interface Props {
    students: PaginatedResponse<WorklistStudent>;
    filters: WorklistFilters;
    programs: WorklistProgram[];
    placementOptions: PlacementOptions;
}

const props = defineProps<Props>();

const { filters, clearFilters, handlePaginationNavigate, handlePageSizeChange, updateField, updateFieldDebounced } = useTableFilters<WorklistFilters>(
    route('students.placement-worklist.index'),
    props.filters,
    ['students', 'filters'],
);

const setProgram = (value: string) => updateField('program_id', value === 'all' ? null : value);

const classifyTarget = ref<WorklistStudent | null>(null);

const formatDate = (value: string | null): string => (value ? new Date(value).toLocaleDateString('vi-VN') : '—');
</script>

<template>
    <Head title="Placement Worklist" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Placement Worklist</h1>
                <p class="text-muted-foreground">Approved students waiting for EGC / major classification</p>
            </div>
            <Badge variant="secondary" class="text-lg">{{ students.total }} students</Badge>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap items-end gap-4">
                    <div class="w-full space-y-2 sm:w-64">
                        <Label>Search Student</Label>
                        <div class="relative">
                            <Search class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                            <Input
                                :model-value="filters.search ?? ''"
                                @update:model-value="(val) => updateFieldDebounced('search', String(val))"
                                placeholder="Search by name or code..."
                                class="pl-8"
                            />
                        </div>
                    </div>

                    <div class="w-full space-y-2 sm:w-64">
                        <Label>Program</Label>
                        <Select :model-value="filters.program_id ? String(filters.program_id) : 'all'" @update:model-value="(v) => setProgram(String(v))">
                            <SelectTrigger>
                                <SelectValue placeholder="All programs" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All programs</SelectItem>
                                <SelectItem v-for="program in programs" :key="program.id" :value="String(program.id)">
                                    {{ program.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Button variant="outline" @click="clearFilters">
                        <RefreshCw class="mr-2 h-4 w-4" />
                        Reset
                    </Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="pt-6">
                <div v-if="students.data.length === 0" class="text-muted-foreground py-12 text-center">
                    No unclassified students for this campus.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-3 text-left font-medium">Student Code</th>
                                <th class="px-4 py-3 text-left font-medium">Name</th>
                                <th class="px-4 py-3 text-left font-medium">Program</th>
                                <th class="px-4 py-3 text-left font-medium">Intake</th>
                                <th class="px-4 py-3 text-left font-medium">Admission Date</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="student in students.data" :key="student.id" class="border-b">
                                <td class="px-4 py-3 font-medium">{{ student.student_id }}</td>
                                <td class="px-4 py-3">
                                    <p>{{ student.full_name }}</p>
                                    <p class="text-muted-foreground text-xs">{{ student.email ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3">{{ student.program?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ student.intake_semester?.code ?? student.intake_semester?.name ?? '—' }}</td>
                                <td class="px-4 py-3">{{ formatDate(student.admission_date) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Button size="sm" @click="classifyTarget = student">
                                        <GraduationCap class="mr-2 h-4 w-4" />
                                        Classify
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <DataPagination :pagination-data="students" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>

        <ClassifyStudentDialog :student="classifyTarget" :placement-options="placementOptions" @close="classifyTarget = null" />
    </div>
</template>
