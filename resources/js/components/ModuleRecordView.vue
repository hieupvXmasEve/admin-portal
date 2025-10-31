<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { ref } from 'vue';

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
}

interface SubUnitProgress {
    unit: Unit;
    grading_type: 'grade' | 'pass_fail';
    grade: number | null;
    letter_grade: string | null;
    status: string;
    academic_record_id: number | null;
}

interface Module {
    id: number;
    code: string;
    name: string;
    description: string | null;
    total_credits: number;
    grading_type: 'grade' | 'pass_fail';
}

interface ModuleProgress {
    module: Module;
    status: 'not_started' | 'in_progress' | 'passed' | 'failed';
    grade: number | null;
    completion: string;
    completed_count: number;
    total_count: number;
    sub_units: SubUnitProgress[];
}

interface Props {
    modules: ModuleProgress[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
});

const expandedModules = ref<Set<number>>(new Set());

const toggleModule = (moduleId: number) => {
    if (expandedModules.value.has(moduleId)) {
        expandedModules.value.delete(moduleId);
    } else {
        expandedModules.value.add(moduleId);
    }
};

const getStatusBadge = (status: string) => {
    const variants: Record<string, { label: string; variant: any }> = {
        passed: { label: 'Passed', variant: 'default' },
        failed: { label: 'Failed', variant: 'destructive' },
        in_progress: { label: 'In Progress', variant: 'secondary' },
        not_started: { label: 'Not Started', variant: 'outline' },
        completed: { label: 'Completed', variant: 'default' },
        not_enrolled: { label: 'Not Enrolled', variant: 'outline' },
    };

    return variants[status] || { label: status, variant: 'outline' };
};

const formatGrade = (grade: number | null, gradingType: string): string => {
    if (grade === null) return 'N/A';
    if (gradingType === 'pass_fail') return grade >= 1 ? 'Pass' : 'Fail';
    return grade.toFixed(2);
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Module Progress</CardTitle>
            <CardDescription>Your academic progress organized by modules</CardDescription>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="flex items-center justify-center py-8">
                <div class="text-sm text-muted-foreground">Loading...</div>
            </div>

            <div v-else-if="modules.length === 0" class="text-center py-8">
                <p class="text-sm text-muted-foreground">No module progress found.</p>
            </div>

            <div v-else class="space-y-4">
                <div v-for="progress in modules" :key="progress.module.id" class="border rounded-lg">
                    <!-- Module Header -->
                    <div
                        class="flex items-center justify-between p-4 cursor-pointer hover:bg-muted/50 transition-colors"
                        @click="toggleModule(progress.module.id)"
                    >
                        <div class="flex items-center gap-3 flex-1">
                            <component
                                :is="expandedModules.has(progress.module.id) ? ChevronDown : ChevronRight"
                                class="h-5 w-5 text-muted-foreground"
                            />

                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold">{{ progress.module.code }}</h3>
                                    <span class="text-sm text-muted-foreground">{{ progress.module.name }}</span>
                                </div>
                                <p v-if="progress.module.description" class="text-sm text-muted-foreground mt-1">
                                    {{ progress.module.description }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <div class="text-sm font-medium">
                                    {{ formatGrade(progress.grade, progress.module.grading_type) }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ progress.module.total_credits }} credits
                                </div>
                            </div>

                            <div class="text-right">
                                <Badge :variant="getStatusBadge(progress.status).variant">
                                    {{ getStatusBadge(progress.status).label }}
                                </Badge>
                                <div class="text-xs text-muted-foreground mt-1">
                                    {{ progress.completion }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-units Table (Expandable) -->
                    <div v-if="expandedModules.has(progress.module.id)" class="border-t">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Unit Code</TableHead>
                                    <TableHead>Unit Name</TableHead>
                                    <TableHead>Credits</TableHead>
                                    <TableHead>Grading Type</TableHead>
                                    <TableHead>Grade</TableHead>
                                    <TableHead>Letter Grade</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="subUnit in progress.sub_units" :key="subUnit.unit.id">
                                    <TableCell class="font-medium">{{ subUnit.unit.code }}</TableCell>
                                    <TableCell>{{ subUnit.unit.name }}</TableCell>
                                    <TableCell>{{ subUnit.unit.credit_points }}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">
                                            {{ subUnit.grading_type === 'grade' ? 'Graded' : 'Pass/Fail' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        {{ formatGrade(subUnit.grade, subUnit.grading_type) }}
                                    </TableCell>
                                    <TableCell>
                                        {{ subUnit.letter_grade || 'N/A' }}
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="getStatusBadge(subUnit.status).variant" size="sm">
                                            {{ getStatusBadge(subUnit.status).label }}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
