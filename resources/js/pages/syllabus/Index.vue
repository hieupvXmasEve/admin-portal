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
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, Edit, FileText, Plus, ToggleLeft, ToggleRight, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
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
    syllabi: Syllabus[];
}>();
console.log('props', props.syllabi);
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
        title: 'Syllabi',
        href: `/units/${props.unit.id}/syllabi`,
    },
];

const isDeleting = ref<number | null>(null);

const deleteSyllabus = (syllabusId: number) => {
    isDeleting.value = syllabusId;

    router.delete(`/units/${props.unit.id}/syllabi/${syllabusId}`, {
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
        `/units/${props.unit.id}/syllabi/${syllabusId}/toggle-active`,
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

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const getAssessmentTypeColor = (type: string) => {
    switch (type) {
        case 'quiz':
            return 'bg-blue-100 text-blue-800';
        case 'assignment':
            return 'bg-green-100 text-green-800';
        case 'project':
            return 'bg-purple-100 text-purple-800';
        case 'exam':
            return 'bg-red-100 text-red-800';
        case 'online_activity':
            return 'bg-yellow-100 text-yellow-800';
        case 'other':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head :title="`Syllabi - ${unit.code}`" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="mb-2 flex items-center gap-3">
                        <h1 class="text-3xl font-bold">{{ unit.code }} Syllabi</h1>
                        <Badge class="bg-blue-100 text-blue-800">{{ unit.credit_points }} CP</Badge>
                    </div>
                    <p class="text-xl text-gray-700">{{ unit.name }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" @click="router.visit(`/units/${unit.id}`)">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Unit
                    </Button>
                    <Button @click="router.visit(`/units/${unit.id}/syllabi/create`)">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Syllabus
                    </Button>
                </div>
            </div>

            <!-- Syllabi List -->
            <div v-if="syllabi.length > 0" class="space-y-4">
                <div
                    v-for="syllabus in syllabi"
                    :key="syllabus.id"
                    class="rounded-lg border p-6 transition-colors"
                    :class="syllabus.is_active ? 'border-green-200 bg-green-50' : 'hover:bg-gray-50'"
                >
                    <div class="mb-4 flex items-start justify-between">
                        <div class="flex-1">
                            <div class="mb-3 flex items-center gap-3">
                                <h2 class="text-xl font-semibold">
                                    {{ syllabus.version || 'No Version' }}
                                </h2>
                                <Badge v-if="syllabus.is_active" class="bg-green-100 text-green-800"> Active</Badge>
                                <Badge v-else class="bg-gray-100 text-gray-800"> Inactive</Badge>
                            </div>

                            <div class="grid grid-cols-1 gap-4 text-sm md:grid-cols-4">
                                <div v-if="syllabus.effective_from_semester">
                                    <span class="font-medium text-gray-500">Effective From:</span>
                                    <div class="mt-1">
                                        {{ syllabus.effective_from_semester.name }}
                                    </div>
                                </div>
                                <div v-if="syllabus.total_hours">
                                    <span class="font-medium text-gray-500">Total Hours:</span>
                                    <div class="mt-1">{{ syllabus.total_hours }}</div>
                                </div>
                                <div v-if="syllabus.hours_per_session">
                                    <span class="font-medium text-gray-500">Hours/Session:</span>
                                    <div class="mt-1">{{ syllabus.hours_per_session }}</div>
                                </div>
                                <div v-if="syllabus.assessment_components.length > 0">
                                    <span class="font-medium text-gray-500">Assessment Weight:</span>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span>{{ syllabus.total_assessment_weight || 0 }}%</span>
                                        <CheckCircle v-if="syllabus.total_assessment_weight === 100" class="h-4 w-4 text-green-600" />
                                        <span v-else class="text-xs text-orange-600">Incomplete</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="toggleActive(syllabus.id)"
                                :title="syllabus.is_active ? 'Deactivate' : 'Activate'"
                            >
                                <ToggleRight v-if="syllabus.is_active" class="h-4 w-4 text-green-600" />
                                <ToggleLeft v-else class="h-4 w-4 text-gray-400" />
                            </Button>
                            <Button variant="ghost" size="sm" @click="router.visit(`/units/${unit.id}/syllabi/${syllabus.id}`)">
                                <FileText class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="sm" @click="router.visit(`/units/${unit.id}/syllabi/${syllabus.id}/edit`)">
                                <Edit class="h-4 w-4" />
                            </Button>
                            <AlertDialog>
                                <AlertDialogTrigger as-child>
                                    <Button variant="ghost" size="sm" :disabled="isDeleting === syllabus.id">
                                        <Trash2 class="h-4 w-4 text-red-600" />
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Delete Syllabus</AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Are you sure you want to delete this syllabus? This action cannot be undone.
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
                    </div>

                    <!-- Assessment Components Preview -->
                    <div v-if="syllabus.assessment_components.length > 0" class="mt-4">
                        <h3 class="mb-3 text-sm font-medium text-gray-700">Assessment Components</h3>
                        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                            <div v-for="component in syllabus.assessment_components" :key="component.id" class="rounded border bg-white p-3">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{ component.name }}</span>
                                    <div class="flex items-center gap-2">
                                        <Badge class="text-xs" :class="getAssessmentTypeColor(component.type)">
                                            {{ component.type.toUpperCase() }}
                                        </Badge>
                                        <span class="text-sm text-gray-500">{{ component.weight }}%</span>
                                    </div>
                                </div>
                                <div v-if="component.details.length > 0" class="mt-2">
                                    <div class="text-xs text-gray-500">{{ component.details.length }} sub-tasks</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div v-if="syllabus.description" class="mt-4">
                        <h3 class="mb-2 text-sm font-medium text-gray-700">Description</h3>
                        <p class="text-sm text-gray-600">{{ syllabus.description }}</p>
                    </div>

                    <!-- Metadata -->
                    <div class="mt-4 flex justify-between text-xs text-gray-500">
                        <span>Created: {{ formatDate(syllabus.created_at) }}</span>
                        <span>Updated: {{ formatDate(syllabus.updated_at) }}</span>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <Card v-else>
                <CardContent class="flex flex-col items-center justify-center py-12 text-center">
                    <FileText class="mb-4 h-16 w-16 text-gray-400" />
                    <h3 class="mb-2 text-xl font-semibold text-gray-900">No Syllabi</h3>
                    <p class="max-w-md text-gray-500">
                        This unit doesn't have any syllabi yet. Create the first syllabus to define the course structure and assessment components.
                    </p>
                    <Button class="mt-4" @click="router.visit(`/units/${unit.id}/syllabi/create`)">
                        <Plus class="mr-2 h-4 w-4" />
                        Create First Syllabus
                    </Button>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
