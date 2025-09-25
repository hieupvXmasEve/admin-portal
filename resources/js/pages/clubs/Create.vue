<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Campus, Student } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ArrowLeft, Building, Calendar, Hash, Mail, Phone, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';
import { route } from 'ziggy-js';

interface Props {
    campuses: Campus[];
}

const props = defineProps<Props>();

// Student search state
const studentSearch = ref('');
const studentOptions = ref<Array<{ id: number; name: string; student_code: string }>>([]);
const isLoadingStudents = ref(false);
const selectedCampusId = ref<string>('');

// Validation schema
const createClubSchema = toTypedSchema(
    z.object({
        name: z.string().min(1, 'Club name is required').max(255, 'Club name must not exceed 255 characters'),
        description: z.string().optional(),
        campus_id: z.string().min(1, 'Campus is required'),
        founded_date: z.string().optional(),
        contact_email: z.string().email('Invalid email format').optional().or(z.literal('')),
        contact_phone: z.string().optional(),
        president_student_id: z.string().min(1, 'President is required'),
    }),
);

// Inertia form for submission
const inertiaForm = useForm({
    name: '',
    description: '',
    campus_id: '',
    founded_date: '',
    contact_email: '',
    contact_phone: '',
    president_student_id: '',
});

// Watch campus selection to load students
watch(selectedCampusId, async (newCampusId) => {
    if (newCampusId) {
        await loadStudentsForCampus(newCampusId);
    } else {
        studentOptions.value = [];
    }
});

const loadStudentsForCampus = async (campusId: string) => {
    if (!campusId) return;

    isLoadingStudents.value = true;
    try {
        const response = await fetch(systemRoutes.clubs.studentsForCampus() + `?campus_id=${campusId}`);
        const data = await response.json();

        if (data.success) {
            studentOptions.value = data.data;
        } else {
            toast.error('Failed to load students');
        }
    } catch (error) {
        console.error('Error loading students:', error);
        toast.error('Failed to load students');
    } finally {
        isLoadingStudents.value = false;
    }
};

const onSubmit = (values: any) => {
    // Copy form data to Inertia form
    Object.assign(inertiaForm, values);

    inertiaForm.post(systemRoutes.clubs.store(), {
        onSuccess: () => {
            toast.success('Club created successfully');
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
        },
    });
};

const goBack = () => {
    router.visit(systemRoutes.clubs.index());
};

const handleCampusChange = (value: string) => {
    selectedCampusId.value = value;
    // Reset president selection when campus changes
    inertiaForm.president_student_id = '';
};
</script>

<template>
    <Head title="Create Club" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl leading-tight font-semibold text-gray-800 dark:text-gray-200">Create New Club</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Add a new student club or organization.</p>
        </div>
        <Button variant="outline" size="sm" @click="goBack" class="gap-2">
            <ArrowLeft class="h-4 w-4" />
            Back to Clubs
        </Button>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Users class="h-5 w-5" />
                Club Information
            </CardTitle>
            <CardDescription>
                Enter the basic information for the new club and assign a president.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <Form
                v-slot="{ meta }"
                :validation-schema="createClubSchema"
                :initial-values="{
                    name: '',
                    description: '',
                    campus_id: '',
                    founded_date: '',
                    contact_email: '',
                    contact_phone: '',
                    president_student_id: '',
                }"
                class="space-y-6"
                @submit="onSubmit"
            >
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Club Name -->
                    <FormField v-slot="{ componentField }" name="name">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Hash class="h-4 w-4" />
                                Club Name *
                            </FormLabel>
                            <FormControl>
                                <Input
                                    v-bind="componentField"
                                    placeholder="e.g., Computer Science Club"
                                    :disabled="inertiaForm.processing"
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- Campus -->
                    <FormField v-slot="{ componentField }" name="campus_id">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Building class="h-4 w-4" />
                                Campus *
                            </FormLabel>
                            <FormControl>
                                <Select
                                    v-bind="componentField"
                                    :disabled="inertiaForm.processing"
                                    @update:model-value="handleCampusChange"
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select campus" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="campus in campuses"
                                            :key="campus.id"
                                            :value="campus.id.toString()"
                                        >
                                            {{ campus.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Description -->
                <FormField v-slot="{ componentField }" name="description">
                    <FormItem>
                        <FormLabel>Club Description</FormLabel>
                        <FormControl>
                            <Textarea
                                v-bind="componentField"
                                placeholder="Describe the club's purpose, activities, and goals..."
                                rows="4"
                                :disabled="inertiaForm.processing"
                            />
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
                                <Input
                                    v-bind="componentField"
                                    type="date"
                                    :disabled="inertiaForm.processing"
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <!-- President -->
                    <FormField v-slot="{ componentField }" name="president_student_id">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Users class="h-4 w-4" />
                                Club President *
                            </FormLabel>
                            <FormControl>
                                <Select
                                    v-bind="componentField"
                                    :disabled="inertiaForm.processing || !selectedCampusId || isLoadingStudents"
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            :placeholder="!selectedCampusId ? 'Select campus first' : isLoadingStudents ? 'Loading students...' : 'Select president'"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="student in studentOptions"
                                            :key="student.id"
                                            :value="student.id.toString()"
                                        >
                                            {{ student.name }} ({{ student.student_code }})
                                        </SelectItem>
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
                                <Input
                                    v-bind="componentField"
                                    type="email"
                                    placeholder="club@example.com"
                                    :disabled="inertiaForm.processing"
                                />
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
                                <Input
                                    v-bind="componentField"
                                    placeholder="+84 123 456 789"
                                    :disabled="inertiaForm.processing"
                                />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <div class="flex items-center justify-end gap-4">
                    <Button type="button" variant="outline" @click="goBack" :disabled="inertiaForm.processing">
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="!meta.valid || inertiaForm.processing"
                        class="gap-2"
                    >
                        <Users class="h-4 w-4" />
                        {{ inertiaForm.processing ? 'Creating...' : 'Create Club' }}
                    </Button>
                </div>
            </Form>
        </CardContent>
    </Card>
</template>
