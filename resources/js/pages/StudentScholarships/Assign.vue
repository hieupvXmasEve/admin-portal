<script setup lang="ts">
import { Head, Link, router, useForm as useInertiaForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { AlertCircle, Calendar, DollarSign, Hash, Loader2 } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    studentScholarshipAssignmentSchema,
    type StudentScholarshipAssignmentData,
} from '@/schemas/scholarship';

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
    scholarships: Scholarship[];
}

const props = defineProps<Props>();

// Validation schema
const validationSchema = toTypedSchema(studentScholarshipAssignmentSchema);

// Initial form values
const initialValues: StudentScholarshipAssignmentData = {
    student_identifier: '',
    scholarship_code: '',
    awarded_at: new Date().toISOString().split('T')[0],
    notes: '',
};

// vee-validate form
const { handleSubmit, values } = useForm({
    validationSchema,
    initialValues,
});

// Inertia form for submission
const inertiaForm = useInertiaForm(initialValues);

const selectedScholarship = ref<Scholarship | null>(null);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatAmount = (amount: number, type: string) => {
    if (type === 'percentage') {
        return `${amount}%`;
    }
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount);
};

const isScholarshipExpired = (scholarship: Scholarship): boolean => {
    return new Date() > new Date(scholarship.valid_until);
};

const isScholarshipValid = (scholarship: Scholarship): boolean => {
    const now = new Date();
    return (
        scholarship.is_active &&
        now >= new Date(scholarship.valid_from) &&
        now <= new Date(scholarship.valid_until)
    );
};

const activeScholarships = computed(() => {
    return props.scholarships.filter((s) => s.is_active && !isScholarshipExpired(s));
});

watch(
    () => values.scholarship_code,
    (code) => {
        selectedScholarship.value = props.scholarships.find((s) => s.code === code) || null;
    }
);

// Form submission handler
const onSubmit = handleSubmit((formValues) => {
    // Prepare data for submission
    const submitData = {
        ...formValues,
        notes: formValues.notes || null,
    };

    // Assign data to Inertia form
    Object.assign(inertiaForm, submitData);

    // Submit to server
    inertiaForm.post(route('student-scholarships.store'), {
        onSuccess: () => {
            toast.success('Scholarship assigned successfully!');
            router.visit(route('student-scholarships.index'));
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
            const firstErrorKey = Object.keys(errors)[0];
            const firstError = errors[firstErrorKey];

            if (firstError) {
                toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
            } else {
                toast.error('Failed to assign scholarship. Please check the form for errors.');
            }
        },
    });
});
</script>

