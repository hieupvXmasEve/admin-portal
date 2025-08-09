<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import CurriculumVersionSummaryLayout from '@/layouts/CurriculumVersionSummaryLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink, GraduationCap, Pause, Users, UserX, X } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    curriculumVersion: {
        id: number;
        version_code: string;
        notes?: string;
        created_at: string;
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
        counts: {
            active: number;
            graduated: number;
            suspended: number;
            withdrawn: number;
            on_leave: number;
        };
        total: number;
    };
    meta: {
        lastUpdatedAt: string;
    };
    links: {
        drillDown: {
            active: string;
            graduated: string;
            suspended: string;
            withdrawn: string;
            on_leave: string;
        };
    };
}

const props = defineProps<Props>();
console.log('%c props', 'color: red', props);
// Student status configurations
const studentStatuses = [
    {
        key: 'active',
        label: 'Active Students',
        description: 'Currently enrolled and studying',
        icon: Users,
        color: 'text-green-600',
        bgColor: 'bg-green-100',
        borderColor: 'border-green-200',
    },
    {
        key: 'graduated',
        label: 'Graduated',
        description: 'Successfully completed the program',
        icon: GraduationCap,
        color: 'text-blue-600',
        bgColor: 'bg-blue-100',
        borderColor: 'border-blue-200',
    },
    {
        key: 'on_leave',
        label: 'On Leave',
        description: 'Temporarily paused studies',
        icon: Pause,
        color: 'text-yellow-600',
        bgColor: 'bg-yellow-100',
        borderColor: 'border-yellow-200',
    },
    {
        key: 'suspended',
        label: 'Suspended',
        description: 'Academic or disciplinary suspension',
        icon: X,
        color: 'text-orange-600',
        bgColor: 'bg-orange-100',
        borderColor: 'border-orange-200',
    },
    {
        key: 'withdrawn',
        label: 'Withdrawn',
        description: 'Left the program permanently',
        icon: UserX,
        color: 'text-red-600',
        bgColor: 'bg-red-100',
        borderColor: 'border-red-200',
    },
];

// Computed properties
const totalStudents = computed(() => props.data.total);

const statusStats = computed(() => {
    return studentStatuses.map((status) => ({
        ...status,
        count: props.data.counts[status.key as keyof typeof props.data.counts],
        percentage: totalStudents.value > 0 ? Math.round((props.data.counts[status.key as keyof typeof props.data.counts] / totalStudents.value) * 100) : 0,
        drillDownUrl: props.links.drillDown[status.key as keyof typeof props.links.drillDown],
    }));
});

