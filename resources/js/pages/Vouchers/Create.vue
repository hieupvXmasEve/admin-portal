<script setup lang="ts">
import { Head, Link, router, useForm as useInertiaForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { Loader2 } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { getVoucherTypeOptions, getDiscountTypeOptions, voucherFormSchema, type VoucherFormData } from '@/schemas/voucher';
import { ref, watch } from 'vue';
import { generateCodeFromName } from '@/utils/string';

// Validation schema
const validationSchema = toTypedSchema(voucherFormSchema);

// Initial form values
const initialValues: VoucherFormData = {
    code: '',
    name: '',
    description: '',
    voucher_type: 'informational',
    discount_type: null,
    discount_value: null,
    valid_from: '',
    valid_until: '',
    is_active: true,
};

// vee-validate form
const { handleSubmit, values, setFieldValue } = useForm({
    validationSchema,
    initialValues,
});

// Inertia form for submission
const inertiaForm = useInertiaForm(initialValues);

// Options
const voucherTypeOptions = getVoucherTypeOptions();
const discountTypeOptions = getDiscountTypeOptions();

// Track if code has been manually edited
const codeManuallyEdited = ref(false);

// Auto-generate code from name
watch(
    () => values.name,
    (newName) => {
        if (!codeManuallyEdited.value && newName) {
            const generatedCode = generateCodeFromName(newName);
            setFieldValue('code', generatedCode);
        }
    },
);

// Reset discount fields when voucher type changes
watch(
    () => values.voucher_type,
    (newType) => {
        if (newType === 'informational') {
            setFieldValue('discount_type', null);
            setFieldValue('discount_value', null);
        } else if (newType === 'discount' && !values.discount_type) {
            setFieldValue('discount_type', 'fixed_amount');
            setFieldValue('discount_value', 0);
        }
    },
);

// Mark code as manually edited when user types in it
const handleCodeInput = () => {
    codeManuallyEdited.value = true;
};

// Form submission handler
const onSubmit = handleSubmit((formValues) => {
    const submitData = {
        ...formValues,
        code: formValues.code.toUpperCase(),
        description: formValues.description || null,
        discount_type: formValues.voucher_type === 'discount' ? formValues.discount_type : null,
        discount_value: formValues.voucher_type === 'discount' ? formValues.discount_value : null,
    };

    Object.assign(inertiaForm, submitData);

    inertiaForm.post(route('vouchers.store'), {
        onSuccess: () => {
            toast.success('Voucher created successfully!');
            router.visit(route('vouchers.index'));
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
            const firstErrorKey = Object.keys(errors)[0];
            const firstError = errors[firstErrorKey];

            if (firstError) {
                toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
            } else {
                toast.error('Failed to create voucher. Please check the form for errors.');
            }
        },
    });
});
</script>

<template>
    <Head title="Create Voucher" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Create Voucher</h2>
            <p class="text-muted-foreground mt-1 text-sm">Define a new voucher with type and validity period</p>
        </div>
        <Button variant="outline" as-child class="gap-2">
            <Link :href="route('vouchers.index')">Back to Vouchers</Link>
        </Button>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Voucher Details</CardTitle>
            <CardDescription>Provide the voucher information including code, type, and validity period</CardDescription>
        </CardHeader>
        <CardContent>
            <form class="space-y-6" @submit.prevent="onSubmit">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Code -->
                    <FormField v-slot="{ componentField }" name="code">
                        <FormItem>
                            <FormLabel>Voucher Code *</FormLabel>
                            <FormControl>
                                <Input
                                    v-bind="componentField"
                                    placeholder="e.g., WELCOME2024"
                                    class="font-mono"
                                    disabled
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
                            <FormLabel>Voucher Name *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., Welcome Voucher 2024" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Description -->
                    <FormField v-slot="{ componentField }" name="description" class="md:col-span-2">
                        <FormItem>
                            <FormLabel>Description</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" rows="3" placeholder="Describe the voucher purpose and usage" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Voucher Type -->
                    <FormField v-slot="{ componentField }" name="voucher_type">
                        <FormItem>
                            <FormLabel>Voucher Type *</FormLabel>
                            <Select v-bind="componentField" :disabled="inertiaForm.processing">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem v-for="option in voucherTypeOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-muted-foreground text-xs">
                                Informational vouchers display info only; Discount vouchers apply discounts
                            </p>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Discount Type (only for discount vouchers) -->
                    <FormField v-if="values.voucher_type === 'discount'" v-slot="{ componentField }" name="discount_type">
                        <FormItem>
                            <FormLabel>Discount Type *</FormLabel>
                            <Select v-bind="componentField" :disabled="inertiaForm.processing">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select discount type" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem v-for="option in discountTypeOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Discount Value (only for discount vouchers) -->
                    <FormField v-if="values.voucher_type === 'discount'" v-slot="{ componentField }" name="discount_value">
                        <FormItem>
                            <FormLabel>
                                {{ values.discount_type === 'percentage' ? 'Discount Percentage *' : 'Discount Amount (VND) *' }}
                            </FormLabel>
                            <FormControl>
                                <Input
                                    v-bind="componentField"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    :placeholder="values.discount_type === 'percentage' ? 'e.g., 50' : 'e.g., 500000'"
                                    :disabled="inertiaForm.processing"
                                    @input="(e: Event) => componentField['onInput'](parseFloat(((e.target as HTMLInputElement).value)) || 0)"
                                />
                            </FormControl>
                            <p class="text-muted-foreground text-xs">
                                {{ values.discount_type === 'percentage' ? 'Enter percentage value (e.g., 50 for 50%)' : 'Enter amount in Vietnamese Dong' }}
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
                            <FormLabel class="cursor-pointer font-normal">Active (voucher can be redeemed by students)</FormLabel>
                        </FormItem>
                    </FormField>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" as-child :disabled="inertiaForm.processing">
                        <Link :href="route('vouchers.index')">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="inertiaForm.processing">
                        <Loader2 v-if="inertiaForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Create Voucher
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
