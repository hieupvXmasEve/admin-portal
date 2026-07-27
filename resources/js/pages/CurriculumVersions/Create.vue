<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Program, Semester, Specialization } from '@/types/models';
import { ValidationRules } from '@/types/validation';
import { curriculumRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Plus } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    programs: Program[];
    specializations: Specialization[];
    semesters: Semester[];
    selectedProgramId?: number;
    selectedSpecializationId?: number;
    source?: string;
}

const props = defineProps<Props>();

// Get parameters from URL query
const urlParams = new URLSearchParams(window.location.search);
const programIdFromUrl = urlParams.get('program_id');
const specializationIdFromUrl = urlParams.get('specialization_id');

// Use props or URL parameters
const initialProgramId = props.selectedProgramId?.toString() || programIdFromUrl || '';
const initialSpecializationId = props.selectedSpecializationId?.toString() || specializationIdFromUrl || '';

// Reactive scope tracking
const currentProgramId = ref(initialProgramId);

// Define validation schema
const formSchema = toTypedSchema(
    z.object({
        program_id: z.string().min(1, 'Program is required'),
        specialization_id: z.string().optional(),
        version_code: z.string().min(ValidationRules.curriculumVersion.versionCode.minLength, 'Version code is required').max(ValidationRules.curriculumVersion.versionCode.maxLength, 'Version code cannot exceed 50 characters'),
        semester_id: z.string().min(1, 'Implementation period is required'),
        notes: z.string().max(ValidationRules.curriculumVersion.notes.maxLength, 'Notes cannot exceed 1000 characters').optional(),
    }),
);

const { handleSubmit, isSubmitting, values, setFieldValue } = useForm({
    validationSchema: formSchema,
    initialValues: {
        program_id: initialProgramId,
        specialization_id: initialSpecializationId,
        version_code: '',
        semester_id: '',
        notes: '',
    },
});

// Watch for program changes to update available specializations
watch(
    () => values.program_id,
    (newProgramId, oldProgramId) => {
        if (newProgramId !== oldProgramId) {
            currentProgramId.value = newProgramId;

            // Reset specialization if current one doesn't belong to new program
            if (values.specialization_id) {
                const currentSpecialization = props.specializations.find((spec) => spec.id.toString() === values.specialization_id);

                if (!currentSpecialization || currentSpecialization.program_id.toString() !== newProgramId) {
                    setFieldValue('specialization_id', '');
                }
            }
        }
    },
);

// Computed specializations filtered by selected program
const filteredSpecializations = computed(() => {
    const programId = values.program_id || currentProgramId.value;
    console.log('Current programId:', programId);
    console.log('All specializations:', props.specializations);

    if (!programId) return [];

    const filtered = props.specializations.filter((spec) => spec.program_id.toString() === programId);
    console.log('Filtered specializations:', filtered);

    return filtered;
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

const onSubmit = handleSubmit((values) => {
    const submitData = {
        ...values,
        program_id: parseInt(values.program_id),
        specialization_id: values.specialization_id ? parseInt(values.specialization_id) : null,
        semester_id: values.semester_id ? parseInt(values.semester_id) : null,
    };

    router.post(route('curriculum_versions.store'), submitData, {
        onSuccess: () => {
            toast.success('Curriculum version created successfully');
        },
        onError: () => toast.error('Failed to create curriculum version'),
    });
});
</script>

<template>
    <Head title="Create Curriculum Version" />

    <!-- Header Section -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold tracking-tight">Create Curriculum Version</h1>
            <p class="text-muted-foreground text-lg">Add a new curriculum version to the system</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <Link :href="curriculumRoutes.curriculumVersions.index()">
                <Button variant="outline">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Curriculum Versions
                </Button>
            </Link>
        </div>
    </div>

    <!-- Create Form -->
    <Card class="mx-auto w-full max-w-2xl">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Plus class="h-5 w-5" />
                Curriculum Version Details
            </CardTitle>
        </CardHeader>
        <CardContent>
            <form @submit="onSubmit" class="space-y-6">
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
                                        {{ program.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ componentField }" name="specialization_id">
                    <FormItem>
                        <FormLabel>Specialization</FormLabel>
                        <FormControl>
                            <Select v-bind="componentField">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a specialization" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="specialization in filteredSpecializations" :key="specialization.id" :value="specialization.id.toString()">
                                        {{ specialization.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ componentField }" name="version_code">
                    <FormItem>
                        <FormLabel>Version Code *</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" placeholder="e.g., v2025.1, Spring2025, CV-2025-01" :maxlength="ValidationRules.curriculumVersion.versionCode.maxLength" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

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

                <FormField v-slot="{ componentField }" name="notes">
                    <FormItem>
                        <FormLabel>Notes</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Enter any additional notes..." rows="4" :maxlength="ValidationRules.curriculumVersion.notes.maxLength" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <div class="flex justify-end gap-3">
                    <Link :href="curriculumRoutes.curriculumVersions.index()">
                        <Button type="button" variant="outline"> Cancel </Button>
                    </Link>
                    <Button type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Creating...' : 'Create Curriculum Version' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
