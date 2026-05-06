<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useApi } from '@/composables/useApiRequest';
import type { BulkRegistrationResponse, StudentEligibilityInfo, StudentSearchResponse } from '@/types/models';
import { debounce } from 'lodash-es';
import { AlertCircle, CheckCircle, Loader2, Search, UserPlus, Users, XCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    courseOfferingId: number;
    onSuccess?: () => void;
}

const props = defineProps<Props>();

// Modal state
const isOpen = ref(false);
const searchInput = ref('');
const searchResults = ref<StudentEligibilityInfo[]>([]);
const selectedStudentIds = ref(new Set<string>());
const isSearching = ref(false);
const isRegistering = ref(false);
const hasSearched = ref(false);

// API composable
const api = useApi();

// Computed properties
const eligibleStudents = computed(() => searchResults.value.filter((student) => student.exists && student.is_eligible && !student.is_already_registered));

const alreadyRegisteredStudents = computed(() => searchResults.value.filter((student) => student.exists && student.is_already_registered));

const ineligibleStudents = computed(() => searchResults.value.filter((student) => student.exists && !student.is_eligible && !student.is_already_registered));

const nonExistentStudents = computed(() => searchResults.value.filter((student) => !student.exists));

const selectedEligibleCount = computed(() => {
    return eligibleStudents.value.filter((student) => selectedStudentIds.value.has(student.student_id)).length;
});

const canRegister = computed(() => {
    return selectedEligibleCount.value > 0 && !isRegistering.value;
});

