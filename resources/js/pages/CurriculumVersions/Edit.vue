<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { CurriculumVersion, Program, Semester, Specialization } from '@/types/models';
import { ValidationRules } from '@/types/validation';
import { curriculumRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    curriculumVersion: CurriculumVersion;
    programs: Program[];
    specializations: Specialization[];
    semesters: Semester[];
}

const props = defineProps<Props>();

// Define validation schema following development standards
const formSchema = toTypedSchema(
    z.object({
        program_id: z.string().min(1, 'Program is required'),
        specialization_id: z.string().min(1, 'Specialization is required'),
        version_code: z.string().min(ValidationRules.curriculumVersion.versionCode.minLength, 'Version code is required').max(ValidationRules.curriculumVersion.versionCode.maxLength, 'Version code cannot exceed 50 characters'),
        semester_id: z.string().min(1, 'Implementation period is required'),
        notes: z.string().max(ValidationRules.curriculumVersion.notes.maxLength, 'Notes cannot exceed 1000 characters').optional(),
    }),
);

// Form setup following development standards
const { handleSubmit, isSubmitting, values, setFieldValue } = useForm({
    validationSchema: formSchema,
    initialValues: {
        program_id: props.curriculumVersion.program_id?.toString() || '',
        specialization_id: props.curriculumVersion.specialization_id?.toString() || '',
        version_code: props.curriculumVersion.version_code || '',
        semester_id: props.curriculumVersion.semester_id?.toString() || '',
        notes: props.curriculumVersion.notes || '',
    },
});

// Computed specializations filtered by selected program following development standards
const filteredSpecializations = computed(() => {
    if (!values.program_id) return [];
    return props.specializations.filter((spec) => spec.program_id.toString() === values.program_id);
});

// Watch for program changes to reset specialization following development standards
watch(
    () => values.program_id,
    (newProgramId, oldProgramId) => {
        if (newProgramId !== oldProgramId && values.specialization_id) {
            // Check if current specialization belongs to new program
            const currentSpec = props.specializations.find((spec) => spec.id.toString() === values.specialization_id);
            if (!currentSpec || currentSpec.program_id.toString() !== newProgramId) {
                setFieldValue('specialization_id', '');
            }
        }
    },
);

// Form submission following development standards
const onSubmit = handleSubmit((formValues) => {
    const submitData = {
        program_id: parseInt(formValues.program_id),
        specialization_id: formValues.specialization_id ? parseInt(formValues.specialization_id) : null,
        version_code: formValues.version_code,
        semester_id: formValues.semester_id ? parseInt(formValues.semester_id) : null,
        notes: formValues.notes || null,
    };

    router.put(route('curriculum_versions.update', { curriculum_version: props.curriculumVersion.id }), submitData, {
        onSuccess: () => {
            toast.success('Curriculum version updated successfully');
        },
        onError: () => {
            toast.error('Failed to update curriculum version');
        },
    });
});

// Helper functions
const formatSemesterDisplay = (semester: Semester): string => {
    // Format as "Academic Year 2025 - Spring"
    const year = semester.code.slice(-4);
    const season = semester.code.slice(0, -4);

    const seasonMap: Record<string, string> = {
        SP: 'Spring',
        SUM: 'Summer',
        FAL: 'Fall',
        INT: 'Intersession',
    };

    const seasonName = seasonMap[season] || season;
    return `Academic Year ${year} - ${seasonName}`;
};
</script>

<template>
    <Head :title="`Edit ${curriculumVersion.version_code || 'Curriculum Version'}`" />

    <!-- Header Section -->
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <div class="space-y-2">
                <h1 class="text-3xl font-bold tracking-tight">Edit Curriculum Version</h1>
                <p class="text-muted-foreground">Update curriculum version information and settings.</p>
            </div>

            <div class="flex items-center gap-2">
                <Link :href="curriculumRoutes.curriculumVersions.summary.overview(curriculumVersion.id)">
                    <Button variant="outline">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to View
                    </Button>
                </Link>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <Card class="mx-auto w-full max-w-2xl">
        <CardHeader>
            <CardTitle class="flex items-center gap-2"> Edit Curriculum Version Details </CardTitle>
        </CardHeader>
        <CardContent>
            <Form @submit="onSubmit" class="space-y-6">
                <div class="grid grid-cols-1 gap-6">
                    <!-- Program Selection -->
                    <FormField v-slot="{ componentField }" name="program_id">
                        <FormItem>
                            <FormLabel>Program *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a program" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="program in programs" :key="program.id" :value="program.id.toString()">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium">{{ program.name }}</span>
                                                <span class="text-muted-foreground text-xs">({{ program.code }})</span>
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Specialization Selection -->
                    <FormField v-slot="{ componentField }" name="specialization_id">
                        <FormItem>
                            <FormLabel>Specialization *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField" :disabled="!values.program_id">
                                    <SelectTrigger :class="{ 'opacity-50': !values.program_id }">
                                        <SelectValue :placeholder="values.program_id ? 'Select a specialization' : 'Select program first'" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="specialization in filteredSpecializations" :key="specialization.id" :value="specialization.id.toString()">
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium">{{ specialization.name }}</span>
                                                <span class="text-muted-foreground text-xs">({{ specialization.code }})</span>
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Version Code -->
                    <FormField v-slot="{ componentField }" name="version_code">
                        <FormItem>
                            <FormLabel>Version Code *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., v2025.1, Spring2025, CV-2025-01" :maxlength="ValidationRules.curriculumVersion.versionCode.maxLength" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Implementation Period -->
                    <FormField v-slot="{ componentField }" name="semester_id">
                        <FormItem>
                            <FormLabel>Implementation Period *</FormLabel>
                            <FormControl>
                                <Select v-bind="componentField">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select when this curriculum becomes effective" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">
                                            <div class="space-y-1">
                                                <div class="font-medium">{{ formatSemesterDisplay(semester) }}</div>
                                                <div class="text-muted-foreground text-xs">Code: {{ semester.code }}</div>
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <div class="text-muted-foreground mt-1 text-xs">This selects when the curriculum version becomes effective for new students entering the program.</div>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Notes -->
                    <FormField v-slot="{ componentField }" name="notes">
                        <FormItem>
                            <FormLabel>Notes</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="Enter any additional notes about this curriculum version..." rows="4" :maxlength="ValidationRules.curriculumVersion.notes.maxLength" />
                            </FormControl>
                            <div class="text-muted-foreground mt-1 text-xs">Optional notes about changes, requirements, or other relevant information.</div>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-3 pt-6">
                    <Link :href="curriculumRoutes.curriculumVersions.summary.overview(curriculumVersion.id)">
                        <Button type="button" variant="outline"> Cancel </Button>
                    </Link>
                    <Button type="submit" :disabled="isSubmitting">
                        <Save class="mr-2 h-4 w-4" />
                        {{ isSubmitting ? 'Updating...' : 'Update Curriculum Version' }}
                    </Button>
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
