<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import HeadlessToastWithProps from '@/components/ui/sonner/HeadlessToastWithProps.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApiRequest';
import { createColumns } from '@/lib/table-utils';
import { systemRoutes } from '@/utils/routes';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { BarChart3, BookOpen, ChevronLeft, FileCheck, Loader2, Plus, Users } from 'lucide-vue-next';
import { h, markRaw, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

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
    created_at: string;
    updated_at: string;
}

interface Unit {
    id: number;
    code: string;
    name: string;
    credit_points: number;
    created_at: string;
    updated_at: string;
}

interface Enrollment {
    id: number;
    student_id: number;
    semester_id: number;
    curriculum_version_id: number;
    semester_number: number;
    status: 'in_progress' | 'completed' | 'withdrawn';
    notes?: string;
    created_at: string;
    updated_at: string;
}

interface SuggestedCourse {
    unit: Unit;
    estimated_students: number;
    curriculum_details: Array<{
        program: string;
        specialization?: string;
        semester_number: number;
        student_count: number;
        is_required: boolean;
    }>;
    existing_offerings: number;
}

interface RegistrationStats {
    total_offerings: number;
    by_status: Record<string, number>;
    enrollment_summary: {
        total_capacity: number;
        total_enrolled: number;
        total_waitlisted: number;
        enrollment_rate: number;
    };
    offerings: Array<{
        id: number;
        unit_code: string;
        unit_name: string;
        section_code?: string;
        instructor_name?: string;
        max_capacity: number;
        current_enrollment: number;
        waitlist_capacity: number;
        current_waitlist: number;
        enrollment_status: string;
        enrollment_rate: number;
    }>;
}

interface Props {
    semester: Semester & {
        enrollments?: Enrollment[];
        course_offerings?: any[];
    };
    enrollmentStats: {
        total_enrolled: number;
        by_status: Record<string, number>;
        by_semester_number: Record<string, number>;
    };
}

const props = defineProps<Props>();

// State
const loading = ref<Record<string, boolean>>({});
const suggestedCourses = ref<SuggestedCourse[]>([]);
const registrationStats = ref<RegistrationStats | null>(null);
const selectedCourses = ref<Set<number>>(new Set());
const showBulkOpenModal = ref(false);
const showSingleOpenModal = ref(false);
const selectedUnit = ref<Unit | null>(null);

// API composable
const api = useApi();

// Forms
const bulkOpenForm = useForm({
    unit_ids: [] as number[],
    default_capacity: 1000,
    delivery_mode: 'in_person' as 'in_person' | 'online' | 'hybrid' | 'blended',
});

const singleOpenForm = useForm({
    unit_id: '',
    instructor_id: '',
    section_code: '',
    max_capacity: 30,
    waitlist_capacity: 10,
    delivery_mode: 'in_person' as 'in_person' | 'online' | 'hybrid' | 'blended',
    schedule_days: [] as string[],
    schedule_time_start: '',
    schedule_time_end: '',
    location: '',
    special_requirements: '',
    notes: '',
});

// Generate enrollments
const generateEnrollments = async () => {
    loading.value.generate = true;

    try {
        const { data } = await api.post(`/api/semesters/${props.semester.id}/enrollment/generate`, {});

        if (data.value?.success) {
            toast.success(data.value.message);
            // Refresh page data
            router.reload({ only: ['semester', 'enrollmentStats'] });
        } else {
            toast.error(data.value?.message || 'Failed to generate enrollments');
        }
    } catch (error) {
        console.error('Generate enrollments error:', error);
        toast.error('Failed to generate enrollments');
    } finally {
        loading.value.generate = false;
    }
};

// Load suggested courses
const loadSuggestedCourses = async () => {
    loading.value.suggested = true;

    try {
        const { data } = await api.get(`/api/semesters/${props.semester.id}/enrollment/suggested-courses`);

        if (data.value?.success) {
            suggestedCourses.value = data.value.data.suggested_courses;
        } else {
            toast.error(data.value?.message || 'Failed to load suggested courses');
        }
    } catch (error) {
        console.error('Load suggested courses error:', error);
        toast.error('Failed to load suggested courses');
    } finally {
        loading.value.suggested = false;
    }
};

