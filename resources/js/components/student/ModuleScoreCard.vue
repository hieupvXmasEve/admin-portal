<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { ModuleScore } from '@/types/models';
import { Book, ChevronDown, ChevronUp, Info, Package } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    module: ModuleScore;
}

const props = defineProps<Props>();
const isExpanded = ref(false);

const getGradeColor = (grade: number | null): string => {
    if (grade === null) return 'text-gray-400';
    if (grade >= 4.5) return 'text-green-600';
    if (grade >= 3.5) return 'text-blue-600';
    if (grade >= 2.5) return 'text-yellow-600';
    if (grade >= 1.0) return 'text-orange-600';
    return 'text-red-600';
};

const getStatusVariant = (status: string) => {
    const variants: Record<string, any> = {
        passed: 'default',
        failed: 'destructive',
        in_progress: 'secondary',
        not_started: 'outline',
    };
    return variants[status] || 'outline';
};

const formatGrade = (grade: number | null): string => {
    if (grade === null) return 'N/A';
    return grade.toFixed(2);
};

const formatStatus = (status: string): string => {
    return status.replace(/_/g, ' ').toUpperCase();
};
</script>

<template>
    <Card class="border-blue-200">
        <Collapsible v-model:open="isExpanded">
            <!-- Module Header -->
            <CardHeader>
                <div class="flex items-start justify-between gap-4">
                    <div class="flex flex-1 items-start gap-3">
                        <Package class="mt-1 h-5 w-5 shrink-0 text-blue-600" />
                        <div class="flex-1 space-y-2">
                            <div>
                                <div class="text-lg font-semibold">{{ module.module_code }}</div>
                                <div class="text-sm text-gray-700">{{ module.module_name }}</div>
                                <div class="mt-1 text-xs text-gray-600">
                                    Year {{ module.year_level }} • Semester {{ module.semester_number }}
                                    <span v-if="module.group_name"> • {{ module.group_name }}</span>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs text-gray-600">
                                    <span>Progress: {{ module.completion.completed }}/{{ module.completion.total }} units</span>
                                    <span>{{ module.completion.percentage }}%</span>
                                </div>
                                <Progress :model-value="module.completion.percentage" class="h-2" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <!-- Module Grade -->
                        <div class="text-right">
                            <div class="text-3xl font-bold" :class="getGradeColor(module.module_grade)">
                                {{ formatGrade(module.module_grade) }}
                            </div>
                            <div class="text-xs text-gray-600">Module Average</div>
                        </div>

                        <!-- Status & Credits -->
                        <div class="flex flex-col gap-2">
                            <Badge :variant="getStatusVariant(module.status)">
                                {{ formatStatus(module.status) }}
                            </Badge>
                            <Badge :variant="module.is_required ? 'default' : 'outline'" class="text-xs">
                                {{ module.is_required ? 'Required' : 'Elective' }}
                            </Badge>
                            <Badge variant="secondary" class="text-xs">
                                {{ module.total_credits }} CP
                            </Badge>
                        </div>

                        <!-- Expand Button -->
                        <CollapsibleTrigger as-child>
                            <Button variant="ghost" size="sm">
                                <ChevronDown v-if="!isExpanded" class="h-4 w-4" />
                                <ChevronUp v-else class="h-4 w-4" />
                            </Button>
                        </CollapsibleTrigger>
                    </div>
                </div>
            </CardHeader>

            <!-- Sub-units Table (Expandable) -->
            <CollapsibleContent>
                <CardContent class="space-y-4">
                    <!-- Grading Info Box -->
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                        <div class="flex items-start gap-2">
                            <Info class="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                            <div class="text-sm text-blue-900">
                                <strong>Grading Calculation:</strong>
                                Module grade calculated from
                                <strong>{{ module.grading_info.graded_units_count }}</strong>
                                graded unit(s)
                                <span v-if="module.grading_info.uses_weights">(weighted average)</span>
                                <span v-else>(simple average)</span>.
                                <span v-if="module.grading_info.passfail_units_count > 0">
                                    <strong>{{ module.grading_info.passfail_units_count }}</strong>
                                    pass/fail unit(s) excluded from average but affect pass/fail status.
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Sub-units Table -->
                    <div class="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-[35%]">Unit</TableHead>
                                    <TableHead class="w-[15%]">Type</TableHead>
                                    <TableHead class="w-[15%]">Grade</TableHead>
                                    <TableHead class="w-[15%]">Status</TableHead>
                                    <TableHead class="w-[10%]">Credits</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="subUnit in module.sub_units"
                                    :key="subUnit.id"
                                    :class="{
                                        'bg-gray-50': subUnit.grading_type === 'pass_fail',
                                    }"
                                >
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <Book class="h-4 w-4 text-gray-600" />
                                            <div>
                                                <div class="font-medium">{{ subUnit.code }}</div>
                                                <div class="text-xs text-gray-600">{{ subUnit.name }}</div>
                                            </div>
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <div class="space-y-1">
                                            <Badge :variant="subUnit.grading_type === 'grade' ? 'default' : 'outline'" class="text-xs">
                                                {{ subUnit.grading_type === 'grade' ? 'Graded' : 'Pass/Fail' }}
                                            </Badge>
                                            <div v-if="subUnit.weight" class="text-xs text-gray-600">
                                                Weight: {{ Math.round(subUnit.weight * 100) }}%
                                            </div>
                                        </div>
                                    </TableCell>

                                    <TableCell>
                                        <div v-if="subUnit.final_grade !== null">
                                            <div class="font-semibold" :class="getGradeColor(subUnit.final_grade)">
                                                {{ subUnit.final_grade.toFixed(1) }}
                                            </div>
                                            <div v-if="subUnit.letter_grade" class="text-xs text-gray-600">{{ subUnit.letter_grade }}</div>
                                        </div>
                                        <span v-else class="text-gray-400">N/A</span>
                                    </TableCell>

                                    <TableCell>
                                        <Badge :variant="subUnit.is_passed ? 'default' : subUnit.status === 'not_enrolled' ? 'outline' : 'secondary'" class="text-xs">
                                            {{ subUnit.status }}
                                        </Badge>
                                    </TableCell>

                                    <TableCell>
                                        <span class="text-sm">{{ subUnit.credits }} CP</span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </CollapsibleContent>
        </Collapsible>
    </Card>
</template>
