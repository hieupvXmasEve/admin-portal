<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useApi } from '@/composables/useApiRequest';
import { createColumns } from '@/lib/table-utils';
import type { StudentEligibilityInfo } from '@/types/models';
import { Head, Link, router } from '@inertiajs/vue3';
import { debounce } from 'lodash-es';
import { AlertCircle, CheckCircle, Loader2, Search, UserPlus, Users, XCircle } from 'lucide-vue-next';
import { computed, h, nextTick, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Event {
    id: number;
    title: string;
    description: string | null;
    start_time: string;
    end_time: string;
    location: string;
    status: 'draft' | 'published' | 'cancelled' | 'completed';
    gold_reward_amount: number;
    max_participants: number | null;
    is_manual: boolean;
    is_historical: boolean;
}

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    phone: string | null;
    program: {
        id: number;
        name: string;
        code: string;
    } | null;
    specialization: {
        id: number;
        name: string;
        code: string;
    } | null;
    status: string;
    academic_status: string;
}

interface Participant {
    id: number;
    student_id: number;
    status: 'registered' | 'checked_in' | 'completed' | 'cancelled';
    registered_at: string;
    checkin_time: string | null;
    gold_awarded: boolean;
    awarded_at: string | null;
    created_at: string;
    student: Student | null;
}

interface ParticipantsPagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Props {
    event: Event;
    can: {
        manage_participants: boolean;
    };
    participants: Participant[];
    participantsPagination: ParticipantsPagination;
    participantFilters: {
        status: string;
        search: string;
        gold_awarded: string;
    };
}

const props = defineProps<Props>();
// API
const api = useApi();

// State
const activeTab = ref('add-students');
const loading = ref(false);
const searchInput = ref('');
const searchResults = ref<StudentEligibilityInfo[]>([]);
const selectedStudentIds = ref(new Set<string>());
const isSearching = ref(false);
const hasSearched = ref(false);
const isAddingParticipants = ref(false);
const selectedParticipants = ref<number[]>([]);
const participantsTableRef = ref<InstanceType<typeof DataTable> | null>(null);
const participantsLoading = ref(false);
const participantsPerPage = ref(props.participantsPagination?.per_page ?? 15);

const participantsData = computed(() => props.participants ?? []);
const participantsPagination = computed(() => props.participantsPagination);

const participantFilters = ref({
    status: props.participantFilters?.status ?? '',
    search: props.participantFilters?.search ?? '',
    gold_awarded: props.participantFilters?.gold_awarded ?? '',
});
const isSyncingParticipantFilters = ref(false);

// Dialogs
const showAddDialog = ref(false);
const showBulkUpdateDialog = ref(false);
const showRemoveDialog = ref(false);
const addStatus = ref('completed');
const bulkUpdateStatus = ref('completed');

// Statistics
const statistics = ref({
    total_added: 0,
    completed_count: 0,
    registered_count: 0,
    cancelled_count: 0,
    gold_awarded_count: 0,
    total_gold_distributed: 0,
    completion_rate: 0,
    gold_award_rate: 0,
});

// Computed
const eligibleStudents = computed(() => searchResults.value.filter((student) => student.exists && student.is_eligible && !student.is_already_registered));
const alreadyRegisteredStudents = computed(() => searchResults.value.filter((student) => student.exists && student.is_already_registered));
const ineligibleStudents = computed(() => searchResults.value.filter((student) => student.exists && !student.is_eligible && !student.is_already_registered));
const nonExistentStudents = computed(() => searchResults.value.filter((student) => !student.exists));

const selectedEligibleCount = computed(() => {
    return eligibleStudents.value.filter((student) => selectedStudentIds.value.has(student.student_id)).length;
});

const selectedEligibleStudents = computed(() => eligibleStudents.value.filter((student) => selectedStudentIds.value.has(student.student_id)));
const hasSelectedEligibleStudents = computed(() => selectedEligibleCount.value > 0);
const canAddParticipants = computed(() => selectedEligibleCount.value > 0 && !isAddingParticipants.value);
const hasSelectedParticipants = computed(() => selectedParticipants.value.length > 0);

