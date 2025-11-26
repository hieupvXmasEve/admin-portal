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
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import * as z from 'zod';

interface QueryForm {
    id: number;
    code: string;
    title: string;
    description: string | null;
}

interface Props {
    config: {
        active_query_forms: number[];
    };
    queryForms: QueryForm[];
}

const props = defineProps<Props>();

// Validation schema
const querySettingsSchema = z.object({
    active_query_forms: z.array(z.number()).min(1, 'Please select at least one form'),
});

const validationSchema = toTypedSchema(querySettingsSchema);

// Initial form values
const initialValues = {
    active_query_forms: props.config.active_query_forms ?? [],
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
    () => values.active_query_forms,
    (newValue) => {
        inertiaForm.active_query_forms = newValue ?? [];
    },
    { deep: true },
);

// Check if form has changes
const hasChanges = computed(() => {
    const currentIds = (props.config.active_query_forms ?? []).sort().join(',');
    const newIds = (values.active_query_forms ?? []).sort().join(',');
    return currentIds !== newIds;
});

// Toggle form selection
const toggleForm = (formId: number) => {
    const currentIds = values.active_query_forms ?? [];
    const isSelected = currentIds.includes(formId);

    if (isSelected) {
        setFieldValue(
            'active_query_forms',
            currentIds.filter((id) => id !== formId),
        );
    } else {
        setFieldValue('active_query_forms', [...currentIds, formId]);
    }
};

// Check if form is selected
const isFormSelected = (formId: number) => {
    return (values.active_query_forms ?? []).includes(formId);
};

// Selected forms for preview
const selectedForms = computed(() => {
    const selectedIds = values.active_query_forms ?? [];
    return props.queryForms.filter((f) => selectedIds.includes(f.id));
});

// Form submission handler
const onSubmit = handleSubmit((formValues) => {
    // Validate: at least one form must be selected
    if (!formValues.active_query_forms || formValues.active_query_forms.length === 0) {
        toast.error('Please select at least one query form.');
        return;
    }

    // Prepare data for submission
    const submitData = {
        active_query_forms: formValues.active_query_forms,
    };

    // Assign data to Inertia form
    Object.assign(inertiaForm, submitData);

    // Submit to server
    inertiaForm.post(route('forms.queries.settings.update'), {
        onSuccess: () => {
            toast.success('Query form settings updated successfully!');
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
            const firstErrorKey = Object.keys(errors)[0];
            const firstError = errors[firstErrorKey];

            if (firstError) {
                toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
            } else {
                toast.error('Failed to update query form settings. Please check the form for errors.');
            }
        },
    });
});

// Preview form handler
const handlePreviewForm = (formId: number) => {
    // Navigate to form preview page
    router.visit(route('forms.admin.show', formId));
};
</script>

<template>
    <Head title="Query Form Settings" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight">
                    <Settings2 class="h-6 w-6" />
                    Query Form Settings
                </h1>
                <p class="text-muted-foreground">Select active query forms to display for students</p>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Active Query Forms</CardTitle>
                <CardDescription>Select which query forms should be available to students through the API</CardDescription>
            </CardHeader>
            <CardContent>
                <form class="space-y-6" @submit.prevent="onSubmit">
                    <!-- Query Forms Selection -->
                    <FormField name="active_query_forms">
                        <FormItem>
                            <FormLabel class="text-base">Available Forms *</FormLabel>
                            <p class="text-muted-foreground mb-4 text-sm">Select one or more query forms to make available to students</p>

                            <div v-if="queryForms.length === 0" class="text-muted-foreground rounded-lg border p-4 text-sm">No active query forms available. Please create query forms first.</div>

                            <div v-else class="space-y-3">
                                <div v-for="form in queryForms" :key="form.id" class="hover:bg-muted/50 flex items-start space-x-3 rounded-lg border p-4 transition-colors">
                                    <FormControl>
                                        <Checkbox :model-value="isFormSelected(form.id)" @update:model-value="() => toggleForm(form.id)" :disabled="inertiaForm.processing" />
                                    </FormControl>
                                    <div class="flex-1 space-y-1">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-medium">{{ form.title }}</p>
                                                <p class="text-muted-foreground text-sm">Code: {{ form.code }}</p>
                                            </div>
                                            <Button type="button" variant="ghost" size="sm" class="h-8 gap-2" @click="handlePreviewForm(form.id)">
                                                <Eye class="h-4 w-4" />
                                                Preview
                                            </Button>
                                        </div>
                                        <p v-if="form.description" class="text-muted-foreground text-sm">
                                            {{ form.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Selected Forms Summary -->
                    <div v-if="selectedForms.length > 0" class="bg-muted/50 rounded-lg border p-4">
                        <h4 class="mb-3 font-semibold">Selected Forms ({{ selectedForms.length }}):</h4>
                        <div class="space-y-2">
                            <div v-for="form in selectedForms" :key="form.id" class="text-sm">
                                <p>
                                    <span class="font-medium">{{ form.title }}</span>
                                    <span class="text-muted-foreground"> ({{ form.code }})</span>
                                </p>
                            </div>
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
                    <p><span class="font-semibold">1. Select Active Forms:</span> Choose which query forms should be available to students through the mobile app API endpoint.</p>
                    <p><span class="font-semibold">2. API Endpoint:</span> Selected forms will be returned by the <code class="bg-muted rounded px-1">GET /api/v1/student/forms/query/active</code> endpoint.</p>
                    <p><span class="font-semibold">3. Requirements:</span> Only active query forms with published versions can be selected. Students will see these forms in their mobile app.</p>
                    <p class="text-muted-foreground pt-2 text-xs"><span class="font-semibold">Note:</span> At least one form must be selected. Forms are returned in the order they appear in the list.</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
