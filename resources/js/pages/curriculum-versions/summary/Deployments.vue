<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import CurriculumVersionSummaryLayout from '@/layouts/CurriculumVersionSummaryLayout.vue';
import { Head, router, useRemember } from '@inertiajs/vue3';
import { AlertTriangle, BookOpen, Calendar, TrendingUp } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface Props {
    curriculumVersion: {
        id: number;
        version_code: string;
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
        semesters: Array<{
            id: number;
            name: string;
            opened_units_count: number;
            classes_count: number;
            students_count: number;
        }>;
        opened_units: {
            items: Array<{
                unit_code: string;
                unit_name: string;
                classes_count: number;
                students_count: number;
            }>;
            meta: {
                total: number;
            };
        };
        never_opened_units: {
            items: Array<{
                unit_code: string;
                unit_name: string;
                year_level: number | null;
                semester_number: number | null;
                credit_points: number;
            }>;
            meta: {
                total: number;
            };
        };
    };
    meta: {
        filters: {
            semester_id?: string;
            unit_scope?: string;
        };
        currentSemester?: {
            id: number;
            name: string;
            code: string;
        } | null;
    };
}

const props = defineProps<Props>();

// Remember filters state
const filters = useRemember(
    {
        semester_id: props.meta.filters.semester_id || 'all',
        unit_scope: props.meta.filters.unit_scope || 'all',
    },
    'curriculum-version-deployments-filters',
);

// Computed statistics
const deploymentStats = computed(() => {
    const totalSemesters = props.data.semesters.length;
    const totalOpenedUnits = props.data.opened_units.meta.total;
    const totalNeverOpenedUnits = props.data.never_opened_units.meta.total;
    const totalUnits = totalOpenedUnits + totalNeverOpenedUnits;

    const deploymentRate = totalUnits > 0 ? Math.round((totalOpenedUnits / totalUnits) * 100) : 0;

    return {
        totalSemesters,
        totalOpenedUnits,
        totalNeverOpenedUnits,
        totalUnits,
        deploymentRate,
    };
});

const criticalUnits = computed(() => {
    // Units that should be offered based on year level and current semester
    const currentSemester = props.meta.currentSemester;
    if (!currentSemester) return [];

    return props.data.never_opened_units.items.filter((unit) => {
        // This is simplified logic - in real implementation, you'd compare with actual semester timing
        return unit.year_level && unit.year_level <= 2; // Show units from early years as more critical
    });
});

const applyFilters = () => {
    const query = {
        semester_id: filters.semester_id !== 'all' ? filters.semester_id : undefined,
        unit_scope: filters.unit_scope !== 'all' ? filters.unit_scope : undefined,
    };

    router.get(route('curriculum_versions.summary.deployments', { curriculum_version: props.curriculumVersion.id }), query, {
        replace: true,
        preserveScroll: true,
        preserveState: true,
    });
};

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

// Watch for filter changes
watch([() => filters.semester_id, () => filters.unit_scope], () => {
    applyFilters();
});
</script>

