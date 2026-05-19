<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { calculateFixedScholarshipAmount, getScholarshipTypeOptions, type ScholarshipFormData } from '@/schemas/scholarship';
import { generateCodeFromName } from '@/utils/string';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { route } from 'ziggy-js';

const form = useForm<ScholarshipFormData>({
    code: '',
    name: '',
    description: '',
    type: 'fixed_amount',
    amount: 0,
    total_amount: null,
    total_terms: null,
    valid_from: '',
    valid_until: '',
    is_active: true,
});

const scholarshipTypeOptions = getScholarshipTypeOptions();
const codeManuallyEdited = ref(false);
const isFixedAmount = computed(() => form.type === 'fixed_amount');
const calculatedFixedAmount = computed(() => calculateFixedScholarshipAmount(form.total_amount, form.total_terms));
const formattedCalculatedAmount = computed(() => formatCurrency(calculatedFixedAmount.value));

const formatCurrency = (amount: number): string =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(amount);

const parseOptionalNumberInput = (event: Event): number | null => {
    const value = (event.target as HTMLInputElement).value;

    if (value === '') {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
};

const parseRequiredNumberInput = (event: Event): number => {
    const value = (event.target as HTMLInputElement).value;

    if (value === '') {
        return 0;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : 0;
};

const syncFixedAmount = () => {
    if (isFixedAmount.value) {
        form.amount = calculatedFixedAmount.value;
    }
};

watch(
    () => form.name,
    (newName) => {
        if (!codeManuallyEdited.value && newName) {
            form.code = generateCodeFromName(newName);
        }
    },
);

watch(
    () => [form.type, form.total_amount, form.total_terms],
    () => {
        syncFixedAmount();

        if (!isFixedAmount.value) {
            form.total_amount = null;
            form.total_terms = null;
        }
    },
    { immediate: true },
);

const handleAmountInput = (event: Event) => {
    if (!isFixedAmount.value) {
        form.amount = parseRequiredNumberInput(event);
    }
};

const submit = () => {
    syncFixedAmount();

    form.transform((data) => ({
        ...data,
        code: data.code.toUpperCase(),
        description: data.description || null,
        amount: data.type === 'fixed_amount' ? calculateFixedScholarshipAmount(data.total_amount, data.total_terms) : data.amount,
        total_amount: data.type === 'fixed_amount' ? data.total_amount : null,
        total_terms: data.type === 'fixed_amount' ? data.total_terms : null,
    })).post(route('scholarships.store'), {
        onSuccess: () => {
            router.visit(route('scholarships.index'));
        },
    });
};
</script>

<template>
    <Head title="Create Scholarship" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Create Scholarship</h2>
            <p class="text-muted-foreground mt-1 text-sm">Define a new scholarship with discount details and validity period</p>
        </div>
        <Button variant="outline" as-child class="gap-2">
            <Link :href="route('scholarships.index')">Back to Scholarships</Link>
        </Button>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Scholarship Details</CardTitle>
            <CardDescription>Provide the scholarship information including code, amount, and validity period</CardDescription>
        </CardHeader>
        <CardContent>
            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="code">Scholarship Code *</Label>
                        <Input id="code" v-model="form.code" placeholder="e.g., MERIT2024" class="font-mono" :disabled="form.processing" :class="{ 'border-red-500': form.errors.code }" @input="codeManuallyEdited = true" />
                        <p class="text-muted-foreground text-xs">Auto-generated from name, or enter manually</p>
                        <InputError :message="form.errors.code" />
                    </div>

                    <div class="space-y-2">
                        <Label for="name">Scholarship Name *</Label>
                        <Input id="name" v-model="form.name" placeholder="e.g., Merit Scholarship 2024" :disabled="form.processing" :class="{ 'border-red-500': form.errors.name }" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <Label for="description">Description</Label>
                        <Textarea id="description" v-model="form.description" rows="3" placeholder="Describe the scholarship purpose and eligibility" :disabled="form.processing" :class="{ 'border-red-500': form.errors.description }" />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="space-y-2">
                        <Label for="type">Discount Type *</Label>
                        <Select v-model="form.type" :disabled="form.processing">
                            <SelectTrigger id="type" :class="{ 'border-red-500': form.errors.type }">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in scholarshipTypeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div v-if="isFixedAmount" class="space-y-2">
                        <Label for="total_amount">Total Amount (VND) *</Label>
                        <Input
                            id="total_amount"
                            :model-value="form.total_amount ?? ''"
                            type="number"
                            placeholder="e.g., 175000000"
                            :disabled="form.processing"
                            :class="{ 'border-red-500': form.errors.total_amount }"
                            @input="form.total_amount = parseOptionalNumberInput($event)"
                        />
                        <InputError :message="form.errors.total_amount" />
                    </div>

                    <div v-if="isFixedAmount" class="space-y-2">
                        <Label for="total_terms">Total Terms *</Label>
                        <Input
                            id="total_terms"
                            :model-value="form.total_terms ?? ''"
                            type="number"
                            step="1"
                            min="1"
                            placeholder="e.g., 9"
                            :disabled="form.processing"
                            :class="{ 'border-red-500': form.errors.total_terms }"
                            @input="form.total_terms = parseOptionalNumberInput($event)"
                        />
                        <InputError :message="form.errors.total_terms" />
                    </div>

                    <div class="space-y-2" :class="{ 'md:col-span-2': !isFixedAmount }">
                        <Label for="amount">
                            {{ isFixedAmount ? 'Calculated Discount Amount (VND) *' : 'Discount Percentage *' }}
                        </Label>
                        <Input
                            id="amount"
                            :model-value="form.amount"
                            type="number"
                            :step="isFixedAmount ? 1000 : 0.01"
                            min="0.01"
                            :readonly="isFixedAmount"
                            :placeholder="isFixedAmount ? 'Auto calculated from total amount and terms' : 'e.g., 50'"
                            :disabled="form.processing"
                            :class="{ 'border-red-500': form.errors.amount, 'bg-muted': isFixedAmount }"
                            @input="handleAmountInput"
                        />
                        <p class="text-muted-foreground text-xs">
                            {{ isFixedAmount ? `Preview: ${formattedCalculatedAmount} = total amount / total terms, rounded up to 1,000 VND` : 'Enter percentage value (e.g., 50 for 50%)' }}
                        </p>
                        <InputError :message="form.errors.amount" />
                    </div>

                    <div class="space-y-2">
                        <Label for="valid_from">Valid From *</Label>
                        <Input id="valid_from" v-model="form.valid_from" type="date" :disabled="form.processing" :class="{ 'border-red-500': form.errors.valid_from }" />
                        <InputError :message="form.errors.valid_from" />
                    </div>

                    <div class="space-y-2">
                        <Label for="valid_until">Valid Until *</Label>
                        <Input id="valid_until" v-model="form.valid_until" type="date" :disabled="form.processing" :class="{ 'border-red-500': form.errors.valid_until }" />
                        <InputError :message="form.errors.valid_until" />
                    </div>

                    <div class="flex flex-row items-center space-y-0 space-x-2 md:col-span-2">
                        <Checkbox :model-value="form.is_active" :disabled="form.processing" @update:model-value="form.is_active = Boolean($event)" />
                        <Label class="cursor-pointer font-normal">Active (scholarship can be assigned to students)</Label>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" as-child :disabled="form.processing">
                        <Link :href="route('scholarships.index')">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Create Scholarship
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
