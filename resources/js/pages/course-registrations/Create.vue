<script setup lang="ts">
import DebouncedInput from '@/components/DebouncedInput.vue';
import StudentCombobox from '@/components/StudentCombobox.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CourseOffering, Semester, Student } from '@/types/models';
import { Head, router } from '@inertiajs/vue3';
import { BookOpen, CreditCard, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    semesters: Semester[];
    selectedSemester?: Semester;
    courseOfferings: CourseOffering[];
}

const props = defineProps<Props>();

const breadcrumbs = ref([
    {
        title: 'Course Registrations',
        href: '/course-registrations',
    },
    {
        title: 'Register Student',
        href: '/course-registrations/create',
    },
]);

// Form state
const form = ref({
    student_id: '',
    course_offering_id: '',
    payment_status: 'pending',
    notes: '',
});

const errors = ref<any>({});
const isSubmitting = ref(false);

// State for dynamic data loading
const selectedSemesterId = ref(props.selectedSemester?.id?.toString() || '');
const courseSearch = ref('');
const selectedStudent = ref<Student | null>(null);
const selectedCourseOffering = ref<CourseOffering | null>(null);

// Computed properties
const filteredCourseOfferings = computed(() => {
    if (!courseSearch.value.trim()) return props.courseOfferings;
    const searchLower = courseSearch.value.toLowerCase();
    return props.courseOfferings.filter(
        (offering) =>
            offering.course_code?.toLowerCase().includes(searchLower) ||
            offering.course_title?.toLowerCase().includes(searchLower) ||
            offering.section_code?.toLowerCase().includes(searchLower),
    );
});

const tuitionAmount = computed(() => {
    if (!selectedCourseOffering.value) return 0;
    const creditHours = selectedCourseOffering.value.credit_hours || 0;
    const perCredit = selectedCourseOffering.value.tuition_per_credit || 0;
    return creditHours * perCredit;
});

const totalAmount = computed(() => {
    if (!selectedCourseOffering.value) return 0;
    return tuitionAmount.value + (selectedCourseOffering.value.additional_fees || 0);
});

// Handle semester change
const handleSemesterChange = (value: any) => {
    const semesterId = String(value);
    selectedSemesterId.value = semesterId;
    if (semesterId && semesterId !== 'none') {
        router.get(
            '/course-registrations/create',
            { semester_id: semesterId },
            {
                preserveState: true,
                only: ['students', 'courseOfferings', 'selectedSemester'],
            },
        );
    }
};

// Handle student selection
const handleStudentSelect = (student: Student | null) => {
    if (student) {
        form.value.student_id = student.id.toString();
        selectedStudent.value = student;
    } else {
        form.value.student_id = '';
        selectedStudent.value = null;
    }
};

// Handle course selection
const handleCourseSelect = (value: any) => {
    const offeringId = String(value);
    form.value.course_offering_id = offeringId;
    selectedCourseOffering.value = props.courseOfferings.find((o) => o.id.toString() === offeringId) || null;
};

// Form submission
const onSubmit = () => {
    if (!form.value.student_id || !form.value.course_offering_id) {
        return;
    }

    isSubmitting.value = true;
    errors.value = {};

    const formData = {
        student_id: Number(form.value.student_id),
        course_offering_id: Number(form.value.course_offering_id),
        payment_status: form.value.payment_status,
        notes: form.value.notes,
    };

    router.post('/course-registrations', formData, {
        onSuccess: () => {
            isSubmitting.value = false;
        },
        onError: (errs) => {
            errors.value = errs;
            isSubmitting.value = false;
        },
    });
};
</script>

