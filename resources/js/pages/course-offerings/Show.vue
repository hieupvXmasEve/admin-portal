<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CourseOffering } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, DollarSign, Edit, MapPin, ToggleLeft, ToggleRight, Trash2, Users } from 'lucide-vue-next';

interface Props {
    courseOffering: CourseOffering;
}

const props = defineProps<Props>();
console.log(props.courseOffering);

const breadcrumbItems = [
    { title: 'Course Offerings', href: '/course-offerings' },
    { title: props.courseOffering.course_code + ' - ' + props.courseOffering.course_title, href: `/course-offerings/${props.courseOffering.id}` },
];

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'open':
            return 'default';
        case 'waitlist_only':
            return 'secondary';
        case 'cancelled':
            return 'destructive';
        case 'closed':
            return 'outline';
        default:
            return 'outline';
    }
};

const getDeliveryModeLabel = (mode: string) => {
    switch (mode) {
        case 'in_person':
            return 'In Person';
        case 'online':
            return 'Online';
        case 'hybrid':
            return 'Hybrid';
        default:
            return mode;
    }
};

const toggleStatus = () => {
    router.patch(
        `/course-offerings/${props.courseOffering.id}/toggle-status`,
        {},
        {
            onSuccess: () => {
                // Success handled by redirect or page refresh
            },
        },
    );
};

const deleteCourseOffering = () => {
    if (confirm('Are you sure you want to delete this course offering?')) {
        router.delete(`/course-offerings/${props.courseOffering.id}`, {
            onSuccess: () => {
                router.visit('/course-offerings');
            },
        });
    }
};

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'Not set';
    return new Date(dateString).toLocaleDateString();
};

const enrollmentPercentage =
    props.courseOffering.max_capacity > 0 ? Math.round((props.courseOffering.current_enrollment / props.courseOffering.max_capacity) * 100) : 0;
</script>

<template>
    <Head title="Course Offering Details" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="h-full space-y-4 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight">{{ courseOffering.course_code }} - {{ courseOffering.course_title }}</h1>
                        <p class="text-muted-foreground">Course offering details and enrollment information</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" @click="toggleStatus">
                        <ToggleLeft v-if="courseOffering.enrollment_status === 'open'" class="mr-2 h-4 w-4" />
                        <ToggleRight v-else class="mr-2 h-4 w-4" />
                        {{ courseOffering.enrollment_status === 'open' ? 'Close Registration' : 'Open Registration' }}
                    </Button>
                    <Link :href="`/course-offerings/${courseOffering.id}/edit`">
                        <Button variant="outline">
                            <Edit class="mr-2 h-4 w-4" />
                            Edit
                        </Button>
                    </Link>
                    <Button variant="destructive" @click="deleteCourseOffering">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                    <Link href="/course-offerings">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Course Information -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Basic Info -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Course Information</CardTitle>
                            <Badge :variant="getStatusVariant(courseOffering.enrollment_status)">
                                {{ courseOffering.enrollment_status.toUpperCase() }}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-muted-foreground text-sm font-medium">Course Code</p>
                                <p class="text-lg font-semibold">{{ courseOffering.course_code }}</p>
                            </div>
                            <div v-if="courseOffering.section_code">
                                <p class="text-muted-foreground text-sm font-medium">Section</p>
                                <p class="text-lg font-semibold">{{ courseOffering.section_code }}</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Course Title</p>
                            <p class="text-lg font-semibold">{{ courseOffering.course_title }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-muted-foreground text-sm font-medium">Credit Hours</p>
                                <p class="text-lg font-semibold">{{ courseOffering.credit_hours }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-sm font-medium">Delivery Mode</p>
                                <p class="text-lg font-semibold">{{ getDeliveryModeLabel(courseOffering.delivery_mode) }}</p>
                            </div>
                        </div>

                        <div v-if="courseOffering.location" class="flex items-center gap-2">
                            <MapPin class="text-muted-foreground h-4 w-4" />
                            <span>{{ courseOffering.location }}</span>
                        </div>

                        <div v-if="courseOffering.notes">
                            <p class="text-muted-foreground text-sm font-medium">Notes</p>
                            <p class="text-sm">{{ courseOffering.notes }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Enrollment Stats -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Users class="h-4 w-4" />
                            Enrollment
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="text-center">
                            <p class="text-3xl font-bold">{{ courseOffering.current_enrollment }}/{{ courseOffering.max_capacity }}</p>
                            <p class="text-muted-foreground text-sm">Students Enrolled</p>
                            <div class="mt-2 h-2 w-full rounded-full bg-gray-200">
                                <div class="h-2 rounded-full bg-blue-600" :style="{ width: `${enrollmentPercentage}%` }"></div>
                            </div>
                            <p class="text-muted-foreground mt-1 text-sm">{{ enrollmentPercentage }}% Full</p>
                        </div>

                        <Separator />

                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Waitlist</p>
                            <p class="text-lg font-semibold">{{ courseOffering.current_waitlist }}/{{ courseOffering.waitlist_capacity }}</p>
                        </div>

                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Available Spots</p>
                            <p class="text-lg font-semibold">{{ Math.max(0, courseOffering.max_capacity - courseOffering.current_enrollment) }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Academic Information -->
            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Semester & Campus -->
                <Card>
                    <CardHeader>
                        <CardTitle>Academic Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Semester</p>
                            <p class="text-lg font-semibold">{{ courseOffering.semester?.name }} ({{ courseOffering.semester?.code }})</p>
                        </div>

                        <div v-if="courseOffering.unit">
                            <p class="text-muted-foreground text-sm font-medium">Unit</p>
                            <p class="text-lg font-semibold">{{ courseOffering.unit.code }} - {{ courseOffering.unit.name }}</p>
                        </div>

                        <div v-if="courseOffering.instructor">
                            <p class="text-muted-foreground text-sm font-medium">Instructor</p>
                            <p class="text-lg font-semibold">{{ courseOffering.instructor.name }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Dates & Deadlines -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Calendar class="h-4 w-4" />
                            Important Dates
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Registration Period</p>
                            <p class="text-sm">
                                {{ formatDate(courseOffering.registration_start_date) }} -
                                {{ formatDate(courseOffering.registration_end_date) }}
                            </p>
                        </div>

                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Drop Deadline</p>
                            <p class="text-sm">{{ formatDate(courseOffering.drop_deadline) }}</p>
                        </div>

                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Withdrawal Deadline</p>
                            <p class="text-sm">{{ formatDate(courseOffering.withdrawal_deadline) }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Financial Information -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <DollarSign class="h-4 w-4" />
                        Financial Information
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Tuition per Credit</p>
                            <p class="text-lg font-semibold">${{ courseOffering.tuition_per_credit?.toFixed(2) || '0.00' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Additional Fees</p>
                            <p class="text-lg font-semibold">${{ courseOffering.additional_fees?.toFixed(2) || '0.00' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Total Cost</p>
                            <p class="text-lg font-semibold">
                                ${{
                                    (
                                        (courseOffering.tuition_per_credit || 0) * (courseOffering.credit_hours || 0) +
                                        (courseOffering.additional_fees || 0)
                                    ).toFixed(2)
                                }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
