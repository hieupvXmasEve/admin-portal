<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FileText } from 'lucide-vue-next';
import { route } from 'ziggy-js';

interface BillingCycle {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    due_date: string;
    status: 'draft' | 'active' | 'closed';
    semester: {
        id: number;
        name: string;
    };
}

interface Props {
    billingCycles: BillingCycle[];
}

defineProps<Props>();

const form = useForm({
    billing_cycle_id: null as number | null,
});

const submit = () => {
    form.post(route('invoices.generate'), {
        preserveScroll: true,
    });
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Generate Invoices" />

    <div class="space-y-6">
        <div class="flex items-center gap-4">
            <Link :href="route('invoices.index')">
                <Button variant="ghost" size="icon">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
            </Link>
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Generate Invoices</h1>
                <p class="text-muted-foreground">Generate invoices for all enrolled students in a billing cycle</p>
            </div>
        </div>

        <Card class="max-w-2xl">
            <CardHeader>
                <CardTitle>Select Billing Cycle</CardTitle>
                <CardDescription>Choose an active billing cycle to generate invoices for all enrolled students</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="space-y-2">
                        <Label for="billing_cycle_id">Billing Cycle *</Label>
                        <Select v-model="form.billing_cycle_id" required>
                            <SelectTrigger id="billing_cycle_id">
                                <SelectValue placeholder="Select a billing cycle" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="cycle in billingCycles" :key="cycle.id" :value="cycle.id">
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ cycle.name }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            {{ cycle.semester.name }} • Due: {{ formatDate(cycle.due_date) }}
                                        </span>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.billing_cycle_id" class="text-sm text-destructive">
                            {{ form.errors.billing_cycle_id }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Only active billing cycles are shown. Invoices will be generated for all students enrolled in the selected semester.
                        </p>
                    </div>

                    <div v-if="billingCycles.length === 0" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                        <p class="text-sm text-yellow-800">
                            No active billing cycles found. Please create and activate a billing cycle first.
                        </p>
                    </div>

                    <div class="flex gap-4">
                        <Button type="submit" :disabled="form.processing || !form.billing_cycle_id">
                            <FileText class="mr-2 h-4 w-4" />
                            Generate Invoices
                        </Button>
                        <Link :href="route('invoices.index')">
                            <Button type="button" variant="outline">Cancel</Button>
                        </Link>
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card class="max-w-2xl">
            <CardHeader>
                <CardTitle>How Invoice Generation Works</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm text-muted-foreground">
                <p>When you generate invoices for a billing cycle:</p>
                <ul class="list-disc space-y-1 pl-6">
                    <li>The system will find all students enrolled in the semester associated with the billing cycle</li>
                    <li>For each student, an invoice will be created with their tuition fees based on their tuition plan</li>
                    <li>Any active scholarships assigned to the student will be automatically applied as discounts</li>
                    <li>Invoices will be set to "pending" status with the due date from the billing cycle</li>
                    <li>If an invoice already exists for a student in this billing cycle, it will be skipped</li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
