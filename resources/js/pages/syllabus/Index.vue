<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import Badge from '@/components/ui/badge/Badge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Copy, Edit, Eye, FileText, Plus, ToggleLeft, ToggleRight, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
}

interface Semester {
    id: number;
    name: string;
    year: number;
}

interface AssessmentComponent {
    id: number;
    name: string;
    weight: number;
    type: string;
    is_required_to_sit_final_exam: boolean;
    details: AssessmentComponentDetail[];
}

interface AssessmentComponentDetail {
    id: number;
    name: string;
    weight: number | null;
}

interface Syllabus {
    id: number;
    version: string | null;
    description: string | null;
    total_hours: number | null;
    hours_per_session: number | null;
    is_active: boolean;
    effective_from_semester: Semester | null;
    assessment_components: AssessmentComponent[];
    total_assessment_weight: number;
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    unit: Unit;
    syllabus: Syllabus[];
}>();
console.log('props', props.syllabus);
const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Units',
        href: '/units',
    },
    {
        title: props.unit.code,
        href: `/units/${props.unit.id}`,
    },
    {
        title: 'Syllabus',
        href: `/units/${props.unit.id}/syllabus`,
    },
];

// Filters
const searchVersion = ref('');
const filterSemester = ref('');
const filterActive = ref('');

// Delete state
const isDeleting = ref<number | null>(null);
// Filtered syllabi
const filteredSyllabi = computed(() => {
    return props.syllabus.filter((syllabus) => {
        const matchesVersion =
            !searchVersion.value || (syllabus.version && syllabus.version.toLowerCase().includes(searchVersion.value.toLowerCase()));

        const matchesSemester =
            !filterSemester.value || (syllabus.effective_from_semester && syllabus.effective_from_semester.id.toString() === filterSemester.value);

        const matchesActive =
            !filterActive.value ||
            (filterActive.value === 'active' && syllabus.is_active) ||
            (filterActive.value === 'inactive' && !syllabus.is_active);

        return matchesVersion && matchesSemester && matchesActive;
    });
});

const clearFilters = () => {
    searchVersion.value = '';
    filterSemester.value = '';
    filterActive.value = '';
};

const deleteSyllabus = (syllabusId: number) => {
    isDeleting.value = syllabusId;

    router.delete(`/units/${props.unit.id}/syllabus/${syllabusId}`, {
        onSuccess: () => {
            toast.success('Syllabus deleted successfully');
            isDeleting.value = null;
        },
        onError: () => {
            toast.error('Failed to delete syllabus');
            isDeleting.value = null;
        },
    });
};

const toggleActive = (syllabusId: number) => {
    router.patch(
        `/units/${props.unit.id}/syllabus/${syllabusId}/toggle-active`,
        {},
        {
            onSuccess: () => {
                toast.success('Syllabus status updated successfully');
            },
            onError: () => {
                toast.error('Failed to update syllabus status');
            },
        },
    );
};

const cloneSyllabus = (syllabusId: number) => {
    router.post(
        `/units/${props.unit.id}/syllabus/${syllabusId}/clone`,
        {},
        {
            onSuccess: () => {
                toast.success('Syllabus cloned successfully');
            },
            onError: () => {
                toast.error('Failed to clone syllabus');
            },
        },
    );
};

const getCurrentSemesterStatus = (syllabus: Syllabus) => {
    if (!syllabus.is_active) return null;

    // This would ideally check against current semester from backend
    // For now, just mark active syllabi
    return syllabus.is_active ? 'Currently in use' : null;
};
</script>

