<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { Loader2 } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import { z } from 'zod';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Semester {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
}

interface Props {
    semesters: Semester[];
}

defineProps<Props>();

// Validation schema
const billingCycleSchema = z.object({
    semester_id: z.number({ required_error: 'Semester is required' }),
    name: z.string().min(1, 'Name is required').max(255, 'Name must not exceed 255 characters'),
    start_date: z.string().min(1, 'Start date is required'),
    end_date: z.string().min(1, 'End date is required'),
    due_date: z.string().min(1, 'Due date is required'),
});

type BillingCycleFormData = z.infer<typeof billingCycleSchema>;

const validationSchema = toTypedSchema(billingCycleSchema);

// Initial form values
const initialValues: BillingCycleFormData = {
    semester_id: undefined as any,
    name: '',
    start_date: '',
    end_date: '',
    due_date: '',
};

// vee-validate form
const { handleSubmit } = useForm({
    validationSchema,
    initialValues,
});

const isSubmitting = ref(false);

// Form submission handler
const onSubmit = handleSubmit(async (formValues) => {
    isSubmitting.value = true;

    router.post(route('billing-cycles.store'), formValues, {
        onSuccess: () => {
            toast.success('Billing cycle created successfully');
        },
        onError: (errors) => {
            if (errors.error) {
                toast.error(errors.error as string);
            } else {
                toast.error('Failed to create billing cycle. Please check the form for errors.');
            }
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
});
</script>

<template>
    <Head title="Create Billing Cycle" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Create Billing Cycle</h1>
                <p class="text-muted-foreground">Create a new billing cycle for a semester</p>
            </div>
            <Link :href="route('billing-cycles.index')">
                <Button variant="outline">Back to List</Button>
            </Link>
        </div>

        <form @submit="onSubmit">
            <Card>
                <CardHeader>
                    <CardTitle>Billing Cycle Details</CardTitle>
                    <CardDescription>Enter the details for the new billing cycle</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <FormField v-slot="{ componentField }" name="semester_id">
                        <FormItem>
                            <FormLabel>Semester</FormLabel>
                            <Select v-bind="componentField">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a semester" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id">
                                        {{ semester.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="name">
                        <FormItem>
                            <FormLabel>Name</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., Fall 2024 Billing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="grid gap-6 md:grid-cols-3">
                        <FormField v-slot="{ componentField }" name="start_date">
                            <FormItem>
                                <FormLabel>Start Date</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="date" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="end_date">
                            <FormItem>
                                <FormLabel>End Date</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="date" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="due_date">
                            <FormItem>
                                <FormLabel>Due Date</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="date" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <div class="flex justify-end gap-4">
                        <Link :href="route('billing-cycles.index')">
                            <Button type="button" variant="outline">Cancel</Button>
                        </Link>
                        <Button type="submit" :disabled="isSubmitting">
                            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            Create Billing Cycle
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>
    </div>
</template>
