<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Save } from 'lucide-vue-next';
const form = useForm({
    code: '',
    name: '',
    start_date: '',
    end_date: '',
    enrollment_start_date: '',
    enrollment_end_date: '',
    is_active: false,
    is_archived: false,
});

const submit = () => {
    form.post(route('semester.store'), {
        onSuccess: () => {
            router.visit(route('semester.index'));
        },
    });
};

const cancel = () => {
    router.visit(route('semester.index'));
};
</script>

<template>
    <Head title="Add Semester" />

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Add New Semester</h1>
            <p class="text-muted-foreground">Create a new semester with enrollment periods</p>
        </div>
    </div>

    <Card class="max-w-4xl">
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <CalendarDays class="h-5 w-5" />
                Semester Information
            </CardTitle>
            <CardDescription> Enter the basic information and schedule for the new semester. </CardDescription>
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
                            <Input id="end_date" v-model="form.end_date" type="date" :class="{ 'border-red-500': form.errors.end_date }" required />
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
                                <p class="text-muted-foreground text-sm">Mark this as the currently active semester</p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2">
                            <Switch id="is_archived" v-model:checked="form.is_archived" />
                            <div class="space-y-1">
                                <Label for="is_archived">Archived</Label>
                                <p class="text-muted-foreground text-sm">Archive this semester (prevents editing)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex gap-4 pt-6">
                    <Button type="submit" :disabled="form.processing" class="flex-1">
                        <Save class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Creating...' : 'Create Semester' }}
                    </Button>
                    <Button type="button" variant="outline" @click="cancel" class="flex-1">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Cancel
                    </Button>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
