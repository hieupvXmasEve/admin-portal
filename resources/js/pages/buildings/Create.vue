<script setup lang="ts">
import type { Campus } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Building, Hash, MapPin, School, Text } from 'lucide-vue-next';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    campus: Campus;
}>();
console.log(props.campus);

// Validation schema
const createBuildingSchema = toTypedSchema(
    z.object({
        name: z.string().min(1, 'Building name is required').max(255),
        code: z.string().min(1, 'Building code is required').max(20),
        campus_id: z.string().min(1, 'Please select a campus.'),
        description: z.string().max(1000, 'Description must not exceed 1000 characters').optional().nullable(),
        address: z.string().max(1000, 'Address must not exceed 1000 characters').optional().nullable(),
    }),
);

// Inertia form for submission
const inertiaForm = useForm({
    name: '',
    code: '',
    campus_id: String(props.campus.id),
    description: '',
    address: '',
});

const onSubmit = (values: any) => {
    // Copy form data to Inertia form
    const formData = {
        ...values,
        campus_id: values.campus_id === 'none' ? null : Number(values.campus_id),
    };
    Object.assign(inertiaForm, formData);

    inertiaForm.post(systemRoutes.campuses.buildings.store(props.campus.id), {
        onSuccess: () => {
            // Success handled by redirect in controller
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
        },
    });
};

const goBack = () => {
    router.visit(systemRoutes.campuses.show(props.campus.id));
};
</script>

<template>
    <Head title="Create Building" />
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Create New Building</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Add a new building to a campus.</p>
        </div>
        <Button variant="outline" size="sm" @click="goBack" class="gap-2">
            <ArrowLeft class="h-4 w-4" />
            Back to Buildings
        </Button>
    </div>

    <div class="mt-6 max-w-2xl">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Building class="h-4 w-4" />
                    Building Information
                </CardTitle>
                <CardDescription> Enter the details for the new building. All fields marked with * are required. </CardDescription>
            </CardHeader>
            <CardContent>
                <Form :validation-schema="createBuildingSchema" :initial-values="{ campus_id: String(props.campus.id) }" @submit="onSubmit" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Building Name -->
                        <FormField v-slot="{ componentField }" name="name">
                            <FormItem>
                                <FormLabel class="flex items-center gap-2">
                                    <Building class="h-4 w-4" />
                                    Building Name *
                                </FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="e.g., FPT Tower" :disabled="inertiaForm.processing" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <!-- Building Code -->
                        <FormField v-slot="{ componentField }" name="code">
                            <FormItem>
                                <FormLabel class="flex items-center gap-2">
                                    <Hash class="h-4 w-4" />
                                    Building Code *
                                </FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="e.g., FPT-HANOI-01" class="font-mono" :disabled="inertiaForm.processing" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <!-- Campus Selection -->
                    <FormField v-slot="{ componentField }" name="campus_id">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <School class="h-4 w-4" />
                                Campus *
                            </FormLabel>
                            <Select v-bind="componentField">
                                <FormControl>
                                    <SelectTrigger :disabled="true">
                                        <SelectValue placeholder="Select a campus" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem :value="String(props.campus.id)">
                                        {{ props.campus.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Building Description -->
                    <FormField v-slot="{ componentField }" name="description">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Text class="h-4 w-4" />
                                Description
                            </FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="Enter a brief description of the building (optional)..." rows="3" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormDescription> A short summary of the building's purpose or features. </FormDescription>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Building Address -->
                    <FormField v-slot="{ componentField }" name="address">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <MapPin class="h-4 w-4" />
                                Address
                            </FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" placeholder="Enter the building address (optional)..." rows="3" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="flex items-center justify-end gap-4">
                        <Button type="button" variant="outline" @click="goBack" :disabled="inertiaForm.processing"> Cancel </Button>
                        <Button type="submit" :disabled="inertiaForm.processing" class="gap-2">
                            <Building class="h-4 w-4" />
                            {{ inertiaForm.processing ? 'Creating...' : 'Create Building' }}
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
