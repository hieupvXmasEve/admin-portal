<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables';
import { createColumns } from '@/lib/table-utils';
import type { CurriculumUnit, CurriculumVersion, Program, Semester, Specialization, Unit, UnitScope } from '@/types/models';
import { ValidationRules } from '@/types/validation';
import { curriculumRoutes } from '@/utils/routes';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Book, Edit, GraduationCap, Info, Plus, School, Target, Trash2 } from 'lucide-vue-next';
import { Form } from 'vee-validate';
import { computed, h, ref } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    curriculumVersion: CurriculumVersion;
    programs?: Program[];
    specializations?: Specialization[];
    semesters?: Semester[];
    units?: Unit[];
    unitScopes?: UnitScope[];
}

const props = defineProps<Props>();
const page = usePage();
const api = useApi();
console.log(props);

// Modal states
const showAddUnitModal = ref(false);
const showEditUnitModal = ref(false);
const curriculumUnitToEdit = ref<CurriculumUnit | null>(null);
const deleteDialogOpen = ref(false);
const curriculumUnitToDelete = ref<CurriculumUnit | null>(null);
const isDeleting = ref(false);

// Filter states
const filters = ref({
    search: '',
    unitScope: 'all',
    yearLevel: 'all',
    semesterNumber: 'all',
    page: 1,
});

// Permission check function
const can = (permission: string) => {
    const permissions = (page.props as any).permissions || [];
    return permissions.includes(permission);
};

// Form schemas following standards
// TypeScript interfaces for form data
interface AddUnitFormData {
    unit_id: string;
    unit_scope: 'program' | 'common' | 'specialization_specific' | 'cross_program' | null;
    semester_number: string;
    note?: string;
}

type EditUnitFormData = AddUnitFormData;

const addUnitFormSchema = toTypedSchema(
    z.object({
        unit_id: z.string().min(1, 'Unit is required'),
        unit_scope: z.enum(['program', 'common', 'specialization_specific', 'cross_program']).nullable().optional(),
        semester_number: z
            .string()
            .optional()
            .refine((val) => {
                if (!val || val === 'none') return true;
                const num = parseInt(val);
                return num >= 1 && num <= 9;
            }, 'Semester number must be between 1 and 9'),
        note: z.string().max(1000, 'Note cannot exceed 1000 characters').optional(),
    }),
);

const editUnitFormSchema = addUnitFormSchema;

// Helper function to calculate year level from semester number
const calculateYearLevel = (semesterNumber: number): number => {
    return Math.ceil(semesterNumber / 3);
};

// Form submission handlers (Form component handles validation automatically)
const isAddSubmitting = ref(false);
const isEditSubmitting = ref(false);

// Computed data
const curriculumUnits = computed(() => props.curriculumVersion.curriculum_units || []);

// Filter available units - only show units that haven't been added yet
const availableUnits = computed(() => {
    if (!props.units) return [];

    const existingUnitIds = curriculumUnits.value.map((cu) => cu.unit_id);
    return props.units.filter((unit) => !existingUnitIds.includes(unit.id));
});

// For edit form - include current unit and other available units
const editableUnits = computed(() => {
    if (!props.units || !curriculumUnitToEdit.value) return [];

    const currentUnitId = curriculumUnitToEdit.value.unit_id;
    const existingUnitIds = curriculumUnits.value
        .filter((cu) => cu.unit_id !== currentUnitId) // Exclude current unit from existing
        .map((cu) => cu.unit_id);

    return props.units.filter((unit) => !existingUnitIds.includes(unit.id));
});

const filteredUnits = computed(() => {
    let units = curriculumUnits.value;

    if (filters.value.search) {
        units = units.filter((unit) => unit.unit?.code?.toLowerCase().includes(filters.value.search.toLowerCase()) || unit.unit?.name?.toLowerCase().includes(filters.value.search.toLowerCase()));
    }

    if (filters.value.unitScope && filters.value.unitScope !== 'all') {
        units = units.filter((unit) => unit.unit_scope === filters.value.unitScope);
    }

    if (filters.value.yearLevel && filters.value.yearLevel !== 'all') {
        units = units.filter((unit) => unit.year_level?.toString() === filters.value.yearLevel);
    }

    if (filters.value.semesterNumber && filters.value.semesterNumber !== 'all') {
        units = units.filter((unit) => unit.semester_number?.toString() === filters.value.semesterNumber);
    }

    return units;
});

