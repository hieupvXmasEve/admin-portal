<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import CurriculumVersionSummaryLayout from '@/layouts/CurriculumVersionSummaryLayout.vue';

import { Head } from '@inertiajs/vue3';
import { Book, GraduationCap, Info, Package, Target } from 'lucide-vue-next';
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
        totalUnits: number;
        totalCreditPoints: number;
        byYearLevel: Record<string, number>;
        byUnitScope: Record<string, number>;
        totalModules?: number;
        totalModuleCredits?: number;
        hasModules?: boolean;
        roadmap?: Record<
            number,
            Record<
                number,
                {
                    items: Array<{
                        type: 'unit' | 'module';
                        id: number;
                        code: string;
                        name: string;
                        credits: number;
                        year_level: number;
                        semester_number: number;
                        unit_scope?: string;
                        is_required?: boolean;
                        group_name?: string;
                        grading_type?: string;
                        note?: string;
                        sub_units?: Array<{
                            id: number;
                            code: string;
                            name: string;
                            credits: number;
                            weight?: number;
                            order: number;
                        }>;
                    }>;
                    total_credits: number;
                }
            >
        >;
    };
    meta: {
        lastUpdatedAt: string;
    };
}

const props = defineProps<Props>();

// Computed properties for better data presentation
const yearLevelStats = computed(() => {
    return Object.entries(props.data.byYearLevel).map(([level, count]) => ({
        level: `Year ${level}`,
        count,
        percentage: props.data.totalUnits > 0 ? Math.round((count / props.data.totalUnits) * 100) : 0,
    }));
});

const unitScopeStats = computed(() => {
    const scopeLabels: Record<string, string> = {
        program: 'Program',
        common: 'Common',
        specialization_specific: 'Specialization Specific',
        cross_program: 'Cross Program',
    };

    return Object.entries(props.data.byUnitScope).map(([scope, count]) => ({
        scope: scopeLabels[scope] || scope,
        count,
        percentage: props.data.totalUnits > 0 ? Math.round((count / props.data.totalUnits) * 100) : 0,
    }));
});

