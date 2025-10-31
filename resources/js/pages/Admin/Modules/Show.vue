<script setup lang="ts">
import ManageUnitsDialog from '@/components/Admin/Modules/ManageUnitsDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useGlobalConfirmDialog } from '@/composables';
import type { Campus, CurriculumVersion, Module, Unit } from '@/types/models';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Edit, GraduationCap, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface CurriculumModuleWithRelations {
    id: number;
    curriculum_version_id: number;
    module_id: number;
    year_level: number | null;
    semester_number: number | null;
    is_required: boolean;
    group_name: string | null;
    order: number;
    note: string | null;
    curriculum_version: CurriculumVersion & {
        program: { id: number; name: string; code: string };
        specialization: { id: number; name: string; code: string } | null;
    };
}

interface ModuleWithRelations extends Module {
    campus: Campus;
    units: Array<
        Unit & {
            pivot: {
                grading_type: 'grade' | 'pass_fail';
                weight: number | null;
                order: number;
            };
        }
    >;
    prerequisite_module: { id: number; code: string; name: string } | null;
    dependent_modules: Array<{ id: number; code: string; name: string }>;
    curriculum_modules: CurriculumModuleWithRelations[];
}

interface Props {
    module: ModuleWithRelations;
    availableUnits: Unit[];
}

const props = defineProps<Props>();
const confirmDialog = useGlobalConfirmDialog();
const manageUnitsDialogOpen = ref(false);

const goBack = () => {
    router.visit(route('admin.modules.index'));
};

const goToEdit = () => {
    router.visit(route('admin.modules.edit', props.module.id));
};

const deleteModule = () => {
    if (props.module.curriculum_modules.length > 0) {
        toast.error('Cannot delete module', {
            description: 'This module is assigned to curriculum versions. Please remove it from curricula first.',
        });
        return;
    }

    confirmDialog.showConfirmDialog(
        {
            title: 'Delete Module',
            message: `Are you sure you want to delete the module "${props.module.code} - ${props.module.name}"? This action cannot be undone.`,
            confirmText: 'Delete',
            cancelText: 'Cancel',
        },
        {
            onConfirm: () => {
                router.delete(route('admin.modules.destroy', props.module.id), {
                    onSuccess: () => {
                        toast.success('Module deleted successfully');
                    },
                    onError: () => {
                        toast.error('Failed to delete module');
                    },
                });
            },
        },
    );
};

const formatGradingType = (type: string): string => {
    return type === 'grade' ? 'Graded (0-5)' : 'Pass/Fail';
};
</script>

