<script setup lang="ts">
import { Head, Link, router, useForm as useInertiaForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { Loader2 } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { getScholarshipTypeOptions, scholarshipFormSchema, type ScholarshipFormData } from '@/schemas/scholarship';
import { generateCodeFromName } from '@/utils/string';

interface Scholarship {
    id: number;
    code: string;
    name: string;
    description: string | null;
    type: 'percentage' | 'fixed_amount';
    amount: number;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
}

interface Props {
    scholarship: Scholarship;
}

const props = defineProps<Props>();

// Format dates for input fields (YYYY-MM-DD)
const formatDateForInput = (dateString: string) => {
    return dateString.split('T')[0];
};

// Validation schema
const validationSchema = toTypedSchema(scholarshipFormSchema);

// Initial form values from scholarship prop
const initialValues: ScholarshipFormData = {
    code: props.scholarship.code,
    name: props.scholarship.name,
    description: props.scholarship.description || '',
    type: props.scholarship.type,
    amount: +props.scholarship.amount,
    valid_from: formatDateForInput(props.scholarship.valid_from),
    valid_until: formatDateForInput(props.scholarship.valid_until),
    is_active: props.scholarship.is_active,
};

// vee-validate form
const { handleSubmit, values, setFieldValue } = useForm({
    validationSchema,
    initialValues,
});

// Inertia form for submission
const inertiaForm = useInertiaForm(initialValues);

// Options
const scholarshipTypeOptions = getScholarshipTypeOptions();

// Track if code has been manually edited
const codeManuallyEdited = ref(false);

// Auto-generate code from name
watch(
    () => values.name,
    (newName) => {
        // Only auto-generate if code hasn't been manually edited
        if (!codeManuallyEdited.value && newName) {
            const generatedCode = generateCodeFromName(newName);
            setFieldValue('code', generatedCode);
        }
    },
);

// Mark code as manually edited when user types in it
const handleCodeInput = () => {
    codeManuallyEdited.value = true;
};

// Form submission handler - wrap with handleSubmit
const onSubmit = handleSubmit((formValues) => {
    // Convert and prepare data for submission
    const submitData = {
        ...formValues,
        code: formValues.code.toUpperCase(), // Ensure uppercase
        description: formValues.description || null,
    };

    // Assign data to Inertia form
    Object.assign(inertiaForm, submitData);

    // Submit to server
    inertiaForm.put(route('scholarships.update', props.scholarship.id), {
        onSuccess: () => {
            toast.success('Scholarship updated successfully!');
            router.visit(route('scholarships.show', props.scholarship.id));
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
            const firstErrorKey = Object.keys(errors)[0];
            const firstError = errors[firstErrorKey];

            if (firstError) {
                toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
            } else {
                toast.error('Failed to update scholarship. Please check the form for errors.');
            }
        },
    });
});
</script>

<template>
    <Head title="Edit Scholarship" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Edit Scholarship</h2>
            <p class="text-muted-foreground mt-1 text-sm">Update scholarship details and validity period</p>
        </div>
        <div class="flex gap-2">
            <Button variant="outline" as-child>
                <Link :href="route('scholarships.show', scholarship.id)">View Details</Link>
            </Button>
            <Button variant="outline" as-child>
                <Link :href="route('scholarships.index')">Back to List</Link>
            </Button>
        </div>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Scholarship Details</CardTitle>
            <CardDescription>Update the scholarship information</CardDescription>
        </CardHeader>
        <CardContent>
            <form class="space-y-6" @submit.prevent="onSubmit">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Code -->
                    <FormField v-slot="{ componentField }" name="code">
                        <FormItem>
                            <FormLabel>Scholarship Code *</FormLabel>
                            <FormControl>
                                <Input
                                    v-bind="componentField"
                                    placeholder="e.g., MERIT2024"
                                    class="font-mono"
                                    :disabled="inertiaForm.processing"
                                    @input="handleCodeInput"
                                />
                            </FormControl>
                            <p class="text-muted-foreground text-xs">Auto-generated from name, or enter manually</p>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Name -->
                    <FormField v-slot="{ componentField }" name="name">
                        <FormItem>
                            <FormLabel>Scholarship Name *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., Merit Scholarship 2024" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Description -->
                    <FormField v-slot="{ componentField }" name="description" class="md:col-span-2">
                        <FormItem>
                            <FormLabel>Description</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" rows="3" placeholder="Describe the scholarship purpose and eligibility" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Type -->
                    <FormField v-slot="{ componentField }" name="type">
                        <FormItem>
                            <FormLabel>Discount Type *</FormLabel>
                            <Select v-bind="componentField" :disabled="inertiaForm.processing">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem v-for="option in scholarshipTypeOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Amount -->
                    <FormField v-slot="{ componentField }" name="amount">
                        <FormItem>
                            <FormLabel>
                                {{ values.type === 'percentage' ? 'Discount Percentage *' : 'Discount Amount (VND) *' }}
                            </FormLabel>
                            <FormControl>
                                <Input
                                    v-bind="componentField"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    :placeholder="values.type === 'percentage' ? 'e.g., 50' : 'e.g., 5000000'"
                                    :disabled="inertiaForm.processing"
                                    @input="(e: any) => componentField['onInput'](parseFloat((e.target as HTMLInputElement).value) || 0)"
                                />
                            </FormControl>
                            <p class="text-muted-foreground text-xs">
                                {{ values.type === 'percentage' ? 'Enter percentage value (e.g., 50 for 50%)' : 'Enter amount in Vietnamese Dong' }}
                            </p>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Valid From -->
                    <FormField v-slot="{ componentField }" name="valid_from">
                        <FormItem>
                            <FormLabel>Valid From *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="date" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Valid Until -->
                    <FormField v-slot="{ componentField }" name="valid_until">
                        <FormItem>
                            <FormLabel>Valid Until *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="date" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Is Active -->
                    <FormField v-slot="{ value: fieldValue, handleChange }" name="is_active" class="md:col-span-2">
                        <FormItem class="flex flex-row items-center space-x-2 space-y-0">
                            <FormControl>
                                <Checkbox :model-value="fieldValue" @update:model-value="handleChange" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormLabel class="cursor-pointer font-normal">Active (scholarship can be assigned to students)</FormLabel>
                        </FormItem>
                    </FormField>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" as-child :disabled="inertiaForm.processing">
                        <Link :href="route('scholarships.show', scholarship.id)">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="inertiaForm.processing">
                        <Loader2 v-if="inertiaForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Update Scholarship
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
