<script setup lang="ts">
import type { Campus } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Building, Hash, MapPin } from 'lucide-vue-next';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'vue-sonner';

interface Props {
    campus: Campus;
}

const props = defineProps<Props>();

// Validation schema
const updateCampusSchema = toTypedSchema(
    z.object({
        name: z.string().min(1, 'Campus name is required').max(255, 'Campus name must not exceed 255 characters'),
        code: z.string().min(1, 'Campus code is required').max(255, 'Campus code must not exceed 255 characters'),
        address: z.string().min(1, 'Campus address is required'),
    }),
);

// Inertia form for submission
const inertiaForm = useForm({
    name: props.campus.name,
    code: props.campus.code,
    address: props.campus.address,
});

const onSubmit = (values: any) => {
    Object.assign(inertiaForm, values);

    inertiaForm.put(route('campuses.update', props.campus.id), {
        onSuccess: () => {
            toast.success('Campus updated successfully');
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
        },
    });
};

const goBack = () => {
    router.visit(systemRoutes.campuses.index());
};
</script>

<template>
    <Head title="Edit Campus" />
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Edit Campus</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Update campus location details.</p>
        </div>
        <Button variant="outline" size="sm" @click="goBack" class="gap-2">
            <ArrowLeft class="h-4 w-4" />
            Back to Campuses
        </Button>
    </div>

    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Building class="h-5 w-5" />
                Campus Information
            </CardTitle>
            <CardDescription> Update the information for this campus location. </CardDescription>
        </CardHeader>
        <CardContent>
            <Form v-slot="{ meta }" :validation-schema="updateCampusSchema" :initial-values="campus" class="space-y-6" @submit="onSubmit">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Campus Name -->
                    <FormField v-slot="{ componentField }" name="name">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Building class="h-4 w-4" />
                                Campus Name *
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., Swinburne Hanoi" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Campus Code -->
                    <FormField v-slot="{ componentField }" name="code">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Hash class="h-4 w-4" />
                                Campus Code *
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., HN, HCM, DN" class="font-mono" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Campus Address -->
                <FormField v-slot="{ componentField }" name="address">
                    <FormItem>
                        <FormLabel class="flex items-center gap-2">
                            <MapPin class="h-4 w-4" />
                            Campus Address *
                        </FormLabel>
                        <FormControl>
                            <Textarea
                                v-bind="componentField"
                                placeholder="Enter the full address of the campus..."
                                rows="3"
                                :disabled="inertiaForm.processing"
                            />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <div class="flex items-center justify-end gap-4">
                    <Button type="button" variant="outline" @click="goBack" :disabled="inertiaForm.processing"> Cancel </Button>
                    <Button type="submit" :disabled="!meta.valid || inertiaForm.processing" class="gap-2">
                        <Building class="h-4 w-4" />
                        {{ inertiaForm.processing ? 'Updating...' : 'Update Campus' }}
                    </Button>
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
