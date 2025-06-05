<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Program, Semester, Specialization } from '@/types/models';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, Book, Calendar, FileText, Info, Plus, School, Target } from 'lucide-vue-next';
import { computed, h } from 'vue';

interface CurriculumUnit {
    id: number;
    curriculum_version_id: number;
    unit_id: number;
    unit_type_id?: number;
    semester_order?: number;
    is_compulsory: boolean;
    note?: string;
    group_type?: string;
    unit_scope?: string;
    is_required: boolean;
    year_level?: number;
    semester_number?: number;
    minimum_grade?: number;
    special_conditions?: string;
    allows_concurrent_enrollment: boolean;
    created_at: string;
    updated_at: string;
    unit?: {
        id: number;
        code: string;
        name: string;
        credit_points: number;
    };
    unitType?: {
        id: number;
        name: string;
        description?: string;
    };
}

interface CurriculumVersion {
    id: number;
    program_id: number;
    specialization_id?: number;
    version_code: string;
    semester_id?: number;
    notes?: string;
    created_at: string;
    updated_at: string;
    program?: {
        id: number;
        name: string;
        code?: string;
        description?: string;
    };
    specialization?: {
        id: number;
        name: string;
        code?: string;
        description?: string;
    };
    effective_from_semester?: {
        id: number;
        name: string;
        code?: string;
    };
    curriculum_units?: CurriculumUnit[];
}

const props = defineProps<{
    curriculumVersion: CurriculumVersion;
    programs?: Program[];
    specializations?: Specialization[];
    semesters?: Semester[];
}>();

const page = usePage();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Curriculum Versions',
        href: '/curriculum-versions',
    },
    {
        title: props.curriculumVersion.version_code || `Version #${props.curriculumVersion.id}`,
        href: `/curriculum-versions/${props.curriculumVersion.id}`,
    },
];

// Permission check function
const can = (permission: string) => {
    const permissions = (page.props as any).permissions || [];
    return permissions.includes(permission);
};

// Computed values
const curriculumUnits = computed(() => props.curriculumVersion.curriculum_units || []);

const stats = computed(() => {
    const units = curriculumUnits.value;
    const totalUnits = units.length;
    const requiredUnits = units.filter((unit) => unit.is_required).length;
    const electiveUnits = totalUnits - requiredUnits;
    const totalCreditPoints = units.reduce((sum, unit) => sum + (unit.unit?.credit_points || 0), 0);

    const byYearLevel = units.reduce(
        (acc, unit) => {
            const year = unit.year_level || 0;
            const key = year > 0 ? `Year ${year}` : 'Unspecified';
            acc[key] = (acc[key] || 0) + 1;
            return acc;
        },
        {} as Record<string, number>,
    );

    const byGroupType = units.reduce(
        (acc, unit) => {
            const type = unit.group_type || 'Unspecified';
            acc[type] = (acc[type] || 0) + 1;
            return acc;
        },
        {} as Record<string, number>,
    );

    return {
        totalUnits,
        requiredUnits,
        electiveUnits,
        totalCreditPoints,
        byYearLevel,
        byGroupType,
    };
});

