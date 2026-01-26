<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { Loader2, Plus, Trash2 } from 'lucide-vue-next';
import { useForm, useFieldArray } from 'vee-validate';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import { z } from 'zod';
import { ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface CurriculumVersion {
    id: number;
    name: string;
    program: {
        id: number;
        name: string;
    };
}

interface Semester {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
}

interface TuitionPlanTerm {
    id: number;
    term_number: number;
    amount: number;
    due_date: string | null;
}

interface TuitionPlan {
    id: number;
    curriculum_version_id: number;
    intake_semester_id: number;
    total_amount: number;
    currency: string;
    is_active: boolean;
    terms: TuitionPlanTerm[];
}

interface Props {
    tuitionPlan: TuitionPlan;
    curriculumVersions: CurriculumVersion[];
    semesters: Semester[];
}

const props = defineProps<Props>();

// Validation schema
const tuitionPlanSchema = z.object({
    curriculum_version_id: z.number({ required_error: 'Curriculum version is required' }),
    intake_semester_id: z.number({ required_error: 'Intake semester is required' }),
    total_amount: z.number().min(0, 'Total amount must be greater than or equal to zero'),
    currency: z.string().length(3).default('VND'),
    is_active: z.boolean().default(true),
    terms: z.array(z.object({
        term_number: z.number().min(1, 'Term number must be greater than zero'),
        amount: z.number().min(0, 'Amount must be greater than or equal to zero'),
        due_date: z.string().nullable().optional(),
    })).optional(),
});

type TuitionPlanFormData = z.infer<typeof tuitionPlanSchema>;

const validationSchema = toTypedSchema(tuitionPlanSchema);

// Initial form values from existing tuition plan
const initialValues: TuitionPlanFormData = {
    curriculum_version_id: props.tuitionPlan.curriculum_version_id,
    intake_semester_id: props.tuitionPlan.intake_semester_id,
    total_amount: Number(props.tuitionPlan.total_amount || 0),
    currency: props.tuitionPlan.currency,
    is_active: props.tuitionPlan.is_active,
    terms: props.tuitionPlan.terms?.map(term => ({
        term_number: term.term_number,
        amount: term.amount,
        due_date: term.due_date,
    })) || [],
};

// vee-validate form
const { handleSubmit } = useForm({
    validationSchema,
    initialValues,
});

// Use FieldArray for dynamic terms
const { fields: terms, push, remove, update } = useFieldArray<any>('terms');
const isSubmitting = ref(false);

const addTerm = () => {
    push({
        term_number: terms.value.length + 1,
        amount: 0,
        due_date: null,
    });
};

const removeTerm = (index: number) => {
    remove(index);
    // Renumber terms after removal
    terms.value.forEach((term, idx) => {
        update(idx, { ...term.value, term_number: idx + 1 });
    });
};

// Form submission handler using vee-validate actions pattern
const onSubmit = handleSubmit(async (formValues, actions) => {
    const submitData = {
        ...formValues,
        curriculum_version_id: Number(formValues.curriculum_version_id),
        intake_semester_id: Number(formValues.intake_semester_id),
        total_amount: Number(formValues.total_amount),
        is_active: Boolean(formValues.is_active),
        terms: formValues.terms?.map(term => ({
            term_number: Number(term.term_number),
            amount: Number(term.amount),
            due_date: term.due_date || null,
        })) || [],
    };

    console.log('Submitting data:', submitData);

    isSubmitting.value = true;

    router.put(route('tuition-plans.update', props.tuitionPlan.id), submitData, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            toast.success('Tuition plan updated successfully');
        },
        onError: (serverErrors) => {
            isSubmitting.value = false;
            toast.error('Failed to update tuition plan. Please check the form for errors.');
            console.error('Validation errors:', serverErrors);

            // Use vee-validate actions to set errors
            actions.setErrors(serverErrors);
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
});
</script>

<template>
    <Head title="Edit Tuition Plan" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Edit Tuition Plan</h1>
                <p class="text-muted-foreground">Update the tuition plan details</p>
            </div>
            <Link :href="route('tuition-plans.show', tuitionPlan.id)">
                <Button variant="outline">Back to Details</Button>
            </Link>
        </div>

        <form @submit.prevent="onSubmit">
            <Card>
                <CardHeader>
                    <CardTitle>Tuition Plan Details</CardTitle>
                    <CardDescription>Update the basic information for the tuition plan</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <FormField v-slot="{ componentField }" name="curriculum_version_id">
                        <FormItem>
                            <FormLabel>Curriculum Version</FormLabel>
                            <Select v-bind="componentField">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select curriculum version" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem
                                        v-for="cv in curriculumVersions"
                                        :key="cv.id"
                                        :value="cv.id"
                                    >
                                        {{ cv.program.name }} - {{ cv.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="intake_semester_id">
                        <FormItem>
                            <FormLabel>Intake Semester</FormLabel>
                            <Select v-bind="componentField">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select intake semester" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem
                                        v-for="semester in semesters"
                                        :key="semester.id"
                                        :value="semester.id"
                                    >
                                        {{ semester.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="total_amount">
                        <FormItem>
                            <FormLabel>Total Amount</FormLabel>
                            <FormControl>
                                <Input
                                    type="number"
                                    step="0.01"
                                    placeholder="Enter total tuition amount"
                                    v-bind="componentField"
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="currency">
                        <FormItem>
                            <FormLabel>Currency</FormLabel>
                            <FormControl>
                                <Input
                                    placeholder="VND"
                                    maxlength="3"
                                    v-bind="componentField"
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ value, handleChange }" name="is_active">
                        <FormItem class="flex flex-row items-start space-x-3 space-y-0">
                            <FormControl>
                                <Checkbox :model-value="value" @update:model-value="handleChange" />
                            </FormControl>
                            <div class="space-y-1 leading-none">
                                <FormLabel>Active</FormLabel>
                            </div>
                        </FormItem>
                    </FormField>
                </CardContent>
            </Card>

            <Card class="mt-6">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Payment Terms</CardTitle>
                            <CardDescription>Define payment terms across semesters</CardDescription>
                        </div>
                        <Button type="button" variant="outline" size="sm" @click="addTerm">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Term
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="terms.length === 0" class="text-center py-8 text-muted-foreground">
                        No terms added yet. Click "Add Term" to create payment terms.
                    </div>
                    <div v-else class="space-y-4">
                        <div
                            v-for="(field, index) in terms"
                            :key="field.key"
                            class="flex gap-4 items-start p-4 border rounded-lg"
                        >
                            <div class="flex-1 grid gap-4 md:grid-cols-3">
                                <FormField v-slot="{ componentField }" :name="`terms[${index}].term_number`">
                                    <FormItem>
                                        <FormLabel>Term Number</FormLabel>
                                        <FormControl>
                                            <Input type="number" v-bind="componentField" disabled />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <FormField v-slot="{ componentField }" :name="`terms[${index}].amount`">
                                    <FormItem>
                                        <FormLabel>Amount <span class="text-destructive">*</span></FormLabel>
                                        <FormControl>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                placeholder="0.00"
                                                v-bind="componentField"
                                            />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>

                                <FormField v-slot="{ componentField }" :name="`terms[${index}].due_date`">
                                    <FormItem>
                                        <FormLabel>Due Date</FormLabel>
                                        <FormControl>
                                            <Input type="date" v-bind="componentField" />
                                        </FormControl>
                                        <FormMessage />
                                    </FormItem>
                                </FormField>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                @click="removeTerm(index)"
                                class="mt-7"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-4 mt-6">
                <Link :href="route('tuition-plans.show', tuitionPlan.id)">
                    <Button type="button" variant="outline">Cancel</Button>
                </Link>
                <Button type="submit" :disabled="isSubmitting">
                    <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                    Update Tuition Plan
                </Button>
            </div>
        </form>
    </div>
</template>
