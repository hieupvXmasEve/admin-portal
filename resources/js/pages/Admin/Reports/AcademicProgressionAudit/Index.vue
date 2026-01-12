<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { useTableFilters } from '@/composables/useFilters';
import type { PaginatedResponse } from '@/types';
import { formatDateToShort } from '@/utils/date';
import { studentRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, Download, FileText, Filter, RefreshCw, TrendingUp, Users } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

interface Semester {
    id: number;
    name: string;
    code: string;
}

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
    missing_documents: boolean;
}

interface ProgressionEvent {
    id: number;
    event_type: string;
    event_type_label_en: string;
    trigger_source: string;
    trigger_source_label: string;
    from_course_stage: string | null;
    to_course_stage: string | null;
    from_english_level: number | null;
    to_english_level: number | null;
    effective_at: string;
    summary: string;
    notes: string | null;
    student: Student;
    semester: Semester;
    created_by: { id: number; name: string } | null;
    ielts_certificate: IeltsCertificate | null;
}

interface Statistics {
    placement_distribution: {
        intake_pre_uni_gc: number;
        intake_course: number;
    };
    stage_changes: number;
    level_changes: Record<number, number>;
    ielts_recorded: number;
    missing_ielts_docs: number;
}

interface AuditFilters {
    semester_id: number | null;
    event_type: string | null;
    trigger_source: string | null;
    from_course_stage: string | null;
    to_course_stage: string | null;
    ielts_score_min: number | null;
    ielts_score_max: number | null;
    missing_documents: boolean | null;
    from_date: string | null;
    to_date: string | null;
    per_page: number;
}

interface Props {
    progressionEvents: PaginatedResponse<ProgressionEvent>;
    statistics: Statistics | null;
    filters: AuditFilters;
    options: {
        eventTypes: { value: string; label: string; labelEn: string }[];
        triggerSources: { value: string; label: string }[];
        semesters: Semester[];
        courseStages: { value: string; label: string }[];
    };
}

const props = defineProps<Props>();

const { filters, applyFilters, clearFilters, handlePaginationNavigate, handlePageSizeChange, updateFieldDebounced } = useTableFilters<AuditFilters>(studentRoutes.academicProgressionAudit(), props.filters, [
    'progressionEvents',
    'statistics',
    'filters',
]);

const handleExport = () => {
    if (!filters.value.semester_id) {
        toast.error('Please select a semester before exporting');
        return;
    }

    const params = new URLSearchParams({
        semester_id: String(filters.value.semester_id),
    });

    if (filters.value.event_type) params.append('event_type', filters.value.event_type);
    if (filters.value.trigger_source) params.append('trigger_source', filters.value.trigger_source);

    window.location.href = `${studentRoutes.academicProgressionExport()}?${params.toString()}`;
};

const getEventTypeBadgeVariant = (eventType: string) => {
    switch (eventType) {
        case 'PLACEMENT_INITIALIZED':
            return 'default';
        case 'ENGLISH_LEVEL_CHANGED':
            return 'secondary';
        case 'COURSE_STAGE_CHANGED':
            return 'success';
        case 'IELTS_RECORDED':
            return 'outline';
        default:
            return 'default';
    }
};

const goToStudentPlacement = (studentId: number) => {
    router.visit(studentRoutes.studentPlacement(studentId));
};
</script>