// Column definitions for curriculum units table
const columns: ColumnDef<CurriculumUnit>[] = [
    {
        header: 'Unit',
        accessorKey: 'unit.code',
        cell: ({ row }) => {
            const curriculumUnit = row.original;
            const unit = curriculumUnit.unit;

            if (!unit) {
                return h('div', { class: 'text-gray-500' }, 'N/A');
            }

            return h('div', { class: 'space-y-1' }, [
                h('div', { class: 'font-medium' }, unit.code),
                h('div', { class: 'text-sm text-gray-600' }, unit.name),
                h('div', { class: 'text-xs text-gray-500' }, `${unit.credit_points} credit points`),
            ]);
        },
    },
    {
        header: 'Type & Requirements',
        accessorKey: 'group_type',
        cell: ({ row }) => {
            const curriculumUnit = row.original;

            return h(
                'div',
                { class: 'space-y-2' },
                [
                    curriculumUnit.group_type
                        ? h(
                              Badge,
                              { variant: 'outline', class: 'text-xs' },
                              {
                                  default: () => curriculumUnit.group_type,
                              },
                          )
                        : null,
                    curriculumUnit.is_required
                        ? h(
                              Badge,
                              { variant: 'default', class: 'text-xs' },
                              {
                                  default: () => 'Required',
                              },
                          )
                        : h(
                              Badge,
                              { variant: 'secondary', class: 'text-xs' },
                              {
                                  default: () => 'Elective',
                              },
                          ),
                    curriculumUnit.is_compulsory
                        ? h(
                              Badge,
                              { variant: 'destructive', class: 'text-xs' },
                              {
                                  default: () => 'Compulsory',
                              },
                          )
                        : null,
                ].filter(Boolean),
            );
        },
    },
    {
        header: 'Academic Level',
        accessorKey: 'year_level',
        cell: ({ row }) => {
            const curriculumUnit = row.original;

            return h(
                'div',
                { class: 'space-y-1' },
                [
                    curriculumUnit.year_level
                        ? h('div', { class: 'text-sm font-medium' }, `Year ${curriculumUnit.year_level}`)
                        : h('div', { class: 'text-sm text-gray-500' }, 'Unspecified'),
                    curriculumUnit.semester_number
                        ? h('div', { class: 'text-xs text-gray-600' }, `Semester ${curriculumUnit.semester_number}`)
                        : null,
                    curriculumUnit.semester_order ? h('div', { class: 'text-xs text-gray-500' }, `Order: ${curriculumUnit.semester_order}`) : null,
                ].filter(Boolean),
            );
        },
    },
    {
        header: 'Additional Info',
        accessorKey: 'note',
        cell: ({ row }) => {
            const curriculumUnit = row.original;

            const infoItems = [];

            if (curriculumUnit.minimum_grade) {
                infoItems.push(h('div', { class: 'text-xs' }, `Min. Grade: ${curriculumUnit.minimum_grade}`));
            }

            if (curriculumUnit.allows_concurrent_enrollment) {
                infoItems.push(
                    h(
                        Badge,
                        { variant: 'outline', class: 'text-xs' },
                        {
                            default: () => 'Concurrent Enrollment',
                        },
                    ),
                );
            }

            if (curriculumUnit.special_conditions) {
                infoItems.push(
                    h('div', { class: 'text-xs text-orange-600 truncate', title: curriculumUnit.special_conditions }, 'Special Conditions'),
                );
            }

            if (curriculumUnit.note) {
                infoItems.push(h('div', { class: 'text-xs text-gray-600 truncate', title: curriculumUnit.note }, curriculumUnit.note));
            }

            return h('div', { class: 'space-y-1' }, infoItems);
        },
    },
];

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const handleAddUnitClick = () => {
    router.visit(`/curriculum-units/create?curriculum_version_id=${props.curriculumVersion.id}`);
};
</script>