const activeStudentsPercentage = computed(() => {
    return totalStudents.value > 0 ? Math.round((props.data.counts.active / totalStudents.value) * 100) : 0;
});

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="`${curriculumVersion.version_code} - Students`" />

    <CurriculumVersionSummaryLayout :curriculum-version="curriculumVersion">
        <div class="space-y-6">
            <!-- Overview Statistics -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Students</CardTitle>
                        <Users class="h-4 w-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ totalStudents }}</div>
                        <p class="text-muted-foreground text-xs">All time enrollment</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Active Students</CardTitle>
                        <Users class="h-4 w-4 text-green-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ data.counts.active }}</div>
                        <p class="text-muted-foreground text-xs">{{ activeStudentsPercentage }}% of total</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Graduated</CardTitle>
                        <GraduationCap class="h-4 w-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">{{ data.counts.graduated }}</div>
                        <p class="text-muted-foreground text-xs">Successfully completed</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Detailed Student Status Breakdown -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Users class="h-5 w-5" />
                        Student Status Breakdown
                    </CardTitle>
                    <p class="text-muted-foreground text-sm">Last updated: {{ formatDate(meta.lastUpdatedAt) }}</p>
                </CardHeader>
                <CardContent>
                    <div v-if="totalStudents === 0" class="py-12 text-center">
                        <Users class="mx-auto h-16 w-16 text-gray-400" />
                        <h3 class="mt-4 text-lg font-medium">No Students Enrolled</h3>
                        <p class="text-muted-foreground mt-2 text-sm">This curriculum version doesn't have any students enrolled yet.</p>
                        <div class="mt-6">
                            <Button variant="outline">
                                <ExternalLink class="mr-2 h-4 w-4" />
                                View All Students
                            </Button>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <div v-for="status in statusStats" :key="status.key" class="group relative overflow-hidden rounded-lg border transition-all hover:shadow-md" :class="status.borderColor">
                            <div class="flex items-center justify-between p-4">
                                <div class="flex items-center gap-4">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-lg" :class="status.bgColor">
                                        <component :is="status.icon" :class="['h-6 w-6', status.color]" />
                                    </div>

                                    <div>
                                        <h3 class="font-semibold">{{ status.label }}</h3>
                                        <p class="text-muted-foreground text-sm">{{ status.description }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <div class="text-2xl font-bold" :class="status.color">
                                            {{ status.count }}
                                        </div>
                                        <div class="text-muted-foreground text-xs">{{ status.percentage }}% of total</div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <!-- Progress bar -->
                                        <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                                            <div class="h-full transition-all duration-500" :class="status.bgColor.replace('bg-', 'bg-').replace('-100', '-500')" :style="{ width: `${status.percentage}%` }"></div>
                                        </div>

                                        <!-- Drill-down link -->
                                        <Link v-if="status.count > 0" :href="status.drillDownUrl" class="opacity-0 transition-opacity group-hover:opacity-100">
                                            <Button variant="ghost" size="sm">
                                                <ExternalLink class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Quick Actions & Information -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Quick Actions -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <ExternalLink class="h-5 w-5" />
                            Quick Actions
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <Button variant="outline" class="w-full justify-start" @click="() => (window as any).open(links.drillDown.active, '_blank')">
                            <Users class="mr-2 h-4 w-4" />
                            View All Active Students
                        </Button>

                        <Button variant="outline" class="w-full justify-start" @click="() => (window as any).open(links.drillDown.graduated, '_blank')">
                            <GraduationCap class="mr-2 h-4 w-4" />
                            View Graduated Students
                        </Button>

                        <Button variant="outline" class="w-full justify-start" disabled>
                            <ExternalLink class="mr-2 h-4 w-4" />
                            Export Student Report
                        </Button>
                    </CardContent>
                </Card>

                <!-- Program Information -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <GraduationCap class="h-5 w-5" />
                            Program Context
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div>
                            <label class="text-sm font-medium text-gray-700">Program</label>
                            <p class="text-sm text-gray-900">{{ curriculumVersion.program.name }} ({{ curriculumVersion.program.code }})</p>
                        </div>

                        <div v-if="curriculumVersion.specialization">
                            <label class="text-sm font-medium text-gray-700">Specialization</label>
                            <p class="text-sm text-gray-900">
                                {{ curriculumVersion.specialization.name }}
                            </p>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-700">Version</label>
                            <p class="text-sm text-gray-900">{{ curriculumVersion.version_code }}</p>
                        </div>

                        <div v-if="curriculumVersion.effective_from_semester">
                            <label class="text-sm font-medium text-gray-700">Effective From</label>
                            <p class="text-sm text-gray-900">{{ curriculumVersion.effective_from_semester.name }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Note about placeholder data -->
            <Card v-if="totalStudents === 0" class="border-dashed">
                <CardContent class="py-6">
                    <div class="text-center">
                        <div class="mx-auto w-fit rounded-full bg-blue-100 p-3">
                            <Users class="h-6 w-6 text-blue-600" />
                        </div>
                        <h3 class="mt-4 font-semibold">Student Data Integration</h3>
                        <p class="text-muted-foreground mx-auto mt-2 max-w-md text-sm">
                            Student enrollment data will appear here once the Student model relationships are properly configured with curriculum versions. This is currently showing placeholder data.
                        </p>
                        <Badge variant="secondary" class="mt-3"> TODO: Implement Student-CurriculumVersion relationship </Badge>
                    </div>
                </CardContent>
            </Card>
        </div>
    </CurriculumVersionSummaryLayout>
</template>
