<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useApi } from '@/composables/useApiRequest';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, BookOpen, ChevronLeft, FileCheck, Loader2, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

interface Semester {
    id: number;
    name: string;
    course_offerings_count: number;
}

interface CampusStats {
    campus_name: string;
    campus_code: string;
    total_eligible_students: number;
    enrolled_students: number;
    not_enrolled_students: number;
    enrollment_rate: number;
}

interface Props {
    semester: Semester;
    enrollmentStats: {
        total_enrolled: number;
        by_status: Record<string, number>;
        by_semester_number: Record<string, number>;
    };
    campusStats?: CampusStats;
}

const props = defineProps<Props>();

const generating = ref(false);
const api = useApi();

const generateEnrollments = async () => {
    generating.value = true;

    try {
        const { data } = await api.post(`/api/semesters/${props.semester.id}/enrollment/generate`, {});

        if (data.value?.success) {
            toast.success(data.value.message);
            router.reload({ only: ['semester', 'enrollmentStats', 'campusStats'] });
        } else {
            toast.error(data.value?.message || 'Failed to generate enrollments');
        }
    } catch (error) {
        console.error('Generate enrollments error:', error);
        toast.error('Failed to generate enrollments');
    } finally {
        generating.value = false;
    }
};
</script>

<template>
    <Head :title="`${semester.name} - Enrollment Management`" />

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="sm" @click="router.get(systemRoutes.semesters.index())">
                <ChevronLeft class="h-4 w-4" />
                Back to Semesters
            </Button>
        </div>
        <Heading :title="`${semester.name} - Enrollment Management`" />
    </div>

    <!-- Campus Overview -->
    <Card v-if="campusStats" class="mb-6 border-blue-200 bg-blue-50">
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-blue-800">
                <Users class="h-5 w-5" />
                {{ campusStats.campus_name }} - Student Enrollment Overview
            </CardTitle>
            <CardDescription class="text-blue-700"> Campus: {{ campusStats.campus_code }} | Enrollment management for {{ semester.name }} </CardDescription>
        </CardHeader>
        <CardContent>
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-800">{{ campusStats.total_eligible_students }}</div>
                    <div class="text-sm text-blue-600">Total Eligible Students</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-800">{{ campusStats.enrolled_students }}</div>
                    <div class="text-sm text-green-600">Already Enrolled</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-amber-800">{{ campusStats.not_enrolled_students }}</div>
                    <div class="text-sm text-amber-600">Not Enrolled Yet</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-indigo-800">{{ campusStats.enrollment_rate }}%</div>
                    <div class="text-sm text-indigo-600">Enrollment Rate</div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Enrolled</CardTitle>
                <Users class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ enrollmentStats.total_enrolled }}</div>
                <div class="text-muted-foreground mt-1 text-xs" v-if="campusStats">
                    {{ campusStats.campus_name }}
                </div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">In Progress</CardTitle>
                <FileCheck class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ enrollmentStats.by_status.in_progress || 0 }}</div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Course Offerings</CardTitle>
                <BookOpen class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ semester.course_offerings_count || 0 }}</div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Campus Enrollment Rate</CardTitle>
                <BarChart3 class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ campusStats?.enrollment_rate || 0 }}%</div>
            </CardContent>
        </Card>
    </div>

    <!-- Generate Enrollments -->
    <Card class="mt-4">
        <CardHeader>
            <CardTitle>Generate Student Enrollments</CardTitle>
            <CardDescription>
                Create enrollments for all active students in {{ campusStats?.campus_name || 'current campus' }} based on their curriculum progress.
                <span v-if="campusStats && campusStats.not_enrolled_students > 0" class="mt-2 block font-medium text-amber-600"> {{ campusStats.not_enrolled_students }} students are eligible for enrollment. </span>
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <p class="text-muted-foreground text-sm">This will create enrollment records for students who don't already have one for this semester.</p>
                    <div v-if="campusStats" class="mt-2 text-sm">
                        <span class="font-medium text-blue-600">{{ campusStats.campus_name }}</span
                        >: <span class="ml-2 text-green-600">{{ campusStats.enrolled_students }} enrolled</span> | <span class="ml-2 text-amber-600">{{ campusStats.not_enrolled_students }} pending</span> |
                        <span class="ml-2 text-gray-600">{{ campusStats.total_eligible_students }} total eligible</span>
                    </div>
                </div>
                <Button @click="generateEnrollments" :disabled="generating">
                    <Loader2 v-if="generating" class="mr-2 h-4 w-4 animate-spin" />
                    Generate Enrollments
                    <span v-if="campusStats && campusStats.not_enrolled_students > 0" class="ml-1"> ({{ campusStats.not_enrolled_students }}) </span>
                </Button>
            </div>

            <!-- Enrollment Stats -->
            <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                <div v-for="(count, status) in enrollmentStats.by_status" :key="status" class="rounded bg-gray-50 p-3 text-center">
                    <div class="text-lg font-semibold">{{ count }}</div>
                    <div class="text-muted-foreground text-sm capitalize">{{ status.replace('_', ' ') }}</div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
