<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList, ComboboxTrigger, ComboboxViewport } from '@/components/ui/combobox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useApi, usePermissions } from '@/composables';
import CurriculumVersionSummaryLayout from '@/layouts/CurriculumVersionSummaryLayout.vue';
import { createColumns } from '@/lib/table-utils';
import type { CurriculumUnit, Unit } from '@/types/models';
import { ValidationRules } from '@/types/validation';
import { curriculumRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { toTypedSchema } from '@vee-validate/zod';
import { Book, ChevronsUpDown, Edit, GraduationCap, Plus, Target, Trash2 } from 'lucide-vue-next';
import { Form } from 'vee-validate';
import { computed, h, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    curriculumVersion: {
        id: number;
        version_code: string;
        notes?: string;
        created_at: string;
        semester_id?: number;
        program: {
            id: number;
            name: string;
            code: string;
        };
        specialization?: {
            id: number;
            name: string;
            code: string;
        } | null;
        effective_from_semester?: {
            id: number;
            name: string;
            code: string;
        } | null;
    };
    data: {
        units: CurriculumUnit[];
        stats: {
            totalUnits: number;
            totalCreditPoints: number;
            byYearLevel: Record<string, { count: number; total_credits: number }>;
            byUnitScope: Record<string, number>;
        };
    };
    meta: {
        filters: {
            search?: string;
            unit_scope?: string;
            year_level?: string;
            semester_number?: string;
        };
    };
    units?: Unit[];
}

const props = defineProps<Props>();
const api = useApi();

// Filters state for the tab
const filters = reactive({
    search: props.meta.filters.search || '',
    unit_scope: props.meta.filters.unit_scope || 'all',
    year_level: props.meta.filters.year_level || 'all',
    semester_number: props.meta.filters.semester_number || 'all',
});

// Modal states
const showAddUnitModal = ref(false);
const showEditUnitModal = ref(false);
const curriculumUnitToEdit = ref<CurriculumUnit | null>(null);
const showDeleteDialog = ref(false);
const unitToDelete = ref<CurriculumUnit | null>(null);
const isDeleting = ref(false);

const permission = usePermissions();
// Form schemas
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

// Form submission handlers
const isAddSubmitting = ref(false);
const isEditSubmitting = ref(false);

// Unit search states
const addUnitSearch = ref('');
const editUnitSearch = ref('');

const yearLevelStats = computed(() => {
    return Object.entries(props.data.stats.byYearLevel).map(([level, data]) => ({
        level: `Year ${level}`,
        count: data.count,
        credits: data.total_credits,
    }));
});

const unitScopeStats = computed(() => {
    const scopeLabels: Record<string, string> = {
        program: 'Program',
        common: 'Common',
        specialization_specific: 'Specialization Specific',
        cross_program: 'Cross Program',
    };

    return Object.entries(props.data.stats.byUnitScope).map(([scope, count]) => ({
        scope: scopeLabels[scope] || scope,
        count,
    }));
});

// Curriculum units data
const curriculumUnits = computed(() => props.data.units || []);

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
    const existingUnitIds = curriculumUnits.value.filter((cu) => cu.unit_id !== currentUnitId).map((cu) => cu.unit_id);
    return props.units.filter((unit) => !existingUnitIds.includes(unit.id));
});

// Filtered units for add modal with search
const filteredAvailableUnits = computed(() => {
    if (!addUnitSearch.value.trim()) {
        return availableUnits.value;
    }
    const searchTerm = addUnitSearch.value.toLowerCase().trim();
    return availableUnits.value.filter((unit) => unit.code.toLowerCase().includes(searchTerm) || unit.name.toLowerCase().includes(searchTerm));
});

// Filtered units for edit modal with search
const filteredEditableUnits = computed(() => {
    if (!editUnitSearch.value.trim()) {
        return editableUnits.value;
    }
    const searchTerm = editUnitSearch.value.toLowerCase().trim();
    return editableUnits.value.filter((unit) => unit.code.toLowerCase().includes(searchTerm) || unit.name.toLowerCase().includes(searchTerm));
});

