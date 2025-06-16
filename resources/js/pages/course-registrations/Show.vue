<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CourseRegistration } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { BookOpen, Calendar, CreditCard, Edit, FileText, GraduationCap, Trash2, User } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    registration: CourseRegistration;
}

const props = defineProps<Props>();

const breadcrumbs = ref([
    {
        title: 'Course Registrations',
        href: '/course-registrations',
    },
    {
        title: 'Registration Details',
        href: `/course-registrations/${props.registration.id}`,
    },
]);

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

const getPaymentStatusVariant = (status: string) => {
    switch (status) {
        case 'paid':
            return 'default';
        case 'pending':
            return 'secondary';
        case 'overdue':
            return 'destructive';
        case 'waived':
            return 'outline';
        default:
            return 'outline';
    }
};

const deleteRegistration = () => {
    if (confirm('Are you sure you want to delete this registration?')) {
        router.delete(`/course-registrations/${props.registration.id}`, {
            onSuccess: () => {
                router.visit('/course-registrations');
            },
        });
    }
};

const dropRegistration = () => {
    if (confirm('Are you sure you want to drop this student from the course?')) {
        router.patch(
            `/course-registrations/${props.registration.id}/drop`,
            {},
            {
                onSuccess: () => {
                    // Registration status will be updated by the backend
                },
            },
        );
    }
};

const withdrawRegistration = () => {
    if (confirm('Are you sure you want to withdraw this student from the course?')) {
        router.patch(
            `/course-registrations/${props.registration.id}/withdraw`,
            {},
            {
                onSuccess: () => {
                    // Registration status will be updated by the backend
                },
            },
        );
    }
};
</script>