const paginatedUnits = computed(() => {
    const start = (filters.value.page - 1) * 10;
    const end = start + 10;
    return {
        data: filteredUnits.value.slice(start, end),
        total: filteredUnits.value.length,
    };
});

const paginationData = computed(() => ({
    from: (filters.value.page - 1) * 10 + 1,
    to: Math.min(filters.value.page * 10, paginatedUnits.value.total),
    total: paginatedUnits.value.total,
    current_page: filters.value.page,
    last_page: Math.ceil(paginatedUnits.value.total / 10),
    per_page: 10,
    prev_page_url: filters.value.page > 1 ? `?page=${filters.value.page - 1}` : null,
    next_page_url: filters.value.page < Math.ceil(paginatedUnits.value.total / 10) ? `?page=${filters.value.page + 1}` : null,
    links: [] as any[],
}));

const stats = computed(() => {
    const units = curriculumUnits.value;
    const totalUnits = units.length;
    const totalCreditPoints = units.reduce((sum, unit) => sum + (Number(unit.unit?.credit_points) || 0), 0);

    const byYearLevel = units.reduce(
        (acc, unit) => {
            const level = unit.year_level ? `Year ${unit.year_level}` : 'Unspecified';
            acc[level] = (acc[level] || 0) + 1;
            return acc;
        },
        {} as Record<string, number>,
    );

    const byUnitScope = units.reduce(
        (acc, unit) => {
            const scope = unit.unit_scope || 'Unspecified';
            acc[scope] = (acc[scope] || 0) + 1;
            return acc;
        },
        {} as Record<string, number>,
    );

    return {
        totalUnits,
        totalCreditPoints,
        byYearLevel,
        byUnitScope: byUnitScope || {},
    };
});

const organizedUnits = computed(() => {
    const units = curriculumUnits.value;

    const organized = {} as Record<number, Record<number, CurriculumUnit[]>>;

    units.forEach((unit) => {
        const year = unit.year_level || 0;
        const semester = unit.semester_number || 0;

        if (!organized[year]) {
            organized[year] = {};
        }
        if (!organized[year][semester]) {
            organized[year][semester] = [];
        }

        organized[year][semester].push(unit);
    });
    console.log('organized', organized);

    return organized;
});

// Column definitions following standards
const baseColumns: ColumnDef<CurriculumUnit>[] = [
    {
        header: 'Unit',
        accessorKey: 'unit.code',
        cell: ({ row }) => {
            const curriculumUnit = row.original;
            const unit = curriculumUnit.unit;

            if (!unit) {
                return h('div', { class: 'text-gray-500' }, 'N/A');
            }

            return h('div', { class: 'space-y-1' }, [h('div', { class: 'font-medium' }, unit.code), h('div', { class: 'text-sm text-gray-600' }, unit.name), h('div', { class: 'text-xs text-gray-500' }, `${unit.credit_points} credit points`)]);
        },
    },
    {
        header: 'Type & Level',
        cell: ({ row }) => {
            const curriculumUnit = row.original;

            return h('div', { class: 'space-y-2' }, [
                curriculumUnit.unit_scope ? h(Badge, { variant: 'outline', class: 'text-xs' }, () => curriculumUnit.unit_scope.toUpperCase()) : h(Badge, { variant: 'secondary', class: 'text-xs' }, () => 'Unspecified'),
                h(
                    'div',
                    { class: 'space-y-1' },
                    [
                        curriculumUnit.year_level ? h('div', { class: 'text-sm font-medium' }, `Year ${curriculumUnit.year_level}`) : h('div', { class: 'text-sm text-gray-500' }, 'Unspecified Year'),
                        curriculumUnit.semester_number ? h('div', { class: 'text-xs text-gray-600' }, `Semester ${curriculumUnit.semester_number}`) : h('div', { class: 'text-xs text-gray-400' }, 'No semester'),
                    ].filter(Boolean),
                ),
            ]);
        },
    },
    {
        header: 'Notes',
        accessorKey: 'note',
        cell: ({ row }) => {
            const curriculumUnit = row.original;
            return curriculumUnit.note ? h('div', { class: 'text-xs text-gray-600 max-w-xs truncate', title: curriculumUnit.note }, curriculumUnit.note) : h('div', { class: 'text-xs text-gray-400' }, 'No notes');
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const curriculumUnit = row.original;
            return h(
                'div',
                { class: 'flex items-center gap-1' },
                [
                    can('edit_curriculum_unit')
                        ? h(
                              Button,
                              {
                                  variant: 'ghost',
                                  size: 'sm',
                                  onClick: () => editCurriculumUnit(curriculumUnit),
                              },
                              () => h(Edit, { class: 'h-4 w-4' }),
                          )
                        : null,
                    can('delete_curriculum_unit')
                        ? h(
                              Button,
                              {
                                  variant: 'ghost',
                                  size: 'sm',
                                  onClick: () => deleteCurriculumUnit(curriculumUnit),
                              },
                              () => h(Trash2, { class: 'h-4 w-4' }),
                          )
                        : null,
                ].filter(Boolean),
            );
        },
    },
];

