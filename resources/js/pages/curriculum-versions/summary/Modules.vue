<script setup lang="ts">
import ManageCurriculumModulesDialog from '@/components/curriculum/ManageCurriculumModulesDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/composables';
import CurriculumVersionSummaryLayout from '@/layouts/CurriculumVersionSummaryLayout.vue';
import type { CurriculumVersion, Module } from '@/types/models';
import { Head, Link } from '@inertiajs/vue3';
import { Info, Plus } from 'lucide-vue-next';
import { ref } from 'vue';

interface CurriculumModule {
    id: number;
    curriculum_version_id: number;
    module_id: number;
    year_level: number | null;
    semester_number: number | null;
    is_required: boolean;
    group_name: string | null;
    order: number;
    note: string | null;
    module: Module;
}

interface CurriculumVersionWithModules extends CurriculumVersion {
    curriculum_modules: CurriculumModule[];
    has_standalone_units?: boolean;
}

interface Props {
    curriculumVersion: CurriculumVersionWithModules;
    availableModules: Module[];
    data?: {
        standaloneUnitsCount?: number;
    };
}

const props = defineProps<Props>();
const permission = usePermissions();
const showManageModulesDialog = ref(false);

// Type assertion helper for layout
const layoutProps = props.curriculumVersion as any;
</script>

<template>
    <Head :title="`Modules - ${curriculumVersion.program?.name || 'Curriculum'}`" />

    <CurriculumVersionSummaryLayout :curriculum-version="layoutProps">
        <div class="space-y-6">
            <!-- Standalone Units Info Banner -->
            <Card v-if="curriculumVersion.has_standalone_units && data?.standaloneUnitsCount" class="border-green-200 bg-green-50">
                <CardContent class="py-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Info class="h-4 w-4 text-green-600" />
                            <span class="text-sm">
                                This curriculum also has <strong>{{ data.standaloneUnitsCount }} standalone unit(s)</strong> (e.g., English, PE, Ethics) outside of modules.
                            </span>
                        </div>
                        <Link :href="route('curriculum_versions.summary.units', { curriculum_version: curriculumVersion.id })">
                            <Button variant="outline" size="sm">View Standalone Units</Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Module Structure</CardTitle>
                            <CardDescription> Manage modules for this curriculum version (modular system for Finland campus) </CardDescription>
                        </div>
                        <Button size="sm" @click="showManageModulesDialog = true" v-if="permission.can('edit_curriculum_version')">
                            <Plus class="mr-2 h-4 w-4" />
                            Manage Modules
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Empty State -->
                    <div v-if="!curriculumVersion.curriculum_modules || curriculumVersion.curriculum_modules.length === 0" class="py-12 text-center">
                        <div class="text-muted-foreground mb-4">
                            <svg class="text-muted-foreground/50 mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="mb-2 text-lg font-medium">No modules assigned yet</h3>
                        <p class="text-muted-foreground mb-6 text-sm">Add modules to organize units into a modular curriculum structure</p>
                        <Button variant="outline" @click="showManageModulesDialog = true" v-if="permission.can('edit_curriculum_version')">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Modules
                        </Button>
                    </div>

                    <!-- Modules List -->
                    <div v-else class="space-y-4">
                        <div v-for="cm in curriculumVersion.curriculum_modules" :key="cm.id" class="hover:bg-muted/50 rounded-lg border p-4 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-1">
                                            <h4 class="text-lg font-semibold">{{ cm.module.code }}</h4>
                                            <p class="mt-1 text-base">{{ cm.module.name }}</p>

                                            <div class="text-muted-foreground mt-2 flex items-center gap-3 text-sm">
                                                <span v-if="cm.year_level" class="flex items-center gap-1">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    Year {{ cm.year_level }}
                                                </span>
                                                <span v-if="cm.semester_number" class="flex items-center gap-1">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    Semester {{ cm.semester_number }}
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                                                        />
                                                    </svg>
                                                    {{ cm.module.total_credits }} credits
                                                </span>
                                            </div>

                                            <div class="mt-3 flex items-center gap-2">
                                                <Badge :variant="cm.is_required ? 'default' : 'secondary'">
                                                    {{ cm.is_required ? 'Required' : 'Elective' }}
                                                </Badge>
                                                <Badge v-if="cm.group_name" variant="outline">
                                                    {{ cm.group_name }}
                                                </Badge>
                                                <Badge :variant="cm.module.grading_type === 'grade' ? 'default' : 'secondary'">
                                                    {{ cm.module.grading_type === 'grade' ? 'Graded' : 'Pass/Fail' }}
                                                </Badge>
                                            </div>

                                            <p v-if="cm.note" class="text-muted-foreground mt-2 text-sm italic">Note: {{ cm.note }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Stats -->
                        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm font-medium">Total Modules</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="text-2xl font-bold">{{ curriculumVersion.curriculum_modules.length }}</div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm font-medium">Total Credits</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="text-2xl font-bold">
                                        {{ curriculumVersion.curriculum_modules.reduce((sum, cm) => sum + Number(cm.module.total_credits), 0) }}
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm font-medium">Required Modules</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="text-2xl font-bold">
                                        {{ curriculumVersion.curriculum_modules.filter((cm) => cm.is_required).length }}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Manage Modules Dialog -->
            <ManageCurriculumModulesDialog
                v-model:open="showManageModulesDialog"
                :curriculum-version-id="curriculumVersion.id"
                :selected-modules="curriculumVersion.curriculum_modules || []"
                :available-modules="availableModules"
                @close="showManageModulesDialog = false"
            />
        </div>
    </CurriculumVersionSummaryLayout>
</template>
