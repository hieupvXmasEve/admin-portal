<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { router } from '@inertiajs/vue3';
import { ArrowDown, ArrowRight, Book, BookOpen, Calendar, ChevronDown, ChevronRight, GraduationCap, Network, Target, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface HierarchyData {
    programs: Array<{
        id: number;
        name: string;
        degree_level: string;
        specializations: Array<{
            id: number;
            name: string;
            code: string;
            curriculum_versions: Array<{
                id: number;
                name: string;
                version: string;
                is_active: boolean;
                units_count: number;
                total_credits: number;
            }>;
        }>;
    }>;
    standalone_curricula: Array<{
        id: number;
        name: string;
        version: string;
        is_active: boolean;
        units_count: number;
        total_credits: number;
    }>;
    semester_info: Array<{
        id: number;
        name: string;
        number: number;
        units_count: number;
        is_active: boolean;
    }>;
}

const props = defineProps<{
    hierarchy: HierarchyData;
    showDetails?: boolean;
}>();

const expandedPrograms = ref<Set<number>>(new Set());
const expandedSpecializations = ref<Set<number>>(new Set());
const showStandaloneCurricula = ref(false);
const showSemesterInfo = ref(false);

const toggleProgram = (programId: number) => {
    if (expandedPrograms.value.has(programId)) {
        expandedPrograms.value.delete(programId);
    } else {
        expandedPrograms.value.add(programId);
    }
};

const toggleSpecialization = (specializationId: number) => {
    if (expandedSpecializations.value.has(specializationId)) {
        expandedSpecializations.value.delete(specializationId);
    } else {
        expandedSpecializations.value.add(specializationId);
    }
};

const getDegreeColor = (degreeLevel: string) => {
    switch (degreeLevel.toLowerCase()) {
        case 'bachelor':
            return 'bg-blue-100 text-blue-800';
        case 'master':
            return 'bg-green-100 text-green-800';
        case 'doctoral':
            return 'bg-purple-100 text-purple-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const totalPrograms = computed(() => props.hierarchy.programs.length);
const totalSpecializations = computed(() => props.hierarchy.programs.reduce((sum, program) => sum + program.specializations.length, 0));
const totalCurriculumVersions = computed(() => {
    let count = props.hierarchy.standalone_curricula.length;
    props.hierarchy.programs.forEach((program) => {
        program.specializations.forEach((spec) => {
            count += spec.curriculum_versions.length;
        });
    });
    return count;
});

const navigateToEntity = (type: string, id: number) => {
    const routes = {
        program: `/programs/${id}`,
        specialization: `/specializations/${id}`,
        curriculum_version: `/curriculum-versions/${id}`,
        semester: `/semesters/${id}`,
    };
    router.visit(routes[type as keyof typeof routes]);
};
</script>

<template>
    <Card class="w-full">
        <CardHeader>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Network class="h-5 w-5 text-blue-600" />
                    <CardTitle>Educational System Hierarchy</CardTitle>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <Badge variant="outline">{{ totalPrograms }} Programs</Badge>
                    <Badge variant="outline">{{ totalSpecializations }} Specializations</Badge>
                    <Badge variant="outline">{{ totalCurriculumVersions }} Curricula</Badge>
                </div>
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <!-- Programs and their hierarchy -->
            <div class="space-y-3">
                <div v-for="program in hierarchy.programs" :key="program.id" class="rounded-lg border p-3">
                    <!-- Program Level -->
                    <div class="flex cursor-pointer items-center justify-between rounded p-2 hover:bg-gray-50" @click="toggleProgram(program.id)">
                        <div class="flex items-center gap-3">
                            <component :is="expandedPrograms.has(program.id) ? ChevronDown : ChevronRight" class="h-4 w-4 text-gray-400" />
                            <GraduationCap class="h-5 w-5 text-blue-600" />
                            <div>
                                <div class="font-medium">{{ program.name }}</div>
                                <Badge :class="getDegreeColor(program.degree_level)" class="mt-1 text-xs">
                                    {{ program.degree_level }}
                                </Badge>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge variant="secondary" class="text-xs"> {{ program.specializations.length }} specializations </Badge>
                            <Button variant="ghost" size="sm" @click.stop="navigateToEntity('program', program.id)"> View </Button>
                        </div>
                    </div>

                    <!-- Specializations (when program is expanded) -->
                    <div v-if="expandedPrograms.has(program.id)" class="mt-3 ml-6 space-y-2">
                        <div v-if="program.specializations.length === 0" class="pl-4 text-sm text-gray-500 italic">No specializations defined</div>

                        <div v-for="specialization in program.specializations" :key="specialization.id" class="border-l-2 border-gray-200 pl-4">
                            <!-- Specialization Level -->
                            <div
                                class="flex cursor-pointer items-center justify-between rounded p-2 hover:bg-gray-50"
                                @click="toggleSpecialization(specialization.id)"
                            >
                                <div class="flex items-center gap-3">
                                    <component
                                        :is="expandedSpecializations.has(specialization.id) ? ChevronDown : ChevronRight"
                                        class="h-3 w-3 text-gray-400"
                                    />
                                    <Target class="h-4 w-4 text-green-600" />
                                    <div>
                                        <div class="text-sm font-medium">{{ specialization.name }}</div>
                                        <code class="rounded bg-gray-100 px-1 text-xs">{{ specialization.code }}</code>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline" class="text-xs"> {{ specialization.curriculum_versions.length }} versions </Badge>
                                    <Button variant="ghost" size="sm" @click.stop="navigateToEntity('specialization', specialization.id)">
                                        View
                                    </Button>
                                </div>
                            </div>

                            <!-- Curriculum Versions (when specialization is expanded) -->
                            <div v-if="expandedSpecializations.has(specialization.id)" class="mt-2 ml-6 space-y-1">
                                <div v-if="specialization.curriculum_versions.length === 0" class="pl-4 text-sm text-gray-500 italic">
                                    No curriculum versions
                                </div>

                                <div
                                    v-for="curriculum in specialization.curriculum_versions"
                                    :key="curriculum.id"
                                    class="flex items-center justify-between rounded bg-gray-50 p-2 hover:bg-gray-100"
                                >
                                    <div class="flex items-center gap-3">
                                        <BookOpen class="h-4 w-4 text-purple-600" />
                                        <div>
                                            <div class="text-sm font-medium">{{ curriculum.name }}</div>
                                            <div class="flex items-center gap-2 text-xs text-gray-600">
                                                <span>Version {{ curriculum.version }}</span>
                                                <Badge :variant="curriculum.is_active ? 'default' : 'secondary'" class="text-xs">
                                                    {{ curriculum.is_active ? 'Active' : 'Inactive' }}
                                                </Badge>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-right text-xs text-gray-600">
                                            <div>{{ curriculum.units_count }} units</div>
                                            <div>{{ curriculum.total_credits }} credits</div>
                                        </div>
                                        <Button variant="ghost" size="sm" @click="navigateToEntity('curriculum_version', curriculum.id)">
                                            View
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Standalone Curriculum Versions -->
            <div v-if="hierarchy.standalone_curricula.length > 0">
                <Separator />
                <div class="cursor-pointer rounded p-2 hover:bg-gray-50" @click="showStandaloneCurricula = !showStandaloneCurricula">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <component :is="showStandaloneCurricula ? ChevronDown : ChevronRight" class="h-4 w-4 text-gray-400" />
                            <BookOpen class="h-5 w-5 text-purple-600" />
                            <div class="font-medium">Standalone Curriculum Versions</div>
                        </div>
                        <Badge variant="outline">{{ hierarchy.standalone_curricula.length }} versions</Badge>
                    </div>
                </div>

                <div v-if="showStandaloneCurricula" class="mt-3 ml-6 space-y-1">
                    <div
                        v-for="curriculum in hierarchy.standalone_curricula"
                        :key="curriculum.id"
                        class="flex items-center justify-between rounded bg-gray-50 p-2 hover:bg-gray-100"
                    >
                        <div class="flex items-center gap-3">
                            <BookOpen class="h-4 w-4 text-purple-600" />
                            <div>
                                <div class="text-sm font-medium">{{ curriculum.name }}</div>
                                <div class="flex items-center gap-2 text-xs text-gray-600">
                                    <span>Version {{ curriculum.version }}</span>
                                    <Badge :variant="curriculum.is_active ? 'default' : 'secondary'" class="text-xs">
                                        {{ curriculum.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="text-right text-xs text-gray-600">
                                <div>{{ curriculum.units_count }} units</div>
                                <div>{{ curriculum.total_credits }} credits</div>
                            </div>
                            <Button variant="ghost" size="sm" @click="navigateToEntity('curriculum_version', curriculum.id)"> View </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Semester Information -->
            <div v-if="hierarchy.semester_info.length > 0">
                <Separator />
                <div class="cursor-pointer rounded p-2 hover:bg-gray-50" @click="showSemesterInfo = !showSemesterInfo">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <component :is="showSemesterInfo ? ChevronDown : ChevronRight" class="h-4 w-4 text-gray-400" />
                            <Calendar class="h-5 w-5 text-red-600" />
                            <div class="font-medium">Academic Semesters</div>
                        </div>
                        <Badge variant="outline">{{ hierarchy.semester_info.length }} semesters</Badge>
                    </div>
                </div>

                <div v-if="showSemesterInfo" class="mt-3 ml-6 space-y-1">
                    <div
                        v-for="semester in hierarchy.semester_info"
                        :key="semester.id"
                        class="flex items-center justify-between rounded bg-gray-50 p-2 hover:bg-gray-100"
                    >
                        <div class="flex items-center gap-3">
                            <Calendar class="h-4 w-4 text-red-600" />
                            <div>
                                <div class="text-sm font-medium">{{ semester.name }}</div>
                                <div class="flex items-center gap-2 text-xs text-gray-600">
                                    <span>Semester {{ semester.number }}</span>
                                    <Badge :variant="semester.is_active ? 'default' : 'secondary'" class="text-xs">
                                        {{ semester.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="text-xs text-gray-600">{{ semester.units_count }} units assigned</div>
                            <Button variant="ghost" size="sm" @click="navigateToEntity('semester', semester.id)"> View </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Relationship Flow Diagram -->
            <Separator />
            <div class="rounded-lg bg-blue-50 p-4">
                <h4 class="mb-3 flex items-center gap-2 font-medium">
                    <Network class="h-4 w-4" />
                    Data Flow & Relationships
                </h4>
                <div class="flex items-center justify-center space-x-4 text-sm">
                    <div class="flex items-center gap-2">
                        <GraduationCap class="h-4 w-4 text-blue-600" />
                        <span>Programs</span>
                    </div>
                    <ArrowRight class="h-4 w-4 text-gray-400" />
                    <div class="flex items-center gap-2">
                        <Target class="h-4 w-4 text-green-600" />
                        <span>Specializations</span>
                    </div>
                    <ArrowRight class="h-4 w-4 text-gray-400" />
                    <div class="flex items-center gap-2">
                        <BookOpen class="h-4 w-4 text-purple-600" />
                        <span>Curriculum Versions</span>
                    </div>
                    <ArrowRight class="h-4 w-4 text-gray-400" />
                    <div class="flex items-center gap-2">
                        <Users class="h-4 w-4 text-indigo-600" />
                        <span>Curriculum Units</span>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-center">
                    <ArrowDown class="h-4 w-4 text-gray-400" />
                </div>
                <div class="flex items-center justify-center">
                    <div class="flex items-center gap-2 text-sm">
                        <Book class="h-4 w-4 text-orange-600" />
                        <span>Academic Units</span>
                        <span class="text-gray-400">+</span>
                        <Calendar class="h-4 w-4 text-red-600" />
                        <span>Semesters</span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