// Columns with selection support
const columns = createColumns(baseColumns, {
    enableSelection: true,
});

// Selection handling
const selectedUnits = ref<Set<number>>(new Set());

const handleSelectionChange = (selectedRows: CurriculumUnit[]) => {
    selectedUnits.value.clear();
    selectedRows.forEach((row) => selectedUnits.value.add(row.id));
};

// Event handlers following standards
const handleSearch = (value: string | number) => {
    filters.value.search = String(value);
    filters.value.page = 1;
};

const handlePageChange = (url: string) => {
    const urlParams = new URLSearchParams(url.split('?')[1] || '');
    const page = parseInt(urlParams.get('page') || '1');
    filters.value.page = page;
};

const handleAddUnitClick = () => {
    showAddUnitModal.value = true;
};

const onAddUnitSubmit = async (values: any) => {
    // Type assertion for better TypeScript experience
    const formData = values as AddUnitFormData;
    console.log('Add form submitted with values:', formData);
    isAddSubmitting.value = true;

    const submitData = {
        curriculum_version_id: props.curriculumVersion.id,
        unit_id: parseInt(formData.unit_id),
        semester_id: props.curriculumVersion.semester_id,
        unit_scope: formData.unit_scope,
        year_level: formData.semester_number ? calculateYearLevel(parseInt(formData.semester_number)) : null,
        semester_number: formData.semester_number ? parseInt(formData.semester_number) : null,
        note: formData.note || null,
    };

    console.log('Submitting add data:', submitData);

    const { data, error, statusCode } = await api.post('/api/curriculum-units', submitData);

    console.log('Add API response:', { data: data.value, error: error.value, statusCode: statusCode.value });

    if (statusCode.value === 201 && data.value?.success) {
        toast.success('Curriculum unit added successfully');
        showAddUnitModal.value = false;
        router.reload({ only: ['curriculumVersion'] });
    } else {
        const errorMessage = data.value?.message || error.value || 'Failed to add curriculum unit';
        toast.error(errorMessage);
        console.error('Add unit error:', errorMessage);
    }

    isAddSubmitting.value = false;
};

const editCurriculumUnit = (curriculumUnit: CurriculumUnit) => {
    curriculumUnitToEdit.value = curriculumUnit;
    showEditUnitModal.value = true;
};