// Column definitions
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
                curriculumUnit.unit_scope ? h(Badge, { variant: 'outline', class: 'text-xs' }, () => curriculumUnit.unit_scope!.toUpperCase()) : h(Badge, { variant: 'secondary', class: 'text-xs' }, () => 'Unspecified'),
                h('div', { class: 'space-y-1' }, [
                    curriculumUnit.year_level ? h('div', { class: 'text-sm font-medium' }, `Year ${curriculumUnit.year_level}`) : h('div', { class: 'text-sm text-gray-500' }, 'Unspecified Year'),
                    curriculumUnit.semester_number ? h('div', { class: 'text-xs text-gray-600' }, `Semester ${curriculumUnit.semester_number}`) : h('div', { class: 'text-xs text-gray-400' }, 'No semester'),
                ]),
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
                    permission.can('edit_curriculum_unit')
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
                    permission.can('delete_curriculum_unit')
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

const columns = createColumns(baseColumns, {
    enableSelection: true,
});

// Event handlers
const handleSearch = (value: string | number) => {
    filters.search = String(value);
    applyFilters();
};

const applyFilters = () => {
    const query = {
        search: filters.search || undefined,
        unit_scope: filters.unit_scope !== 'all' ? filters.unit_scope : undefined,
        year_level: filters.year_level !== 'all' ? filters.year_level : undefined,
        semester_number: filters.semester_number !== 'all' ? filters.semester_number : undefined,
    };

    router.get(route('curriculum_versions.summary.units', { curriculum_version: props.curriculumVersion.id }), query, {
        replace: true,
        preserveScroll: true,
        preserveState: true,
    });
};

// Event handlers
const handleAddUnitClick = () => {
    addUnitSearch.value = '';
    showAddUnitModal.value = true;
};

const onAddUnitSubmit = async (values: any) => {
    const formData = values as AddUnitFormData;
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

    const { data, error, statusCode } = await api.post('/api/curriculum-units', submitData);

    if (statusCode.value === 201 && data.value?.success) {
        toast.success('Curriculum unit added successfully');
        showAddUnitModal.value = false;
        addUnitSearch.value = '';
        router.reload({ only: ['data', 'units'] });
    } else {
        const errorMessage = data.value?.message || error.value || 'Failed to add curriculum unit';
        toast.error(errorMessage);
    }
    isAddSubmitting.value = false;
};

const editCurriculumUnit = (curriculumUnit: CurriculumUnit) => {
    editUnitSearch.value = '';
    curriculumUnitToEdit.value = curriculumUnit;
    showEditUnitModal.value = true;
};

