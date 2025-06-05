<template>
    <Head title="Create Curriculum Version" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex flex-col gap-6 p-4">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <h1 class="text-3xl font-bold tracking-tight">Create Curriculum Version</h1>
                    <p class="text-muted-foreground text-lg">Add a new curriculum version to the system</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link :href="route('curriculum_version.index')">
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
                                <FormLabel>Specialization *</FormLabel>
                                <FormControl>
                                    <Select v-bind="componentField">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a specialization" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="specialization in filteredSpecializations"
                                                :key="specialization.id"
                                                :value="specialization.id.toString()"
                                            >
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
                                    <Input
                                        v-bind="componentField"
                                        placeholder="e.g., v1.0, 2023-S1"
                                        :maxlength="ValidationRules.curriculumVersion.versionCode.maxLength"
                                    />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="semester_id">
                            <FormItem>
                                <FormLabel>Effective From Semester *</FormLabel>
                                <FormControl>
                                    <Select v-bind="componentField">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select semester" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">
                                                {{ semester.name }} ({{ semester.code }})
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="notes">
                            <FormItem>
                                <FormLabel>Notes</FormLabel>
                                <FormControl>
                                    <Textarea
                                        v-bind="componentField"
                                        placeholder="Enter any additional notes..."
                                        rows="4"
                                        :maxlength="ValidationRules.curriculumVersion.notes.maxLength"
                                    />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <div class="flex justify-end gap-3">
                            <Link :href="route('curriculum_version.index')">
                                <Button type="button" variant="outline"> Cancel </Button>
                            </Link>
                            <Button type="submit" :disabled="isSubmitting">
                                {{ isSubmitting ? 'Creating...' : 'Create Curriculum Version' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Program, Semester, Specialization } from '@/types/models';
import { ValidationRules } from '@/types/validation';
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Plus } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref } from 'vue';
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

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Curriculum Versions',
        href: '/curriculum-versions',
    },
    {
        title: 'Create',
        href: '/curriculum-versions/create',
    },
];

// Get parameters from URL query
const urlParams = new URLSearchParams(window.location.search);
const programIdFromUrl = urlParams.get('program_id');
const specializationIdFromUrl = urlParams.get('specialization_id');
const sourceFromUrl = urlParams.get('source');

// Use props or URL parameters
const initialProgramId = props.selectedProgramId?.toString() || programIdFromUrl || '';
const initialSpecializationId = props.selectedSpecializationId?.toString() || specializationIdFromUrl || '';

// Reactive scope tracking
const currentProgramId = ref(initialProgramId);

// Define validation schema
const formSchema = toTypedSchema(
    z.object({
        program_id: z.string().min(1, 'Program is required'),
        specialization_id: z.string().min(1, 'Specialization is required'),
        version_code: z
            .string()
            .min(ValidationRules.curriculumVersion.versionCode.minLength, 'Version code is required')
            .max(ValidationRules.curriculumVersion.versionCode.maxLength, 'Version code cannot exceed 50 characters'),
        semester_id: z.string().min(1, 'Semester is required'),
        notes: z.string().max(ValidationRules.curriculumVersion.notes.maxLength, 'Notes cannot exceed 1000 characters').optional(),
    }),
);

const { handleSubmit, isSubmitting, values } = useForm({
    validationSchema: formSchema,
    initialValues: {
        program_id: initialProgramId,
        specialization_id: initialSpecializationId,
        version_code: '',
        semester_id: '',
        notes: '',
    },
});

// Computed specializations filtered by selected program
const filteredSpecializations = computed(() => {
    const programId = values.program_id || currentProgramId.value;
    if (!programId) return props.specializations;
    return props.specializations.filter((spec) => spec.program_id.toString() === programId);
});

const onSubmit = handleSubmit((values) => {
    const submitData = {
        ...values,
        program_id: parseInt(values.program_id),
        specialization_id: values.specialization_id ? parseInt(values.specialization_id) : null,
        semester_id: values.semester_id ? parseInt(values.semester_id) : null,
    };

    router.post('/curriculum-versions', submitData, {
        onSuccess: () => {
            toast.success('Curriculum version created successfully');

            // Redirect based on source
            if (sourceFromUrl === 'program-show') {
                // Return to Programs Show page
                router.visit(`/programs/${values.program_id}`);
            } else if (sourceFromUrl === 'specialization-show' && values.specialization_id) {
                // Return to Specializations Show page
                router.visit(`/specializations/${values.specialization_id}`);
            } else {
                // Return to Curriculum Versions Index
                router.visit('/curriculum-versions');
            }
        },
        onError: () => toast.error('Failed to create curriculum version'),
    });
});
</script>