<template>
    <Head title="Assign Scholarship" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Assign Scholarship</h2>
            <p class="text-muted-foreground mt-1 text-sm">Assign a scholarship to a student</p>
        </div>
        <Button variant="outline" as-child>
            <Link :href="route('student-scholarships.index')">Back to List</Link>
        </Button>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <!-- Form -->
        <div class="lg:col-span-2">
            <Card>
                <CardHeader>
                    <CardTitle>Assignment Details</CardTitle>
                    <CardDescription>Select a student and scholarship to create an assignment</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="onSubmit" class="space-y-6">
                        <!-- Student Selection -->
                        <FormField v-slot="{ componentField }" name="student_identifier">
                            <FormItem>
                                <FormLabel>Student Email or ID *</FormLabel>
                                <FormControl>
                                    <Input
                                        v-bind="componentField"
                                        placeholder="e.g., student@example.com or S12345"
                                        :disabled="inertiaForm.processing"
                                    />
                                </FormControl>
                                <p class="text-muted-foreground text-xs">
                                    Enter student email or student ID
                                </p>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <!-- Scholarship Selection -->
                        <FormField v-slot="{ componentField }" name="scholarship_code">
                            <FormItem>
                                <FormLabel>Scholarship *</FormLabel>
                                <Select v-bind="componentField" :disabled="inertiaForm.processing">
                                    <FormControl>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select a scholarship" />
                                        </SelectTrigger>
                                    </FormControl>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="scholarship in activeScholarships"
                                            :key="scholarship.id"
                                            :value="scholarship.code"
                                        >
                                            {{ scholarship.code }} - {{ scholarship.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <!-- Awarded Date -->
                        <FormField v-slot="{ componentField }" name="awarded_at">
                            <FormItem>
                                <FormLabel>Awarded Date</FormLabel>
                                <FormControl>
                                    <Input
                                        v-bind="componentField"
                                        type="date"
                                        :disabled="inertiaForm.processing"
                                    />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <!-- Notes -->
                        <FormField v-slot="{ componentField }" name="notes">
                            <FormItem>
                                <FormLabel>Notes</FormLabel>
                                <FormControl>
                                    <Textarea
                                        v-bind="componentField"
                                        placeholder="Optional notes about this assignment..."
                                        rows="3"
                                        :disabled="inertiaForm.processing"
                                    />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <!-- Submit Button -->
                        <div class="flex justify-end gap-2">
                            <Button type="button" variant="outline" as-child :disabled="inertiaForm.processing">
                                <Link :href="route('student-scholarships.index')">Cancel</Link>
                            </Button>
                            <Button type="submit" :disabled="inertiaForm.processing">
                                <Loader2 v-if="inertiaForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                                Assign Scholarship
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Scholarship Preview -->
        <div>
            <Card v-if="selectedScholarship">
                <CardHeader>
                    <CardTitle>Scholarship Details</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-start gap-3">
                        <Hash class="text-muted-foreground mt-0.5 h-5 w-5" />
                        <div class="flex-1">
                            <p class="text-muted-foreground text-sm">Code</p>
                            <p class="font-mono font-medium">{{ selectedScholarship.code }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <DollarSign class="text-muted-foreground mt-0.5 h-5 w-5" />
                        <div class="flex-1">
                            <p class="text-muted-foreground text-sm">Name</p>
                            <p class="font-medium">{{ selectedScholarship.name }}</p>
                        </div>
                    </div>

                    <div v-if="selectedScholarship.description" class="flex items-start gap-3">
                        <div class="text-muted-foreground mt-0.5 h-5 w-5"></div>
                        <div class="flex-1">
                            <p class="text-muted-foreground text-sm">Description</p>
                            <p class="text-sm">{{ selectedScholarship.description }}</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <DollarSign class="text-muted-foreground mt-0.5 h-5 w-5" />
                        <div class="flex-1">
                            <p class="text-muted-foreground text-sm">Discount Type</p>
                            <Badge variant="outline">
                                {{ selectedScholarship.type === 'percentage' ? 'Percentage' : 'Fixed Amount' }}
                            </Badge>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <DollarSign class="text-muted-foreground mt-0.5 h-5 w-5" />
                        <div class="flex-1">
                            <p class="text-muted-foreground text-sm">Discount Amount</p>
                            <p class="text-lg font-semibold">
                                {{ formatAmount(selectedScholarship.amount, selectedScholarship.type) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <Calendar class="text-muted-foreground mt-0.5 h-5 w-5" />
                        <div class="flex-1">
                            <p class="text-muted-foreground text-sm">Valid Period</p>
                            <p class="text-sm">{{ formatDate(selectedScholarship.valid_from) }}</p>
                            <p class="text-muted-foreground text-xs">to {{ formatDate(selectedScholarship.valid_until) }}</p>
                        </div>
                    </div>

                    <Alert v-if="!isScholarshipValid(selectedScholarship)" variant="destructive">
                        <AlertCircle class="h-4 w-4" />
                        <AlertDescription>
                            This scholarship is not currently valid. It may be expired or not yet active.
                        </AlertDescription>
                    </Alert>
                </CardContent>
            </Card>

            <Card v-else>
                <CardContent class="text-muted-foreground py-8 text-center">
                    <p class="text-sm">Select a scholarship to view details</p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