const onEditUnitSubmit = async (values: any) => {
    const formData = values as EditUnitFormData;
    isEditSubmitting.value = true;
    if (!curriculumUnitToEdit.value) {
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

    const { data, error, statusCode } = await api.put(`/api/curriculum-units/${curriculumUnitToEdit.value.id}`, submitData);

    if (statusCode.value === 200 && data.value?.success) {
        toast.success('Curriculum unit updated successfully');
        showEditUnitModal.value = false;
        editUnitSearch.value = '';
        curriculumUnitToEdit.value = null;
        router.reload({ only: ['data', 'units'] });
    } else {
        const errorMessage = data.value?.message || error.value || 'Failed to update curriculum unit';
        toast.error(errorMessage);
    }
    isEditSubmitting.value = false;
};

const deleteCurriculumUnit = (curriculumUnit: CurriculumUnit) => {
    unitToDelete.value = curriculumUnit;
    showDeleteDialog.value = true;
};

const confirmDelete = async () => {
    if (!unitToDelete.value) return;

    isDeleting.value = true;

    const { data, error, statusCode } = await api.delete(`/api/curriculum-units/${unitToDelete.value.id}`);

    if (statusCode.value === 200 && data.value?.success) {
        toast.success('Curriculum unit deleted successfully');
        showDeleteDialog.value = false;
        unitToDelete.value = null;
        router.reload({ only: ['data'] });
    } else {
        const errorMessage = data.value?.message || error.value || 'Failed to delete curriculum unit';
        toast.error(errorMessage);
    }

    isDeleting.value = false;
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

// Watch for filter changes
watch([() => filters.unit_scope, () => filters.year_level, () => filters.semester_number], () => {
    applyFilters();
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

    return organized;
});
</script>

<template>
    <Head :title="`${curriculumVersion.version_code} - Units`" />

    <CurriculumVersionSummaryLayout :curriculum-version="curriculumVersion">
        <div class="space-y-6">
            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Units</CardTitle>
                        <Book class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ data.stats.totalUnits }}</div>
                        <p class="text-muted-foreground text-xs">Academic units</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Credit Points</CardTitle>
                        <Target class="h-4 w-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">{{ data.stats.totalCreditPoints }}</div>
                        <p class="text-muted-foreground text-xs">Total credits</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Year Levels</CardTitle>
                        <GraduationCap class="h-4 w-4 text-green-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ yearLevelStats.length }}</div>
                        <p class="text-muted-foreground text-xs">Academic years</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Unit Scopes</CardTitle>
                        <Book class="h-4 w-4 text-purple-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-purple-600">{{ unitScopeStats.length }}</div>
                        <p class="text-muted-foreground text-xs">Different scopes</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Structure overview -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <Book class="h-5 w-5" />
                            Structure
                        </CardTitle>

                        <div v-if="permission.can('create_curriculum_unit')" class="flex items-center gap-2">
                            <Button variant="outline" size="sm" :disabled="availableUnits.length === 0" @click="handleAddUnitClick">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Unit
                            </Button>
                            <span v-if="availableUnits.length === 0" class="text-muted-foreground text-xs"> All units added </span>
                        </div>
                    </div>
                </CardHeader>
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
                                                <Button v-if="permission.can('edit_curriculum_unit')" variant="ghost" size="sm" @click="editCurriculumUnit(unit)">
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
            <!-- Units Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <Book class="h-5 w-5" />
                            Curriculum Units
                            <Badge variant="secondary" class="ml-2"> {{ data.units.length }} </Badge>
                        </CardTitle>
                    </div>

                    <!-- Filters -->
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-4">
                        <DebouncedInput v-model="filters.search" @debounced="handleSearch" placeholder="Search units..." class="w-full" />

                        <Select v-model="filters.unit_scope">
                            <SelectTrigger>
                                <SelectValue placeholder="All scopes" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All scopes</SelectItem>
                                <SelectItem value="program">Program</SelectItem>
                                <SelectItem value="common">Common</SelectItem>
                                <SelectItem value="specialization_specific">Specialization Specific</SelectItem>
                                <SelectItem value="cross_program">Cross Program</SelectItem>
                            </SelectContent>
                        </Select>

                        <Select v-model="filters.year_level">
                            <SelectTrigger>
                                <SelectValue placeholder="All years" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All years</SelectItem>
                                <SelectItem v-for="year in [1, 2, 3, 4, 5]" :key="year" :value="year.toString()"> Year {{ year }} </SelectItem>
                            </SelectContent>
                        </Select>

                        <Select v-model="filters.semester_number">
                            <SelectTrigger>
                                <SelectValue placeholder="All semesters" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All semesters</SelectItem>
                                <SelectItem v-for="semester in [1, 2, 3, 4, 5, 6]" :key="semester" :value="semester.toString()"> Semester {{ semester }} (Year {{ Math.ceil(semester / 3) }}) </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardHeader>

                <CardContent>
                    <div v-if="data.units.length === 0" class="py-8 text-center">
                        <Book class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-4 text-sm font-medium">No curriculum units found</h3>
                        <p class="text-muted-foreground mt-2 text-sm">
                            {{ Object.values(filters).some((f) => f && f !== 'all') ? 'Try adjusting your search filters.' : "This curriculum version doesn't have any units yet." }}
                        </p>
                        <div v-if="permission.can('create_curriculum_unit')" class="mt-6">
                            <Button :disabled="availableUnits.length === 0" @click="handleAddUnitClick">
                                <Plus class="mr-2 h-4 w-4" />
                                Add First Unit
                            </Button>
                            <p v-if="availableUnits.length === 0" class="text-muted-foreground mt-2 text-xs">All available units have been added to this curriculum version.</p>
                        </div>
                    </div>

                    <div v-else>
                        <DataTable :data="data.units" :columns="columns" :loading="false" :enable-row-selection="false" class="border-0" />
                    </div>
                </CardContent>
            </Card>
        </div>

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
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-1">
                        <FormField v-slot="{ componentField }" name="unit_id">
                            <FormItem>
                                <FormLabel>Unit *</FormLabel>
                                <FormControl>
                                    <Combobox v-bind="componentField">
                                        <ComboboxAnchor>
                                            <div class="relative w-full items-center">
                                                <ComboboxInput
                                                    v-model="addUnitSearch"
                                                    placeholder="Search for a unit..."
                                                    :display-value="
                                                        (value) => {
                                                            const unit = availableUnits.find((u) => u.id.toString() === value?.toString());
                                                            return unit ? `${unit.code} - ${unit.name} (${unit.credit_points} CP)` : '';
                                                        }
                                                    "
                                                />
                                                <ComboboxTrigger class="absolute inset-y-0 end-0 flex items-center justify-center px-3">
                                                    <ChevronsUpDown class="text-muted-foreground size-4" />
                                                </ComboboxTrigger>
                                            </div>
                                        </ComboboxAnchor>
                                        <ComboboxList class="max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-y-auto">
                                            <ComboboxViewport>
                                                <ComboboxEmpty v-if="filteredAvailableUnits.length === 0">
                                                    {{ availableUnits.length === 0 ? 'No available units to add' : 'No units found' }}
                                                </ComboboxEmpty>
                                                <ComboboxItem v-for="unit in filteredAvailableUnits" :key="unit.id" :value="unit.id.toString()" class="cursor-pointer"> {{ unit.code }} - {{ unit.name }} ({{ unit.credit_points }} CP) </ComboboxItem>
                                            </ComboboxViewport>
                                        </ComboboxList>
                                    </Combobox>
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
                                            <SelectItem v-for="semester in [1, 2, 3, 4, 5, 6]" :key="semester" :value="semester.toString()"> Semester {{ semester }} (Year {{ Math.ceil(semester / 3) }}) </SelectItem>
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
                                    <Combobox v-bind="componentField">
                                        <ComboboxAnchor>
                                            <ComboboxInput
                                                v-model="editUnitSearch"
                                                placeholder="Search for a unit..."
                                                :display-value="
                                                    (value) => {
                                                        const unit = editableUnits.find((u) => u.id.toString() === value?.toString());
                                                        return unit ? `${unit.code} - ${unit.name} (${unit.credit_points} CP)` : '';
                                                    }
                                                "
                                            />
                                            <ComboboxTrigger />
                                        </ComboboxAnchor>
                                        <ComboboxList class="max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-y-auto">
                                            <ComboboxViewport>
                                                <ComboboxEmpty v-if="filteredEditableUnits.length === 0"> No units found </ComboboxEmpty>
                                                <ComboboxItem v-for="unit in filteredEditableUnits" :key="unit.id" :value="unit.id.toString()" class="cursor-pointer"> {{ unit.code }} - {{ unit.name }} ({{ unit.credit_points }} CP) </ComboboxItem>
                                            </ComboboxViewport>
                                        </ComboboxList>
                                    </Combobox>
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
                                            <SelectItem v-for="semester in [1, 2, 3, 4, 5, 6]" :key="semester" :value="semester.toString()"> Semester {{ semester }} (Year {{ Math.ceil(semester / 3) }}) </SelectItem>
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
        <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Curriculum Unit</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete the curriculum unit
                        <strong>{{ unitToDelete?.unit?.code }}</strong>
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
    </CurriculumVersionSummaryLayout>
</template>