<template>
    <Head title="Register Student for Course" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Register Student for Course</h1>
                <p class="text-muted-foreground">Add a new course registration for a student</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Form -->
                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Registration Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form @submit.prevent="onSubmit" class="space-y-6">
                                <!-- Semester Selection -->
                                <div class="space-y-2">
                                    <label class="text-sm font-medium">Semester *</label>
                                    <Select :model-value="selectedSemesterId" @update:model-value="handleSemesterChange">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select semester" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Select semester</SelectItem>
                                            <SelectItem v-for="semester in semesters" :key="semester.id" :value="semester.id.toString()">
                                                {{ semester.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div v-if="!selectedSemesterId || selectedSemesterId === 'none'" class="py-8 text-center">
                                    <div class="flex flex-col items-center space-y-3">
                                        <BookOpen class="text-muted-foreground h-12 w-12" />
                                        <p class="text-muted-foreground">Please select a semester to continue</p>
                                    </div>
                                </div>

                                <div v-else class="space-y-6">
                                    <!-- Student Selection -->
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Student *</label>
                                        <StudentCombobox
                                            v-model="form.student_id"
                                            :error-message="errors.student_id"
                                            placeholder="Search and select a student..."
                                            @select="handleStudentSelect"
                                        />
                                    </div>

                                    <!-- Course Offering Selection -->
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Course Offering *</label>
                                        <div class="space-y-3">
                                            <DebouncedInput
                                                v-model="courseSearch"
                                                placeholder="Search courses by code, title, or section..."
                                                class="w-full"
                                            />
                                            <Select :model-value="form.course_offering_id" @update:model-value="handleCourseSelect">
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select course offering" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">Select course offering</SelectItem>
                                                    <SelectItem
                                                        v-for="offering in filteredCourseOfferings"
                                                        :key="offering.id"
                                                        :value="offering.id.toString()"
                                                    >
                                                        <div class="flex flex-col">
                                                            <span class="font-medium">{{ offering.course_code }} - {{ offering.course_title }}</span>
                                                            <span class="text-muted-foreground text-sm">
                                                                Section {{ offering.section_code }} • {{ offering.credit_hours }} credits •
                                                                {{ offering.current_enrollment }}/{{ offering.max_enrollment }} enrolled
                                                            </span>
                                                        </div>
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div v-if="errors.course_offering_id" class="text-sm text-red-600">{{ errors.course_offering_id }}</div>
                                    </div>

                                    <!-- Payment Status -->
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Payment Status</label>
                                        <Select v-model="form.payment_status">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select payment status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="pending">Pending</SelectItem>
                                                <SelectItem value="paid">Paid</SelectItem>
                                                <SelectItem value="overdue">Overdue</SelectItem>
                                                <SelectItem value="waived">Waived</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <!-- Notes -->
                                    <div class="space-y-2">
                                        <label class="text-sm font-medium">Notes</label>
                                        <Textarea v-model="form.notes" placeholder="Optional registration notes..." class="min-h-[100px]" />
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="flex space-x-4">
                                        <Button type="submit" :disabled="isSubmitting || !form.student_id || !form.course_offering_id" class="flex-1">
                                            {{ isSubmitting ? 'Registering...' : 'Register Student' }}
                                        </Button>
                                        <Button type="button" variant="outline" @click="router.visit('/course-registrations')" class="flex-1">
                                            Cancel
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <!-- Summary Sidebar -->
                <div class="space-y-6">
                    <!-- Selected Student Info -->
                    <Card v-if="selectedStudent">
                        <CardHeader>
                            <CardTitle class="flex items-center space-x-2">
                                <Users class="h-5 w-5" />
                                <span>Selected Student</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div>
                                <p class="font-medium">{{ selectedStudent.full_name }}</p>
                                <p class="text-muted-foreground text-sm">{{ selectedStudent.student_id }}</p>
                                <p class="text-muted-foreground text-sm">{{ selectedStudent.email }}</p>
                            </div>
                            <div v-if="selectedStudent.program">
                                <Badge variant="outline">{{ selectedStudent.program.name }}</Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Selected Course Info -->
                    <Card v-if="selectedCourseOffering">
                        <CardHeader>
                            <CardTitle class="flex items-center space-x-2">
                                <BookOpen class="h-5 w-5" />
                                <span>Selected Course</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div>
                                <p class="font-medium">{{ selectedCourseOffering.course_code }}</p>
                                <p class="text-muted-foreground text-sm">{{ selectedCourseOffering.course_title }}</p>
                                <p class="text-muted-foreground text-sm">Section {{ selectedCourseOffering.section_code }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span class="text-muted-foreground">Credits:</span>
                                    <span class="ml-1 font-medium">{{ selectedCourseOffering.credit_hours }}</span>
                                </div>
                                <div>
                                    <span class="text-muted-foreground">Enrolled:</span>
                                    <span class="ml-1 font-medium"
                                        >{{ selectedCourseOffering.current_enrollment }}/{{ selectedCourseOffering.max_enrollment }}</span
                                    >
                                </div>
                            </div>
                            <Badge :variant="selectedCourseOffering.enrollment_status === 'open' ? 'default' : 'secondary'">
                                {{ selectedCourseOffering.enrollment_status }}
                            </Badge>
                        </CardContent>
                    </Card>

                    <!-- Financial Summary -->
                    <Card v-if="selectedCourseOffering">
                        <CardHeader>
                            <CardTitle class="flex items-center space-x-2">
                                <CreditCard class="h-5 w-5" />
                                <span>Financial Summary</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground"
                                        >Tuition ({{ selectedCourseOffering.credit_hours }} × ${{ selectedCourseOffering.tuition_per_credit }}):</span
                                    >
                                    <span class="font-medium">${{ tuitionAmount.toFixed(2) }}</span>
                                </div>
                                <div v-if="selectedCourseOffering.additional_fees" class="flex justify-between">
                                    <span class="text-muted-foreground">Additional Fees:</span>
                                    <span class="font-medium">${{ selectedCourseOffering.additional_fees.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-2">
                                    <span class="font-medium">Total Amount:</span>
                                    <span class="text-lg font-bold">${{ totalAmount.toFixed(2) }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