// Load registration stats
const loadRegistrationStats = async () => {
    loading.value.stats = true;

    try {
        const { data } = await api.get(`/api/semesters/${props.semester.id}/enrollment/stats`);

        if (data.value?.success) {
            registrationStats.value = data.value.data;
        } else {
            toast.error(data.value?.message || 'Failed to load registration statistics');
        }
    } catch (error) {
        console.error('Load registration stats error:', error);
        toast.error('Failed to load registration statistics');
    } finally {
        loading.value.stats = false;
    }
};

// Bulk open courses
const openBulkOpenModal = () => {
    bulkOpenForm.unit_ids = Array.from(selectedCourses.value);
    showBulkOpenModal.value = true;
};

const submitBulkOpen = async () => {
    try {
        const { data } = await api.post(`/api/semesters/${props.semester.id}/enrollment/bulk-open-courses`, {
            unit_ids: bulkOpenForm.unit_ids,
            default_capacity: bulkOpenForm.default_capacity,
            delivery_mode: bulkOpenForm.delivery_mode,
        });
        console.log(data.value?.errors);

        if (data.value?.success && data.value?.errors?.length === 0) {
            showBulkOpenModal.value = false;
            toast.success('Course offerings created successfully');
            loadSuggestedCourses();
            loadRegistrationStats();
        } else {
            if (data.value?.errors?.length && data.value?.errors?.length > 0) {
                toast.error(markRaw(HeadlessToastWithProps), {
                    componentProps: {
                        message: data.value?.errors.join(',  <br />'),
                    },
                });
            } else {
                toast.error(data.value?.message || 'Failed to create course offerings');
            }
        }
    } catch (error) {
        console.error('Bulk open courses error:', error);
        toast.error('Failed to create course offerings');
    }
};

// Single course open
const openSingleCourseModal = (unit: Unit) => {
    selectedUnit.value = unit;
    singleOpenForm.reset();
    singleOpenForm.unit_id = unit.id.toString();
    showSingleOpenModal.value = true;
};

const submitSingleOpen = async () => {
    try {
        const { data } = await api.post(`/api/semesters/${props.semester.id}/enrollment/open-single-course`, {
            unit_id: singleOpenForm.unit_id,
            instructor_id: singleOpenForm.instructor_id || null,
            section_code: singleOpenForm.section_code || null,
            max_capacity: singleOpenForm.max_capacity,
            waitlist_capacity: singleOpenForm.waitlist_capacity,
            delivery_mode: singleOpenForm.delivery_mode,
            schedule_days: singleOpenForm.schedule_days?.length ? singleOpenForm.schedule_days : null,
            schedule_time_start: singleOpenForm.schedule_time_start || null,
            schedule_time_end: singleOpenForm.schedule_time_end || null,
            location: singleOpenForm.location || null,
            special_requirements: singleOpenForm.special_requirements || null,
            notes: singleOpenForm.notes || null,
        });

        if (data.value?.success) {
            showSingleOpenModal.value = false;
            selectedUnit.value = null;
            toast.success('Course offering created successfully');
            loadSuggestedCourses();
            loadRegistrationStats();
        } else {
            toast.error(data.value?.message || 'Failed to create course offering');
        }
    } catch (error) {
        console.error('Single course open error:', error);
        toast.error('Failed to create course offering');
    }
};

