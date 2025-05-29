<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';
import { CalendarDays, Clock, GraduationCap, Settings } from 'lucide-vue-next';

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'Semesters', href: '/semesters' },
    { title: 'Add Semester', href: '/semesters/create' }
];

const form = useForm({
    name: '',
    semester_type: 'fall',
    academic_year: '',
    start_date: '',
    end_date: '',
    enrollment_start_date: '',
    enrollment_end_date: '',
    add_drop_deadline: '',
    withdrawal_deadline: '',
    final_exam_start: '',
    final_exam_end: '',
    locked_status: 'unlocked',
    is_current: false,
    is_registration_open: false,
    max_credit_load: 18.00,
    min_credit_load: 12.00,
    is_attendance_locked: false,
    is_certificate_locked: false,
    has_tuition_fee: false,
    has_gc_fee: false,
});

const submit = () => {
    form.post(route('semester.store'), {
        onSuccess: () => {
            // Form will redirect on success
        },
    });
};

const semesterTypes = [
    { value: 'fall', label: 'Fall' },
    { value: 'spring', label: 'Spring' },
    { value: 'summer', label: 'Summer' },
    { value: 'winter', label: 'Winter' },
    { value: 'intersession', label: 'Intersession' },
];

const lockStatuses = [
    { value: 'unlocked', label: 'Unlocked' },
    { value: 'locked', label: 'Locked' },
];
</script>

