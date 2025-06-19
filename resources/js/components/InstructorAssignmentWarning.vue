<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertTriangle } from 'lucide-vue-next';
import { onMounted, ref, watch } from 'vue';

interface Props {
    semesterId?: string;
}

interface InstructorAssignmentData {
    semester_id: string;
    semester_name: string;
    classes_started: boolean;
    offerings_without_instructors: number;
    unassigned_offerings: Array<{
        id: number;
        course_code: string;
        course_title: string;
        section_code: string;
        current_enrollment: number;
        max_capacity: number;
    }>;
    is_ready_for_classes: boolean;
    warning_message?: string;
}

const props = defineProps<Props>();

const assignmentData = ref<InstructorAssignmentData | null>(null);
const isLoading = ref(false);
const showDetails = ref(false);

const loadInstructorAssignments = async () => {
    if (!props.semesterId || props.semesterId === 'all') {
        assignmentData.value = null;
        return;
    }

    isLoading.value = true;
    try {
        const response = await fetch(`/api/course-offerings/check-instructor-assignments?semester_id=${props.semesterId}`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const result = await response.json();
        if (result.success) {
            assignmentData.value = result.data;
        }
    } catch (error) {
        console.error('Failed to load instructor assignments:', error);
    } finally {
        isLoading.value = false;
    }
};

// Watch for semester changes
watch(() => props.semesterId, loadInstructorAssignments, { immediate: true });

onMounted(() => {
    loadInstructorAssignments();
});

const getCardBorderClass = () => {
    if (!assignmentData.value) return '';

    if (assignmentData.value.classes_started && assignmentData.value.offerings_without_instructors > 0) {
        return 'border-destructive bg-destructive/5';
    }

    if (assignmentData.value.offerings_without_instructors > 0) {
        return 'border-orange-500 bg-orange-50';
    }

    return '';
};

const getBadgeVariant = () => {
    if (!assignmentData.value) return 'secondary';

    if (assignmentData.value.classes_started && assignmentData.value.offerings_without_instructors > 0) {
        return 'destructive';
    }

    if (assignmentData.value.offerings_without_instructors > 0) {
        return 'secondary';
    }

    return 'secondary';
};
</script>

<template>
    <div v-if="assignmentData && assignmentData.offerings_without_instructors > 0" class="mb-4">
        <Card :class="getCardBorderClass()">
            <CardHeader class="pb-3">
                <div class="flex items-center gap-2">
                    <AlertTriangle class="h-5 w-5 text-orange-600" />
                    <CardTitle class="text-lg">
                        {{ assignmentData.classes_started ? 'Urgent: Missing Instructors' : 'Instructor Assignment Required' }}
                    </CardTitle>
                    <Badge :variant="getBadgeVariant()"> {{ assignmentData.offerings_without_instructors }} unassigned </Badge>
                </div>
                <CardDescription>
                    {{ assignmentData.offerings_without_instructors }} course offering(s) in <strong>{{ assignmentData.semester_name }}</strong> do
                    not have assigned instructors.

                    <span v-if="assignmentData.warning_message" class="text-destructive mt-1 block font-medium">
                        {{ assignmentData.warning_message }}
                    </span>
                </CardDescription>
            </CardHeader>
            <CardContent class="pt-0">
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="showDetails = !showDetails"> {{ showDetails ? 'Hide' : 'Show' }} Details </Button>
                </div>

                <div v-if="showDetails" class="mt-4 space-y-2">
                    <h4 class="text-sm font-medium">Course Offerings Without Instructors:</h4>
                    <div class="grid gap-2">
                        <div
                            v-for="offering in assignmentData.unassigned_offerings"
                            :key="offering.id"
                            class="bg-background/50 flex items-center justify-between rounded border p-2 text-sm"
                        >
                            <div>
                                <span class="font-medium">{{ offering.course_code }}</span>
                                <span v-if="offering.section_code"> - Section {{ offering.section_code }}</span>
                                <span class="text-muted-foreground ml-2">{{ offering.course_title }}</span>
                            </div>
                            <div class="text-muted-foreground text-xs">{{ offering.current_enrollment }}/{{ offering.max_capacity }} enrolled</div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