<template>
    <Head :title="`Curriculum Version: ${curriculumVersion.version_code || `#${curriculumVersion.id}`}`" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex flex-col gap-6 p-4">
            <!-- Header Section -->
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <h1 class="text-3xl font-bold tracking-tight">
                                {{ curriculumVersion.version_code || `Curriculum Version #${curriculumVersion.id}` }}
                            </h1>
                        </div>

                        <!-- Program and Specialization Info -->
                        <div class="flex flex-wrap gap-4 text-sm">
                            <div class="flex items-center gap-2" v-if="curriculumVersion.program">
                                <School class="h-4 w-4 text-gray-500" />
                                <span class="font-medium">{{ curriculumVersion.program.name }}</span>
                                <Badge v-if="curriculumVersion.program.code" variant="outline" class="text-xs">
                                    {{ curriculumVersion.program.code }}
                                </Badge>
                            </div>

                            <div class="flex items-center gap-2" v-if="curriculumVersion.specialization">
                                <Target class="h-4 w-4 text-blue-500" />
                                <span class="font-medium text-blue-700">{{ curriculumVersion.specialization.name }}</span>
                                <Badge v-if="curriculumVersion.specialization.code" variant="outline" class="border-blue-200 text-xs text-blue-700">
                                    {{ curriculumVersion.specialization.code }}
                                </Badge>
                            </div>

                            <div class="flex items-center gap-2" v-if="curriculumVersion.effective_from_semester">
                                <Calendar class="h-4 w-4 text-green-500" />
                                <span class="text-green-700">{{ curriculumVersion.effective_from_semester.name }}</span>
                            </div>
                        </div>

                        <div class="text-muted-foreground flex flex-wrap gap-4 text-sm">
                            <span>Created: {{ formatDate(curriculumVersion.created_at) }}</span>
                            <span>Updated: {{ formatDate(curriculumVersion.updated_at) }}</span>
                        </div>
                    </div>

                    <Link :href="route('curriculum_version.index')">
                        <Button variant="outline">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Versions
                        </Button>
                    </Link>
                </div>

                <!-- Notes Section -->
                <Card v-if="curriculumVersion.notes" class="border-blue-200 bg-blue-50">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-blue-800">
                            <FileText class="h-4 w-4" />
                            Notes
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pt-0">
                        <p class="whitespace-pre-wrap text-blue-700">{{ curriculumVersion.notes }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Statistics Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Units</CardTitle>
                        <Book class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.totalUnits }}</div>
                        <p class="text-muted-foreground text-xs">{{ stats.totalCreditPoints }} credit points</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Required Units</CardTitle>
                        <Target class="h-4 w-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">{{ stats.requiredUnits }}</div>
                        <p class="text-muted-foreground text-xs">{{ ((stats.requiredUnits / stats.totalUnits) * 100).toFixed(1) }}% of total</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Elective Units</CardTitle>
                        <Info class="h-4 w-4 text-gray-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-gray-600">{{ stats.electiveUnits }}</div>
                        <p class="text-muted-foreground text-xs">{{ ((stats.electiveUnits / stats.totalUnits) * 100).toFixed(1) }}% of total</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Year Levels</CardTitle>
                        <Calendar class="h-4 w-4 text-green-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ Object.keys(stats.byYearLevel).length }}</div>
                        <p class="text-muted-foreground text-xs">Different levels</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Curriculum Units Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <Book class="h-5 w-5" />
                            Curriculum Units
                            <Badge variant="secondary" class="ml-2">{{ curriculumUnits.length }}</Badge>
                        </CardTitle>

                        <Button v-if="can('create_curriculum_unit')" variant="outline" size="sm" @click="handleAddUnitClick">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Unit
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="px-6">
                    <div v-if="curriculumUnits.length === 0" class="py-8 text-center">
                        <Book class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-4 text-sm font-medium">No curriculum units found</h3>
                        <p class="text-muted-foreground mt-2 text-sm">Get started by adding curriculum units to this version.</p>
                        <div class="mt-6" v-if="can('create_curriculum_unit')">
                            <Button @click="handleAddUnitClick">
                                <Plus class="mr-2 h-4 w-4" />
                                Add First Unit
                            </Button>
                        </div>
                    </div>

                    <div v-else>
                        <DataTable :data="curriculumUnits" :columns="columns" :loading="false" class="border-0" />
                    </div>
                </CardContent>
            </Card>

            <!-- Year Level Breakdown -->
            <div class="grid gap-4 md:grid-cols-2" v-if="Object.keys(stats.byYearLevel).length > 1">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Calendar class="h-5 w-5" />
                            Units by Year Level
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="(count, year) in stats.byYearLevel"
                                :key="year"
                                class="flex items-center justify-between rounded-lg bg-gray-50 p-2"
                            >
                                <span class="font-medium">{{ year }}</span>
                                <Badge variant="outline">{{ count }} units</Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Info class="h-5 w-5" />
                            Units by Group Type
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="(count, type) in stats.byGroupType"
                                :key="type"
                                class="flex items-center justify-between rounded-lg bg-gray-50 p-2"
                            >
                                <span class="font-medium">{{ type }}</span>
                                <Badge variant="outline">{{ count }} units</Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
