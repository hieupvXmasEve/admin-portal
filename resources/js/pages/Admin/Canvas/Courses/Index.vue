<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxTrigger, ComboboxViewport } from '@/components/ui/combobox';
import ComboboxListInline from '@/components/ui/combobox/ComboboxListInline.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useApi } from '@/composables/useApiRequest';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import type { PaginatedResponse } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ColumnDef } from '@tanstack/vue-table';
import { AlertCircle, Award, BarChart3, Check, CheckCircle2, ChevronsUpDown, Clock, ExternalLink, EyeOff, Link2, Link2Off, ListChecks, MoreHorizontal, RefreshCw, Search } from 'lucide-vue-next';
import { computed, h, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface CanvasCourseMapping {
    id: number;
    canvas_course_id: string;
    canvas_course_code: string | null;
    canvas_course_name: string | null;
    canvas_url: string | null;
    sync_status: 'pending' | 'mapped' | 'ignored';
    is_mapped: boolean;
    is_ignored: boolean;
    last_synced_at: string | null;
    course_offering: {
        id: number;
        course_code: string;
        course_title: string;
        section_code: string;
        semester: {
            id: number;
            name: string;
            code: string;
        };
    } | null;
    created_at: string;
    updated_at: string;
}

interface CanvasIntegration {
    id: number;
    canvas_url: string;
    has_token: boolean;
    has_refresh_token: boolean;
    sync_status: string;
    last_sync_at: string | null;
}

interface CourseOffering {
    id: number;
    label: string;
    course_code: string;
    course_title: string;
    section_code: string | null;
    semester: string;
    semester_id: number;
}

interface SemesterOption {
    id: number;
    name: string;
    code: string;
}

interface Props {
    mappings: PaginatedResponse<CanvasCourseMapping>;
    filters: {
        search?: string;
        sync_status?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
    integration: CanvasIntegration | null;
    semesters: SemesterOption[];
}

const props = defineProps<Props>();
const { showConfirmDialog } = useGlobalConfirmDialog();
const api = useApi();

const filters = ref({
    search: props.filters.search || '',
    sync_status: props.filters.sync_status || 'all',
    sort: props.filters.sort || 'created_at',
    direction: props.filters.direction || 'desc',
    per_page: props.filters.per_page || 15,
});

const showMappingDialog = ref(false);
const selectedMapping = ref<CanvasCourseMapping | null>(null);
const selectedCourseOffering = ref<CourseOffering | null>(null);
const selectedMappingSemester = ref('all');
const searchCourseOffering = ref('');
const availableCourseOfferings = ref<CourseOffering[]>([]);
const isLoadingCourseOfferings = ref(false);
let courseOfferingSearchTimer: ReturnType<typeof setTimeout> | null = null;

// Sync Syllabus

const selectedMappingForSync = ref<CanvasCourseMapping | null>(null);

// Assignment sync state
const syncAssignmentsDialogOpen = ref(false);
const syncAssignmentsLoading = ref(false);
const assignmentsSyncSummary = ref<any>(null);
const selectedGroups = ref<string[]>([]);

// Grade sync state
const syncGradesDialogOpen = ref(false);
const syncGradesLoading = ref(false);
const gradesSyncSummary = ref<any>(null);

const updateFilters = () => {
    const filterParams = {
        search: filters.value.search || undefined,
        sync_status: filters.value.sync_status === 'all' ? undefined : filters.value.sync_status,
        sort: filters.value.sort,
        direction: filters.value.direction,
        per_page: filters.value.per_page,
    };

    router.get('/admin/canvas/courses', filterParams, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    updateFilters();
};

const handlePageChange = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handlePageSizeChange = (pageSize: number) => {
    filters.value.per_page = pageSize;
    updateFilters();
};

const syncCourses = () => {
    if (!props.integration) {
        toast.error('No integration found');
        return;
    }

    // Only require manual auth if no token AND no refresh token
    if (!props.integration.has_token && !props.integration.has_refresh_token) {
        toast.error('Please authorize Canvas integration first');
        router.visit('/admin/canvas/integrations');
        return;
    }

    router.post(
        `/admin/canvas/sync/${props.integration.id}`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Course sync started');
            },
            onError: () => {
                toast.error('Failed to start sync');
            },
        },
    );
};

const openMappingDialog = async (mapping: CanvasCourseMapping) => {
    selectedMapping.value = mapping;
    selectedCourseOffering.value = mapping.course_offering?.id
        ? {
              id: mapping.course_offering.id,
              label: `${mapping.course_offering.course_code} - ${mapping.course_offering.course_title} (Section ${mapping.course_offering.section_code ?? 'N/A'}, ${mapping.course_offering.semester?.code ?? mapping.course_offering.semester?.name ?? 'N/A'})`,
              course_code: mapping.course_offering.course_code,
              course_title: mapping.course_offering.course_title,
              section_code: mapping.course_offering.section_code,
              semester: mapping.course_offering.semester?.name ?? '',
              semester_id: mapping.course_offering.semester?.id ?? 0,
          }
        : null;
    selectedMappingSemester.value = mapping.course_offering?.semester?.id ? String(mapping.course_offering.semester.id) : 'all';
    searchCourseOffering.value = '';
    showMappingDialog.value = true;

    await loadCourseOfferings();
};

const handleCourseOfferingSelect = (value: unknown) => {
    if (value && typeof value === 'object' && 'id' in value) {
        selectedCourseOffering.value = value as CourseOffering;
        searchCourseOffering.value = '';
        return;
    }

    selectedCourseOffering.value = null;
};

const getCourseOfferingDisplayValue = (value: unknown) => {
    return value && typeof value === 'object' && 'label' in value ? String(value.label ?? '') : '';
};

const loadCourseOfferings = async () => {
    isLoadingCourseOfferings.value = true;
    try {
        const params = new URLSearchParams();

        if (searchCourseOffering.value.trim()) {
            params.set('search', searchCourseOffering.value.trim());
        }

        if (selectedMappingSemester.value !== 'all') {
            params.set('semester_id', selectedMappingSemester.value);
        }

        const queryString = params.toString();
        const response = await fetch(`${route('admin.canvas.api.course-offerings')}${queryString ? `?${queryString}` : ''}`);

        if (!response.ok) {
            throw new Error('Course offerings request failed');
        }

        const data = await response.json();
        availableCourseOfferings.value = data;
    } catch (error) {
        toast.error('Failed to load course offerings');
        console.error(error);
    } finally {
        isLoadingCourseOfferings.value = false;
    }
};

watch([searchCourseOffering, selectedMappingSemester], () => {
    if (!showMappingDialog.value) {
        return;
    }

    if (selectedCourseOffering.value && selectedMappingSemester.value !== 'all' && String(selectedCourseOffering.value.semester_id) !== selectedMappingSemester.value) {
        selectedCourseOffering.value = null;
    }

    if (courseOfferingSearchTimer) {
        clearTimeout(courseOfferingSearchTimer);
    }

    courseOfferingSearchTimer = setTimeout(() => {
        loadCourseOfferings();
    }, 300);
});

watch(showMappingDialog, (isOpen) => {
    if (isOpen) {
        return;
    }

    if (courseOfferingSearchTimer) {
        clearTimeout(courseOfferingSearchTimer);
        courseOfferingSearchTimer = null;
    }

    selectedMapping.value = null;
    selectedCourseOffering.value = null;
    selectedMappingSemester.value = 'all';
    searchCourseOffering.value = '';
});

const mapCourse = () => {
    if (!selectedMapping.value || !selectedCourseOffering.value) {
        toast.error('Please select a course offering');
        return;
    }

    router.post(
        '/admin/canvas/courses/map',
        {
            mapping_id: selectedMapping.value.id,
            course_offering_id: selectedCourseOffering.value.id,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Course mapped successfully');
                showMappingDialog.value = false;
                selectedMapping.value = null;
                selectedCourseOffering.value = null;
                selectedMappingSemester.value = 'all';
                searchCourseOffering.value = '';
            },
            onError: () => {
                toast.error('Failed to map course');
            },
        },
    );
};