<template>
    <Head :title="`Module: ${module.code}`" />

    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="outline" size="sm" @click="goBack">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back
                </Button>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-3xl font-bold tracking-tight">{{ module.code }}</h1>
                        <Badge :variant="module.grading_type === 'grade' ? 'default' : 'secondary'">
                            {{ formatGradingType(module.grading_type) }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground mt-1">{{ module.name }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" @click="goToEdit">
                    <Edit class="mr-2 h-4 w-4" />
                    Edit
                </Button>
                <Button variant="destructive" size="sm" @click="deleteModule">
                    <Trash2 class="mr-2 h-4 w-4" />
                    Delete
                </Button>
            </div>
        </div>

        <!-- Module Information -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <BookOpen class="h-5 w-5" />
                    Module Information
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-muted-foreground text-sm font-medium">Campus</label>
                        <p class="text-base">{{ module.campus.name }}</p>
                    </div>
                    <div>
                        <label class="text-muted-foreground text-sm font-medium">Total Credits</label>
                        <p class="text-base">{{ module.total_credits }}</p>
                    </div>
                    <div>
                        <label class="text-muted-foreground text-sm font-medium">Grading Type</label>
                        <p class="text-base">{{ formatGradingType(module.grading_type) }}</p>
                    </div>
                    <div>
                        <label class="text-muted-foreground text-sm font-medium">Number of Units</label>
                        <p class="text-base">{{ module.units.length }} units</p>
                    </div>
                    <div v-if="module.prerequisite_module" class="md:col-span-2">
                        <label class="text-muted-foreground text-sm font-medium">Prerequisite Module</label>
                        <p class="text-base">{{ module.prerequisite_module.code }} - {{ module.prerequisite_module.name }}</p>
                    </div>
                    <div v-if="module.description" class="md:col-span-2">
                        <label class="text-muted-foreground text-sm font-medium">Description</label>
                        <p class="text-base">{{ module.description }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Sub-Units -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Sub-Units</CardTitle>
                        <CardDescription> Units that are part of this module (ordered by display order) </CardDescription>
                    </div>
                    <Button size="sm" @click="manageUnitsDialogOpen = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Manage Units
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <div v-if="module.units.length === 0" class="py-8 text-center">
                    <p class="text-muted-foreground text-sm">No units assigned to this module yet.</p>
                    <Button variant="outline" size="sm" class="mt-4" @click="manageUnitsDialogOpen = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Units
                    </Button>
                </div>
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Order</TableHead>
                            <TableHead>Unit Code</TableHead>
                            <TableHead>Unit Name</TableHead>
                            <TableHead>Credits</TableHead>
                            <TableHead>Weight</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="unit in module.units" :key="unit.id">
                            <TableCell>{{ unit.pivot.order }}</TableCell>
                            <TableCell class="font-medium">{{ unit.code }}</TableCell>
                            <TableCell>{{ unit.name }}</TableCell>
                            <TableCell>{{ unit.credit_points }}</TableCell>
                            <TableCell>
                                {{ unit.pivot.weight !== null ? unit.pivot.weight : 'N/A' }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Dependent Modules -->
        <Card v-if="module.dependent_modules.length > 0">
            <CardHeader>
                <CardTitle>Dependent Modules</CardTitle>
                <CardDescription> Modules that require this module as a prerequisite </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-2">
                    <div v-for="dependent in module.dependent_modules" :key="dependent.id" class="flex items-center justify-between rounded-lg border p-3">
                        <div>
                            <p class="font-medium">{{ dependent.code }}</p>
                            <p class="text-muted-foreground text-sm">{{ dependent.name }}</p>
                        </div>
                        <Button variant="ghost" size="sm" @click="router.visit(route('admin.modules.show', dependent.id))"> View </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Curriculum Assignments -->
        <Card v-if="module.curriculum_modules.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <GraduationCap class="h-5 w-5" />
                    Curriculum Assignments
                </CardTitle>
                <CardDescription> Curriculum versions where this module is assigned </CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Program</TableHead>
                            <TableHead>Specialization</TableHead>
                            <TableHead>Year</TableHead>
                            <TableHead>Semester</TableHead>
                            <TableHead>Group</TableHead>
                            <TableHead>Required</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="cm in module.curriculum_modules" :key="cm.id">
                            <TableCell class="font-medium">
                                {{ cm.curriculum_version.program.code }}
                            </TableCell>
                            <TableCell>
                                {{ cm.curriculum_version.specialization?.code || 'General' }}
                            </TableCell>
                            <TableCell>{{ cm.year_level || 'N/A' }}</TableCell>
                            <TableCell>{{ cm.semester_number || 'N/A' }}</TableCell>
                            <TableCell>
                                <Badge v-if="cm.group_name" variant="outline">{{ cm.group_name }}</Badge>
                                <span v-else class="text-muted-foreground text-sm">N/A</span>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="cm.is_required ? 'default' : 'secondary'">
                                    {{ cm.is_required ? 'Required' : 'Elective' }}
                                </Badge>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Manage Units Dialog -->
        <ManageUnitsDialog v-model:open="manageUnitsDialogOpen" :module-id="module.id" :selected-units="module.units" :available-units="availableUnits" @close="manageUnitsDialogOpen = false" />
    </div>
</template>