<template>
    <Head title="Add Semester" />

    <AppLayout :breadcrumb-items="breadcrumbItems">
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Add New Semester</h1>
                    <p class="text-muted-foreground">Create a new semester for the current campus</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <CalendarDays class="h-5 w-5" />
                            Basic Information
                        </CardTitle>
                        <CardDescription>
                            Set the basic semester details and academic calendar
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="name">Semester Name *</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    placeholder="e.g., Fall 2024"
                                    :class="{ 'border-red-500': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="text-sm text-red-500">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="semester_type">Semester Type *</Label>
                                <select
                                    id="semester_type"
                                    v-model="form.semester_type"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="{ 'border-red-500': form.errors.semester_type }"
                                >
                                    <option v-for="type in semesterTypes" :key="type.value" :value="type.value">
                                        {{ type.label }}
                                    </option>
                                </select>
                                <p v-if="form.errors.semester_type" class="text-sm text-red-500">
                                    {{ form.errors.semester_type }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="academic_year">Academic Year</Label>
                                <Input
                                    id="academic_year"
                                    v-model="form.academic_year"
                                    placeholder="e.g., 2024-2025"
                                    :class="{ 'border-red-500': form.errors.academic_year }"
                                />
                                <p v-if="form.errors.academic_year" class="text-sm text-red-500">
                                    {{ form.errors.academic_year }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="locked_status">Status *</Label>
                                <select
                                    id="locked_status"
                                    v-model="form.locked_status"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="{ 'border-red-500': form.errors.locked_status }"
                                >
                                    <option v-for="status in lockStatuses" :key="status.value" :value="status.value">
                                        {{ status.label }}
                                    </option>
                                </select>
                                <p v-if="form.errors.locked_status" class="text-sm text-red-500">
                                    {{ form.errors.locked_status }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="start_date">Start Date *</Label>
                                <Input
                                    id="start_date"
                                    v-model="form.start_date"
                                    type="date"
                                    :class="{ 'border-red-500': form.errors.start_date }"
                                />
                                <p v-if="form.errors.start_date" class="text-sm text-red-500">
                                    {{ form.errors.start_date }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="end_date">End Date *</Label>
                                <Input
                                    id="end_date"
                                    v-model="form.end_date"
                                    type="date"
                                    :class="{ 'border-red-500': form.errors.end_date }"
                                />
                                <p v-if="form.errors.end_date" class="text-sm text-red-500">
                                    {{ form.errors.end_date }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Enrollment Periods -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Clock class="h-5 w-5" />
                            Enrollment Periods
                        </CardTitle>
                        <CardDescription>
                            Configure enrollment, add/drop, and withdrawal deadlines
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="enrollment_start_date">Enrollment Start Date</Label>
                                <Input
                                    id="enrollment_start_date"
                                    v-model="form.enrollment_start_date"
                                    type="date"
                                    :class="{ 'border-red-500': form.errors.enrollment_start_date }"
                                />
                                <p v-if="form.errors.enrollment_start_date" class="text-sm text-red-500">
                                    {{ form.errors.enrollment_start_date }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="enrollment_end_date">Enrollment End Date</Label>
                                <Input
                                    id="enrollment_end_date"
                                    v-model="form.enrollment_end_date"
                                    type="date"
                                    :class="{ 'border-red-500': form.errors.enrollment_end_date }"
                                />
                                <p v-if="form.errors.enrollment_end_date" class="text-sm text-red-500">
                                    {{ form.errors.enrollment_end_date }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="add_drop_deadline">Add/Drop Deadline</Label>
                                <Input
                                    id="add_drop_deadline"
                                    v-model="form.add_drop_deadline"
                                    type="date"
                                    :class="{ 'border-red-500': form.errors.add_drop_deadline }"
                                />
                                <p v-if="form.errors.add_drop_deadline" class="text-sm text-red-500">
                                    {{ form.errors.add_drop_deadline }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="withdrawal_deadline">Withdrawal Deadline</Label>
                                <Input
                                    id="withdrawal_deadline"
                                    v-model="form.withdrawal_deadline"
                                    type="date"
                                    :class="{ 'border-red-500': form.errors.withdrawal_deadline }"
                                />
                                <p v-if="form.errors.withdrawal_deadline" class="text-sm text-red-500">
                                    {{ form.errors.withdrawal_deadline }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Exam Periods -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <GraduationCap class="h-5 w-5" />
                            Exam Periods
                        </CardTitle>
                        <CardDescription>
                            Set final examination period dates
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="final_exam_start">Final Exam Start Date</Label>
                                <Input
                                    id="final_exam_start"
                                    v-model="form.final_exam_start"
                                    type="date"
                                    :class="{ 'border-red-500': form.errors.final_exam_start }"
                                />
                                <p v-if="form.errors.final_exam_start" class="text-sm text-red-500">
                                    {{ form.errors.final_exam_start }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="final_exam_end">Final Exam End Date</Label>
                                <Input
                                    id="final_exam_end"
                                    v-model="form.final_exam_end"
                                    type="date"
                                    :class="{ 'border-red-500': form.errors.final_exam_end }"
                                />
                                <p v-if="form.errors.final_exam_end" class="text-sm text-red-500">
                                    {{ form.errors.final_exam_end }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Academic Settings -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Settings class="h-5 w-5" />
                            Academic Settings
                        </CardTitle>
                        <CardDescription>
                            Configure credit loads and semester settings
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="max_credit_load">Maximum Credit Load</Label>
                                <Input
                                    id="max_credit_load"
                                    v-model="form.max_credit_load"
                                    type="number"
                                    step="0.5"
                                    min="1"
                                    max="30"
                                    placeholder="18.00"
                                    :class="{ 'border-red-500': form.errors.max_credit_load }"
                                />
                                <p v-if="form.errors.max_credit_load" class="text-sm text-red-500">
                                    {{ form.errors.max_credit_load }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="min_credit_load">Minimum Credit Load</Label>
                                <Input
                                    id="min_credit_load"
                                    v-model="form.min_credit_load"
                                    type="number"
                                    step="0.5"
                                    min="1"
                                    max="30"
                                    placeholder="12.00"
                                    :class="{ 'border-red-500': form.errors.min_credit_load }"
                                />
                                <p v-if="form.errors.min_credit_load" class="text-sm text-red-500">
                                    {{ form.errors.min_credit_load }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center space-x-2">
                                <Switch id="is_current" v-model:checked="form.is_current" />
                                <Label for="is_current">Set as Current Semester</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="is_registration_open" v-model:checked="form.is_registration_open" />
                                <Label for="is_registration_open">Open Registration</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="is_attendance_locked" v-model:checked="form.is_attendance_locked" />
                                <Label for="is_attendance_locked">Lock Attendance</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="is_certificate_locked" v-model:checked="form.is_certificate_locked" />
                                <Label for="is_certificate_locked">Lock Certificates</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="has_tuition_fee" v-model:checked="form.has_tuition_fee" />
                                <Label for="has_tuition_fee">Has Tuition Fee</Label>
                            </div>

                            <div class="flex items-center space-x-2">
                                <Switch id="has_gc_fee" v-model:checked="form.has_gc_fee" />
                                <Label for="has_gc_fee">Has GC Fee</Label>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div class="flex items-center justify-end space-x-2">
                    <Button type="button" variant="outline" @click="router.visit(route('semester.index'))">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Semester' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