const getUnitScopeColor = (scope: string) => {
    switch (scope.toLowerCase().replace(' ', '_')) {
        case 'program':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'common':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'specialization_specific':
            return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'cross_program':
            return 'bg-orange-100 text-orange-800 border-orange-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

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
    <Head :title="`${curriculumVersion.version_code} - Overview`" />

    <CurriculumVersionSummaryLayout :curriculum-version="curriculumVersion">
        <div class="space-y-6">
            <!-- Key Statistics Cards -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Standalone Units</CardTitle>
                        <Book class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ data.totalUnits }}</div>
                        <p class="text-muted-foreground text-xs">{{ data.totalCreditPoints }} credits</p>
                    </CardContent>
                </Card>

                <Card v-if="data.hasModules">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Modules</CardTitle>
                        <Target class="h-4 w-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">{{ data.totalModules }}</div>
                        <p class="text-muted-foreground text-xs">{{ data.totalModuleCredits }} credits</p>
                    </CardContent>
                </Card>

                <Card v-else>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Credits</CardTitle>
                        <Target class="h-4 w-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">{{ data.totalCreditPoints }}</div>
                        <p class="text-muted-foreground text-xs">From all units</p>
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
                        <Info class="h-4 w-4 text-purple-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-purple-600">{{ unitScopeStats.length }}</div>
                        <p class="text-muted-foreground text-xs">Different scopes</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Detailed Breakdowns -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Year Level Distribution -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <GraduationCap class="h-5 w-5" />
                            Year Level Distribution
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="yearLevelStats.length === 0" class="py-8 text-center">
                            <GraduationCap class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-4 text-sm font-medium">No year level data</h3>
                            <p class="text-muted-foreground mt-2 text-sm">Units in this curriculum version don't have year levels assigned.</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div v-for="yearStat in yearLevelStats" :key="yearStat.level" class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <Badge variant="outline" class="text-xs font-medium">
                                        {{ yearStat.level }}
                                    </Badge>
                                    <span class="text-sm text-gray-600">{{ yearStat.count }} units</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-20 overflow-hidden rounded-full bg-gray-200">
                                        <div class="h-full bg-green-500 transition-all duration-300" :style="{ width: `${yearStat.percentage}%` }"></div>
                                    </div>
                                    <span class="w-8 text-right text-xs text-gray-500">{{ yearStat.percentage }}%</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Unit Scope Distribution -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Target class="h-5 w-5" />
                            Unit Scope Distribution
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="unitScopeStats.length === 0" class="py-8 text-center">
                            <Target class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-4 text-sm font-medium">No scope data</h3>
                            <p class="text-muted-foreground mt-2 text-sm">Units in this curriculum version don't have scopes assigned.</p>
                        </div>

                        <div v-else class="space-y-4">
                            <div v-for="scopeStat in unitScopeStats" :key="scopeStat.scope" class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <Badge :class="getUnitScopeColor(scopeStat.scope)" class="border text-xs font-medium">
                                        {{ scopeStat.scope }}
                                    </Badge>
                                    <span class="text-sm text-gray-600">{{ scopeStat.count }} units</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-20 overflow-hidden rounded-full bg-gray-200">
                                        <div class="h-full bg-blue-500 transition-all duration-300" :style="{ width: `${scopeStat.percentage}%` }"></div>
                                    </div>
                                    <span class="w-8 text-right text-xs text-gray-500">{{ scopeStat.percentage }}%</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Program Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Info class="h-5 w-5" />
                        Curriculum Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Program</label>
                                <p class="text-sm text-gray-900">{{ curriculumVersion.program.name }} ({{ curriculumVersion.program.code }})</p>
                            </div>

                            <div v-if="curriculumVersion.specialization">
                                <label class="text-sm font-medium text-gray-700">Specialization</label>
                                <p class="text-sm text-gray-900">{{ curriculumVersion.specialization.name }} ({{ curriculumVersion.specialization.code }})</p>
                            </div>

                            <div v-if="curriculumVersion.effective_from_semester">
                                <label class="text-sm font-medium text-gray-700">Effective From</label>
                                <p class="text-sm text-gray-900">{{ curriculumVersion.effective_from_semester.name }}</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Version Code</label>
                                <p class="text-sm text-gray-900">{{ curriculumVersion.version_code }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">Created</label>
                                <p class="text-sm text-gray-900">{{ formatDate(curriculumVersion.created_at) }}</p>
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700">Last Updated</label>
                                <p class="text-sm text-gray-900">{{ formatDate(meta.lastUpdatedAt) }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="curriculumVersion.notes" class="mt-4 border-t pt-4">
                        <label class="text-sm font-medium text-gray-700">Notes</label>
                        <p class="mt-1 text-sm text-gray-900">{{ curriculumVersion.notes }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Curriculum Roadmap -->
            <Card v-if="data.roadmap && Object.keys(data.roadmap).length > 0">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <GraduationCap class="h-5 w-5" />
                        Curriculum Roadmap
                    </CardTitle>
                    <p class="text-sm text-gray-600">Visual overview of units and modules organized by semester</p>
                </CardHeader>
                <CardContent>
                    <div class="space-y-8">
                        <!-- Loop through years -->
                        <div v-for="(semesters, year) in data.roadmap" :key="year" class="space-y-4">
                            <div class="flex items-center gap-2">
                                <div class="bg-primary rounded-full px-3 py-1 text-sm font-semibold text-white">
                                    {{ year === 0 ? 'Common/Unassigned' : `Year ${year}` }}
                                </div>
                            </div>

                            <!-- Loop through semesters in this year -->
                            <div class="grid gap-4 md:grid-cols-3">
                                <div v-for="(semesterData, semester) in semesters" :key="semester" class="space-y-3">
                                    <!-- Semester header -->
                                    <div class="border-primary flex items-center justify-between border-l-4 bg-gray-50 px-3 py-2">
                                        <div>
                                            <div class="font-semibold">
                                                {{ semester === 0 ? 'Unassigned' : `Semester ${semester}` }}
                                            </div>
                                            <div class="text-xs text-gray-600">{{ semesterData.total_credits }} credits • {{ semesterData.items.length }} items</div>
                                        </div>
                                    </div>

                                    <!-- Items in this semester -->
                                    <div class="space-y-2">
                                        <div
                                            v-for="item in semesterData.items"
                                            :key="`${item.type}-${item.id}`"
                                            class="hover:bg-muted/50 group rounded-lg border p-3 transition-colors"
                                            :class="{
                                                'border-blue-200 bg-blue-50/50': item.type === 'module',
                                                'border-gray-200': item.type === 'unit',
                                            }"
                                        >
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="flex-1 space-y-1">
                                                    <div class="flex items-center gap-2">
                                                        <Package v-if="item.type === 'module'" class="h-3.5 w-3.5 text-blue-600" />
                                                        <Book v-else class="h-3.5 w-3.5 text-gray-600" />
                                                        <span class="text-sm font-medium">{{ item.code }}</span>
                                                    </div>
                                                    <div class="line-clamp-2 text-xs text-gray-700">{{ item.name }}</div>
                                                </div>
                                                <Badge variant="secondary" class="shrink-0 text-xs"> {{ item.credits }} CP </Badge>
                                            </div>

                                            <!-- Additional info -->
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                <Badge v-if="item.type === 'module'" :variant="item.is_required ? 'default' : 'outline'" class="text-xs">
                                                    {{ item.is_required ? 'Required' : 'Elective' }}
                                                </Badge>
                                                <Badge v-if="item.unit_scope" variant="outline" class="text-xs">
                                                    {{ item.unit_scope }}
                                                </Badge>
                                                <Badge v-if="item.group_name" variant="outline" class="text-xs">
                                                    {{ item.group_name }}
                                                </Badge>
                                            </div>

                                            <!-- Sub-units for modules -->
                                            <div v-if="item.type === 'module' && item.sub_units && item.sub_units.length > 0" class="mt-3 space-y-1 border-t pt-2">
                                                <div class="mb-1 text-xs font-medium text-gray-600">Contains {{ item.sub_units.length }} unit(s):</div>
                                                <div class="space-y-1">
                                                    <div v-for="subUnit in item.sub_units" :key="subUnit.id" class="flex items-center justify-between rounded bg-white/80 px-2 py-1 text-xs">
                                                        <div class="flex items-center gap-1.5">
                                                            <Book class="h-3 w-3 text-gray-500" />
                                                            <span class="font-medium text-gray-700">{{ subUnit.code }}</span>
                                                            <span class="text-gray-600">{{ subUnit.name }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-2">
                                                            <span v-if="subUnit.weight" class="text-gray-500">{{ Math.round(subUnit.weight * 100) }}%</span>
                                                            <span class="text-gray-600">{{ subUnit.credits }} CP</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-if="item.note" class="text-muted-foreground mt-2 text-xs italic">
                                                {{ item.note }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </CurriculumVersionSummaryLayout>
</template>
