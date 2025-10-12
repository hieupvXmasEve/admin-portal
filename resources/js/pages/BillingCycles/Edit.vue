<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { AlertCircle, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useForm } from 'vee-validate';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { billingCycleFormSchema, type BillingCycleFormData } from '@/schemas/billingCycle';

interface Semester {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
}

interface BillingCycle {
    id: number;
    semester_id: number;
    name: string;
    start_date: string;
    end_date: string;
    due_date: string;
    status: 'draft' | 'active' | 'closed';
}

interface Props {
    billingCycle: BillingCycle;
    semesters: Semester[];
}

const props = defineProps<Props>();

const formatDateForInput = (dateValue: string) => {
    if (!dateValue) return '';
    return dateValue.includes('T') ? dateValue.split('T')[0] : dateValue;
};

const initialValues: BillingCycleFormData = {
    semester_id: props.billingCycle.semester_id,
    name: props.billingCycle.name,
    start_date: formatDateForInput(props.billingCycle.start_date),
    end_date: formatDateForInput(props.billingCycle.end_date),
    due_date: formatDateForInput(props.billingCycle.due_date),
};

const validationSchema = toTypedSchema(billingCycleFormSchema);

const { handleSubmit } = useForm({
    validationSchema,
    initialValues,
});

const isSubmitting = ref(false);

const status = computed(() => props.billingCycle.status);
const isDraft = computed(() => status.value === 'draft');
const isActive = computed(() => status.value === 'active');
const isClosed = computed(() => status.value === 'closed');

const semesterName = computed(() => {
    const semester = props.semesters.find((item) => item.id === props.billingCycle.semester_id);
    return semester ? semester.name : 'N/A';
});

const statusVariant = computed(() => {
    switch (status.value) {
        case 'active':
            return 'default' as const;
        case 'closed':
            return 'secondary' as const;
        case 'draft':
        default:
            return 'outline' as const;
    }
});

const buildPayload = (formValues: BillingCycleFormData) => {
    if (isDraft.value) {
        return {
            ...formValues,
            semester_id: Number(formValues.semester_id),
        };
    }

    if (isActive.value) {
        return {
            name: formValues.name,
        };
    }

    return {
        name: props.billingCycle.name,
    };
};

const onSubmit = handleSubmit((formValues) => {
    if (isClosed.value) {
        toast.error('Closed billing cycles cannot be edited.');
        return;
    }

    isSubmitting.value = true;

    const payload = buildPayload(formValues);

    router.put(route('billing-cycles.update', props.billingCycle.id), payload, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Billing cycle updated successfully.');
        },
        onError: (errors) => {
            const firstErrorKey = Object.keys(errors)[0];
            const firstError = firstErrorKey ? errors[firstErrorKey] : null;

            if (typeof firstError === 'string') {
                toast.error(firstError);
            } else if (Array.isArray(firstError) && firstError.length > 0) {
                toast.error(firstError[0]);
            } else if (errors.error) {
                toast.error(errors.error as string);
            } else {
                toast.error('Failed to update billing cycle. Please review the form.');
            }
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
});
</script>

<template>
    <Head :title="`Edit Billing Cycle - ${billingCycle.name}`" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-3xl font-bold tracking-tight">Edit Billing Cycle</h1>
                    <Badge :variant="statusVariant">{{ status.charAt(0).toUpperCase() + status.slice(1) }}</Badge>
                </div>
                <p class="text-muted-foreground">Update billing cycle details. Editing capabilities depend on the current status.</p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="route('billing-cycles.show', billingCycle.id)">View Details</Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="route('billing-cycles.index')">Back to List</Link>
                </Button>
            </div>
        </div>

        <Alert v-if="isActive" class="border-blue-200 bg-blue-50 text-blue-900">
            <AlertCircle class="h-4 w-4" />
            <AlertTitle>Limited Editing</AlertTitle>
            <AlertDescription>Only the name can be updated while the billing cycle is active. Other fields are read-only.</AlertDescription>
        </Alert>

        <Alert v-if="isClosed" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertTitle>Billing Cycle Closed</AlertTitle>
            <AlertDescription>This billing cycle is closed and cannot be edited. Review the details below.</AlertDescription>
        </Alert>

        <form v-if="!isClosed" @submit.prevent="onSubmit">
            <Card>
                <CardHeader>
                    <CardTitle>Billing Cycle Details</CardTitle>
                    <CardDescription>
                        {{ isDraft ? 'Modify the full billing cycle configuration.' : 'Update the billing cycle name while other fields remain locked.' }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <FormField v-slot="{ componentField }" name="semester_id">
                        <FormItem>
                            <FormLabel>Semester</FormLabel>
                            <Select v-bind="componentField" :disabled="!isDraft || isSubmitting">
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
                                <Input v-bind="componentField" placeholder="e.g., Fall 2024 Billing" :disabled="isSubmitting" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="grid gap-6 md:grid-cols-3">
                        <FormField v-slot="{ componentField }" name="start_date">
                            <FormItem>
                                <FormLabel>Start Date</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="date" :disabled="!isDraft || isSubmitting" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="end_date">
                            <FormItem>
                                <FormLabel>End Date</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="date" :disabled="!isDraft || isSubmitting" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="due_date">
                            <FormItem>
                                <FormLabel>Due Date</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="date" :disabled="!isDraft || isSubmitting" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <div class="flex justify-end gap-4">
                        <Button type="button" variant="outline" as-child>
                            <Link :href="route('billing-cycles.index')">Cancel</Link>
                        </Button>
                        <Button type="submit" :disabled="isSubmitting">
                            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                            Save Changes
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </form>

        <Card v-else>
            <CardHeader>
                <CardTitle>Billing Cycle Details</CardTitle>
                <CardDescription>Review the final configuration of this billing cycle.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Semester</p>
                        <p class="text-sm font-semibold">{{ semesterName }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Name</p>
                        <p class="text-sm font-semibold">{{ billingCycle.name }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Start Date</p>
                        <p class="text-sm font-semibold">{{ formatDateForInput(billingCycle.start_date) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">End Date</p>
                        <p class="text-sm font-semibold">{{ formatDateForInput(billingCycle.end_date) }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-muted-foreground">Due Date</p>
                        <p class="text-sm font-semibold">{{ formatDateForInput(billingCycle.due_date) }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