const unmapCourse = (mapping: CanvasCourseMapping) => {
    showConfirmDialog(
        {
            title: 'Unmap Course',
            message: 'Are you sure you want to unmap this Canvas course?',
            confirmText: 'Unmap',
        },
        {
            onConfirm: () => {
                router.post(
                    `/admin/canvas/courses/${mapping.id}/unmap`,
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            toast.success('Course unmapped successfully');
                        },
                        onError: () => {
                            toast.error('Failed to unmap course');
                        },
                    },
                );
            },
        },
    );
};

const ignoreCourse = (mapping: CanvasCourseMapping) => {
    showConfirmDialog(
        {
            title: 'Ignore Course',
            message: 'Are you sure you want to ignore this Canvas course?',
            confirmText: 'Ignore',
        },
        {
            onConfirm: () => {
                router.post(
                    `/admin/canvas/courses/${mapping.id}/ignore`,
                    {},
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            toast.success('Course marked as ignored');
                        },
                        onError: () => {
                            toast.error('Failed to ignore course');
                        },
                    },
                );
            },
        },
    );
};

const getSyncStatusBadge = (status: string) => {
    const variants: Record<string, any> = {
        pending: { variant: 'secondary', icon: Clock, text: 'Pending' },
        mapped: { variant: 'success', icon: CheckCircle2, text: 'Mapped' },
        ignored: { variant: 'outline', icon: EyeOff, text: 'Ignored' },
    };
    return variants[status] || variants.pending;
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never';
    return new Date(dateString).toLocaleString();
};

