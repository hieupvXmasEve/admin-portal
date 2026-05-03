<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { CourseOffering } from '@/types/models';
import { curriculumRoutes } from '@/utils/routes';
import { Link } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { Calendar, ExternalLink, MapPin, Users } from 'lucide-vue-next';
import { route } from 'ziggy-js';

interface Props {
    courseOffering: CourseOffering;
    siblingOfferings: CourseOffering[];
}

const props = defineProps<Props>();

const enrollmentPercentage = props.courseOffering.max_capacity > 0 ? Math.round((props.courseOffering.current_enrollment / props.courseOffering.max_capacity) * 100) : 0;

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

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    return format(dateString, 'EEEE, dd/MM/yyyy');
};
</script>

<template>
    <div class="space-y-6">
        <!-- Course info + enrollment stats -->
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
                        <p class="text-lg font-semibold">
                            {{ Math.max(0, courseOffering.max_capacity - courseOffering.current_enrollment) }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Academic Info + Dates -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Academic Details -->
            <Card>
                <CardHeader>
                    <CardTitle>Academic Details</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-2 gap-4">
                    <div class="space-y-4">
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Semester</p>
                            <p class="text-lg font-semibold">{{ courseOffering.semester?.name }} ({{ courseOffering.semester?.code }})</p>
                        </div>

                        <div v-if="courseOffering.curriculum_unit">
                            <p class="text-muted-foreground text-sm font-medium">Unit</p>
                            <Link :href="`/units/${courseOffering.curriculum_unit.unit.id}`" class="flex items-center gap-2 text-lg font-semibold text-green-400">
                                <span>{{ courseOffering.curriculum_unit.unit.code }} - {{ courseOffering.curriculum_unit.unit.name }}</span>
                                <ExternalLink class="h-4 w-4" />
                            </Link>
                        </div>

                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Lecturer</p>
                            <p class="text-muted-foreground text-lg font-semibold">{{ courseOffering.lecture?.display_name || 'Not set' }}</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Syllabus</p>
                            <div v-if="courseOffering.syllabus_template" class="space-y-2">
                                <div class="flex items-start gap-2">
                                    <div class="flex-1">
                                        <Link :href="curriculumRoutes.syllabusTemplates.show(courseOffering.syllabus_template.id)" class="flex items-center gap-2 text-lg font-semibold">
                                            {{ courseOffering.syllabus_template.title }} - {{ courseOffering.syllabus_template.version }}
                                            <ExternalLink class="h-4 w-4" />
                                        </Link>
                                        <p v-if="courseOffering.syllabus_template.description" class="text-muted-foreground mt-1 text-sm">
                                            {{ courseOffering.syllabus_template.description }}
                                        </p>
                                        <div class="text-muted-foreground mt-2 flex items-center gap-4 text-xs">
                                            <span v-if="courseOffering.syllabus_template.unit"> {{ courseOffering.syllabus_template.unit.code }} - {{ courseOffering.syllabus_template.unit.name }} </span>
                                            <span v-if="courseOffering.syllabus_template.delivery_mode">
                                                {{ getDeliveryModeLabel(courseOffering.syllabus_template.delivery_mode) }}
                                            </span>
                                            <span v-if="courseOffering.syllabus_template.applicable_campus">
                                                {{ courseOffering.syllabus_template.applicable_campus.name }}
                                            </span>
                                            <span v-if="courseOffering.syllabus_template.applicable_program">
                                                {{ courseOffering.syllabus_template.applicable_program.name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-muted-foreground font-semibold">No Syllabus assigned</p>
                        </div>
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
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Schedule Day</p>
                            <p class="text-muted-foreground text-sm font-semibold">
                                {{ courseOffering.schedule_days?.join(',') ?? 'Not set' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm font-medium">Schedule Time</p>
                            <p class="text-sm">{{ courseOffering.schedule_time_start }} - {{ courseOffering.schedule_time_end }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Sibling Offerings (other sections) -->
        <div v-if="siblingOfferings.length > 0">
            <Card>
                <CardHeader>
                    <CardTitle>Other Sections</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-2">
                        <Link v-for="sibling in siblingOfferings" :key="sibling.id" :href="route('course-offerings.show', sibling.id)">
                            <Button variant="outline" size="sm"> Section {{ sibling.section_code || sibling.id }} ({{ sibling.current_enrollment }}/{{ sibling.max_capacity }}) </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
