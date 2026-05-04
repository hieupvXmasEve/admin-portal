<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type { CourseOffering } from '@/types/models';
import { curriculumRoutes } from '@/utils/routes';
import { Link } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { BookOpen, Calendar, ExternalLink, MapPin, Users } from 'lucide-vue-next';
import { route } from 'ziggy-js';

interface Props {
    courseOffering: CourseOffering;
    siblingOfferings: CourseOffering[];
}

const props = defineProps<Props>();

const enrollmentPercentage =
    props.courseOffering.max_capacity > 0
        ? Math.round((props.courseOffering.current_enrollment / props.courseOffering.max_capacity) * 100)
        : 0;

const getEnrollmentColor = () => {
    if (enrollmentPercentage >= 90) return 'bg-red-500';
    if (enrollmentPercentage >= 70) return 'bg-yellow-500';
    return 'bg-blue-600';
};

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
    return format(dateString, 'EEE, dd/MM/yyyy');
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
                            {{ courseOffering.enrollment_status.replace('_', ' ').toUpperCase() }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Course Code</p>
                            <p class="mt-0.5 text-lg font-semibold">{{ courseOffering.course_code }}</p>
                        </div>
                        <div v-if="courseOffering.section_code">
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Section</p>
                            <p class="mt-0.5 text-lg font-semibold">{{ courseOffering.section_code }}</p>
                        </div>
                    </div>

                    <div>
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Course Title</p>
                        <p class="mt-0.5 text-lg font-semibold">{{ courseOffering.course_title }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Delivery Mode</p>
                            <p class="mt-0.5 font-semibold">{{ getDeliveryModeLabel(courseOffering.delivery_mode) }}</p>
                        </div>
                        <div v-if="courseOffering.location">
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Location</p>
                            <div class="mt-0.5 flex items-center gap-1.5 font-semibold">
                                <MapPin class="text-muted-foreground h-3.5 w-3.5 shrink-0" />
                                <span>{{ courseOffering.location }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="courseOffering.notes">
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Notes</p>
                        <p class="text-muted-foreground mt-0.5 text-sm">{{ courseOffering.notes }}</p>
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
                <CardContent class="space-y-5">
                    <div>
                        <div class="flex items-end justify-between">
                            <p class="text-3xl font-bold">{{ courseOffering.current_enrollment }}</p>
                            <p class="text-muted-foreground pb-1 text-sm">/ {{ courseOffering.max_capacity }}</p>
                        </div>
                        <p class="text-muted-foreground mt-0.5 text-xs">Students enrolled</p>
                        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            <div
                                class="h-2 rounded-full transition-all"
                                :class="getEnrollmentColor()"
                                :style="{ width: `${enrollmentPercentage}%` }"
                            ></div>
                        </div>
                        <p class="text-muted-foreground mt-1 text-xs">{{ enrollmentPercentage }}% full</p>
                    </div>

                    <Separator />

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Waitlist</p>
                            <p class="mt-0.5 font-semibold">{{ courseOffering.current_waitlist }}/{{ courseOffering.waitlist_capacity }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Available</p>
                            <p class="mt-0.5 font-semibold text-green-600">
                                {{ Math.max(0, courseOffering.max_capacity - courseOffering.current_enrollment) }}
                            </p>
                        </div>
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
                <CardContent class="space-y-5">
                    <div>
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Semester</p>
                        <p class="mt-0.5 font-semibold">
                            {{ courseOffering.semester?.name }}
                            <span v-if="courseOffering.semester?.code" class="text-muted-foreground font-normal">({{ courseOffering.semester.code }})</span>
                        </p>
                    </div>

                    <div>
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Lecturer</p>
                        <p class="mt-0.5 font-semibold">{{ courseOffering.lecture?.display_name || 'Not assigned' }}</p>
                    </div>

                    <div v-if="courseOffering.curriculum_unit">
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Curriculum Unit</p>
                        <!-- /units/:id has no named route yet — kept as literal path -->
                        <Link
                            :href="`/units/${courseOffering.curriculum_unit.unit.id}`"
                            class="mt-0.5 flex items-center gap-1.5 text-sm font-semibold text-green-500 hover:underline"
                        >
                            {{ courseOffering.curriculum_unit.unit.code }} — {{ courseOffering.curriculum_unit.unit.name }}
                            <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                        </Link>
                    </div>

                    <div>
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Syllabus</p>
                        <div v-if="courseOffering.syllabus_template" class="mt-0.5 space-y-1.5">
                            <Link
                                :href="curriculumRoutes.syllabusTemplates.show(courseOffering.syllabus_template.id)"
                                class="flex items-center gap-1.5 font-semibold hover:underline"
                            >
                                {{ courseOffering.syllabus_template.title }} — {{ courseOffering.syllabus_template.version }}
                                <ExternalLink class="h-3.5 w-3.5 shrink-0" />
                            </Link>
                            <p v-if="courseOffering.syllabus_template.description" class="text-muted-foreground text-sm">
                                {{ courseOffering.syllabus_template.description }}
                            </p>
                            <div class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                <span v-if="courseOffering.syllabus_template.unit">
                                    {{ courseOffering.syllabus_template.unit.code }} — {{ courseOffering.syllabus_template.unit.name }}
                                </span>
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
                        <p v-else class="text-muted-foreground mt-0.5 text-sm">No syllabus assigned</p>
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
                <CardContent class="space-y-5">
                    <div>
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Registration Period</p>
                        <p class="mt-0.5 text-sm">
                            {{ formatDate(courseOffering.registration_start_date) }}
                            <span class="text-muted-foreground mx-1">→</span>
                            {{ formatDate(courseOffering.registration_end_date) }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Drop Deadline</p>
                            <p class="mt-0.5 text-sm">{{ formatDate(courseOffering.drop_deadline) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Withdrawal Deadline</p>
                            <p class="mt-0.5 text-sm">{{ formatDate(courseOffering.withdrawal_deadline) }}</p>
                        </div>
                    </div>

                    <Separator />

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Schedule Days</p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ courseOffering.schedule_days?.join(', ') ?? 'Not set' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">Schedule Time</p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ courseOffering.schedule_time_start ?? 'N/A' }} — {{ courseOffering.schedule_time_end ?? 'N/A' }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Sibling Offerings (other sections) -->
        <Card v-if="siblingOfferings.length > 0">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <BookOpen class="h-4 w-4" />
                    Other Sections
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap gap-2">
                    <Link v-for="sibling in siblingOfferings" :key="sibling.id" :href="route('course-offerings.show', sibling.id)">
                        <Button variant="outline" size="sm">
                            Section {{ sibling.section_code || sibling.id }}
                            <Badge variant="secondary" class="ml-1.5 px-1.5 py-0 text-[11px]">
                                {{ sibling.current_enrollment }}/{{ sibling.max_capacity }}
                            </Badge>
                        </Button>
                    </Link>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