// Search function with debouncing
const debouncedSearch = debounce(async (studentIdsInput: string) => {
    if (!studentIdsInput.trim()) {
        searchResults.value = [];
        selectedStudentIds.value = new Set();
        hasSearched.value = false;
        return;
    }

    isSearching.value = true;
    hasSearched.value = true;
    // Reset selected students when starting a new search
    selectedStudentIds.value = new Set();

    try {
        const response = await api.post<StudentSearchResponse>(`/api/course-offerings/${props.courseOfferingId}/search-students`, {
            student_ids: studentIdsInput.trim(),
        });

        if (response.data?.value?.success) {
            searchResults.value = response.data.value.data.students;

            // Auto-select all eligible students
            const newSelectedIds = new Set(selectedStudentIds.value);
            // Filter eligible students directly from the response
            response.data.value.data.students
                .filter((student) => student.exists && student.is_eligible && !student.is_already_registered)
                .forEach((student) => {
                    newSelectedIds.add(student.student_id);
                });
            selectedStudentIds.value = newSelectedIds;
        } else {
            toast.error('Failed to search students');
            searchResults.value = [];
        }
    } catch (error) {
        console.error('Error searching students:', error);
        toast.error('Failed to search students');
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
}, 500);

// Watch for search input changes
watch(searchInput, (newValue) => {
    debouncedSearch(newValue);
});

// Handle student selection toggle
const toggleStudentSelection = (studentId: string, isSelected: boolean) => {
    const newSelectedIds = new Set(selectedStudentIds.value);
    if (isSelected) {
        newSelectedIds.add(studentId);
    } else {
        newSelectedIds.delete(studentId);
    }
    selectedStudentIds.value = newSelectedIds;
};

// Handle bulk registration
const handleBulkRegistration = async () => {
    if (!canRegister.value) return;

    const selectedIds = Array.from(selectedStudentIds.value);
    isRegistering.value = true;

    try {
        const response = await api.post<BulkRegistrationResponse>(`/api/course-offerings/${props.courseOfferingId}/bulk-register-students`, {
            student_ids: selectedIds,
        });

        if (response.data?.value?.success) {
            const data = response.data.value.data;
            toast.success(response.data.value.message);

            // Reset modal state
            resetModal();
            closeModal();

            // Call success callback if provided
            props.onSuccess?.();
        } else {
            toast.error(response.data?.value?.message || 'Failed to register students');
        }
    } catch (error) {
        console.error('Error registering students:', error);
        toast.error('Failed to register students');
    } finally {
        isRegistering.value = false;
    }
};

// Reset modal state
const resetModal = () => {
    searchInput.value = '';
    searchResults.value = [];
    selectedStudentIds.value = new Set();
    hasSearched.value = false;
    isSearching.value = false;
    isRegistering.value = false;
};

// Close modal
const closeModal = () => {
    isOpen.value = false;
    // Reset after a brief delay to avoid visual glitch
    setTimeout(resetModal, 300);
};

// Get badge variant for eligibility status
const getEligibilityBadgeVariant = (student: StudentEligibilityInfo) => {
    if (!student.exists) return 'destructive';
    if (student.is_already_registered) return 'secondary';
    if (student.is_eligible) return 'default';
    return 'outline';
};

// Get badge text for eligibility status
const getEligibilityBadgeText = (student: StudentEligibilityInfo) => {
    if (!student.exists) return 'Not Found';
    if (student.is_already_registered) return 'Already Registered';
    if (student.is_eligible) return 'Eligible';
    return 'Ineligible';
};

// Get icon for student status
const getStudentStatusIcon = (student: StudentEligibilityInfo) => {
    if (!student.exists) return XCircle;
    if (student.is_already_registered) return AlertCircle;
    if (student.is_eligible) return CheckCircle;
    return XCircle;
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger as-child>
            <Button variant="default" size="sm">
                <UserPlus class="mr-2 h-4 w-4" />
                Add Students
            </Button>
        </DialogTrigger>
        <DialogContent class="flex max-h-[90vh] max-w-4xl flex-col overflow-hidden">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Add Students to Course
                </DialogTitle>
                <DialogDescription> Enter student IDs separated by whitespace to search and add eligible students to this course offering. </DialogDescription>
            </DialogHeader>

            <!-- Search Input -->
            <div class="space-y-4">
                <div class="space-y-2">
                    <Label for="student-search">Student IDs</Label>
                    <div class="relative">
                        <Search class="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                        <Input id="student-search" v-model="searchInput" placeholder="Enter student IDs separated by spaces (e.g., ST001 ST002 ST003)" class="pl-10" :disabled="isSearching || isRegistering" />
                        <Loader2 v-if="isSearching" class="text-muted-foreground absolute top-3 right-3 h-4 w-4 animate-spin" />
                    </div>
                    <p class="text-muted-foreground text-sm">Eligible students will be automatically selected for registration</p>
                </div>
            </div>

            <!-- Search Results -->
            <div v-if="hasSearched" class="flex flex-1 flex-col space-y-4 overflow-hidden">
                <!-- Summary -->
                <div v-if="searchResults.length > 0" class="flex flex-wrap gap-2 text-sm">
                    <Badge v-if="eligibleStudents.length > 0" variant="default"> {{ eligibleStudents.length }} Eligible </Badge>
                    <Badge v-if="alreadyRegisteredStudents.length > 0" variant="secondary"> {{ alreadyRegisteredStudents.length }} Already Registered </Badge>
                    <Badge v-if="ineligibleStudents.length > 0" variant="outline"> {{ ineligibleStudents.length }} Ineligible </Badge>
                    <Badge v-if="nonExistentStudents.length > 0" variant="destructive"> {{ nonExistentStudents.length }} Not Found </Badge>
                </div>

                <!-- Results List -->
                <div class="flex-1 space-y-3 overflow-y-auto rounded-md border p-4">
                    <div v-if="searchResults.length === 0" class="text-muted-foreground py-8 text-center">
                        <Users class="mx-auto mb-2 h-12 w-12" />
                        <p>No students found</p>
                    </div>

                    <div v-else class="space-y-2">
                        <div v-for="student in searchResults" :key="student.student_id" class="hover:bg-accent/50 flex items-center justify-between rounded-lg border p-3">
                            <div class="flex items-center space-x-3">
                                <!-- Checkbox for eligible students only -->
                                <Checkbox
                                    v-if="student.exists && student.is_eligible && !student.is_already_registered"
                                    :id="`student-${student.student_id}`"
                                    :model-value="selectedStudentIds.has(student.student_id)"
                                    @update:model-value="(value: boolean) => toggleStudentSelection(student.student_id, value)"
                                    :disabled="isRegistering"
                                />
                                <div v-else class="h-4 w-4"></div>

                                <!-- Student Status Icon -->
                                <component
                                    :is="getStudentStatusIcon(student)"
                                    :class="[
                                        'h-5 w-5',
                                        student.exists && student.is_eligible && !student.is_already_registered
                                            ? 'text-green-500'
                                            : student.exists && student.is_already_registered
                                              ? 'text-yellow-500'
                                              : student.exists && !student.is_eligible
                                                ? 'text-orange-500'
                                                : 'text-red-500',
                                    ]"
                                />

                                <!-- Student Info -->
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ student.student_id }}</span>
                                        <Badge :variant="getEligibilityBadgeVariant(student)">
                                            {{ getEligibilityBadgeText(student) }}
                                        </Badge>
                                    </div>

                                    <div v-if="student.exists && student.student_data" class="text-muted-foreground text-sm">
                                        <p>{{ student.student_data.full_name }}</p>
                                        <p class="flex items-center gap-2">
                                            <span>{{ student.student_data.email }}</span>
                                            <span v-if="student.major_code">• {{ student.major_code }}</span>
                                        </p>
                                    </div>

                                    <!-- Eligibility Reasons -->
                                    <div class="mt-1 space-y-0.5 text-xs">
                                        <template v-if="student.exists && !student.is_eligible && !student.is_already_registered">
                                            <div v-for="(reason, index) in student.eligibility_reasons" :key="index" class="flex items-start gap-1 text-orange-600 dark:text-orange-400">
                                                <AlertCircle class="mt-0.5 h-3 w-3 shrink-0" />
                                                <span>{{ reason }}</span>
                                            </div>
                                        </template>
                                        <template v-else-if="student.is_already_registered">
                                            <div v-for="(reason, index) in student.eligibility_reasons" :key="index" class="flex items-start gap-1 text-yellow-600 dark:text-yellow-500">
                                                <AlertCircle class="mt-0.5 h-3 w-3 shrink-0" />
                                                <span>{{ reason }}</span>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div v-for="(reason, index) in student.eligibility_reasons" :key="index" class="text-muted-foreground">
                                                {{ reason }}
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="border-t pt-4">
                <div class="flex w-full items-center justify-between">
                    <div v-if="selectedEligibleCount > 0" class="text-muted-foreground text-sm">{{ selectedEligibleCount }} student(s) selected for registration</div>
                    <div v-else class="text-muted-foreground text-sm">No students selected</div>

                    <div class="flex gap-2">
                        <DialogClose as-child>
                            <Button variant="outline" :disabled="isRegistering"> Cancel </Button>
                        </DialogClose>
                        <Button @click="handleBulkRegistration" :disabled="!canRegister" :class="{ 'opacity-50': !canRegister }">
                            <Loader2 v-if="isRegistering" class="mr-2 h-4 w-4 animate-spin" />
                            <UserPlus v-else class="mr-2 h-4 w-4" />
                            Register {{ selectedEligibleCount > 0 ? selectedEligibleCount : '' }} Student{{ selectedEligibleCount !== 1 ? 's' : '' }}
                        </Button>
                    </div>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