// Base columns for suggested courses (without selection)
const baseSuggestedCoursesColumns: ColumnDef<SuggestedCourse>[] = [
    {
        accessorKey: 'unit.code',
        header: 'Unit Code',
        cell: ({ row }) => row.original.unit.code,
    },
    {
        accessorKey: 'unit.name',
        header: 'Unit Name',
        cell: ({ row }) =>
            h('div', { class: 'max-w-xs' }, [
                h('div', { class: 'font-medium' }, row.original.unit.name),
                h('div', { class: 'text-sm text-muted-foreground' }, `${row.original.unit.credit_points} credits`),
            ]),
    },
    {
        accessorKey: 'estimated_students',
        header: 'Est. Students',
        cell: ({ row }) => h('div', { class: 'text-center font-medium' }, row.original.estimated_students.toString()),
    },
    {
        accessorKey: 'existing_offerings',
        header: 'Existing Classes',
        cell: ({ row }) => h('div', { class: 'text-center' }, row.original.existing_offerings.toString()),
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) =>
            h('div', { class: 'flex gap-2' }, [
                h(
                    Button,
                    {
                        size: 'sm',
                        variant: 'outline',
                        onClick: () => openSingleCourseModal(row.original.unit),
                    },
                    'Open Course',
                ),
            ]),
    },
];

// Create suggested courses columns with selection support
const suggestedCoursesColumns = createColumns(baseSuggestedCoursesColumns, {
    enableSelection: true,
});

// Handle selection changes for suggested courses
const handleSuggestedCoursesSelection = (selectedRows: SuggestedCourse[]) => {
    selectedCourses.value.clear();
    selectedRows.forEach((course) => selectedCourses.value.add(course.unit.id));
};

// Table columns for registration stats
const registrationStatsColumns: ColumnDef<RegistrationStats['offerings'][0]>[] = [
    {
        accessorKey: 'unit_code',
        header: 'Unit Code',
    },
    {
        accessorKey: 'unit_name',
        header: 'Unit Name',
        cell: ({ row }) =>
            h('div', { class: 'max-w-xs' }, [
                h('div', { class: 'font-medium' }, row.original.unit_name),
                row.original.section_code && h('div', { class: 'text-sm text-muted-foreground' }, `Section: ${row.original.section_code}`),
            ]),
    },
    {
        accessorKey: 'instructor_name',
        header: 'Instructor',
        cell: ({ row }) => row.original.instructor_name || 'Not assigned',
    },
    {
        accessorKey: 'enrollment',
        header: 'Enrollment',
        cell: ({ row }) =>
            h('div', { class: 'text-center' }, [
                h('div', { class: 'font-medium' }, `${row.original.current_enrollment}/${row.original.max_capacity}`),
                h('div', { class: 'text-sm text-muted-foreground' }, `${row.original.enrollment_rate}%`),
            ]),
    },
    {
        accessorKey: 'enrollment_status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.original.enrollment_status;
            const variant = status === 'open' ? 'default' : status === 'closed' ? 'secondary' : 'outline';
            return h(
                'span',
                {
                    class: `px-2 py-1 rounded text-xs font-medium ${
                        variant === 'default'
                            ? 'bg-green-100 text-green-800'
                            : variant === 'secondary'
                              ? 'bg-gray-100 text-gray-800'
                              : 'bg-blue-100 text-blue-800'
                    }`,
                },
                status.replace('_', ' ').toUpperCase(),
            );
        },
    },
];

onMounted(() => {
    if (props.enrollmentStats.total_enrolled > 0) {
        loadSuggestedCourses();
        loadRegistrationStats();
    }
});
</script>