const formatDateTime = (dateTime?: string | null) => {
    if (!dateTime) return '—';
    return new Date(dateTime).toLocaleString();
};

const participantsColumns = createColumns<Participant>(
    [
        {
            id: 'student_id',
            header: 'Student ID',
            accessorFn: (row) => row.student?.student_id ?? '—',
            cell: ({ row }) => row.original.student?.student_id ?? '—',
        },
        {
            id: 'student_name',
            header: 'Student',
            accessorFn: (row) => row.student?.full_name ?? '—',
            cell: ({ row }) => row.original.student?.full_name ?? '—',
        },
        {
            id: 'student_email',
            header: 'Email',
            accessorFn: (row) => row.student?.email ?? '—',
            cell: ({ row }) => row.original.student?.email ?? '—',
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => h(Badge, { variant: getStatusVariant(row.original.status) }, { default: () => row.original.status.replace('_', ' ') }),
        },
        {
            id: 'registered_at',
            header: 'Registered At',
            cell: ({ row }) => formatDateTime(row.original.registered_at ?? row.original.created_at),
        },
        {
            id: 'gold_awarded',
            header: 'Gold',
            cell: ({ row }) => (row.original.gold_awarded ? h(Badge, { variant: 'default' }, { default: () => 'Awarded' }) : h(Badge, { variant: 'outline' }, { default: () => 'Pending' })),
        },
    ],
    { enableSelection: true },
);

// Methods
const resetSearchState = () => {
    searchResults.value = [];
    selectedStudentIds.value = new Set();
    hasSearched.value = false;
};

const searchStudents = async (studentIdsInput: string) => {
    const trimmedInput = studentIdsInput.trim();

    if (!trimmedInput) {
        resetSearchState();
        return;
    }

    isSearching.value = true;
    hasSearched.value = true;
    selectedStudentIds.value = new Set();

    try {
        const response = await api.post<{ students: StudentEligibilityInfo[] }>(route('api.admin.events.manual-participants.search-students', props.event.id), {
            student_ids: trimmedInput,
        });

        if (response.data?.value?.success) {
            const students = response.data.value.data.students;
            searchResults.value = students;

            const newSelectedIds = new Set<string>();
            students
                .filter((student) => student.exists && student.is_eligible && !student.is_already_registered)
                .forEach((student) => {
                    newSelectedIds.add(student.student_id);
                });
            selectedStudentIds.value = newSelectedIds;
        } else {
            toast.error(response.data?.value?.message || 'Failed to search students');
            searchResults.value = [];
        }
    } catch (error) {
        console.error('Error searching students:', error);
        toast.error('Failed to search students');
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

const debouncedSearch = debounce((studentIdsInput: string) => {
    void searchStudents(studentIdsInput);
}, 500);

const debouncedParticipantSearch = debounce(() => {
    if (isSyncingParticipantFilters.value) return;
    applyParticipantFilters({ page: 1 });
}, 500);

const toggleStudentSelection = (studentId: string, isSelected: boolean) => {
    const newSelectedIds = new Set(selectedStudentIds.value);
    if (isSelected) {
        newSelectedIds.add(studentId);
    } else {
        newSelectedIds.delete(studentId);
    }
    selectedStudentIds.value = newSelectedIds;
};

const handleParticipantSelection = (rows: Participant[]) => {
    selectedParticipants.value = rows.map((row) => row.id);
};

const buildParticipantQuery = (overrides: Record<string, unknown> = {}) => {
    const query: Record<string, unknown> = {
        status: participantFilters.value.status || undefined,
        search: participantFilters.value.search || undefined,
        gold_awarded: participantFilters.value.gold_awarded || undefined,
        per_page: overrides.per_page ?? participantsPerPage.value ?? undefined,
        page: overrides.page,
    };

    Object.assign(query, overrides);

    return Object.fromEntries(Object.entries(query).filter(([, value]) => value !== undefined));
};

const applyParticipantFilters = (overrides: Record<string, unknown> = {}) => {
    const query = buildParticipantQuery(overrides);

    router.visit(route('events.manage-participants', props.event.id), {
        method: 'get',
        data: query,
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onStart: () => {
            participantsLoading.value = true;
        },
        onFinish: () => {
            participantsLoading.value = false;
            selectedParticipants.value = [];
            participantsTableRef.value?.clearSelection?.();
        },
    });
};

const handleParticipantsNavigate = (url: string) => {
    if (!url) return;

    participantsLoading.value = true;
    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onFinish: () => {
            participantsLoading.value = false;
            selectedParticipants.value = [];
            participantsTableRef.value?.clearSelection?.();
        },
    });
};

