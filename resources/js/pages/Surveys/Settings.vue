<script setup lang="ts">
import { Head, router, useForm as useInertiaForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { Eye, Loader2, Save, Settings2 } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import * as z from 'zod';

interface SurveyForm {
    id: number;
    code: string;
    title: string;
    description: string | null;
}

interface Props {
    config: {
        survey_enabled: boolean;
        default_course_survey: number | null;
    };
    surveyForms: SurveyForm[];
}

const props = defineProps<Props>();
// console.log(props.config);
console.log(props.surveyForms);
// Validation schema
const surveySettingsSchema = z.object({
    survey_enabled: z.boolean(),
    default_course_survey: z.number().nullable(),
});

const validationSchema = toTypedSchema(surveySettingsSchema);

// Initial form values
const initialValues = {
    survey_enabled: props.config.survey_enabled ?? false,
    default_course_survey: props.config.default_course_survey ?? null,
};

// vee-validate form
const { handleSubmit, values, setFieldValue } = useForm({
    validationSchema,
    initialValues,
});

// Inertia form for submission
const inertiaForm = useInertiaForm(initialValues);

// Watch for changes to sync with Inertia form
watch(
    () => values.survey_enabled,
    (newValue) => {
        inertiaForm.survey_enabled = newValue ?? false;
    },
);

watch(
    () => values.default_course_survey,
    (newValue) => {
        inertiaForm.default_course_survey = newValue ?? null;
    },
);

// Check if form has changes
const hasChanges = computed(() => {
    return values.survey_enabled !== props.config.survey_enabled || values.default_course_survey !== props.config.default_course_survey;
});

// Selected form for preview
const selectedForm = computed(() => {
    if (!values.default_course_survey) {
        return null;
    }
    return props.surveyForms.find((f) => f.id === values.default_course_survey);
});

// Form submission handler
const onSubmit = handleSubmit((formValues) => {
    // Validate: if survey is enabled, default form must be selected
    if (formValues.survey_enabled && !formValues.default_course_survey) {
        toast.error('Please select a default survey form when survey feature is enabled.');
        return;
    }

    // Prepare data for submission
    const submitData = {
        survey_enabled: formValues.survey_enabled,
        default_course_survey: formValues.default_course_survey || null,
    };

    // Assign data to Inertia form
    Object.assign(inertiaForm, submitData);

    // Submit to server
    inertiaForm.post(route('surveys.settings.update'), {
        onSuccess: () => {
            toast.success('Survey settings updated successfully!');
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
            const firstErrorKey = Object.keys(errors)[0];
            const firstError = errors[firstErrorKey];

            if (firstError) {
                toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
            } else {
                toast.error('Failed to update survey settings. Please check the form for errors.');
            }
        },
    });
});

// Preview form handler
const handlePreviewForm = () => {
    if (!selectedForm.value) {
        toast.error('Please select a form first');
        return;
    }
    // Navigate to form preview page (if exists) or show in modal
    router.visit(route('forms.admin'), {
        data: { preview_form_id: selectedForm.value.id },
    });
};
</script>

<template>
    <Head title="Course Survey Settings" />

    <div class="space-y-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                    <Settings2 class="h-6 w-6" />
                    Course Survey Settings
                </h1>
                <p class="text-muted-foreground">Configure automatic course survey settings</p>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Survey Configuration</CardTitle>
                <CardDescription> Enable automatic survey assignment when courses are completed and select the default survey form </CardDescription>
            </CardHeader>
            <CardContent>
                <form class="space-y-6" @submit.prevent="onSubmit">
                    <!-- Enable Survey -->
                    <FormField name="survey_enabled">
                        <FormItem class="flex flex-row items-center justify-between rounded-lg border p-4">
                            <div class="space-y-0.5">
                                <FormLabel class="text-base">Enable Course Survey</FormLabel>
                                <p class="text-muted-foreground text-sm">Automatically assign surveys to students when courses are completed</p>
                            </div>
                            <FormControl>
                                <Switch :model-value="values.survey_enabled" @update:model-value="(val) => setFieldValue('survey_enabled', val)" :disabled="inertiaForm.processing" />
                            </FormControl>
                        </FormItem>
                    </FormField>

                    <!-- Default Survey Form -->
                    <FormField name="default_course_survey">
                        <FormItem>
                            <div class="flex items-center justify-between">
                                <FormLabel>Default Survey Form *</FormLabel>
                                <Button v-if="selectedForm" type="button" variant="ghost" size="sm" class="h-8 gap-2" @click="handlePreviewForm">
                                    <Eye class="h-4 w-4" />
                                    Preview
                                </Button>
                            </div>
                            <Select :model-value="values.default_course_survey ? String(values.default_course_survey) : undefined" :disabled="!values.survey_enabled || inertiaForm.processing" @update:model-value="(val) => setFieldValue('default_course_survey', val ? Number(val) : null)">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a survey form" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem v-if="!values.survey_enabled" value="0" disabled> Enable survey first </SelectItem>
                                    <SelectItem v-for="form in surveyForms" :key="form.id" :value="String(form.id)">
                                        {{ form.title }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-muted-foreground text-sm">This form will be automatically assigned to all completed courses</p>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Selected Form Info -->
                    <div v-if="selectedForm && values.survey_enabled" class="bg-muted/50 rounded-lg border p-4">
                        <h4 class="mb-2 font-semibold">Selected Form:</h4>
                        <div class="space-y-1 text-sm">
                            <p><span class="font-medium">Title:</span> {{ selectedForm.title }}</p>
                            <p><span class="font-medium">Code:</span> {{ selectedForm.code }}</p>
                            <p v-if="selectedForm.description" class="text-muted-foreground">
                                {{ selectedForm.description }}
                            </p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-4 border-t pt-6">
                        <Button type="submit" :disabled="inertiaForm.processing || !hasChanges" class="min-w-24">
                            <Save v-if="!inertiaForm.processing" class="mr-2 h-4 w-4" />
                            <Loader2 v-else class="mr-2 h-4 w-4 animate-spin" />
                            {{ inertiaForm.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <!-- Information Card -->
        <Card>
            <CardHeader>
                <CardTitle>How It Works</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-2 text-sm">
                    <p><span class="font-semibold">1. Enable Survey:</span> When enabled, the system will automatically assign surveys to students when courses are marked as completed.</p>
                    <p><span class="font-semibold">2. Select Default Form:</span> Choose the survey form that will be used for all completed courses. Only active survey forms with published versions are available.</p>
                    <p><span class="font-semibold">3. Automatic Assignment:</span> When a course is completed:</p>
                    <ul class="text-muted-foreground ml-6 list-disc space-y-1">
                        <li>The selected form is attached to the course offering</li>
                        <li>Survey tasks are created for all enrolled students</li>
                        <li>Students will see a mandatory popup until they complete all pending surveys</li>
                    </ul>
                    <p class="text-muted-foreground pt-2 text-xs"><span class="font-semibold">Note:</span> EGC courses are excluded from automatic survey assignment.</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
