<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { CourseRegistration } from '@/types/models';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, Save, Users, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    registration: CourseRegistration;
    canEditStatus: boolean;
}

const props = defineProps<Props>();

// Form state
const form = ref({
    registration_status: props.registration.registration_status,
    notes: props.registration.notes || '',
});

const errors = ref<any>({});
const isSubmitting = ref(false);

// Form submission
const onSubmit = () => {
    isSubmitting.value = true;
    errors.value = {};

    router.put(`/course-registrations/${props.registration.id}`, form.value, {
        onSuccess: () => {
            isSubmitting.value = false;
            router.visit(`/course-registrations/${props.registration.id}`);
        },
        onError: (errs) => {
            errors.value = errs;
            isSubmitting.value = false;
        },
    });
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'registered':
        case 'confirmed':
            return 'default';
        case 'completed':
            return 'secondary';
        case 'dropped':
        case 'withdrawn':
            return 'destructive';
        default:
            return 'outline';
    }
};
</script>

<template>
    <Head title="Edit Course Registration" />
    <!-- Header -->
    <div>
        <h1 class="text-3xl font-bold tracking-tight">Edit Course Registration</h1>
        <p class="text-muted-foreground">Registration #{{ registration.id }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Main Form -->
        <div class="lg:col-span-2">
            <Card>
                <CardHeader>
                    <CardTitle>Edit Registration Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="onSubmit" class="space-y-6">
                        <!-- Registration Status -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Registration Status *</label>
                            <Select v-model="form.registration_status" :disabled="!props.canEditStatus">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select registration status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="registered">Registered</SelectItem>
                                    <SelectItem value="confirmed">Confirmed</SelectItem>
                                    <SelectItem value="dropped">Dropped</SelectItem>
                                    <SelectItem value="withdrawn">Withdrawn</SelectItem>
                                    <SelectItem value="completed">Completed</SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-if="errors.registration_status" class="text-sm text-red-600">{{ errors.registration_status }}</div>
                            <div v-if="!props.canEditStatus" class="text-sm text-amber-600">Status can only be changed during the course registration period</div>
                        </div>

                        <!-- Notes -->
                        <div class="space-y-2">
                            <label class="text-sm font-medium">Notes</label>
                            <Textarea v-model="form.notes" placeholder="Registration notes..." class="min-h-[100px]" />
                            <div v-if="errors.notes" class="text-sm text-red-600">{{ errors.notes }}</div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex space-x-4">
                            <Button type="submit" :disabled="isSubmitting" class="flex-1">
                                <Save class="mr-2 h-4 w-4" />
                                {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                            </Button>
                            <Button type="button" variant="outline" @click="router.visit(`/course-registrations/${registration.id}`)" class="flex-1">
                                <X class="mr-2 h-4 w-4" />
                                Cancel
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Summary Sidebar -->
        <div class="space-y-6">
            <!-- Registration Info -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center space-x-2">
                        <BookOpen class="h-5 w-5" />
                        <span>Registration Info</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div>
                        <label class="text-muted-foreground text-sm font-medium">Current Status</label>
                        <div class="mt-1">
                            <Badge :variant="getStatusVariant(registration.registration_status)">
                                {{ registration.registration_status }}
                            </Badge>
                        </div>
                    </div>
                    <div>
                        <label class="text-muted-foreground text-sm font-medium">Registration Date</label>
                        <p class="mt-1 text-sm">{{ new Date(registration.registration_date).toLocaleDateString() }}</p>
                    </div>
                    <div>
                        <label class="text-muted-foreground text-sm font-medium">Credit Hours</label>
                        <p class="mt-1 text-sm font-medium">{{ registration.credit_hours }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Student Info -->
            <Card v-if="registration.student">
                <CardHeader>
                    <CardTitle class="flex items-center space-x-2">
                        <Users class="h-5 w-5" />
                        <span>Student</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div>
                        <p class="font-medium">{{ registration.student.full_name }}</p>
                        <p class="text-muted-foreground text-sm">{{ registration.student.student_id }}</p>
                        <p class="text-muted-foreground text-sm">{{ registration.student.email }}</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Course Info -->
            <Card v-if="registration.course_offering">
                <CardHeader>
                    <CardTitle class="flex items-center space-x-2">
                        <BookOpen class="h-5 w-5" />
                        <span>Course</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div>
                        <p class="font-medium">{{ registration.course_offering.course_code }}</p>
                        <p class="text-muted-foreground text-sm">{{ registration.course_offering.course_title }}</p>
                        <p class="text-muted-foreground text-sm">Section {{ registration.course_offering.section_code }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
