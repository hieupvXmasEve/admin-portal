<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';
import { CalendarDays, Save, ArrowLeft } from 'lucide-vue-next';

interface Semester {
    id: number;
    code: string;
    name: string;
    start_date: string;
    end_date: string;
    enrollment_start_date: string | null;
    enrollment_end_date: string | null;
    is_active: boolean;
    is_archived: boolean;
}

interface Props {
    semester: Semester;
}

const props = defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Semesters', href: '/semesters' },
    { title: 'Edit Semester', href: `/semesters/${props.semester.id}/edit` }
];

// Format datetime strings to the format expected by datetime-local inputs
const formatDateTimeLocal = (dateTime: string | null): string => {
    if (!dateTime) return '';
    const date = new Date(dateTime);
    return date.toISOString().slice(0, 16);
};

// Format date strings to the format expected by date inputs
const formatDate = (date: string): string => {
    return new Date(date).toISOString().split('T')[0];
};

const form = useForm({
    code: props.semester.code || '',
    name: props.semester.name || '',
    start_date: formatDate(props.semester.start_date),
    end_date: formatDate(props.semester.end_date),
    enrollment_start_date: formatDateTimeLocal(props.semester.enrollment_start_date),
    enrollment_end_date: formatDateTimeLocal(props.semester.enrollment_end_date),
    is_active: props.semester.is_active,
    is_archived: props.semester.is_archived,
});

const submit = () => {
    form.put(route('semester.update', props.semester.id), {
        onSuccess: () => {
            router.visit(route('semester.index'));
        }
    });
};

const cancel = () => {
    router.visit(route('semester.index'));
};
</script>

<template>
    <Head title="Edit Semester" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Edit Semester</h1>
                    <p class="text-muted-foreground">Update semester information and enrollment periods</p>
                </div>
            </div>

            <Card class="max-w-4xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CalendarDays class="h-5 w-5" />
                        Semester Information
                    </CardTitle>
                    <CardDescription>
                        Update the information and schedule for this semester.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Basic Information -->
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="code">Semester Code *</Label>
                                <Input
                                    id="code"
                                    v-model="form.code"
                                    placeholder="e.g., SPR2025, FALL2024"
                                    :class="{ 'border-red-500': form.errors.code }"
                                    required
                                />
                                <p v-if="form.errors.code" class="text-sm text-red-500">{{ form.errors.code }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="name">Semester Name *</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="e.g., Spring 2025, Fall 2024"
                                    :class="{ 'border-red-500': form.errors.name }"
                                    required
                                />
                                <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
                            </div>
                        </div>

                        <!-- Semester Dates -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium">Semester Period</h3>
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="start_date">Start Date *</Label>
                                    <Input
                                        id="start_date"
                                        v-model="form.start_date"
                                        type="date"
                                        :class="{ 'border-red-500': form.errors.start_date }"
                                        required
                                    />
                                    <p v-if="form.errors.start_date" class="text-sm text-red-500">{{ form.errors.start_date }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="end_date">End Date *</Label>
                                    <Input
                                        id="end_date"
                                        v-model="form.end_date"
                                        type="date"
                                        :class="{ 'border-red-500': form.errors.end_date }"
                                        required
                                    />
                                    <p v-if="form.errors.end_date" class="text-sm text-red-500">{{ form.errors.end_date }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Enrollment Dates -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium">Enrollment Period (Optional)</h3>
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="enrollment_start_date">Enrollment Start Date</Label>
                                    <Input
                                        id="enrollment_start_date"
                                        v-model="form.enrollment_start_date"
                                        type="datetime-local"
                                        :class="{ 'border-red-500': form.errors.enrollment_start_date }"
                                    />
                                    <p v-if="form.errors.enrollment_start_date" class="text-sm text-red-500">{{ form.errors.enrollment_start_date }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="enrollment_end_date">Enrollment End Date</Label>
                                    <Input
                                        id="enrollment_end_date"
                                        v-model="form.enrollment_end_date"
                                        type="datetime-local"
                                        :class="{ 'border-red-500': form.errors.enrollment_end_date }"
                                    />
                                    <p v-if="form.errors.enrollment_end_date" class="text-sm text-red-500">{{ form.errors.enrollment_end_date }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Status Settings -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium">Status Settings</h3>
                            <div class="space-y-4">
                                <div class="flex items-center space-x-2">
                                    <Switch id="is_active" v-model:checked="form.is_active" />
                                    <div class="space-y-1">
                                        <Label for="is_active">Active Semester</Label>
                                        <p class="text-sm text-muted-foreground">Mark this as the currently active semester</p>
                                    </div>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <Switch id="is_archived" v-model:checked="form.is_archived" />
                                    <div class="space-y-1">
                                        <Label for="is_archived">Archived</Label>
                                        <p class="text-sm text-muted-foreground">Archive this semester (prevents editing)</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex gap-4 pt-6">
                            <Button type="submit" :disabled="form.processing" class="flex-1">
                                <Save class="mr-2 h-4 w-4" />
                                {{ form.processing ? 'Updating...' : 'Update Semester' }}
                            </Button>
                            <Button type="button" variant="outline" @click="cancel" class="flex-1">
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Cancel
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
