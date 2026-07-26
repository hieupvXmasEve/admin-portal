<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Club } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Building, Calendar, Edit, Hash, Mail, Phone, Users } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    club: Club;
}

const props = defineProps<Props>();

// Validation schema
const updateClubSchema = toTypedSchema(
    z.object({
        name: z.string().min(1, 'Club name is required').max(255, 'Club name must not exceed 255 characters'),
        description: z.string().optional(),
        founded_date: z.string().optional(),
        contact_email: z.string().email('Invalid email format').optional().or(z.literal('')),
        contact_phone: z.string().optional(),
        status: z.enum(['active', 'inactive']),
    }),
);

// Inertia form for submission
const inertiaForm = useForm({
    name: props.club.name,
    description: props.club.description || '',
    founded_date: props.club.founded_date || '',
    contact_email: props.club.contact_email || '',
    contact_phone: props.club.contact_phone || '',
    status: props.club.status,
});

const onSubmit = (values: any) => {
    // Copy form data to Inertia form
    Object.assign(inertiaForm, values);

    inertiaForm.put(systemRoutes.clubs.update(props.club.id), {
        onSuccess: () => {
            toast.success('Club updated successfully');
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
        },
    });
};

const goBack = () => {
    router.visit(systemRoutes.clubs.show(props.club.id));
};
console.log(props.club);
// const goToIndex = () => {
//     router.visit(systemRoutes.clubs.index());
// };
</script>

<template>
    <Head :title="`Edit ${club.name}`" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Edit Club: {{ club.name }}</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Update club information and settings.</p>
        </div>
        <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" @click="goBack" class="gap-2">
                <ArrowLeft class="h-4 w-4" />
                Back to Club
            </Button>
        </div>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Edit class="h-5 w-5" />
                Club Information
            </CardTitle>
            <CardDescription> Update the club's basic information and settings. </CardDescription>
        </CardHeader>
        <CardContent>
            <Form
                v-slot="{ meta }"
                :validation-schema="updateClubSchema"
                :initial-values="{
                    name: club.name,
                    description: club.description || '',
                    founded_date: club.founded_date || '',
                    contact_email: club.contact_email || '',
                    contact_phone: club.contact_phone || '',
                    status: club.status,
                }"
                class="space-y-6"
                @submit="onSubmit"
            >
                <!-- Club Name -->
                <FormField v-slot="{ componentField }" name="name">
                    <FormItem>
                        <FormLabel class="flex items-center gap-2">
                            <Hash class="h-4 w-4" />
                            Club Name *
                        </FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" placeholder="e.g., Computer Science Club" :disabled="inertiaForm.processing" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <!-- Campus (Read-only) -->
                <div class="bg-muted/50 rounded-lg border p-4">
                    <div class="flex items-center gap-2 text-sm">
                        <Building class="text-muted-foreground h-4 w-4" />
                        <span class="text-muted-foreground font-medium">Campus:</span>
                        <span class="font-semibold">{{ club.campus?.name }}</span>
                    </div>
                    <p class="text-muted-foreground mt-1 text-xs">Campus cannot be changed after club creation</p>
                </div>

                <!-- Description -->
                <FormField v-slot="{ componentField }" name="description">
                    <FormItem>
                        <FormLabel>Club Description</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Describe the club's purpose, activities, and goals..." rows="4" :disabled="inertiaForm.processing" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Founded Date -->
                    <FormField v-slot="{ componentField }" name="founded_date">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Calendar class="h-4 w-4" />
                                Founded Date
                            </FormLabel>
                            <FormControl>
                                <DatePicker v-bind="componentField" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Status -->
                    <FormField v-slot="{ componentField }" name="status">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Users class="h-4 w-4" />
                                Club Status *
                            </FormLabel>
                            <FormControl>
                                <Select v-bind="componentField" :disabled="inertiaForm.processing">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Contact Email -->
                    <FormField v-slot="{ componentField }" name="contact_email">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Mail class="h-4 w-4" />
                                Contact Email
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="email" placeholder="club@example.com" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Contact Phone -->
                    <FormField v-slot="{ componentField }" name="contact_phone">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Phone class="h-4 w-4" />
                                Contact Phone
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="+84 123 456 789" :disabled="inertiaForm.processing" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <div class="flex items-center justify-end gap-4">
                    <Button type="button" variant="outline" @click="goBack" :disabled="inertiaForm.processing"> Cancel </Button>
                    <Button type="submit" :disabled="!meta.valid || inertiaForm.processing" class="gap-2">
                        <Edit class="h-4 w-4" />
                        {{ inertiaForm.processing ? 'Updating...' : 'Update Club' }}
                    </Button>
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