<template>
    <Head title="Academic Progression Audit" />

    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Academic Progression Audit</h1>
                <p class="text-muted-foreground">Track and audit student placement and progression events</p>
            </div>

            <div class="flex gap-2">
                <Button variant="outline" @click="handleExport" :disabled="!filters.semester_id">
                    <Download class="mr-2 h-4 w-4" />
                    Export CSV
                </Button>
            </div>
        </div>

        <!-- Statistics Cards (shown when semester is selected) -->
        <div v-if="statistics" class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium">Pre-Uni GC Placements</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ statistics.placement_distribution.intake_pre_uni_gc }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium">Intake Course Placements</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ statistics.placement_distribution.intake_course }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium">Stage Changes</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-2">
                        <TrendingUp class="h-5 w-5 text-green-500" />
                        <span class="text-2xl font-bold">{{ statistics.stage_changes }}</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium">IELTS Recorded</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-2">
                        <FileText class="h-5 w-5 text-blue-500" />
                        <span class="text-2xl font-bold">{{ statistics.ielts_recorded }}</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-muted-foreground text-sm font-medium">Missing IELTS Docs</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-2">
                        <AlertCircle class="h-5 w-5 text-orange-500" />
                        <span class="text-2xl font-bold">{{ statistics.missing_ielts_docs }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Filter class="h-5 w-5" />
                    Filters
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div class="space-y-2">
                        <Label>Semester</Label>
                        <Select :model-value="filters.semester_id?.toString() ?? 'all'" @update:model-value="(val) => applyFilters({ semester_id: val === 'all' ? null : Number(val) })">
                            <SelectTrigger>
                                <SelectValue placeholder="All semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All semesters</SelectItem>
                                <SelectItem v-for="semester in options.semesters" :key="semester.id" :value="String(semester.id)">
                                    {{ semester.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Event Type</Label>
                        <Select :model-value="filters.event_type ?? 'all'" @update:model-value="(val) => applyFilters({ event_type: val === 'all' ? null : val })">
                            <SelectTrigger>
                                <SelectValue placeholder="All types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                <SelectItem v-for="type in options.eventTypes" :key="type.value" :value="type.value">
                                    {{ type.labelEn }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Trigger Source</Label>
                        <Select :model-value="filters.trigger_source ?? 'all'" @update:model-value="(val) => applyFilters({ trigger_source: val === 'all' ? null : val })">
                            <SelectTrigger>
                                <SelectValue placeholder="All sources" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All sources</SelectItem>
                                <SelectItem v-for="source in options.triggerSources" :key="source.value" :value="source.value">
                                    {{ source.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>To Stage</Label>
                        <Select :model-value="filters.to_course_stage ?? 'all'" @update:model-value="(val) => applyFilters({ to_course_stage: val === 'all' ? null : val })">
                            <SelectTrigger>
                                <SelectValue placeholder="All stages" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All stages</SelectItem>
                                <SelectItem v-for="stage in options.courseStages" :key="stage.value" :value="stage.value">
                                    {{ stage.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>IELTS Score Range</Label>
                        <div class="flex gap-2">
                            <Input :model-value="filters.ielts_score_min ?? ''" @update:model-value="(val) => updateFieldDebounced('ielts_score_min', val ? Number(val) : null)" type="number" step="0.5" min="0" max="9" placeholder="Min" />
                            <Input :model-value="filters.ielts_score_max ?? ''" @update:model-value="(val) => updateFieldDebounced('ielts_score_max', val ? Number(val) : null)" type="number" step="0.5" min="0" max="9" placeholder="Max" />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>Date Range</Label>
                        <div class="flex gap-2">
                            <Input :model-value="filters.from_date ?? ''" @update:model-value="(val) => updateFieldDebounced('from_date', String(val))" type="date" />
                            <Input :model-value="filters.to_date ?? ''" @update:model-value="(val) => updateFieldDebounced('to_date', String(val))" type="date" />
                        </div>
                    </div>

                    <div class="flex items-end gap-2">
                        <Button variant="outline" @click="clearFilters">
                            <RefreshCw class="mr-2 h-4 w-4" />
                            Reset
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Results Table -->
        <Card>
            <CardHeader>
                <CardTitle>Progression Events</CardTitle>
                <CardDescription> {{ progressionEvents.total }} events found </CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="progressionEvents.data.length === 0" class="py-12 text-center">
                    <Users class="text-muted-foreground mx-auto h-12 w-12" />
                    <p class="text-muted-foreground mt-4">No progression events found matching your filters.</p>
                    <p class="text-muted-foreground text-sm">Try adjusting your filters or selecting a different semester.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="px-4 py-3 text-left font-medium">Student</th>
                                <th class="px-4 py-3 text-left font-medium">Event Type</th>
                                <th class="px-4 py-3 text-left font-medium">Details</th>
                                <th class="px-4 py-3 text-left font-medium">IELTS</th>
                                <th class="px-4 py-3 text-left font-medium">Semester</th>
                                <th class="px-4 py-3 text-left font-medium">Date</th>
                                <th class="px-4 py-3 text-left font-medium">By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="event in progressionEvents.data" :key="event.id" class="hover:bg-muted/50 cursor-pointer border-b" @click="goToStudentPlacement(event.student.id)">
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="font-medium">{{ event.student.full_name }}</p>
                                        <p class="text-muted-foreground text-xs">{{ event.student.student_id }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="getEventTypeBadgeVariant(event.event_type)">
                                        {{ event.event_type_label_en }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <p>{{ event.summary }}</p>
                                    <Badge variant="outline" class="mt-1">{{ event.trigger_source_label }}</Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <template v-if="event.ielts_certificate">
                                        <Badge variant="secondary">{{ event.ielts_certificate.overall_score }}</Badge>
                                        <AlertCircle v-if="event.ielts_certificate.missing_documents" class="ml-1 inline h-4 w-4 text-orange-500" />
                                    </template>
                                    <span v-else class="text-muted-foreground">-</span>
                                </td>
                                <td class="px-4 py-3">{{ event.semester.name }}</td>
                                <td class="px-4 py-3">{{ formatDateToShort(event.effective_at) }}</td>
                                <td class="px-4 py-3">{{ event.created_by?.name ?? 'System' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Separator class="my-4" />

                <DataPagination :pagination-data="progressionEvents" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" />
            </CardContent>
        </Card>
    </div>
</template>
