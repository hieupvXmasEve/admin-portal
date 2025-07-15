<script setup lang="ts">
import RegistrationStatusModal from '@/components/RegistrationStatusModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { CourseOffering, CourseRegistration } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, MapPin, UserCheck, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
interface Props {
    courseOffering: CourseOffering & {
        course_registrations: CourseRegistration[];
    };
}

const props = defineProps<Props>();
console.log(props.courseOffering);
const showStatusModal = ref(false);

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

const getRegistrationStatusVariant = (status: string) => {
    switch (status) {
        case 'registered':
            return 'default';
        case 'confirmed':
            return 'default';
        case 'dropped':
            return 'destructive';
        case 'withdrawn':
            return 'secondary';
        case 'completed':
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

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'Not set';
    return new Date(dateString).toLocaleDateString();
};

const formatDateTime = (dateString: string | null | undefined): string => {
    if (!dateString) return 'Not set';
    return new Date(dateString).toLocaleDateString() + ' ' + new Date(dateString).toLocaleTimeString();
};

const enrollmentPercentage =
    props.courseOffering.max_capacity > 0 ? Math.round((props.courseOffering.current_enrollment / props.courseOffering.max_capacity) * 100) : 0;

const handleStatusUpdate = () => {
    // Refresh the page to get updated registration data
    router.reload({
        only: ['courseOffering'],
    });
    toast.success('Registration status updated successfully');
};
console.log(props.courseOffering);
</script>

<template>
    <Head title="Course Offering Details" />
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">{{ courseOffering.course_code }} - {{ courseOffering.course_title }}</h1>
                <p class="text-muted-foreground">Course offering details and enrollment information</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <Link v-if="!courseOffering.section_code && courseOffering.current_enrollment > 0" :href="`/course-offerings/${courseOffering.id}/split`">
                <Button variant="outline">
                    <Users class="mr-2 h-4 w-4" />
                    Split into Sections
                </Button>
            </Link>
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

                <div v-if="courseOffering.curriculum_unit">
                    <p class="text-muted-foreground text-sm font-medium">Unit</p>
                    <p class="text-lg font-semibold">
                        {{ courseOffering.curriculum_unit.unit.code }} - {{ courseOffering.curriculum_unit.unit.name }}
                    </p>
                </div>

                <div v-if="courseOffering.lecture">
                    <p class="text-muted-foreground text-sm font-medium">Lecture</p>
                    <p class="text-lg font-semibold">{{ courseOffering.lecture.display_name }}</p>
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
            <CardContent class="grid grid-cols-2 gap-4">
                <div class="space-y-4">
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
                </div>
                <div class="space-y-4">
                    <!-- schedule day -->
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Schedule</p>
                        <p class="text-sm">{{ courseOffering.schedule_days?.join(', ') ?? 'Not set' }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm font-medium">Schedule</p>
                        <p class="text-sm">{{ courseOffering.schedule_time_start }} - {{ courseOffering.schedule_time_end }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Student Registration Status -->
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <CardTitle class="flex items-center gap-2">
                    <UserCheck class="h-4 w-4" />
                    Student Registration Status
                </CardTitle>
                <!-- <Button
                    v-if="courseOffering.course_registrations && courseOffering.course_registrations.length > 0"
                    @click="showStatusModal = true"
                    variant="outline"
                >
                    <UserCheck class="mr-2 h-4 w-4" />
                    Manage Status
                </Button> -->
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="!courseOffering.course_registrations || courseOffering.course_registrations?.length === 0" class="py-8 text-center">
                <Users class="text-muted-foreground mx-auto h-12 w-12" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900">No registrations</h3>
                <p class="text-muted-foreground mt-1 text-sm">No students have registered for this course offering yet.</p>
            </div>
            <div v-else>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>No</TableHead>
                            <TableHead>Student ID</TableHead>
                            <TableHead>Student Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Registration Date</TableHead>
                            <TableHead>Method</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="(registration, index) in courseOffering.course_registrations" :key="registration.id">
                            <!-- No column -->
                            <TableCell>
                                {{ index + 1 }}
                            </TableCell>
                            <TableCell class="font-medium">
                                {{ registration.student?.student_id }}
                            </TableCell>
                            <TableCell>
                                {{ registration.student?.full_name }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ registration.student?.email }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="getRegistrationStatusVariant(registration.registration_status)">
                                    {{ registration.registration_status.toUpperCase() }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ formatDateTime(registration.registration_date) }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ registration.registration_method }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </CardContent>
    </Card>

    <!-- Registration Status Management Modal -->
    <RegistrationStatusModal
        v-if="showStatusModal"
        :course-offering="courseOffering"
        @close="showStatusModal = false"
        @updated="handleStatusUpdate"
    />
</template>