const onEditUnitSubmit = async (values: any) => {
    // Type assertion for better TypeScript experience
    const formData = values as EditUnitFormData;
    isEditSubmitting.value = true;
    if (!curriculumUnitToEdit.value) {
        console.error('No curriculum unit to edit');
        isEditSubmitting.value = false;
        return;
    }

    const submitData = {
        curriculum_version_id: props.curriculumVersion.id,
        unit_id: parseInt(formData.unit_id),
        semester_id: props.curriculumVersion.semester_id,
        unit_scope: formData.unit_scope,
        year_level: formData.semester_number ? calculateYearLevel(parseInt(formData.semester_number)) : null,
        semester_number: formData.semester_number ? parseInt(formData.semester_number) : null,
        note: formData.note || null,
    };

    console.log('Submitting edit data:', submitData);

    const { data, error, statusCode } = await api.put(`/api/curriculum-units/${curriculumUnitToEdit.value.id}`, submitData);

    console.log('Edit API response:', { data: data.value, error: error.value, statusCode: statusCode.value });

    if (statusCode.value === 200 && data.value?.success) {
        toast.success('Curriculum unit updated successfully');
        showEditUnitModal.value = false;
        curriculumUnitToEdit.value = null;
        router.reload({ only: ['curriculumVersion'] });
    } else {
        const errorMessage = data.value?.message || error.value || 'Failed to update curriculum unit';
        toast.error(errorMessage);
        console.error('Edit unit error:', errorMessage);
    }

    isEditSubmitting.value = false;
};

const deleteCurriculumUnit = (curriculumUnit: CurriculumUnit) => {
    curriculumUnitToDelete.value = curriculumUnit;
    deleteDialogOpen.value = true;
};

const confirmDelete = async () => {
    if (!curriculumUnitToDelete.value) return;

    isDeleting.value = true;

    const { data, error, statusCode } = await api.delete(`/api/curriculum-units/${curriculumUnitToDelete.value.id}`);

    if (statusCode.value === 200 && data.value?.success) {
        toast.success('Curriculum unit deleted successfully');
        deleteDialogOpen.value = false;
        curriculumUnitToDelete.value = null;
        router.reload({ only: ['curriculumVersion'] });
    } else {
        const errorMessage = data.value?.message || error.value || 'Failed to delete curriculum unit';
        toast.error(errorMessage);
    }

    isDeleting.value = false;
};

