<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Campus, Unit } from '@/types/models';
import { Head, router, useForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, BookOpen } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Module {
    id: number;
    code: string;
    name: string;
}

interface Props {
    campuses: Campus[];
    modules: Module[];
    units: Unit[];
}

defineProps<Props>();

const createModuleSchema = toTypedSchema(
    z.object({
        campus_id: z.number({ required_error: 'Campus is required' }),
        code: z.string().min(1, 'Module code is required').max(20, 'Code must not exceed 20 characters'),
        name: z.string().min(1, 'Module name is required').max(255, 'Name must not exceed 255 characters'),
        description: z.string().optional(),
        grading_type: z.enum(['grade', 'pass_fail'], { required_error: 'Grading type is required' }),
        prerequisite_module_id: z.number().optional().nullable(),
    }),
);

const inertiaForm = useForm({
    campus_id: null as number | null,
    code: '',
    name: '',
    description: '',
    grading_type: 'grade' as 'grade' | 'pass_fail',
    prerequisite_module_id: null as number | null,
    units: [] as any[],
});

const onSubmit = (values: any) => {
    Object.assign(inertiaForm, values);

    inertiaForm.post(route('admin.modules.store'), {
        onSuccess: () => {
            toast.success('Module created successfully');
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
            toast.error('Failed to create module');
        },
    });
};

const goBack = () => {
    router.visit(route('admin.modules.index'));
};
</script>

<template>
    <Head title="Create Module" />

    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Create New Module</h2>
                <p class="text-muted-foreground mt-1">Add a new academic module to the system</p>
            </div>
            <Button variant="outline" size="sm" @click="goBack">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Back to Modules
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <BookOpen class="h-5 w-5" />
                    Module Information
                </CardTitle>
                <CardDescription>Enter the basic information for the new module</CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-slot="{ meta }"
                    :validation-schema="createModuleSchema"
                    :initial-values="{
                        campus_id: null,
                        code: '',
                        name: '',
                        description: '',
                        grading_type: 'grade',
                        prerequisite_module_id: null,
                    }"
                    class="space-y-6"
                    @submit="onSubmit"
                >
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Campus -->
                        <FormField v-slot="{ componentField }" name="campus_id">
                            <FormItem>
                                <FormLabel>Campus *</FormLabel>
                                <FormControl>
                                    <Select
                                        v-bind="componentField"
                                        :model-value="componentField.modelValue?.toString()"
                                        @update:model-value="
                                            (val) => {
                                                const handler = componentField['onUpdate:modelValue'];
                                                if (handler && typeof val === 'string') {
                                                    handler(val ? parseInt(val, 10) : null);
                                                } else if (handler) {
                                                    handler(null);
                                                }
                                            }
                                        "
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select campus" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="campus in campuses" :key="campus.id" :value="campus.id.toString()">
                                                {{ campus.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <!-- Module Code -->
                        <FormField v-slot="{ componentField }" name="code">
                            <FormItem>
                                <FormLabel>Module Code *</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="e.g., SW1" :disabled="inertiaForm.processing" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <!-- Module Name -->
                        <FormField v-slot="{ componentField }" name="name" class="md:col-span-2">
                            <FormItem>
                                <FormLabel>Module Name *</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="e.g., Software 1" :disabled="inertiaForm.processing" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <!-- Grading Type -->
                        <FormField v-slot="{ componentField }" name="grading_type">
                            <FormItem>
                                <FormLabel>Grading Type *</FormLabel>
                                <FormControl>
                                    <Select v-bind="componentField">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="grade">Graded (0-5)</SelectItem>
                                            <SelectItem value="pass_fail">Pass/Fail</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <!-- Prerequisite Module -->
                        <FormField v-slot="{ componentField }" name="prerequisite_module_id">
                            <FormItem>
                                <FormLabel>Prerequisite Module</FormLabel>
                                <FormControl>
                                    <Select
                                        v-bind="componentField"
                                        :model-value="componentField.modelValue ? componentField.modelValue.toString() : 'none'"
                                        @update:model-value="
                                            (val) => {
                                                const handler = componentField['onUpdate:modelValue'];
                                                if (handler && typeof val === 'string') {
                                                    handler(val === 'none' ? null : val ? parseInt(val, 10) : null);
                                                } else if (handler) {
                                                    handler(null);
                                                }
                                            }
                                        "
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="None" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">None</SelectItem>
                                            <SelectItem v-for="module in modules" :key="module.id" :value="module.id.toString()"> {{ module.code }} - {{ module.name }} </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <!-- Description -->
                        <FormField v-slot="{ componentField }" name="description" class="md:col-span-2">
                            <FormItem>
                                <FormLabel>Description</FormLabel>
                                <FormControl>
                                    <Textarea v-bind="componentField" placeholder="Module description..." :disabled="inertiaForm.processing" rows="3" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <Button type="button" variant="outline" @click="goBack" :disabled="inertiaForm.processing"> Cancel </Button>
                        <Button type="submit" :disabled="inertiaForm.processing || !meta.valid">
                            {{ inertiaForm.processing ? 'Creating...' : 'Create Module' }}
                        </Button>
                    </div>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Note</CardTitle>
                <CardDescription> After creating the module, you can add units to it from the module details page. </CardDescription>
            </CardHeader>
        </Card>
    </div>
</template>
