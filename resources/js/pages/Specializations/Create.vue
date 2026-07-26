<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Program } from '@/types/models';
import { ValidationRules } from '@/types/validation';
import { curriculumRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Plus } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    programs: Program[];
    selectedProgramId?: number;
}

const props = defineProps<Props>();

// Get program_id from URL query parameters
const urlParams = new URLSearchParams(window.location.search);
const programIdFromUrl = urlParams.get('program_id');
const sourceFromUrl = urlParams.get('source');

// Use selectedProgramId prop or URL parameter
const initialProgramId = props.selectedProgramId?.toString() || programIdFromUrl || '';

// Define validation schema
const formSchema = toTypedSchema(
    z.object({
        program_id: z.string().min(1, 'Program is required'),
        name: z.string().min(ValidationRules.specialization.name.minLength, 'Name is required').max(ValidationRules.specialization.name.maxLength, 'Name cannot exceed 255 characters'),
        code: z.string().min(ValidationRules.specialization.code.minLength, 'Code is required').max(ValidationRules.specialization.code.maxLength, 'Code cannot exceed 50 characters'),
        description: z.string().max(ValidationRules.specialization.description.maxLength, 'Description cannot exceed 1000 characters').optional(),
        is_active: z.union([z.boolean(), z.string()]).transform((val) => {
            if (typeof val === 'string') {
                return val === 'true' || val === '1' || val === 'on';
            }
            return Boolean(val);
        }),
    }),
);

const { handleSubmit, isSubmitting } = useForm({
    validationSchema: formSchema,
    initialValues: {
        program_id: initialProgramId,
        name: '',
        code: '',
        description: '',
        is_active: true,
    },
});

const onSubmit = handleSubmit((values) => {
    // Build URL with source parameter if present
    let submitUrl = '/specializations';
    if (sourceFromUrl) {
        submitUrl += `?source=${sourceFromUrl}`;
    }

    router.post(
        submitUrl,
        {
            ...values,
            program_id: parseInt(values.program_id),
        },
        {
            onSuccess: () => {
                toast.success('Specialization created successfully');
            },
            onError: () => toast.error('Failed to create specialization'),
        },
    );
});
</script>

<template>
    <Head title="Create Specialization" />

    <!-- Header Section -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-3xl font-bold tracking-tight">Create Specialization</h1>
            <p class="text-muted-foreground text-lg">Add a new specialization to the system</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <Link :href="curriculumRoutes.specializations.index()">
                <Button variant="outline">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Specializations
                </Button>
            </Link>
        </div>
    </div>

    <!-- Create Form -->
    <Card class="mx-auto w-full max-w-2xl">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Plus class="h-5 w-5" />
                Specialization Details
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

                <FormField v-slot="{ componentField }" name="name">
                    <FormItem>
                        <FormLabel>Name *</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" placeholder="e.g., Software Development" :maxlength="ValidationRules.specialization.name.maxLength" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ componentField }" name="code">
                    <FormItem>
                        <FormLabel>Code *</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" placeholder="e.g., IT-SD" :maxlength="ValidationRules.specialization.code.maxLength" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ componentField }" name="description">
                    <FormItem>
                        <FormLabel>Description</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Enter specialization description..." rows="4" :maxlength="ValidationRules.specialization.description.maxLength" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField v-slot="{ componentField }" name="is_active">
                    <FormItem class="flex items-center space-y-0 space-x-3">
                        <FormControl>
                            <Checkbox :model-value="componentField.modelValue" @update:model-value="(value) => componentField['onUpdate:modelValue']?.(!!value)" />
                        </FormControl>
                        <FormLabel class="text-sm font-normal"> Active (specialization will be available for enrollment) </FormLabel>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <div class="flex justify-end gap-3">
                    <Link :href="curriculumRoutes.specializations.index()">
                        <Button type="button" variant="outline"> Cancel </Button>
                    </Link>
                    <Button type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Creating...' : 'Create Specialization' }}
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
