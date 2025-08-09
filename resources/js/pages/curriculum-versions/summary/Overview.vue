<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import CurriculumVersionSummaryLayout from '@/layouts/CurriculumVersionSummaryLayout.vue';

import { Head } from '@inertiajs/vue3';
import { Book, GraduationCap, Info, Target } from 'lucide-vue-next';
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
                        <CardTitle class="text-sm font-medium">Total Units</CardTitle>
                        <Book class="text-muted-foreground h-4 w-4" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ data.totalUnits }}</div>
                        <p class="text-muted-foreground text-xs">Academic units</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Credit Points</CardTitle>
                        <Target class="h-4 w-4 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">{{ data.totalCreditPoints }}</div>
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
        </div>
    </CurriculumVersionSummaryLayout>
</template>
