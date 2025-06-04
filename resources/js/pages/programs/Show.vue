<template>
    <Head :title="`${program.name} Program`" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex flex-col gap-4 p-4">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <h1 class="text-3xl font-bold tracking-tight">{{ program.name }}</h1>
                    </div>
                    <p class="text-muted-foreground text-lg">Program Details & Structure</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link :href="route('programs.index')">
                        <Button variant="outline">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back to Programs
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Quick Stats Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="space-y-1">
                                <p class="text-muted-foreground text-sm font-medium">Total Specializations</p>
                                <p class="text-2xl font-bold">{{ stats.totalSpecializations }}</p>
                            </div>
                            <Users class="text-muted-foreground ml-auto h-4 w-4" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="space-y-1">
                                <p class="text-muted-foreground text-sm font-medium">Active Specializations</p>
                                <p class="text-2xl font-bold">{{ stats.activeSpecializations }}</p>
                            </div>
                            <CheckCircle class="ml-auto h-4 w-4 text-green-600" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="space-y-1">
                                <p class="text-muted-foreground text-sm font-medium">Curriculum Versions</p>
                                <p class="text-2xl font-bold">{{ stats.totalCurriculumVersions }}</p>
                            </div>
                            <BookOpen class="text-muted-foreground ml-auto h-4 w-4" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="space-y-1">
                                <p class="text-muted-foreground text-sm font-medium">Program Level</p>
                                <p class="text-2xl font-bold">{{ stats.programLevelVersions }}</p>
                            </div>
                            <Settings class="text-muted-foreground ml-auto h-4 w-4" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="p-6">
                        <div class="flex items-center">
                            <div class="space-y-1">
                                <p class="text-muted-foreground text-sm font-medium">Specialization Level</p>
                                <p class="text-2xl font-bold">{{ stats.specializationLevelVersions }}</p>
                            </div>
                            <Target class="text-muted-foreground ml-auto h-4 w-4" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Program Information -->
                <div class="lg:col-span-1">
                    <Card class="h-full">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Info class="h-5 w-5" />
                                Program Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <Label class="text-muted-foreground text-sm font-medium">Program Name</Label>
                                    <p class="text-sm">{{ program.name }}</p>
                                </div>
                                <div>
                                    <Label class="text-muted-foreground text-sm font-medium">Created</Label>
                                    <p class="text-sm">{{ formatDate(program.created_at) }}</p>
                                </div>
                                <div>
                                    <Label class="text-muted-foreground text-sm font-medium">Last Updated</Label>
                                    <p class="text-sm">{{ formatDate(program.updated_at) }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Specializations -->
                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <CardTitle class="flex items-center gap-2">
                                    <Users class="h-5 w-5" />
                                    Specializations ({{ program.specializations.length }})
                                </CardTitle>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="() => router.visit('/specializations/create?program_id=' + program.id)"
                                    v-if="program.specializations.length > 0"
                                >
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add Specialization
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="program.specializations.length === 0" class="py-8 text-center">
                                <Users class="mx-auto h-12 w-12 text-gray-400" />
                                <h3 class="mt-4 text-sm font-medium text-gray-900">No specializations found</h3>
                                <p class="mt-2 text-sm text-gray-500">Get started by creating a new specialization for this program.</p>
                                <div class="mt-6">
                                    <Button @click="() => router.visit('/specializations/create?program_id=' + program.id)">
                                        <Plus class="mr-2 h-4 w-4" />
                                        Add Specialization
                                    </Button>
                                </div>
                            </div>

                            <div v-else class="space-y-4">
                                <div
                                    v-for="specialization in program.specializations"
                                    :key="specialization.id"
                                    class="hover:bg-accent/50 flex items-center justify-between rounded-lg border p-4 transition-colors"
                                >
                                    <div class="flex-1 space-y-1">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-medium">{{ specialization.name }}</h4>
                                            <Badge
                                                v-if="specialization.is_active"
                                                variant="secondary"
                                                class="border-green-200 bg-green-100 text-xs text-green-700"
                                            >
                                                Active
                                            </Badge>
                                            <Badge v-else variant="outline" class="text-xs"> Inactive </Badge>
                                        </div>
                                        <p v-if="specialization.code" class="text-muted-foreground text-sm">Code: {{ specialization.code }}</p>
                                        <p v-if="specialization.description" class="text-muted-foreground text-sm">
                                            {{ specialization.description }}
                                        </p>
                                        <p class="text-muted-foreground text-xs">
                                            {{ specialization.curriculum_versions_count }} curriculum version{{
                                                specialization.curriculum_versions_count !== 1 ? 's' : ''
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button variant="ghost" size="sm" @click="() => router.visit('/specializations/' + specialization.id)">
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="() => router.visit('/specializations/' + specialization.id + '/edit')"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Curriculum Versions -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <BookOpen class="h-5 w-5" />
                            Curriculum Versions ({{ program.curriculum_versions.length }})
                        </CardTitle>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="() => router.visit('/curriculum-versions/create?program_id=' + program.id)"
                            v-if="program.curriculum_versions.length > 0"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            Add Curriculum Version
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="program.curriculum_versions.length === 0" class="py-8 text-center">
                        <BookOpen class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-4 text-sm font-medium text-gray-900">No curriculum versions found</h3>
                        <p class="mt-2 text-sm text-gray-500">
                            Create curriculum versions to define the structure and requirements for this program.
                        </p>
                        <div class="mt-6">
                            <Button @click="() => router.visit('/curriculum-versions/create?program_id=' + program.id)">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Curriculum Version
                            </Button>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <!-- Program Level Versions -->
                        <div v-if="programLevelVersions.length > 0">
                            <h4 class="text-muted-foreground mb-3 flex items-center gap-2 text-sm font-medium">
                                <Settings class="h-4 w-4" />
                                Program Level Curricula
                            </h4>
                            <div class="space-y-3">
                                <div
                                    v-for="version in programLevelVersions"
                                    :key="version.id"
                                    class="hover:bg-accent/50 rounded-lg border p-4 transition-colors"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <h5 class="font-medium">{{ version.version_code || 'Unnamed Version' }}</h5>
                                                <Badge variant="outline" class="text-xs"> Program Level </Badge>
                                            </div>
                                            <div class="text-muted-foreground flex items-center gap-4 text-sm">
                                                <span v-if="version.effective_from_semester">
                                                    Effective from: {{ version.effective_from_semester.name }}
                                                </span>
                                                <span>{{ version.curriculum_units_count }} units</span>
                                                <span>{{ formatDate(version.created_at) }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Button variant="ghost" size="sm" @click="() => router.visit('/curriculum-versions/' + version.id)">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="() => router.visit('/curriculum-versions/' + version.id + '/edit')"
                                            >
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Specialization Level Versions -->
                        <div v-if="specializationLevelVersions.length > 0">
                            <h4
                                class="text-muted-foreground mb-3 flex items-center gap-2 text-sm font-medium"
                                :class="{ 'mt-6': programLevelVersions.length > 0 }"
                            >
                                <Target class="h-4 w-4" />
                                Specialization Level Curricula
                            </h4>
                            <div class="space-y-3">
                                <div
                                    v-for="version in specializationLevelVersions"
                                    :key="version.id"
                                    class="hover:bg-accent/50 rounded-lg border p-4 transition-colors"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <h5 class="font-medium">{{ version.version_code || 'Unnamed Version' }}</h5>
                                                <Badge variant="secondary" class="text-xs">
                                                    {{ version.specialization?.name || 'Specialization Level' }}
                                                </Badge>
                                            </div>
                                            <div class="text-muted-foreground flex items-center gap-4 text-sm">
                                                <span v-if="version.effective_from_semester">
                                                    Effective from: {{ version.effective_from_semester.name }}
                                                </span>
                                                <span>{{ version.curriculum_units_count }} units</span>
                                                <span>{{ formatDate(version.created_at) }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Button variant="ghost" size="sm" @click="() => router.visit('/curriculum-versions/' + version.id)">
                                                <Eye class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="() => router.visit('/curriculum-versions/' + version.id + '/edit')"
                                            >
                                                <Edit class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, CheckCircle, Edit, Eye, Info, Plus, Settings, Target, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface Specialization {
    id: number;
    name: string;
    code?: string;
    description?: string;
    is_active: boolean;
    duration_years: number;
    curriculum_versions_count: number;
}

interface Semester {
    id: number;
    name: string;
    code: string;
}

interface CurriculumVersion {
    id: number;
    version_code?: string;
    scope: 'program' | 'specialization';
    curriculum_units_count: number;
    created_at: string;
    specialization?: Specialization;
    effective_from_semester?: Semester;
}

interface Program {
    id: number;
    name: string;
    created_at: string;
    updated_at: string;
    specializations: Specialization[];
    curriculum_versions: CurriculumVersion[];
}

interface Stats {
    totalSpecializations: number;
    activeSpecializations: number;
    totalCurriculumVersions: number;
    programLevelVersions: number;
    specializationLevelVersions: number;
}

const props = defineProps<{
    program: Program;
    stats: Stats;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Programs',
        href: '/programs',
    },
    {
        title: props.program.name,
        href: `/programs/${props.program.id}`,
    },
];

// Computed properties for filtered curriculum versions
const programLevelVersions = computed(() => props.program.curriculum_versions.filter((version) => version.scope === 'program'));

const specializationLevelVersions = computed(() => props.program.curriculum_versions.filter((version) => version.scope === 'specialization'));

// Helper functions
const formatDegreeLevel = (level: string): string => {
    const levels = {
        bachelor: "Bachelor's Degree",
        master: "Master's Degree",
        phd: 'Ph.D.',
    };
    return levels[level as keyof typeof levels] || level;
};

const degreeLevelVariant = (level: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    const variants = {
        bachelor: 'default' as const,
        master: 'secondary' as const,
        phd: 'outline' as const,
    };
    return variants[level as keyof typeof variants] || 'default';
};

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>
