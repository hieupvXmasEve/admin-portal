<script setup lang="ts">
import MoveStudentModal from '@/components/MoveStudentModal.vue';
import StudentSearchModal from '@/components/StudentSearchModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useApi } from '@/composables/useApiRequest';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { usePermission } from '@/composables/usePermission';
import type { AcademicRecord, CourseOffering, CourseRegistration, Student } from '@/types/models';
import { formatDateTimeToShort } from '@/utils/date';
import { router } from '@inertiajs/vue3';
import { ArrowRightLeft, Trash2, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    courseOffering: CourseOffering & {
        course_registrations: CourseRegistration[];
        academic_records?: AcademicRecord[];
    };
    siblingOfferings: CourseOffering[];
}

const props = defineProps<Props>();
const api = useApi();
const { showConfirmDialog } = useGlobalConfirmDialog();
const { can } = usePermission();

// Move student modal
const moveStudentOpen = ref(false);
const studentToMove = ref<Student | null>(null);

const isStartedCourse = computed(() => props.courseOffering.class_sessions?.some((s) => s.status === 'in_progress' || s.status === 'completed') ?? false);

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

const getAcademicRecordForStudent = (studentId: number) => props.courseOffering.academic_records?.find((r) => r.student_id === studentId);

// Student added handler
const handleStudentAddSuccess = () => {
    router.reload({ only: ['courseOffering'] });
};

// Delete student registration
const deleteStudentRegistration = (registration: CourseRegistration) => {
    const studentName = registration.student?.full_name || 'Unknown Student';
    const studentId = registration.student?.student_id || 'Unknown ID';
    showConfirmDialog(
        {
            title: 'Remove Student from Course',
            message: `Are you sure you want to remove ${studentName} (${studentId}) from this course? This action cannot be undone.`,
            confirmText: 'Remove Student',
        },
        {
            onConfirm: async () => {
                try {
                    const result = await api.post(`/course-offerings/${props.courseOffering.id}/delete-student-registration`, {
                        registration_id: registration.id,
                    });
                    if (result.data?.value?.success) {
                        toast.success(result.data.value.message || `Successfully removed ${studentName} from the course`);
                        router.reload({ only: ['courseOffering'] });
                    } else {
                        toast.error(result.data?.value?.message || 'Failed to remove student from course');
                    }
                } catch {
                    toast.error('Failed to remove student from course');
                    throw new Error('Failed to remove student from course');
                }
            },
        },
    );
};

const openMoveStudentModal = (student: Student | undefined) => {
    if (!student) return;
    studentToMove.value = student;
    moveStudentOpen.value = true;
};

const handleMoveSuccess = () => {
    router.reload({ only: ['courseOffering', 'siblingOfferings'] });
};
</script>

<template>
    <div class="space-y-4">
        <StudentSearchModal :course-offering-id="courseOffering.id" :on-success="handleStudentAddSuccess" />

        <!-- Empty state -->
        <div v-if="!courseOffering.course_registrations || courseOffering.course_registrations.length === 0" class="py-8 text-center">
            <Users class="text-muted-foreground mx-auto h-12 w-12" />
            <h3 class="mt-2 text-sm font-semibold text-gray-900">No students enrolled</h3>
            <p class="text-muted-foreground mt-1 text-sm">No students have registered for this course offering yet.</p>
        </div>

        <!-- Students table -->
        <div v-else>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>No</TableHead>
                        <TableHead>Student ID</TableHead>
                        <TableHead>Student Name</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Retake Info</TableHead>
                        <TableHead>Registration Date</TableHead>
                        <TableHead>Method</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(registration, index) in courseOffering.course_registrations" :key="registration.id">
                        <TableCell>{{ index + 1 }}</TableCell>
                        <TableCell class="font-medium">{{ registration.student?.student_id }}</TableCell>
                        <TableCell>{{ registration.student?.full_name }}</TableCell>
                        <TableCell class="text-muted-foreground">{{ registration.student?.email }}</TableCell>
                        <TableCell>
                            <Badge :variant="getRegistrationStatusVariant(registration.registration_status)">
                                {{ registration.registration_status.toUpperCase() }}
                            </Badge>
                        </TableCell>
                        <TableCell>
                            <template v-if="registration.student">
                                <div v-if="getAcademicRecordForStudent(registration.student.id)" class="space-y-1">
                                    <div v-if="getAcademicRecordForStudent(registration.student.id)?.is_repeat_course" class="flex items-center gap-2">
                                        <Badge variant="secondary" class="bg-orange-100 text-orange-800"> Retake (Attempt #{{ getAcademicRecordForStudent(registration.student.id)?.attempt_number }}) </Badge>
                                    </div>
                                    <div v-else class="text-muted-foreground text-sm">First Attempt</div>
                                    <div v-if="getAcademicRecordForStudent(registration.student.id)?.is_repeat_course && getAcademicRecordForStudent(registration.student.id)?.original_record" class="text-muted-foreground text-xs">
                                        Previous: {{ getAcademicRecordForStudent(registration.student.id)?.original_record?.final_letter_grade || 'N/A' }}
                                        <template v-if="getAcademicRecordForStudent(registration.student.id)?.original_record?.final_percentage">
                                            ({{ Number(getAcademicRecordForStudent(registration.student.id)?.original_record?.final_percentage).toFixed(2) }}%)
                                        </template>
                                        <template v-else>(N/A)</template>
                                    </div>
                                </div>
                                <span v-else class="text-muted-foreground text-sm">N/A</span>
                            </template>
                        </TableCell>
                        <TableCell class="text-muted-foreground">{{ formatDateTimeToShort(registration.registration_date) }}</TableCell>
                        <TableCell class="text-muted-foreground">{{ registration.registration_method }}</TableCell>
                        <TableCell>
                            <div class="flex items-center gap-1">
                                <Button v-if="can('edit_course_offering') && siblingOfferings.length > 0" variant="ghost" size="sm" title="Move to another section" @click="openMoveStudentModal(registration.student)">
                                    <ArrowRightLeft class="h-4 w-4" />
                                </Button>
                                <Button v-if="can('delete_student_registration') && !isStartedCourse" variant="ghost" size="sm" class="text-destructive hover:text-destructive hover:bg-destructive/10" @click="deleteStudentRegistration(registration)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Move Student Modal -->
        <MoveStudentModal :open="moveStudentOpen" :current-offering-id="courseOffering.id" :student="studentToMove" :sibling-offerings="siblingOfferings" @update:open="moveStudentOpen = $event" @success="handleMoveSuccess" />
    </div>
</template>