<template>
    <Head :title="`${curriculumVersion.version_code} - Deployments`" />

    <CurriculumVersionSummaryLayout :curriculum-version="curriculumVersion">
        <div class="space-y-6">
            <!-- Deployment Overview Statistics -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Deployment Rate</CardTitle>
                        <TrendingUp class="h-4 w-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">{{ deploymentStats.deploymentRate }}%</div>
                        <p class="text-muted-foreground text-xs">Units offered at least once</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Opened Units</CardTitle>
                        <BookOpen class="h-4 w-4 text-green-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ deploymentStats.totalOpenedUnits }}</div>
                        <p class="text-muted-foreground text-xs">Have been offered</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Never Opened</CardTitle>
                        <AlertTriangle class="h-4 w-4 text-orange-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-orange-600">{{ deploymentStats.totalNeverOpenedUnits }}</div>
                        <p class="text-muted-foreground text-xs">Not yet offered</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Active Semesters</CardTitle>
                        <Calendar class="h-4 w-4 text-purple-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-purple-600">{{ deploymentStats.totalSemesters }}</div>
                        <p class="text-muted-foreground text-xs">With deployments</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters -->
            <Card>
                <CardHeader>
                    <CardTitle>Deployment Filters</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Semester</label>
                            <Select v-model="filters.semester_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="All semesters" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All semesters</SelectItem>
                                    <SelectItem v-for="semester in data.semesters" :key="semester.id" :value="semester.id.toString()">
                                        {{ semester.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Unit Scope</label>
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
                        </div>

                        <div class="flex items-end">
                            <Button
                                variant="outline"
                                class="w-full"
                                @click="
                                    filters.semester_id = 'all';
                                    filters.unit_scope = 'all';
                                "
                            >
                                Clear Filters
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Main Content Tabs -->
            <Tabs default-value="semesters" class="w-full">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="semesters">Semester Overview</TabsTrigger>
                    <TabsTrigger value="opened">Opened Units</TabsTrigger>
                    <TabsTrigger value="never-opened">Never Opened</TabsTrigger>
                </TabsList>

                <!-- Semester Overview Tab -->
                <TabsContent value="semesters" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Calendar class="h-5 w-5" />
                                Deployment by Semester
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="data.semesters.length === 0" class="py-12 text-center">
                                <Calendar class="mx-auto h-16 w-16 text-gray-400" />
                                <h3 class="mt-4 text-lg font-medium">No Deployment Data</h3>
                                <p class="text-muted-foreground mt-2 text-sm">No semester deployment information is available for this curriculum version.</p>
                                <Badge variant="secondary" class="mt-3"> TODO: Implement CourseOffering integration </Badge>
                            </div>

                            <div v-else class="space-y-4">
                                <div v-for="semester in data.semesters" :key="semester.id" class="rounded-lg border p-4 transition-colors hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="font-semibold">{{ semester.name }}</h3>
                                            <p class="text-muted-foreground text-sm">Semester deployment summary</p>
                                        </div>

                                        <div class="grid grid-cols-3 gap-4 text-center">
                                            <div>
                                                <div class="text-lg font-bold text-blue-600">{{ semester.opened_units_count }}</div>
                                                <div class="text-muted-foreground text-xs">Units</div>
                                            </div>
                                            <div>
                                                <div class="text-lg font-bold text-green-600">{{ semester.classes_count }}</div>
                                                <div class="text-muted-foreground text-xs">Classes</div>
                                            </div>
                                            <div>
                                                <div class="text-lg font-bold text-purple-600">{{ semester.students_count }}</div>
                                                <div class="text-muted-foreground text-xs">Students</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Opened Units Tab -->
                <TabsContent value="opened" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <BookOpen class="h-5 w-5" />
                                Units That Have Been Offered
                                <Badge variant="secondary" class="ml-2">{{ data.opened_units.meta.total }}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="data.opened_units.items.length === 0" class="py-12 text-center">
                                <BookOpen class="mx-auto h-16 w-16 text-gray-400" />
                                <h3 class="mt-4 text-lg font-medium">No Units Opened Yet</h3>
                                <p class="text-muted-foreground mt-2 text-sm">None of the units from this curriculum version have been offered yet, or the data is not available.</p>
                                <Badge variant="secondary" class="mt-3"> TODO: Implement CourseOffering integration </Badge>
                            </div>

                            <div v-else class="space-y-3">
                                <div v-for="unit in data.opened_units.items" :key="unit.unit_code" class="rounded-lg border p-4 transition-colors hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="font-semibold">{{ unit.unit_code }}</h3>
                                            <p class="text-muted-foreground text-sm">{{ unit.unit_name }}</p>
                                        </div>

                                        <div class="flex items-center gap-6">
                                            <div class="text-center">
                                                <div class="text-lg font-bold text-blue-600">{{ unit.classes_count }}</div>
                                                <div class="text-muted-foreground text-xs">Classes</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-lg font-bold text-green-600">{{ unit.students_count }}</div>
                                                <div class="text-muted-foreground text-xs">Students</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Never Opened Tab -->
                <TabsContent value="never-opened" class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <AlertTriangle class="h-5 w-5" />
                                Units Never Offered
                                <Badge variant="secondary" class="ml-2">{{ data.never_opened_units.meta.total }}</Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="data.never_opened_units.items.length === 0" class="py-12 text-center">
                                <BookOpen class="mx-auto h-16 w-16 text-green-400" />
                                <h3 class="mt-4 text-lg font-medium">All Units Have Been Offered</h3>
                                <p class="text-muted-foreground mt-2 text-sm">Great! All units in this curriculum version have been offered at least once.</p>
                            </div>

                            <div v-else class="space-y-3">
                                <!-- Critical units warning -->
                                <div v-if="criticalUnits.length > 0" class="rounded-lg border border-orange-200 bg-orange-50 p-4">
                                    <div class="flex items-center gap-2 text-orange-800">
                                        <AlertTriangle class="h-5 w-5" />
                                        <h3 class="font-semibold">Critical Units Requiring Attention</h3>
                                    </div>
                                    <p class="mt-1 text-sm text-orange-700">{{ criticalUnits.length }} early-year units have never been offered and may affect student progression.</p>
                                </div>

                                <div
                                    v-for="unit in data.never_opened_units.items"
                                    :key="unit.unit_code"
                                    class="rounded-lg border p-4 transition-colors hover:bg-gray-50"
                                    :class="criticalUnits.some((cu) => cu.unit_code === unit.unit_code) ? 'border-orange-200 bg-orange-50/30' : ''"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div>
                                                <h3 class="font-semibold">{{ unit.unit_code }}</h3>
                                                <p class="text-muted-foreground text-sm">{{ unit.unit_name }}</p>
                                            </div>

                                            <div v-if="criticalUnits.some((cu) => cu.unit_code === unit.unit_code)">
                                                <Badge variant="outline" class="border-orange-300 text-orange-700"> Critical </Badge>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-6 text-sm">
                                            <div v-if="unit.year_level" class="text-center">
                                                <div class="font-medium">Year {{ unit.year_level }}</div>
                                                <div class="text-muted-foreground text-xs">Level</div>
                                            </div>
                                            <div v-if="unit.semester_number" class="text-center">
                                                <div class="font-medium">Sem {{ unit.semester_number }}</div>
                                                <div class="text-muted-foreground text-xs">Number</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="font-medium">{{ unit.credit_points }}</div>
                                                <div class="text-muted-foreground text-xs">Credits</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            <!-- Implementation Note -->
            <Card class="border-dashed">
                <CardContent class="py-6">
                    <div class="text-center">
                        <div class="mx-auto w-fit rounded-full bg-blue-100 p-3">
                            <BookOpen class="h-6 w-6 text-blue-600" />
                        </div>
                        <h3 class="mt-4 font-semibold">Course Offering Integration</h3>
                        <p class="text-muted-foreground mx-auto mt-2 max-w-md text-sm">Deployment tracking will show real data once the CourseOffering model relationships are implemented. This currently displays placeholder data structure.</p>
                        <Badge variant="secondary" class="mt-3"> TODO: Implement CourseOffering-CurriculumVersion queries </Badge>
                    </div>
                </CardContent>
            </Card>
        </div>
    </CurriculumVersionSummaryLayout>
</template>