const handleParticipantsPageSizeChange = (pageSize: number) => {
    participantsPerPage.value = pageSize;
    applyParticipantFilters({ per_page: pageSize, page: 1 });
};

const getEligibilityBadgeVariant = (student: StudentEligibilityInfo) => {
    if (!student.exists) return 'destructive';
    if (student.is_already_registered) return 'secondary';
    if (student.is_eligible) return 'default';
    return 'outline';
};

const getEligibilityBadgeText = (student: StudentEligibilityInfo) => {
    if (!student.exists) return 'Not Found';
    if (student.is_already_registered) return 'Already Registered';
    if (student.is_eligible) return 'Eligible';
    return 'Ineligible';
};

const getStudentStatusIcon = (student: StudentEligibilityInfo) => {
    if (!student.exists) return XCircle;
    if (student.is_already_registered) return AlertCircle;
    if (student.is_eligible) return CheckCircle;
    return XCircle;
};

const fetchStatistics = async () => {
    try {
        const response = await fetch(route('api.admin.events.manual-participants.statistics', props.event.id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            const data = await response.json();
            statistics.value = data.data;
        }
    } catch (error) {
        console.error('Failed to fetch statistics:', error);
    }
};

const addParticipants = async () => {
    if (!canAddParticipants.value) return;

    const studentDatabaseIds = selectedEligibleStudents.value.map((student) => student.student_data?.id).filter((id): id is number => typeof id === 'number');

    if (studentDatabaseIds.length === 0) {
        toast.error('Unable to add students because their records could not be found.');
        return;
    }

    isAddingParticipants.value = true;
    try {
        const response = await api.post(route('api.admin.events.manual-participants.add', props.event.id), {
            student_ids: studentDatabaseIds,
            status: addStatus.value,
        });

        if (response.data?.value?.success) {
            const data = response.data.value.data;
            showAddDialog.value = false;
            toast.success(response.data.value.message || `Added ${data.added.length} participants`);

            await searchStudents(searchInput.value);
            await router.reload({
                only: ['participants', 'participantsPagination'],
                onStart: () => {
                    participantsLoading.value = true;
                },
                onFinish: () => {
                    participantsLoading.value = false;
                    selectedParticipants.value = [];
                    participantsTableRef.value?.clearSelection?.();
                },
            });

            await fetchStatistics();
        } else {
            toast.error(response.data?.value?.message || 'Failed to add participants');
        }
    } catch (error) {
        console.error('Failed to add participants:', error);
        toast.error('Failed to add participants');
    } finally {
        isAddingParticipants.value = false;
    }
};

const bulkUpdateParticipants = async () => {
    if (!hasSelectedParticipants.value) return;

    loading.value = true;
    try {
        const response = await fetch(route('api.admin.events.manual-participants.bulk-update-status', props.event.id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                participant_ids: selectedParticipants.value,
                status: bulkUpdateStatus.value,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            showBulkUpdateDialog.value = false;
            selectedParticipants.value = [];

            await router.reload({
                only: ['participants', 'participantsPagination'],
                onStart: () => {
                    participantsLoading.value = true;
                },
                onFinish: () => {
                    participantsLoading.value = false;
                    participantsTableRef.value?.clearSelection?.();
                },
            });

            await fetchStatistics();

            toast.success(`Updated ${data.data.updated.length} participants`);
        } else {
            const errorData = await response.json();
            toast.error('Failed to update participants: ' + errorData.message);
        }
    } catch (error) {
        console.error('Failed to update participants:', error);
        toast.error('Failed to update participants');
    } finally {
        loading.value = false;
    }
};

const removeParticipants = async () => {
    if (!hasSelectedParticipants.value) return;

    loading.value = true;
    try {
        const response = await fetch(route('api.admin.events.manual-participants.remove', props.event.id), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                participant_ids: selectedParticipants.value,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            showRemoveDialog.value = false;
            selectedParticipants.value = [];

            await searchStudents(searchInput.value);
            await router.reload({
                only: ['participants', 'participantsPagination'],
                onStart: () => {
                    participantsLoading.value = true;
                },
                onFinish: () => {
                    participantsLoading.value = false;
                    participantsTableRef.value?.clearSelection?.();
                },
            });

            await fetchStatistics();

            toast.success(`Removed ${data.data.removed.length} participants`);
        } else {
            const errorData = await response.json();
            toast.error('Failed to remove participants: ' + errorData.message);
        }
    } catch (error) {
        console.error('Failed to remove participants:', error);
        toast.error('Failed to remove participants');
    } finally {
        loading.value = false;
    }
};

const getStatusVariant = (status: string): 'default' | 'destructive' | 'outline' | 'secondary' => {
    const variants = {
        registered: 'secondary' as const,
        checked_in: 'default' as const,
        completed: 'outline' as const,
        cancelled: 'destructive' as const,
    };
    return variants[status as keyof typeof variants] || 'secondary';
};

// Watchers
watch(searchInput, (newValue) => {
    debouncedSearch(newValue);
});

watch(
    () => props.participantsPagination?.per_page,
    (newPerPage) => {
        if (typeof newPerPage === 'number') {
            participantsPerPage.value = newPerPage;
        }
    },
);

watch(
    () => props.participants,
    () => {
        participantsLoading.value = false;
        selectedParticipants.value = [];
        participantsTableRef.value?.clearSelection?.();
    },
);

watch(
    () => props.participantFilters,
    async (newFilters) => {
        isSyncingParticipantFilters.value = true;
        participantFilters.value = {
            status: newFilters?.status ?? '',
            search: newFilters?.search ?? '',
            gold_awarded: newFilters?.gold_awarded ?? '',
        };
        await nextTick();
        isSyncingParticipantFilters.value = false;
    },
    { deep: true },
);

watch(
    () => participantFilters.value.status,
    (newValue, oldValue) => {
        if (isSyncingParticipantFilters.value || newValue === oldValue) return;
        applyParticipantFilters({ page: 1 });
    },
);

watch(
    () => participantFilters.value.gold_awarded,
    (newValue, oldValue) => {
        if (isSyncingParticipantFilters.value || newValue === oldValue) return;
        applyParticipantFilters({ page: 1 });
    },
);

watch(
    () => participantFilters.value.search,
    () => {
        if (isSyncingParticipantFilters.value) return;
        debouncedParticipantSearch();
    },
);

// Lifecycle
onMounted(() => {
    void fetchStatistics();
});
</script>

<template>
    <Head title="Manage Participants" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl leading-tight font-semibold text-gray-800">Manage Participants</h2>
                <p class="text-sm text-gray-600">{{ event.title }}</p>
            </div>
            <div class="flex items-center space-x-2">
                <Link :href="route('events.show', event.id)">
                    <Button variant="outline">Back to Event</Button>
                </Link>
            </div>
        </div>

        <!-- Statistics Card -->
        <Card>
            <CardHeader>
                <CardTitle>Participation Statistics</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ statistics.total_added }}</div>
                        <div class="text-sm text-gray-500">Total Added</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ statistics.completed_count }}</div>
                        <div class="text-sm text-gray-500">Completed</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-600">{{ statistics.gold_awarded_count }}</div>
                        <div class="text-sm text-gray-500">Gold Awarded</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600">{{ statistics.total_gold_distributed }}</div>
                        <div class="text-sm text-gray-500">Total Gold</div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Main Content -->
        <Tabs v-model="activeTab" class="w-full">
            <TabsList class="grid w-full grid-cols-2">
                <TabsTrigger value="add-students">Add Students</TabsTrigger>
                <TabsTrigger value="manage-participants">Manage Participants</TabsTrigger>
            </TabsList>

            <!-- Add Students Tab -->
            <TabsContent value="add-students" class="space-y-4">
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Search Students</CardTitle>
                            <Button class="bg-green-600 hover:bg-green-700" @click="showAddDialog = true" :disabled="!hasSelectedEligibleStudents || isSearching || isAddingParticipants">
                                <UserPlus class="mr-2 h-4 w-4" />
                                Add Selected ({{ selectedEligibleCount }})
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-6">
                            <div class="space-y-2">
                                <Label for="student-search">Student IDs</Label>
                                <div class="relative">
                                    <Search class="text-muted-foreground absolute top-3 left-3 h-4 w-4" />
                                    <Input id="student-search" v-model="searchInput" placeholder="Enter student IDs separated by spaces (e.g., ST001 ST002)" class="pl-10" :disabled="isSearching || isAddingParticipants" />
                                    <Loader2 v-if="isSearching" class="text-muted-foreground absolute top-3 right-3 h-4 w-4 animate-spin" />
                                </div>
                                <p class="text-muted-foreground text-sm">Eligible students will be automatically selected.</p>
                            </div>

                            <div v-if="hasSearched" class="space-y-4">
                                <div v-if="searchResults.length > 0" class="flex flex-wrap gap-2 text-sm">
                                    <Badge v-if="eligibleStudents.length > 0" variant="default"> {{ eligibleStudents.length }} Eligible </Badge>
                                    <Badge v-if="alreadyRegisteredStudents.length > 0" variant="secondary"> {{ alreadyRegisteredStudents.length }} Already Registered </Badge>
                                    <Badge v-if="ineligibleStudents.length > 0" variant="outline"> {{ ineligibleStudents.length }} Ineligible </Badge>
                                    <Badge v-if="nonExistentStudents.length > 0" variant="destructive"> {{ nonExistentStudents.length }} Not Found </Badge>
                                </div>

                                <div class="max-h-96 space-y-3 overflow-y-auto rounded-md border p-4">
                                    <div v-if="searchResults.length === 0" class="text-muted-foreground py-8 text-center">
                                        <Users class="mx-auto mb-2 h-12 w-12" />
                                        <p>No students found</p>
                                    </div>

                                    <div v-else class="space-y-2">
                                        <div v-for="student in searchResults" :key="student.student_id" class="hover:bg-accent/50 flex items-center justify-between rounded-lg border p-3">
                                            <div class="flex w-full items-center space-x-3">
                                                <Checkbox
                                                    v-if="student.exists && student.is_eligible && !student.is_already_registered"
                                                    :id="`student-${student.student_id}`"
                                                    :model-value="selectedStudentIds.has(student.student_id)"
                                                    @update:model-value="(value: boolean) => toggleStudentSelection(student.student_id, value)"
                                                    :disabled="isAddingParticipants"
                                                />
                                                <div v-else class="h-4 w-4"></div>

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

                                                    <div class="text-muted-foreground mt-1 text-xs">
                                                        <span v-for="(reason, index) in student.eligibility_reasons" :key="index">
                                                            {{ reason }}
                                                            <span v-if="index < student.eligibility_reasons.length - 1"> • </span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-muted-foreground text-sm">
                                    <span v-if="selectedEligibleCount > 0"> {{ selectedEligibleCount }} eligible student{{ selectedEligibleCount === 1 ? '' : 's' }} selected for addition. </span>
                                    <span v-else>No eligible students selected.</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- Manage Participants Tab -->
            <TabsContent value="manage-participants" class="space-y-4">
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Event Participants</CardTitle>
                            <div class="flex space-x-2">
                                <Button @click="showBulkUpdateDialog = true" :disabled="!hasSelectedParticipants || loading" variant="outline"> Update Status ({{ selectedParticipants.length }}) </Button>
                                <Button @click="showRemoveDialog = true" :disabled="!hasSelectedParticipants || loading" variant="destructive"> Remove ({{ selectedParticipants.length }}) </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <!-- Participant Filters -->
                        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <Label for="participant-search">Search</Label>
                                <Input id="participant-search" v-model="participantFilters.search" placeholder="Student name or ID..." />
                            </div>
                            <div>
                                <Label for="participant-status">Status</Label>
                                <Select v-model="participantFilters.status">
                                    <SelectTrigger>
                                        <SelectValue placeholder="All Statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        <SelectItem value="registered">Registered</SelectItem>
                                        <SelectItem value="checked_in">Checked In</SelectItem>
                                        <SelectItem value="completed">Completed</SelectItem>
                                        <SelectItem value="cancelled">Cancelled</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label for="gold-awarded">Gold Status</Label>
                                <Select v-model="participantFilters.gold_awarded">
                                    <SelectTrigger>
                                        <SelectValue placeholder="All" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All</SelectItem>
                                        <SelectItem value="true">Awarded</SelectItem>
                                        <SelectItem value="false">Not Awarded</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <!-- Participants Table -->
                        <DataTable
                            ref="participantsTableRef"
                            :data="participantsData"
                            :columns="participantsColumns"
                            :loading="participantsLoading"
                            :enable-row-selection="true"
                            empty-message="No participants found."
                            @selection-change="handleParticipantSelection"
                        />

                        <DataPagination
                            v-if="participantsPagination.total > participantsPagination.per_page"
                            :pagination-data="participantsPagination"
                            item-name="participants"
                            @navigate="handleParticipantsNavigate"
                            @page-size-change="handleParticipantsPageSizeChange"
                        />
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>

        <!-- Add Participants Dialog -->
        <Dialog :open="showAddDialog" @update:open="showAddDialog = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Participants</DialogTitle>
                    <DialogDescription> Add {{ selectedEligibleCount }} eligible student{{ selectedEligibleCount === 1 ? '' : 's' }} to the event. </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label for="add-status">Initial Status</Label>
                        <Select v-model="addStatus">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="registered">Registered</SelectItem>
                                <SelectItem value="completed">Completed (with gold reward)</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="mt-1 text-sm text-gray-500">
                            <span v-if="addStatus === 'completed'"> Students will be marked as completed and receive {{ event.gold_reward_amount }} gold immediately. </span>
                            <span v-else> Students will be registered but not receive gold until marked as completed. </span>
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddDialog = false">Cancel</Button>
                    <Button @click="addParticipants" :disabled="!canAddParticipants">
                        <Loader2 v-if="isAddingParticipants" class="mr-2 h-4 w-4 animate-spin" />
                        <span>Add Participants</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Bulk Update Dialog -->
        <Dialog :open="showBulkUpdateDialog" @update:open="showBulkUpdateDialog = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Update Participant Status</DialogTitle>
                    <DialogDescription> Update the status of {{ selectedParticipants.length }} selected participants. </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <Label for="bulk-status">New Status</Label>
                        <Select v-model="bulkUpdateStatus">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="registered">Registered</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="cancelled">Cancelled</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="mt-1 text-sm text-gray-500">
                            <span v-if="bulkUpdateStatus === 'completed'"> Participants will receive gold rewards if not already awarded. </span>
                            <span v-else-if="bulkUpdateStatus === 'cancelled'"> Any awarded gold will be reclaimed from participants. </span>
                        </p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showBulkUpdateDialog = false">Cancel</Button>
                    <Button @click="bulkUpdateParticipants" :disabled="loading"> Update Status </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Remove Participants Dialog -->
        <Dialog :open="showRemoveDialog" @update:open="showRemoveDialog = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove Participants</DialogTitle>
                    <DialogDescription> Are you sure you want to remove {{ selectedParticipants.length }} selected participants? Any awarded gold will be reclaimed. This action cannot be undone. </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="showRemoveDialog = false">Cancel</Button>
                    <Button variant="destructive" @click="removeParticipants" :disabled="loading"> Remove Participants </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