<template>
    <Head :title="`Syllabus - ${unit.code}`" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="mb-2 flex items-center gap-3">
                        <h1 class="text-3xl font-bold">{{ unit.code }} Syllabus</h1>
                        <Badge class="bg-blue-100 text-blue-800">{{ unit.credit_points }} CP</Badge>
                    </div>
                    <p class="text-xl text-gray-700">{{ unit.name }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" @click="router.visit(`/units/${unit.id}`)">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Unit
                    </Button>
                    <Button @click="router.visit(`/units/${unit.id}/syllabus/create`)">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Syllabus
                    </Button>
                </div>
            </div>

            <!-- Syllabus Table -->
            <Card v-if="filteredSyllabi.length > 0">
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Version</TableHead>
                                <TableHead>Semester</TableHead>
                                <TableHead>Active?</TableHead>
                                <TableHead>Total Hours</TableHead>
                                <TableHead>Hours/Session</TableHead>
                                <TableHead>Assessment</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="syllabus in filteredSyllabi" :key="syllabus.id" :class="syllabus.is_active ? 'bg-green-50' : ''">
                                <TableCell class="font-medium">
                                    <div class="flex items-center gap-2">
                                        {{ syllabus.version || 'No Version' }}
                                        <span v-if="getCurrentSemesterStatus(syllabus)" class="text-xs font-medium text-green-600">
                                            {{ getCurrentSemesterStatus(syllabus) }}
                                        </span>
                                    </div>
                                </TableCell>

                                <TableCell>
                                    {{ syllabus.effective_from_semester?.name || '-' }}
                                </TableCell>

                                <TableCell>
                                    <Badge v-if="syllabus.is_active" class="bg-green-100 text-green-800"> ✅ Active </Badge>
                                    <Badge v-else class="bg-gray-100 text-gray-800"> ❌ Inactive </Badge>
                                </TableCell>

                                <TableCell>
                                    {{ syllabus.total_hours || '-' }}
                                </TableCell>

                                <TableCell>
                                    {{ syllabus.hours_per_session || '-' }}
                                </TableCell>

                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm">{{ syllabus.total_assessment_weight || 0 }}%</span>
                                        <Badge v-if="syllabus.total_assessment_weight === 100" class="bg-green-100 text-xs text-green-800">
                                            Complete
                                        </Badge>
                                        <Badge v-else class="bg-orange-100 text-xs text-orange-800"> Incomplete </Badge>
                                    </div>
                                </TableCell>

                                <TableCell class="text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <!-- Toggle Active -->
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="toggleActive(syllabus.id)"
                                            :title="syllabus.is_active ? 'Deactivate' : 'Activate'"
                                        >
                                            <ToggleRight v-if="syllabus.is_active" class="h-4 w-4 text-green-600" />
                                            <ToggleLeft v-else class="h-4 w-4 text-gray-400" />
                                        </Button>

                                        <!-- View -->
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="router.visit(`/units/${unit.id}/syllabus/${syllabus.id}`)"
                                            title="View"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Button>

                                        <!-- Edit -->
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="router.visit(`/units/${unit.id}/syllabus/${syllabus.id}/edit`)"
                                            title="Edit"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>

                                        <!-- Clone -->
                                        <Button variant="ghost" size="sm" @click="cloneSyllabus(syllabus.id)" title="Clone">
                                            <Copy class="h-4 w-4" />
                                        </Button>

                                        <!-- Delete -->
                                        <AlertDialog>
                                            <AlertDialogTrigger as-child>
                                                <Button variant="ghost" size="sm" :disabled="isDeleting === syllabus.id" title="Delete">
                                                    <Trash2 class="h-4 w-4 text-red-600" />
                                                </Button>
                                            </AlertDialogTrigger>
                                            <AlertDialogContent>
                                                <AlertDialogHeader>
                                                    <AlertDialogTitle>Delete Syllabus</AlertDialogTitle>
                                                    <AlertDialogDescription>
                                                        Are you sure you want to delete syllabus "{{ syllabus.version || 'Untitled' }}"? This action
                                                        cannot be undone.
                                                    </AlertDialogDescription>
                                                </AlertDialogHeader>
                                                <AlertDialogFooter>
                                                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                                                    <AlertDialogAction @click="deleteSyllabus(syllabus.id)" class="bg-red-600 hover:bg-red-700">
                                                        Delete
                                                    </AlertDialogAction>
                                                </AlertDialogFooter>
                                            </AlertDialogContent>
                                        </AlertDialog>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Empty State -->
            <Card v-else-if="syllabus.length === 0">
                <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                    <FileText class="mb-4 h-16 w-16 text-gray-400" />
                    <h3 class="mb-2 text-xl font-semibold text-gray-900">No Syllabus</h3>
                    <p class="max-w-md text-gray-500">
                        This unit doesn't have any syllabus yet. Create the first syllabus to define the course structure and assessment components.
                    </p>
                    <Button class="mt-4" @click="router.visit(`/units/${unit.id}/syllabus/create`)">
                        <Plus class="mr-2 h-4 w-4" />
                        Create First Syllabus
                    </Button>
                </CardContent>
            </Card>

            <!-- No Results State -->
            <Card v-else>
                <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                    <h3 class="mb-2 text-xl font-semibold text-gray-900">No Results Found</h3>
                    <p class="max-w-md text-gray-500">No syllabi match your current filters. Try adjusting your search criteria.</p>
                    <Button variant="outline" class="mt-4" @click="clearFilters"> Clear Filters </Button>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