// Helper functions
const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getUnitScopeColor = (scope: string) => {
    switch (scope.toLowerCase()) {
        case 'program':
            return 'bg-blue-100 text-blue-800';
        case 'common':
            return 'bg-green-100 text-green-800';
        case 'specialization_specific':
            return 'bg-purple-100 text-purple-800';
        case 'cross_program':
            return 'bg-orange-100 text-orange-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head :title="`${curriculumVersion.version_code || 'Curriculum Version'}`" />

    <!-- Header Section -->
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-2">
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-bold tracking-tight">
                        {{ curriculumVersion.version_code || `Version #${curriculumVersion.id}` }}
                    </h1>
                    <Badge variant="secondary" class="text-xs">
                        {{ curriculumVersion.specialization ? 'Specialization Level' : 'Program Level' }}
                    </Badge>
                </div>
                <div class="text-muted-foreground flex flex-wrap gap-4 text-sm">
                    <span>Program: {{ curriculumVersion.program?.name }}</span>
                    <span v-if="curriculumVersion.specialization">Specialization: {{ curriculumVersion.specialization.name }}</span>
                    <span v-if="curriculumVersion.effective_from_semester"> Effective From: {{ curriculumVersion.effective_from_semester.name }} </span>
                    <span>Created: {{ formatDate(curriculumVersion.created_at) }}</span>
                </div>
                <p v-if="curriculumVersion.notes" class="text-muted-foreground text-sm">{{ curriculumVersion.notes }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link :href="'/curriculum-versions'">
                    <Button variant="outline">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to List
                    </Button>
                </Link>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Total Units</CardTitle>
                    <Book class="text-muted-foreground h-4 w-4" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ stats.totalUnits }}</div>
                    <p class="text-muted-foreground text-xs">Academic units</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Credit Points</CardTitle>
                    <Target class="h-4 w-4 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-blue-600">{{ stats.totalCreditPoints }}</div>
                    <p class="text-muted-foreground text-xs">Total credits</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Year Levels</CardTitle>
                    <School class="h-4 w-4 text-green-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-green-600">{{ Object.keys(stats.byYearLevel).length }}</div>
                    <p class="text-muted-foreground text-xs">Academic years</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Unit Scopes</CardTitle>
                    <Info class="h-4 w-4 text-purple-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-purple-600">{{ Object.keys(stats.byUnitScope || {}).length }}</div>
                    <p class="text-muted-foreground text-xs">Different scopes</p>
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <Tabs default-value="structure" class="w-full">
        <TabsList class="grid w-full grid-cols-3">
            <TabsTrigger value="structure">Academic Structure</TabsTrigger>
            <TabsTrigger value="units">Unit List</TabsTrigger>
            <TabsTrigger value="overview">Overview</TabsTrigger>
        </TabsList>

        <!-- Academic Structure Tab -->
        <TabsContent value="structure">
            <Card>
                <CardContent class="space-y-4">
                    <div v-for="(yearData, year) in organizedUnits" :key="year" class="space-y-4">
                        <h3 class="text-lg font-semibold">
                            {{ String(year) === '0' ? 'Common' : `Year ${year}` }}
                        </h3>
                        <div class="space-y-3">
                            <div v-for="(semesterUnits, semester) in yearData" :key="semester" class="space-y-1">
                                <h4 class="text-sm font-medium text-gray-700">
                                    {{ String(semester) === '0' ? '' : `Semester ${semester}` }}
                                </h4>
                                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                    <Card v-for="unit in semesterUnits" :key="unit.id" class="p-3">
                                        <div class="space-y-2">
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <Link :href="curriculumRoutes.units.show(unit.unit?.id)" class="text-sm font-medium">
                                                        {{ unit.unit?.code }}
                                                    </Link>
                                                    <p class="text-xs text-gray-600">{{ unit.unit?.name }}</p>
                                                </div>
                                                <Badge :class="getUnitScopeColor(unit.unit_scope || 'unknown')" class="text-xs">
                                                    {{ unit.unit_scope?.toUpperCase() || 'UNKNOWN' }}
                                                </Badge>
                                            </div>
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-gray-500">{{ unit.unit?.credit_points }} CP</span>
                                                <Button v-if="can('edit_curriculum_unit')" variant="ghost" size="sm" @click="editCurriculumUnit(unit)">
                                                    <Edit class="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </div>
                                    </Card>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </TabsContent>

        <!-- Unit List Tab -->
        <TabsContent value="units" class="space-y-4">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <Book class="h-5 w-5" />
                            Curriculum Units
                            <Badge variant="secondary" class="ml-2">{{ paginatedUnits.data.length }}/{{ paginatedUnits.total }}</Badge>
                        </CardTitle>

                        <div v-if="can('create_curriculum_unit')" class="flex items-center gap-2">
                            <Button variant="outline" size="sm" :disabled="availableUnits.length === 0" @click="handleAddUnitClick">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Unit
                            </Button>
                            <span v-if="availableUnits.length === 0" class="text-muted-foreground text-xs"> All units have been added </span>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-4">
                        <DebouncedInput v-model="filters.search" @debounced="handleSearch" placeholder="Search units..." class="w-full" />

                        <Select v-model="filters.unitScope">
                            <SelectTrigger>
                                <SelectValue placeholder="All scopes" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All scopes</SelectItem>
                                <SelectItem value="program">Program</SelectItem>
                                <SelectItem value="common">Common</SelectItem>
                                <SelectItem value="specialization_specific">Specialization Specific</SelectItem>
                                <!--                                <SelectItem value="cross_program">Cross Program</SelectItem>-->
                            </SelectContent>
                        </Select>

                        <Select v-model="filters.yearLevel">
                            <SelectTrigger>
                                <SelectValue placeholder="All years" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All years</SelectItem>
                                <SelectItem v-for="year in [1, 2, 3, 4, 5]" :key="year" :value="year.toString()"> Year {{ year }} </SelectItem>
                            </SelectContent>
                        </Select>

                        <Select v-model="filters.semesterNumber">
                            <SelectTrigger>
                                <SelectValue placeholder="All semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All semesters</SelectItem>
                                <SelectItem v-for="semester in [1, 2, 3, 4, 5, 6]" :key="semester" :value="semester.toString()"> Semester {{ semester }} (Year {{ Math.ceil(semester / 2) }}) </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="paginatedUnits.data.length === 0" class="py-8 text-center">
                        <Book class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-4 text-sm font-medium">No curriculum units found</h3>
                        <p class="text-muted-foreground mt-2 text-sm">
                            {{ filters.search || filters.unitScope !== 'all' || filters.yearLevel !== 'all' || filters.semesterNumber !== 'all' ? 'Try adjusting your search filters.' : 'Get started by adding curriculum units to this version.' }}
                        </p>
                        <div class="mt-6" v-if="!filters.search && filters.unitScope === 'all' && filters.yearLevel === 'all' && filters.semesterNumber === 'all' && can('create_curriculum_unit')">
                            <Button :disabled="availableUnits.length === 0" @click="handleAddUnitClick">
                                <Plus class="mr-2 h-4 w-4" />
                                Add First Unit
                            </Button>
                            <p v-if="availableUnits.length === 0" class="text-muted-foreground mt-2 text-xs">All available units have been added to this curriculum version.</p>
                        </div>
                    </div>

                    <div v-else>
                        <DataTable :data="paginatedUnits.data" :columns="columns" :loading="false" :enable-row-selection="true" @selection-change="handleSelectionChange" class="border-0" />
                        <div class="border-t p-4" v-if="paginatedUnits.total > 10">
                            <DataPagination :pagination-data="paginationData" @navigate="handlePageChange" />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </TabsContent>

        <!-- Overview Tab -->
        <TabsContent value="overview" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Year Level Distribution -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <GraduationCap class="h-5 w-5" />
                            Year Level Distribution
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="(count, year) in stats.byYearLevel" :key="year" class="flex items-center justify-between">
                                <span class="text-sm font-medium">{{ year }}</span>
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-16 overflow-hidden rounded-full bg-gray-200">
                                        <div class="h-full bg-blue-500" :style="{ width: `${(count / stats.totalUnits) * 100}%` }"></div>
                                    </div>
                                    <span class="text-sm text-gray-600">{{ count }}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Unit Type Distribution -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Target class="h-5 w-5" />
                            Unit Scope Distribution
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div v-for="(count, scope) in stats.byUnitScope" :key="scope" class="flex items-center justify-between">
                                <span class="text-sm font-medium">{{ scope }}</span>
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-16 overflow-hidden rounded-full bg-gray-200">
                                        <div class="h-full bg-green-500" :style="{ width: `${(count / stats.totalUnits) * 100}%` }"></div>
                                    </div>
                                    <span class="text-sm text-gray-600">{{ count }}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </TabsContent>
    </Tabs>

    <!-- Add Unit Modal -->
    <Dialog v-model:open="showAddUnitModal">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Add Curriculum Unit</DialogTitle>
                <DialogDescription>
                    Add a new unit to this curriculum version.
                    <span v-if="availableUnits.length > 0" class="mt-1 block text-sm text-green-600"> {{ availableUnits.length }} unit(s) available to add </span>
                    <span v-else class="mt-1 block text-sm text-amber-600"> No units available to add </span>
                </DialogDescription>
            </DialogHeader>

            <Form :validation-schema="addUnitFormSchema" @submit="onAddUnitSubmit" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="unit_id">
                        <FormItem>
                            <FormLabel>Unit *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a unit" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-if="availableUnits.length === 0" value="none" disabled> No available units to add </SelectItem>
                                        <SelectItem v-for="unit in availableUnits" :key="unit.id" :value="unit.id.toString()"> {{ unit.code }} - {{ unit.name }} ({{ unit.credit_points }} CP) </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="unit_scope">
                        <FormItem>
                            <FormLabel>Unit Scope</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select scope (optional)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="program">Program</SelectItem>
                                        <SelectItem value="common">Common</SelectItem>
                                        <SelectItem value="specialization_specific">Specialization Specific</SelectItem>
                                        <!--                                        <SelectItem value="cross_program">Cross Program</SelectItem>-->
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="semester_number">
                        <FormItem>
                            <FormLabel>Semester Number</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None</SelectItem>
                                        <SelectItem v-for="semester in [1, 2, 3, 4, 5, 6]" :key="semester" :value="semester.toString()"> Semester {{ semester }} (Year {{ Math.ceil(semester / 2) }}) </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <FormField v-slot="{ componentField }" name="note">
                    <FormItem>
                        <FormLabel>Notes</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Additional notes..." rows="3" :maxlength="ValidationRules.curriculumUnit.note.maxLength" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="showAddUnitModal = false">Cancel</Button>
                    <Button type="submit" :disabled="isAddSubmitting || availableUnits.length === 0">
                        {{ isAddSubmitting ? 'Adding...' : 'Add Unit' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Edit Unit Modal -->
    <Dialog v-model:open="showEditUnitModal">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Edit Curriculum Unit</DialogTitle>
                <DialogDescription>Update curriculum unit information.</DialogDescription>
            </DialogHeader>

            <Form
                v-if="curriculumUnitToEdit"
                :key="curriculumUnitToEdit.id"
                :validation-schema="editUnitFormSchema"
                :initial-values="{
                    unit_id: curriculumUnitToEdit.unit_id?.toString() || '',
                    unit_scope: curriculumUnitToEdit.unit_scope || null,
                    semester_number: curriculumUnitToEdit.semester_number?.toString() || '',
                    note: curriculumUnitToEdit.note || '',
                }"
                @submit="onEditUnitSubmit"
                class="space-y-4"
            >
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="unit_id">
                        <FormItem>
                            <FormLabel>Unit *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a unit" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="unit in editableUnits" :key="unit.id" :value="unit.id.toString()"> {{ unit.code }} - {{ unit.name }} ({{ unit.credit_points }} CP) </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="unit_scope">
                        <FormItem>
                            <FormLabel>Unit Scope</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select scope (optional)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="program">Program</SelectItem>
                                        <SelectItem value="common">Common</SelectItem>
                                        <SelectItem value="specialization_specific">Specialization Specific</SelectItem>
                                        <!--                                        <SelectItem value="cross_program">Cross Program</SelectItem>-->
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="semester_number">
                        <FormItem>
                            <FormLabel>Semester Number *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">None</SelectItem>
                                        <SelectItem v-for="semester in [1, 2, 3, 4, 5, 6]" :key="semester" :value="semester.toString()"> Semester {{ semester }} (Year {{ Math.ceil(semester / 2) }}) </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <FormField v-slot="{ componentField }" name="note">
                    <FormItem>
                        <FormLabel>Notes</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Additional notes..." rows="3" :maxlength="ValidationRules.curriculumUnit.note.maxLength" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="
                            showEditUnitModal = false;
                            curriculumUnitToEdit = null;
                        "
                        >Cancel</Button
                    >
                    <Button type="submit" :disabled="isEditSubmitting">
                        {{ isEditSubmitting ? 'Updating...' : 'Update Unit' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="deleteDialogOpen" @update:open="deleteDialogOpen = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Curriculum Unit</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete the curriculum unit
                    <strong>{{ curriculumUnitToDelete?.unit?.code }}</strong>
                    from this curriculum version?
                    <br /><br />
                    This action cannot be undone and will permanently remove this unit from the curriculum structure.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="isDeleting">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="confirmDelete" :disabled="isDeleting" class="bg-destructive hover:bg-destructive/90 text-white">
                    {{ isDeleting ? 'Deleting...' : 'Delete Unit' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>

<style scoped>
/* Force text truncation in select components */
:deep([data-slot='select-trigger']) {
    overflow: hidden !important;
}

:deep([data-slot='select-value']) {
    min-width: 0 !important;
    flex: 1 !important;
    text-overflow: ellipsis !important;
    overflow: hidden !important;
    white-space: nowrap !important;
    max-width: calc(100% - 2rem) !important;
}
</style>