<template>
    <Head title="Course Registration Details" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Course Registration Details</h1>
                    <p class="text-muted-foreground">Registration #{{ registration.id }}</p>
                </div>
                <div class="flex space-x-2">
                    <Link :href="`/course-registrations/${registration.id}/edit`">
                        <Button variant="outline">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                    <Button variant="destructive" @click="deleteRegistration">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Registration Overview -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center space-x-2">
                                <BookOpen class="h-5 w-5" />
                                <span>Registration Overview</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div class="space-y-3">
                                    <div>
                                        <label class="text-muted-foreground text-sm font-medium">Registration Status</label>
                                        <div class="mt-1">
                                            <Badge :variant="getStatusVariant(registration.registration_status)">
                                                {{ registration.registration_status }}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-muted-foreground text-sm font-medium">Registration Method</label>
                                        <p class="mt-1 text-sm">{{ registration.registration_method }}</p>
                                    </div>
                                    <div>
                                        <label class="text-muted-foreground text-sm font-medium">Registration Date</label>
                                        <p class="mt-1 text-sm">{{ new Date(registration.registration_date).toLocaleDateString() }}</p>
                                    </div>
                                </div>
                                <div class="space-y-3">
                                    <div>
                                        <label class="text-muted-foreground text-sm font-medium">Credit Hours</label>
                                        <p class="mt-1 text-sm font-medium">{{ registration.credit_hours }}</p>
                                    </div>
                                    <div>
                                        <label class="text-muted-foreground text-sm font-medium">Attempt Number</label>
                                        <p class="mt-1 text-sm">{{ registration.attempt_number }}</p>
                                    </div>
                                    <div v-if="registration.is_retake">
                                        <Badge variant="outline">Retake</Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Student Information -->
                    <Card v-if="registration.student">
                        <CardHeader>
                            <CardTitle class="flex items-center space-x-2">
                                <User class="h-5 w-5" />
                                <span>Student Information</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-muted-foreground text-sm font-medium">Student Name</label>
                                    <p class="mt-1 font-medium">{{ registration.student.full_name }}</p>
                                </div>
                                <div>
                                    <label class="text-muted-foreground text-sm font-medium">Student ID</label>
                                    <p class="mt-1 text-sm">{{ registration.student.student_id }}</p>
                                </div>
                                <div>
                                    <label class="text-muted-foreground text-sm font-medium">Email</label>
                                    <p class="mt-1 text-sm">{{ registration.student.email }}</p>
                                </div>
                                <div v-if="registration.student.program">
                                    <label class="text-muted-foreground text-sm font-medium">Program</label>
                                    <p class="mt-1 text-sm">{{ registration.student.program.name }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Course Information -->
                    <Card v-if="registration.course_offering">
                        <CardHeader>
                            <CardTitle class="flex items-center space-x-2">
                                <GraduationCap class="h-5 w-5" />
                                <span>Course Information</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="text-muted-foreground text-sm font-medium">Course Code</label>
                                    <p class="mt-1 font-medium">{{ registration.course_offering.course_code }}</p>
                                </div>
                                <div>
                                    <label class="text-muted-foreground text-sm font-medium">Course Title</label>
                                    <p class="mt-1 text-sm">{{ registration.course_offering.course_title }}</p>
                                </div>
                                <div>
                                    <label class="text-muted-foreground text-sm font-medium">Section</label>
                                    <p class="mt-1 text-sm">{{ registration.course_offering.section_code }}</p>
                                </div>
                                <div>
                                    <label class="text-muted-foreground text-sm font-medium">Credit Hours</label>
                                    <p class="mt-1 text-sm">{{ registration.course_offering.credit_hours }}</p>
                                </div>
                                <div v-if="registration.course_offering.instructor">
                                    <label class="text-muted-foreground text-sm font-medium">Instructor</label>
                                    <p class="mt-1 text-sm">{{ registration.course_offering.instructor.name }}</p>
                                </div>
                                <div v-if="registration.course_offering.campus">
                                    <label class="text-muted-foreground text-sm font-medium">Campus</label>
                                    <p class="mt-1 text-sm">{{ registration.course_offering.campus.name }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Academic Record -->
                    <Card v-if="registration.final_grade || registration.grade_points">
                        <CardHeader>
                            <CardTitle class="flex items-center space-x-2">
                                <GraduationCap class="h-5 w-5" />
                                <span>Academic Record</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div v-if="registration.final_grade">
                                    <label class="text-muted-foreground text-sm font-medium">Final Grade</label>
                                    <p class="mt-1 text-lg font-bold">{{ registration.final_grade }}</p>
                                </div>
                                <div v-if="registration.grade_points">
                                    <label class="text-muted-foreground text-sm font-medium">Grade Points</label>
                                    <p class="mt-1 text-sm">{{ registration.grade_points }}</p>
                                </div>
                                <div v-if="registration.completion_date">
                                    <label class="text-muted-foreground text-sm font-medium">Completion Date</label>
                                    <p class="mt-1 text-sm">{{ new Date(registration.completion_date).toLocaleDateString() }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Notes -->
                    <Card v-if="registration.notes">
                        <CardHeader>
                            <CardTitle class="flex items-center space-x-2">
                                <FileText class="h-5 w-5" />
                                <span>Notes</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm whitespace-pre-wrap">{{ registration.notes }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Financial Summary -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center space-x-2">
                                <CreditCard class="h-5 w-5" />
                                <span>Financial Summary</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground text-sm">Tuition Amount:</span>
                                    <span class="font-medium">${{ registration.tuition_amount.toFixed(2) }}</span>
                                </div>
                                <div v-if="registration.fees_amount > 0" class="flex justify-between">
                                    <span class="text-muted-foreground text-sm">Fees Amount:</span>
                                    <span class="font-medium">${{ registration.fees_amount.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-3">
                                    <span class="font-medium">Total Amount:</span>
                                    <span class="text-lg font-bold">${{ (registration.tuition_amount + registration.fees_amount).toFixed(2) }}</span>
                                </div>
                                <div class="pt-2">
                                    <label class="text-muted-foreground text-sm font-medium">Payment Status</label>
                                    <div class="mt-1">
                                        <Badge :variant="getPaymentStatusVariant(registration.payment_status)">
                                            {{ registration.payment_status }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Important Dates -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center space-x-2">
                                <Calendar class="h-5 w-5" />
                                <span>Important Dates</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div>
                                <label class="text-muted-foreground text-sm font-medium">Registration Date</label>
                                <p class="mt-1 text-sm">{{ new Date(registration.registration_date).toLocaleDateString() }}</p>
                            </div>
                            <div v-if="registration.drop_date">
                                <label class="text-muted-foreground text-sm font-medium">Drop Date</label>
                                <p class="mt-1 text-sm">{{ new Date(registration.drop_date).toLocaleDateString() }}</p>
                            </div>
                            <div v-if="registration.withdrawal_date">
                                <label class="text-muted-foreground text-sm font-medium">Withdrawal Date</label>
                                <p class="mt-1 text-sm">{{ new Date(registration.withdrawal_date).toLocaleDateString() }}</p>
                            </div>
                            <div v-if="registration.completion_date">
                                <label class="text-muted-foreground text-sm font-medium">Completion Date</label>
                                <p class="mt-1 text-sm">{{ new Date(registration.completion_date).toLocaleDateString() }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Actions -->
                    <Card v-if="registration.registration_status === 'registered' || registration.registration_status === 'confirmed'">
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Button variant="outline" class="w-full" @click="dropRegistration" v-if="!registration.drop_date"> Drop Course </Button>
                            <Button variant="outline" class="w-full" @click="withdrawRegistration" v-if="!registration.withdrawal_date">
                                Withdraw from Course
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