// Weight validation computed
const selectedWeight = computed(() => {
    if (!assignmentsSyncSummary.value?.groups) return 0;
    return assignmentsSyncSummary.value.groups.filter((g: any) => selectedGroups.value.includes(g.id)).reduce((sum: number, g: any) => sum + g.weight, 0);
});

const isWeightValid = computed(() => {
    const required = assignmentsSyncSummary.value?.total_weight_required || 100;
    return Math.abs(selectedWeight.value - required) < 0.01;
});

const weightValidationMessage = computed(() => {
    const required = assignmentsSyncSummary.value?.total_weight_required || 100;
    const diff = selectedWeight.value - required;
    if (Math.abs(diff) < 0.01) return 'Valid';
    if (diff > 0) return `Too much by ${diff.toFixed(1)}%`;
    return `Need ${Math.abs(diff).toFixed(1)}% more`;
});

// Assignment sync functions
const openSyncAssignmentsDialog = async (mapping: CanvasCourseMapping) => {
    selectedMappingForSync.value = mapping;
    syncAssignmentsDialogOpen.value = true;
    selectedGroups.value = [];

    try {
        const { data, error } = await api.get(route('admin.canvas.courses.assignments.sync-summary', mapping.id));

        if (error.value) {
            toast.error('Failed to load sync summary');
            syncAssignmentsDialogOpen.value = false;
            return;
        }

        assignmentsSyncSummary.value = data.value;

        // Auto-select already synced groups
        if (data.value && 'groups' in data.value && Array.isArray(data.value.groups)) {
            selectedGroups.value = data.value.groups.filter((g: any) => g.is_currently_synced).map((g: any) => g.id);
        }
    } catch {
        toast.error('Failed to load sync summary');
        syncAssignmentsDialogOpen.value = false;
    }
};

const syncAssignments = async () => {
    if (!selectedMappingForSync.value) return;

    if (!isWeightValid.value) {
        const required = assignmentsSyncSummary.value?.total_weight_required || 100;
        toast.error('Please select assignment groups that total ' + required + '%');
        return;
    }

    syncAssignmentsLoading.value = true;

    router.post(
        route('admin.canvas.courses.sync-assignments', selectedMappingForSync.value.id),
        {
            selected_group_ids: selectedGroups.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Assignments synced successfully');
                closeSyncAssignmentsDialog();
            },
            onError: (errors) => {
                toast.error((errors as any).error || 'Failed to sync assignments');
            },
            onFinish: () => {
                syncAssignmentsLoading.value = false;
            },
        },
    );
};

const closeSyncAssignmentsDialog = () => {
    syncAssignmentsDialogOpen.value = false;
    selectedMappingForSync.value = null;
    assignmentsSyncSummary.value = null;
    selectedGroups.value = [];
};

const toggleGroupSelection = (groupId: string) => {
    const index = selectedGroups.value.indexOf(groupId);
    if (index > -1) {
        selectedGroups.value.splice(index, 1);
    } else {
        selectedGroups.value.push(groupId);
    }
};

const selectAllGroups = () => {
    if (!assignmentsSyncSummary.value?.groups) return;
    selectedGroups.value = assignmentsSyncSummary.value.groups.map((g: any) => g.id);
};

const deselectAllGroups = () => {
    selectedGroups.value = [];
};

const allGroupsSelected = computed(() => {
    if (!assignmentsSyncSummary.value?.groups) return false;
    return selectedGroups.value.length === assignmentsSyncSummary.value.groups.length;
});

// Grade sync functions
const openSyncGradesDialog = async (mapping: CanvasCourseMapping) => {
    selectedMappingForSync.value = mapping;
    syncGradesDialogOpen.value = true;

    try {
        const { data, error } = await api.get(route('admin.canvas.courses.grades.sync-summary', mapping.id));

        if (error.value) {
            toast.error('Failed to load sync summary');
            syncGradesDialogOpen.value = false;
            return;
        }

        gradesSyncSummary.value = data.value;
    } catch {
        toast.error('Failed to load sync summary');
        syncGradesDialogOpen.value = false;
    }
};

