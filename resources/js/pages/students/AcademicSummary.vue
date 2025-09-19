<script setup lang="ts">
import type { Student, StudentAcademicSummary } from '@/types/models';
import { router } from '@inertiajs/vue3';
import { onMounted } from 'vue';

interface Props {
    student: Student;
    academicSummary: StudentAcademicSummary;
}

const props = defineProps<Props>();

// Redirect to overview tab on mount
onMounted(() => {
    router.visit(route('students.academic-summary.overview', props.student.id), {
        replace: true,
    });
});
</script>

<template>
    <div>
        <!-- This page will automatically redirect to the overview tab -->
        <div class="flex min-h-screen items-center justify-center">
            <div class="text-center">
                <h2 class="text-lg font-semibold">Loading Academic Summary...</h2>
                <p class="text-muted-foreground">Redirecting to overview...</p>
            </div>
        </div>
    </div>
</template>