<template>
    <Head :title="`${semester.name} - Enrollment Management`" />

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="sm" @click="router.get(systemRoutes.semesters.index())">
                <ChevronLeft class="h-4 w-4" />
                Back to Semesters
            </Button>
        </div>
        <Heading :title="`${semester.name} - Enrollment Management`" />
    </div>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Enrolled</CardTitle>
                <Users class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ enrollmentStats.total_enrolled }}</div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">In Progress</CardTitle>
                <FileCheck class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ enrollmentStats.by_status.in_progress || 0 }}</div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Course Offerings</CardTitle>
                <BookOpen class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ semester.course_offerings?.length || 0 }}</div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Enrollment Rate</CardTitle>
                <BarChart3 class="text-muted-foreground h-4 w-4" />
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">{{ registrationStats?.enrollment_summary.enrollment_rate || 0 }}%</div>
            </CardContent>
        </Card>
    </div>

    <!-- Tabs -->
    <Tabs default-value="enrollments" class="space-y-4">
        <TabsList>
            <TabsTrigger value="enrollments">Manage Enrollments</TabsTrigger>
            <TabsTrigger value="courses">Suggest Courses</TabsTrigger>
            <TabsTrigger value="statistics">Registration Statistics</TabsTrigger>
        </TabsList>

        <!-- Step 1: Manage Enrollments -->
        <TabsContent value="enrollments" class="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle>Generate Student Enrollments</CardTitle>
                    <CardDescription> Create enrollments for all active students based on their curriculum progress. </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-muted-foreground text-sm">
                                This will create enrollment records for students who don't already have one for this semester.
                            </p>
                        </div>
                        <Button @click="generateEnrollments" :disabled="loading.generate">
                            <Loader2 v-if="loading.generate" class="mr-2 h-4 w-4 animate-spin" />
                            Generate Enrollments
                        </Button>
                    </div>

                    <!-- Enrollment Stats -->
                    <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div v-for="(count, status) in enrollmentStats.by_status" :key="status" class="rounded bg-gray-50 p-3 text-center">
                            <div class="text-lg font-semibold">{{ count }}</div>
                            <div class="text-muted-foreground text-sm capitalize">{{ status.replace('_', ' ') }}</div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </TabsContent>

        <!-- Step 2: Suggest Courses -->
        <TabsContent value="courses" class="space-y-4">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Suggested Courses to Open</CardTitle>
                            <CardDescription> Based on student enrollments and curriculum requirements. </CardDescription>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" @click="loadSuggestedCourses" :disabled="loading.suggested">
                                <Loader2 v-if="loading.suggested" class="mr-2 h-4 w-4 animate-spin" />
                                Refresh Suggestions
                            </Button>
                            <Button @click="openBulkOpenModal" :disabled="selectedCourses.size === 0">
                                <Plus class="mr-2 h-4 w-4" />
                                Open Selected ({{ selectedCourses.size }})
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :data="suggestedCourses"
                        :columns="suggestedCoursesColumns"
                        :loading="loading.suggested"
                        :enable-row-selection="true"
                        empty-message="No course suggestions available. Please generate enrollments first."
                        @selection-change="handleSuggestedCoursesSelection"
                    />
                </CardContent>
            </Card>
        </TabsContent>

        <!-- Step 4: Registration Statistics -->
        <TabsContent value="statistics" class="space-y-4">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Registration Statistics</CardTitle>
                            <CardDescription> Track student registration progress for opened courses. </CardDescription>
                        </div>
                        <Button variant="outline" @click="loadRegistrationStats" :disabled="loading.stats">
                            <Loader2 v-if="loading.stats" class="mr-2 h-4 w-4 animate-spin" />
                            Refresh Stats
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div v-if="registrationStats" class="space-y-4">
                        <!-- Summary Stats -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div class="rounded bg-gray-50 p-3 text-center">
                                <div class="text-lg font-semibold">{{ registrationStats.enrollment_summary.total_capacity }}</div>
                                <div class="text-muted-foreground text-sm">Total Capacity</div>
                            </div>
                            <div class="rounded bg-gray-50 p-3 text-center">
                                <div class="text-lg font-semibold">{{ registrationStats.enrollment_summary.total_enrolled }}</div>
                                <div class="text-muted-foreground text-sm">Total Enrolled</div>
                            </div>
                            <div class="rounded bg-gray-50 p-3 text-center">
                                <div class="text-lg font-semibold">{{ registrationStats.enrollment_summary.total_waitlisted }}</div>
                                <div class="text-muted-foreground text-sm">Total Waitlisted</div>
                            </div>
                            <div class="rounded bg-gray-50 p-3 text-center">
                                <div class="text-lg font-semibold">{{ registrationStats.enrollment_summary.enrollment_rate }}%</div>
                                <div class="text-muted-foreground text-sm">Enrollment Rate</div>
                            </div>
                        </div>

                        <!-- Course Offerings Table -->
                        <DataTable
                            :data="registrationStats.offerings"
                            :columns="registrationStatsColumns"
                            empty-message="No course offerings found."
                        />
                    </div>
                    <div v-else class="text-muted-foreground py-8 text-center">No registration statistics available.</div>
                </CardContent>
            </Card>
        </TabsContent>
    </Tabs>

    <!-- Bulk Open Modal -->
    <Dialog v-model:open="showBulkOpenModal">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Bulk Open Course Offerings</DialogTitle>
                <DialogDescription> Create course offerings for {{ selectedCourses.size }} selected units. </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-4">
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="default-capacity" class="text-right">Default Capacity</Label>
                    <Input id="default-capacity" v-model.number="bulkOpenForm.default_capacity" type="number" min="1" max="500" class="col-span-3" />
                </div>
                <div class="grid grid-cols-4 items-center gap-4">
                    <Label for="delivery-mode" class="text-right">Delivery Mode</Label>
                    <Select v-model="bulkOpenForm.delivery_mode">
                        <SelectTrigger class="col-span-3">
                            <SelectValue placeholder="Select delivery mode" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="in_person">In Person</SelectItem>
                            <SelectItem value="online">Online</SelectItem>
                            <SelectItem value="hybrid">Hybrid</SelectItem>
                            <SelectItem value="blended">Blended</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showBulkOpenModal = false">Cancel</Button>
                <Button @click="submitBulkOpen"> Create Offerings </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Single Open Modal -->
    <Dialog v-model:open="showSingleOpenModal">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Open Course Offering</DialogTitle>
                <DialogDescription v-if="selectedUnit">
                    Create a detailed course offering for {{ selectedUnit.code }} - {{ selectedUnit.name }}.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="section-code">Section Code</Label>
                        <Input id="section-code" v-model="singleOpenForm.section_code" placeholder="e.g., A01" />
                    </div>
                    <div>
                        <Label for="instructor">Instructor</Label>
                        <Input id="instructor" v-model="singleOpenForm.instructor_id" placeholder="Select instructor" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="max-capacity">Max Capacity</Label>
                        <Input id="max-capacity" v-model.number="singleOpenForm.max_capacity" type="number" min="1" max="500" />
                    </div>
                    <div>
                        <Label for="waitlist-capacity">Waitlist Capacity</Label>
                        <Input id="waitlist-capacity" v-model.number="singleOpenForm.waitlist_capacity" type="number" min="0" max="100" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="delivery-mode-single">Delivery Mode</Label>
                        <Select v-model="singleOpenForm.delivery_mode">
                            <SelectTrigger>
                                <SelectValue placeholder="Select delivery mode" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="in_person">In Person</SelectItem>
                                <SelectItem value="online">Online</SelectItem>
                                <SelectItem value="hybrid">Hybrid</SelectItem>
                                <SelectItem value="blended">Blended</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label for="location">Location</Label>
                        <Input id="location" v-model="singleOpenForm.location" placeholder="Room/Building" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="start-time">Start Time</Label>
                        <Input id="start-time" v-model="singleOpenForm.schedule_time_start" type="time" />
                    </div>
                    <div>
                        <Label for="end-time">End Time</Label>
                        <Input id="end-time" v-model="singleOpenForm.schedule_time_end" type="time" />
                    </div>
                </div>
                <div>
                    <Label for="notes">Notes</Label>
                    <Textarea id="notes" v-model="singleOpenForm.notes" placeholder="Additional notes..." />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="showSingleOpenModal = false">Cancel</Button>
                <Button @click="submitSingleOpen"> Create Offering </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