const syncGrades = async () => {
    if (!selectedMappingForSync.value) return;

    syncGradesLoading.value = true;

    try {
        const { data, error } = await api.post(route('admin.canvas.courses.sync-grades', selectedMappingForSync.value.id), {});

        if (error.value) {
            toast.error('Failed to sync grades');
            syncGradesLoading.value = false;
            return;
        }

        // Store detailed results
        gradesSyncSummary.value = {
            ...gradesSyncSummary.value,
            sync_completed: true,
            sync_results: data.value,
        };

        toast.success(`Grades synced! ${data.value && 'students_synced' in data.value ? data.value.students_synced : 0} students processed`);
    } catch {
        toast.error('Failed to sync grades');
    } finally {
        syncGradesLoading.value = false;
    }
};

const closeSyncGradesDialog = () => {
    syncGradesDialogOpen.value = false;
    selectedMappingForSync.value = null;
    gradesSyncSummary.value = null;
};

const openCanvasUrl = (url: string) => {
    window.open(url, '_blank');
};

const columns: ColumnDef<CanvasCourseMapping>[] = [
    {
        accessorKey: 'canvas_course_code',
        header: 'Canvas Code',
        size: 150,
        minSize: 150,
        maxSize: 150,
        cell: ({ row }) => {
            const mapping = row.original;
            return h('div', { class: 'font-medium' }, mapping.canvas_course_code || 'N/A');
        },
    },
    {
        accessorKey: 'canvas_course_name',
        header: 'Canvas Course',
        cell: ({ row }) => {
            const mapping = row.original;
            return h('div', { class: 'max-w-[300px]' }, [h('div', { class: 'font-medium truncate' }, mapping.canvas_course_name || ''), h('div', { class: 'text-xs text-muted-foreground' }, `ID: ${mapping.canvas_course_id}`)]);
        },
    },
    {
        accessorKey: 'sync_status',
        header: 'Status',
        cell: ({ row }) => {
            const mapping = row.original;
            const badge = getSyncStatusBadge(mapping.sync_status);
            return h(Badge, { variant: badge.variant, class: 'flex items-center gap-1 w-fit' }, () => [h(badge.icon, { class: 'h-3 w-3' }), badge.text]);
        },
    },
    {
        accessorKey: 'course_offering',
        header: 'Mapped To',
        cell: ({ row }) => {
            const mapping = row.original;
            if (!mapping.course_offering || !mapping.course_offering.id) {
                return h('span', { class: 'text-muted-foreground text-sm' }, '—');
            }
            const offering = mapping.course_offering;
            const courseUrl = `/course-offerings/${offering.id}`;

            return h('div', {}, [
                h(
                    Link,
                    {
                        href: courseUrl,
                        class: 'text-primary font-medium hover:underline transition-colors',
                    },
                    () => `${offering.course_code || 'N/A'} - ${offering.course_title || 'N/A'}`,
                ),
                h('div', { class: 'text-xs text-muted-foreground' }, `Section ${offering.section_code || 'N/A'}${offering.semester?.name ? ' • ' + offering.semester.name : ''}`),
            ]);
        },
    },
    {
        accessorKey: 'statistics',
        header: 'Statistics',
        cell: ({ row }) => {
            const mapping = row.original;
            if (!mapping.course_offering || !mapping.course_offering.id || !mapping.is_mapped) {
                return h('span', { class: 'text-muted-foreground text-sm' }, '—');
            }
            const statisticsUrl = `/course-statistics/${mapping.course_offering.id}/assessment-scores`;

            return h(
                Link,
                {
                    href: statisticsUrl,
                    class: 'inline-flex items-center gap-2 text-primary font-medium hover:underline transition-colors',
                },
                () => [h(BarChart3, { class: 'h-4 w-4' }), 'View Stats'],
            );
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: () => null, // We'll use template slot instead
    },
];
</script>

<template>
    <Head title="Canvas Courses" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Canvas Courses</h1>
                <p class="text-muted-foreground mt-2">View and map Canvas courses to local course offerings</p>
            </div>

            <div class="flex items-center gap-2">
                <Button variant="outline" @click="() => router.visit('/admin/canvas/integrations')"> Manage Integrations </Button>
                <Button @click="syncCourses" :disabled="!integration?.has_token && !integration?.has_refresh_token">
                    <RefreshCw class="mr-2 h-4 w-4" />
                    Sync Courses
                </Button>
            </div>
        </div>

        <!-- Integration Status -->
        <Card v-if="integration">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Integration Status</CardTitle>
                        <CardDescription>{{ integration.canvas_url }}</CardDescription>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">Authorization:</span>
                            <Badge v-if="integration.has_token" variant="success">Authorized</Badge>
                            <Badge v-else variant="secondary">Not Authorized</Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">Last Sync:</span>
                            <span>{{ formatDate(integration.last_sync_at) }}</span>
                        </div>
                    </div>
                </div>
            </CardHeader>
        </Card>

        <Card v-else>
            <CardContent class="py-12 text-center">
                <AlertCircle class="text-muted-foreground mx-auto h-12 w-12" />
                <h3 class="mt-4 text-lg font-semibold">No Canvas Integration</h3>
                <p class="text-muted-foreground mt-2">Please set up a Canvas integration first.</p>
                <Button class="mt-4" @click="() => router.visit('/admin/canvas/integrations')"> Go to Integrations </Button>
            </CardContent>
        </Card>

        <!-- Filters -->
        <Card>
            <CardContent class="pt-6">
                <div class="flex flex-wrap gap-4">
                    <div class="min-w-[300px] flex-1">
                        <DebouncedInput :model-value="filters.search" placeholder="Search by course code, name, or Canvas ID..." @debounced="handleSearch" />
                    </div>
                    <Select v-model="filters.sync_status" @update:model-value="updateFilters">
                        <SelectTrigger class="w-[180px]">
                            <SelectValue placeholder="Filter by status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Status</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="mapped">Mapped</SelectItem>
                            <SelectItem value="ignored">Ignored</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </CardContent>
        </Card>

        <!-- Data Table -->
        <Card>
            <CardContent class="pt-6">
                <DataTable :columns="columns" :data="mappings.data">
                    <template #cell-actions="{ row }">
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                    <span class="sr-only">Open menu</span>
                                    <MoreHorizontal class="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <!-- View in Canvas link -->
                                <DropdownMenuItem v-if="row.original.canvas_url" @click="() => row.original.canvas_url && openCanvasUrl(row.original.canvas_url)" class="cursor-pointer">
                                    <ExternalLink class="mr-2 h-4 w-4" />
                                    View in Canvas
                                </DropdownMenuItem>

                                <!-- Separator if there's a Canvas link and other actions -->
                                <DropdownMenuSeparator v-if="row.original.canvas_url && (row.original.is_mapped || !row.original.is_ignored)" />

                                <!-- Map course action -->
                                <DropdownMenuItem v-if="!row.original.is_ignored && !row.original.is_mapped" @click="openMappingDialog(row.original)" class="cursor-pointer">
                                    <Link2 class="mr-2 h-4 w-4" />
                                    Map Course
                                </DropdownMenuItem>

                                <!-- Sync actions for mapped courses -->
                                <template v-if="row.original.is_mapped">
                                    <DropdownMenuItem @click="openSyncAssignmentsDialog(row.original)" class="cursor-pointer">
                                        <ListChecks class="mr-2 h-4 w-4" />
                                        Sync Assignments
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="openSyncGradesDialog(row.original)" class="cursor-pointer">
                                        <Award class="mr-2 h-4 w-4" />
                                        Sync Grades
                                    </DropdownMenuItem>
                                </template>

                                <!-- Separator before destructive actions -->
                                <DropdownMenuSeparator v-if="row.original.is_mapped || !row.original.is_ignored" />

                                <!-- Unmap action for mapped courses -->
                                <DropdownMenuItem v-if="row.original.is_mapped" @click="unmapCourse(row.original)" class="text-destructive focus:text-destructive cursor-pointer">
                                    <Link2Off class="mr-2 h-4 w-4" />
                                    Unmap Course
                                </DropdownMenuItem>

                                <!-- Ignore action for non-ignored courses -->
                                <DropdownMenuItem v-if="!row.original.is_ignored" @click="ignoreCourse(row.original)" class="text-destructive focus:text-destructive cursor-pointer">
                                    <EyeOff class="mr-2 h-4 w-4" />
                                    Ignore Course
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </template>
                </DataTable>
                <div class="mt-4">
                    <DataPagination :pagination-data="mappings" @navigate="handlePageChange" @page-size-change="handlePageSizeChange" />
                </div>
            </CardContent>
        </Card>

        <!-- Mapping Dialog -->
        <Dialog v-model:open="showMappingDialog">
            <DialogContent class="sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>Map Canvas Course</DialogTitle>
                    <DialogDescription> Select a local course offering to map with this Canvas course </DialogDescription>
                </DialogHeader>

                <div v-if="selectedMapping" class="space-y-4">
                    <div class="bg-muted/50 rounded-lg border p-4">
                        <div class="font-medium">{{ selectedMapping.canvas_course_code }}</div>
                        <div class="text-muted-foreground text-sm">{{ selectedMapping.canvas_course_name }}</div>
                        <div class="text-muted-foreground mt-1 text-xs">Canvas ID: {{ selectedMapping.canvas_course_id }}</div>
                    </div>

                    <div class="space-y-2">
                        <Label>Semester</Label>
                        <Select v-model="selectedMappingSemester">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="All semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All semesters</SelectItem>
                                <SelectItem v-for="semester in semesters" :key="semester.id" :value="String(semester.id)"> {{ semester.name }} ({{ semester.code }}) </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label>Select Course Offering</Label>
                        <Combobox :model-value="selectedCourseOffering" v-model:search-term="searchCourseOffering" by="id" :ignore-filter="true" @update:model-value="handleCourseOfferingSelect">
                            <ComboboxAnchor as-child>
                                <ComboboxTrigger as-child>
                                    <Button variant="outline" class="h-auto min-h-10 w-full justify-between">
                                        <span class="min-w-0 flex-1 truncate text-left">
                                            {{ selectedCourseOffering ? selectedCourseOffering.label : 'Search by section code, unit code, or unit name...' }}
                                        </span>
                                        <ChevronsUpDown class="text-muted-foreground ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </ComboboxTrigger>
                            </ComboboxAnchor>

                            <ComboboxListInline class="w-[var(--reka-combobox-trigger-width)]">
                                <div class="relative w-full items-center">
                                    <ComboboxInput
                                        class="h-10 rounded-none border-0 border-b pr-4 pl-10 focus-visible:ring-0"
                                        placeholder="Filter course offerings..."
                                        :display-value="getCourseOfferingDisplayValue"
                                        @update:model-value="(value) => (searchCourseOffering = String(value ?? ''))"
                                    />
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center justify-center px-3">
                                        <Search class="text-muted-foreground size-4" />
                                    </span>
                                </div>

                                <ComboboxViewport class="max-h-[320px] overflow-y-auto">
                                    <div v-if="isLoadingCourseOfferings" class="text-muted-foreground px-3 py-6 text-center text-sm">Loading course offerings...</div>
                                    <ComboboxEmpty v-else>
                                        <div class="text-muted-foreground px-3 py-6 text-center text-sm">No course offerings found.</div>
                                    </ComboboxEmpty>
                                    <ComboboxGroup v-if="!isLoadingCourseOfferings && availableCourseOfferings.length > 0">
                                        <ComboboxItem v-for="offering in availableCourseOfferings" :key="offering.id" :value="offering">
                                            <div class="flex min-w-0 flex-1 flex-col">
                                                <span class="truncate font-medium">{{ offering.course_code }} - {{ offering.course_title }}</span>
                                                <span class="text-muted-foreground truncate text-xs">Section {{ offering.section_code || 'N/A' }} • {{ offering.semester }}</span>
                                            </div>
                                            <ComboboxItemIndicator>
                                                <Check class="ml-2 h-4 w-4" />
                                            </ComboboxItemIndicator>
                                        </ComboboxItem>
                                    </ComboboxGroup>
                                </ComboboxViewport>
                            </ComboboxListInline>
                        </Combobox>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showMappingDialog = false">Cancel</Button>
                    <Button @click="mapCourse" :disabled="!selectedCourseOffering"> Map Course </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Sync Assignments Dialog -->
        <Dialog v-model:open="syncAssignmentsDialogOpen">
            <DialogContent class="sm:max-w-[700px]">
                <DialogHeader>
                    <DialogTitle>Sync Canvas Assignments</DialogTitle>
                    <DialogDescription> Select assignment groups to sync from Canvas </DialogDescription>
                </DialogHeader>

                <div v-if="selectedMappingForSync && assignmentsSyncSummary" class="space-y-4">
                    <!-- Course Info -->
                    <div class="bg-muted/50 rounded-lg border p-4">
                        <div class="font-medium">{{ selectedMappingForSync.canvas_course_name }}</div>
                    </div>

                    <!-- Content -->
                    <div v-if="assignmentsSyncSummary.can_sync" class="space-y-4">
                        <!-- Weight Summary -->
                        <div class="rounded-lg border p-4">
                            <div class="mb-3 text-sm font-medium">Weight Allocation</div>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <div class="text-muted-foreground">Selected</div>
                                    <div class="text-lg font-bold" :class="isWeightValid ? 'text-green-600' : 'text-red-600'">{{ selectedWeight.toFixed(1) }}%</div>
                                </div>
                                <div>
                                    <div class="text-muted-foreground">Required</div>
                                    <div class="text-lg font-bold">{{ assignmentsSyncSummary.total_weight_required || 100 }}%</div>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-muted-foreground text-xs">Total must equal {{ assignmentsSyncSummary.total_weight_required || 100 }}%</span>
                                <Badge :variant="isWeightValid ? 'default' : 'destructive'" class="text-xs">
                                    {{ weightValidationMessage }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Assignment Groups -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label class="text-sm font-medium">Assignment Groups ({{ assignmentsSyncSummary.groups?.length || 0 }})</Label>
                                <div class="flex items-center gap-2">
                                    <Button v-if="!allGroupsSelected" variant="outline" size="sm" @click="selectAllGroups"> Select All </Button>
                                    <Button v-else variant="outline" size="sm" @click="deselectAllGroups"> Deselect All </Button>
                                </div>
                            </div>
                            <ScrollArea class="h-[400px] rounded-lg border">
                                <div class="space-y-3 p-4">
                                    <div v-for="group in assignmentsSyncSummary.groups" :key="group.id" class="rounded-lg border p-4 transition-colors" :class="selectedGroups.includes(group.id) ? 'border-primary bg-primary/5' : ''">
                                        <div class="flex items-start gap-3">
                                            <Checkbox :id="`group-${group.id}`" :model-value="selectedGroups.includes(group.id)" @update:model-value="() => toggleGroupSelection(group.id)" class="mt-1" />
                                            <div class="flex-1">
                                                <Label :for="`group-${group.id}`" class="cursor-pointer">
                                                    <div class="flex items-center justify-between">
                                                        <span class="font-medium">{{ group.name }}</span>
                                                        <div class="flex items-center gap-2">
                                                            <Badge variant="outline" class="text-xs">{{ group.weight }}%</Badge>
                                                            <Badge v-if="group.is_currently_synced" variant="secondary" class="text-xs">Synced</Badge>
                                                        </div>
                                                    </div>
                                                    <div class="text-muted-foreground mt-1 text-xs">{{ group.assignments_count }} assignments</div>
                                                </Label>

                                                <!-- Assignments List -->
                                                <ul v-if="group.assignments?.length > 0" class="mt-3 space-y-1.5 border-l-2 pl-3">
                                                    <li v-for="assignment in group.assignments" :key="assignment.id" class="text-xs">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="text-muted-foreground">{{ assignment.name }}</span>
                                                            <span class="text-muted-foreground whitespace-nowrap">{{ assignment.points_possible }} pts</span>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="!assignmentsSyncSummary.groups || assignmentsSyncSummary.groups.length === 0" class="text-muted-foreground py-8 text-center text-sm">No assignment groups found in Canvas</div>
                                </div>
                            </ScrollArea>
                        </div>
                    </div>

                    <div v-else class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
                        <div class="flex items-start gap-2">
                            <AlertCircle class="mt-0.5 h-5 w-5 text-red-600 dark:text-red-400" />
                            <div class="text-sm text-red-800 dark:text-red-200">
                                {{ assignmentsSyncSummary.error }}
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeSyncAssignmentsDialog" :disabled="syncAssignmentsLoading"> Cancel </Button>
                    <Button @click="syncAssignments" :disabled="syncAssignmentsLoading || !assignmentsSyncSummary?.can_sync || !isWeightValid">
                        <RefreshCw v-if="syncAssignmentsLoading" class="mr-2 h-4 w-4 animate-spin" />
                        <ListChecks v-else class="mr-2 h-4 w-4" />
                        Sync Selected Groups
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Sync Grades Dialog -->
        <Dialog v-model:open="syncGradesDialogOpen">
            <DialogContent class="sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>Sync Canvas Grades</DialogTitle>
                    <DialogDescription> Sync student grades from Canvas to local database </DialogDescription>
                </DialogHeader>

                <div v-if="selectedMappingForSync && gradesSyncSummary" class="space-y-4">
                    <!-- Course Info -->
                    <div class="bg-muted/50 rounded-lg border p-4">
                        <div class="font-medium">{{ selectedMappingForSync.canvas_course_name }}</div>
                    </div>

                    <!-- Summary -->
                    <div v-if="gradesSyncSummary.can_sync" class="space-y-3">
                        <div class="grid grid-cols-2 gap-4 rounded-lg border p-4">
                            <div>
                                <div class="text-muted-foreground text-sm">Assignments</div>
                                <div class="text-2xl font-bold">{{ gradesSyncSummary.assignments_count }}</div>
                            </div>
                            <div>
                                <div class="text-muted-foreground text-sm">Enrolled Students</div>
                                <div class="text-2xl font-bold">{{ gradesSyncSummary.students_count }}</div>
                            </div>
                        </div>

                        <div class="rounded-lg border p-4">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-muted-foreground text-sm">Sync Progress</span>
                                <span class="text-sm font-medium">{{ gradesSyncSummary.completion_percentage }}%</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                <div class="bg-primary h-2 rounded-full transition-all" :style="{ width: `${gradesSyncSummary.completion_percentage}%` }"></div>
                            </div>
                            <div class="text-muted-foreground mt-2 text-xs">{{ gradesSyncSummary.graded_scores_count }} / {{ gradesSyncSummary.expected_total_scores }} scores synced</div>
                        </div>

                        <div v-if="gradesSyncSummary.last_synced_at" class="text-muted-foreground text-sm">Last synced: {{ formatDate(gradesSyncSummary.last_synced_at) }}</div>
                    </div>

                    <div v-else class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
                        <div class="flex items-start gap-2">
                            <AlertCircle class="mt-0.5 h-5 w-5 text-red-600 dark:text-red-400" />
                            <div class="text-sm text-red-800 dark:text-red-200">
                                {{ gradesSyncSummary.error }}
                            </div>
                        </div>
                    </div>

                    <!-- Sync Results (after sync completes) -->
                    <div v-if="gradesSyncSummary.sync_completed && gradesSyncSummary.sync_results" class="mt-4 space-y-4">
                        <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
                            <div class="mb-3 flex items-center gap-2">
                                <CheckCircle2 class="h-5 w-5 text-green-600 dark:text-green-400" />
                                <span class="font-medium text-green-800 dark:text-green-200">Sync Completed!</span>
                            </div>
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <div class="text-muted-foreground">Synced</div>
                                    <div class="text-lg font-bold text-green-700 dark:text-green-300">{{ gradesSyncSummary.sync_results.students_synced }}</div>
                                </div>
                                <div>
                                    <div class="text-muted-foreground">Skipped</div>
                                    <div class="text-lg font-bold">{{ gradesSyncSummary.sync_results.students_skipped }}</div>
                                </div>
                                <div>
                                    <div class="text-muted-foreground">Errors</div>
                                    <div class="text-lg font-bold" :class="gradesSyncSummary.sync_results.errors?.length > 0 ? 'text-red-600' : ''">
                                        {{ gradesSyncSummary.sync_results.errors?.length || 0 }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Student Details -->
                        <div v-if="gradesSyncSummary.sync_results.students_processed?.length > 0" class="max-h-96 space-y-2 overflow-y-auto rounded-lg border p-4">
                            <div class="mb-3 font-medium">Students Processed:</div>
                            <div v-for="student in gradesSyncSummary.sync_results.students_processed" :key="student.student_id" class="flex items-start justify-between gap-2 rounded-md border p-3 text-sm">
                                <div class="flex-1">
                                    <div class="font-medium">{{ student.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ student.student_code }}</div>
                                </div>
                                <div class="text-right">
                                    <Badge v-if="student.status === 'success'" variant="default" class="flex items-center gap-1">
                                        <CheckCircle2 class="h-3 w-3" />
                                        Success
                                    </Badge>
                                    <Badge v-else-if="student.status === 'skipped'" variant="secondary" class="flex items-center gap-1">
                                        <EyeOff class="h-3 w-3" />
                                        Skipped
                                    </Badge>
                                    <Badge v-else variant="destructive" class="flex items-center gap-1">
                                        <AlertCircle class="h-3 w-3" />
                                        Error
                                    </Badge>
                                    <div v-if="student.status === 'success'" class="text-muted-foreground mt-1 text-xs">{{ student.grades_synced }} grades ({{ student.grades_created }} new, {{ student.grades_updated }} updated)</div>
                                    <div v-else-if="student.status === 'skipped'" class="text-muted-foreground mt-1 text-xs">{{ student.reason }}</div>
                                    <div v-else-if="student.error" class="mt-1 text-xs text-red-600">{{ student.error }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button v-if="gradesSyncSummary?.sync_completed" variant="default" @click="closeSyncGradesDialog"> Close </Button>
                    <template v-else>
                        <Button variant="outline" @click="closeSyncGradesDialog" :disabled="syncGradesLoading"> Cancel </Button>
                        <Button @click="syncGrades" :disabled="syncGradesLoading || !gradesSyncSummary?.can_sync">
                            <RefreshCw v-if="syncGradesLoading" class="mr-2 h-4 w-4 animate-spin" />
                            <Award v-else class="mr-2 h-4 w-4" />
                            Sync Grades
                        </Button>
                    </template>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
