<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import DebouncedInput from '@/components/DebouncedInput.vue';
import StudentCombobox from '@/components/StudentCombobox.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import type { PaginatedResponse } from '@/types';
import type { Club, ClubMember, Student } from '@/types/models';
import { systemRoutes } from '@/utils/routes';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { ArrowLeft, Building, Calendar, Crown, Edit, Mail, Phone, Shield, User, UserPlus, Users, X } from 'lucide-vue-next';
import { computed, h, onMounted, onUnmounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    club: Club;
    members: PaginatedResponse<ClubMember>;
    filters?: {
        search?: string;
        status?: string;
        role?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
}

const props = defineProps<Props>();

// Tab state management with URL parameters
const validTabs = ['overview', 'members'] as const;
type ValidTab = (typeof validTabs)[number];

const getCurrentTabFromURL = (): ValidTab => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab') as ValidTab;
    return validTabs.includes(tabParam) ? tabParam : 'overview';
};

const currentTab = ref<ValidTab>(getCurrentTabFromURL());

const updateTabInURL = (newTab: ValidTab) => {
    const url = new URL(window.location.href);
    if (newTab === 'overview') {
        url.searchParams.delete('tab');
    } else {
        url.searchParams.set('tab', newTab);
    }
    router.visit(url.pathname + url.search, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: [],
    });
};

const handleTabChange = (newTab: string | number) => {
    const tabValue = String(newTab) as ValidTab;
    if (validTabs.includes(tabValue)) {
        currentTab.value = tabValue;
        updateTabInURL(tabValue);
    }
};

onMounted(() => {
    const handlePopState = () => {
        const newTab = getCurrentTabFromURL();
        currentTab.value = newTab;
    };
    window.addEventListener('popstate', handlePopState);
    onUnmounted(() => {
        window.removeEventListener('popstate', handlePopState);
    });
});

// Member data and filters
const memberData = computed(() => props.members.data);

// Group members by status
const groupedMembers = computed(() => {
    const groups = {
        active: [] as ClubMember[],
        pending: [] as ClubMember[],
        rejected: [] as ClubMember[],
        left: [] as ClubMember[],
        banned: [] as ClubMember[],
    };

    memberData.value.forEach(member => {
        if (member.status in groups) {
            groups[member.status as keyof typeof groups].push(member);
        }
    });

    return groups;
});

const statusLabels: Record<string, string> = {
    active: 'Active Members',
    pending: 'Pending Applications',
    rejected: 'Rejected',
    left: 'Left Club',
    banned: 'Banned',
};

const statusCounts = computed(() => ({
    active: groupedMembers.value.active.length,
    pending: groupedMembers.value.pending.length,
    rejected: groupedMembers.value.rejected.length,
    left: groupedMembers.value.left.length,
    banned: groupedMembers.value.banned.length,
}));
const filters = ref({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    role: props.filters?.role || '',
    sort: props.filters?.sort || '',
    direction: props.filters?.direction || 'asc',
    per_page: props.filters?.per_page || 15,
});

const hasActiveFilters = computed(() => {
    return Object.entries(filters.value).some(([key, value]) => {
        if (key === 'per_page' || key === 'sort' || key === 'direction') return false;
        return value && value !== '';
    });
});

// President assignment dialog
const assignPresidentDialogOpen = ref(false);
const assignPresidentForm = ref({
    student_id: '',
    assignment_reason: '',
});
const assignPresidentErrors = ref<any>({});
const isAssigningPresident = ref(false);

const openAssignPresidentDialog = () => {
    assignPresidentDialogOpen.value = true;
};

const closeAssignPresidentDialog = () => {
    assignPresidentDialogOpen.value = false;
    assignPresidentForm.value = {
        student_id: '',
        assignment_reason: '',
    };
    assignPresidentErrors.value = {};
};

const handleStudentSelect = (student: any) => {
    if (student) {
        assignPresidentForm.value.student_id = student.id.toString();
    }
};

const submitAssignPresident = () => {
    if (!assignPresidentForm.value.student_id) {
        assignPresidentErrors.value = { student_id: 'Student is required' };
        return;
    }

    isAssigningPresident.value = true;
    assignPresidentErrors.value = {};

    const formData = {
        student_id: Number(assignPresidentForm.value.student_id),
        assignment_reason: assignPresidentForm.value.assignment_reason,
    };

    router.post(systemRoutes.clubs.assignPresident(props.club.id), formData, {
        preserveScroll: true,
        onSuccess: () => {
            isAssigningPresident.value = false;
            closeAssignPresidentDialog();
            toast.success('President assigned successfully');
        },
        onError: (errors) => {
            assignPresidentErrors.value = errors;
            isAssigningPresident.value = false;
            toast.error('Failed to assign president');
        },
        onFinish: () => {
            isAssigningPresident.value = false;
        },
    });
};

// Add member dialog
const addMemberDialogOpen = ref(false);
const addMemberForm = ref({
    student_id: '',
    role: 'member',
    notes: '',
});
const addMemberErrors = ref<any>({});
const isAddingMember = ref(false);

const openAddMemberDialog = () => {
    addMemberDialogOpen.value = true;
};

const closeAddMemberDialog = () => {
    addMemberDialogOpen.value = false;
    addMemberForm.value = {
        student_id: '',
        role: 'member',
        notes: '',
    };
    addMemberErrors.value = {};
};

const handleAddMemberStudentSelect = (student: Student | null) => {
    if (student) {
        addMemberForm.value.student_id = student.id.toString();
    }
};

const submitAddMember = () => {
    if (!addMemberForm.value.student_id) {
        addMemberErrors.value = { student_id: 'Student is required' };
        return;
    }

    isAddingMember.value = true;
    addMemberErrors.value = {};

    const formData = {
        student_id: Number(addMemberForm.value.student_id),
        role: addMemberForm.value.role,
        notes: addMemberForm.value.notes || null,
    };

    router.post(systemRoutes.clubs.addMember(props.club.id), formData, {
        preserveScroll: true,
        onSuccess: () => {
            isAddingMember.value = false;
            closeAddMemberDialog();
            toast.success('Member added successfully');
        },
        onError: (errors) => {
            addMemberErrors.value = errors;
            isAddingMember.value = false;
            toast.error('Failed to add member');
        },
        onFinish: () => {
            isAddingMember.value = false;
        },
    });
};

// Event handlers
const goBack = () => {
    router.visit(systemRoutes.clubs.index());
};

const goToEdit = () => {
    router.visit(systemRoutes.clubs.edit(props.club.id));
};

// Filter handlers
const handleMemberSearch = (value: string | number) => {
    filters.value.search = String(value);
    applyMemberFilters();
};

const handleStatusFilter = (value: any) => {
    filters.value.status = String(value || '');
    applyMemberFilters();
};

const handleRoleFilter = (value: any) => {
    filters.value.role = String(value || '');
    applyMemberFilters();
};

const handlePerPageChange = (value: any) => {
    filters.value.per_page = parseInt(String(value || '15'));
    applyMemberFilters();
};

const resetMemberFilters = () => {
    filters.value = {
        search: '',
        status: '',
        role: '',
        sort: '',
        direction: 'asc',
        per_page: 15,
    };
    applyMemberFilters();
};

const applyMemberFilters = () => {
    const cleanFilters = Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== '' && value !== null));

    router.visit(systemRoutes.clubs.show(props.club.id), {
        data: cleanFilters,
        preserveState: true,
        preserveScroll: true,
        only: ['members'],
    });
};

const handlePaginationNavigate = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        only: ['members'],
    });
};
const handlePageSizeChange = (pageSize: number) => {
    console.log('Page size changed to:', pageSize);
    filters.value.per_page = pageSize;
    applyMemberFilters();
};

// Member table columns
const memberColumns: ColumnDef<ClubMember>[] = [
    {
        header: 'No',
        id: 'no',
        enableSorting: false,
        cell: ({ row }) => {
            const currentPage = props.members.current_page;
            const perPage = props.members.per_page;
            return (currentPage - 1) * perPage + row.index + 1;
        },
    },
    {
        header: 'Student',
        id: 'student',
        enableSorting: false,
        cell: ({ row }) => {
            const member = row.original;
            const student = member.student;
            if (!student) return h('span', { class: 'text-gray-500' }, 'Unknown');

            return h('div', { class: 'flex items-center gap-3' }, [
                h('div', { class: 'h-8 w-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center' }, [h('span', { class: 'text-xs font-medium' }, student.full_name.charAt(0).toUpperCase())]),
                h('div', [h('div', { class: 'font-medium' }, student.full_name), h('div', { class: 'text-sm text-gray-500' }, student.student_id)]),
            ]);
        },
    },
    {
        header: 'Role',
        accessorKey: 'role',
        enableSorting: true,
        cell: ({ row }) => {
            const role = row.original.role;
            const roleLabels: Record<string, string> = {
                president: 'President',
                vice_president: 'Vice President',
                secretary: 'Secretary',
                treasurer: 'Treasurer',
                member: 'Member',
            };

            const roleConfig: Record<string, { icon: any; variant: any; color?: string }> = {
                president: { icon: Crown, variant: 'purple' },
                vice_president: { icon: Shield, variant: 'indigo' },
                secretary: { icon: User, variant: 'info' },
                treasurer: { icon: User, variant: 'warning' },
                member: { icon: User, variant: 'outline' },
            };

            const config = roleConfig[role] || { icon: User, variant: 'outline' };
            const icon = config.icon;
            const variant = config.variant;

            return h('div', { class: 'flex items-center gap-2' }, [h(icon, { class: 'h-4 w-4' }), h(Badge, { variant }, () => roleLabels[role] || role)]);
        },
    },
    {
        header: 'Status',
        accessorKey: 'status',
        enableSorting: true,
        cell: ({ row }) => {
            const status = row.original.status;
            const statusLabels: Record<string, string> = {
                active: 'Active',
                pending: 'Pending',
                rejected: 'Rejected',
                left: 'Left',
                banned: 'Banned',
            };

            const variants: Record<string, any> = {
                active: 'success',
                pending: 'warning',
                rejected: 'destructive',
                left: 'secondary',
                banned: 'destructive',
            };

            return h(Badge, { variant: variants[status] || 'secondary' }, () => statusLabels[status] || status);
        },
    },
    {
        header: 'Joined',
        accessorKey: 'joined_at',
        enableSorting: true,
        cell: ({ row }) => {
            const joinedAt = row.original.joined_at;
            if (!joinedAt) return h('span', { class: 'text-gray-500 text-sm' }, 'Not joined');

            return h('div', { class: 'text-sm' }, new Date(joinedAt).toLocaleDateString());
        },
    },
    {
        header: 'Participation',
        accessorKey: 'participation_score',
        enableSorting: true,
        cell: ({ row }) => {
            const score = row.original.participation_score || 0;
            return h('div', { class: 'text-sm font-medium' }, score.toString());
        },
    },
];

// Helper functions are now inline in the table columns
</script>

<template>
    <Head :title="`${club.name} - Club Details`" />

    <div class="flex flex-col gap-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Button variant="ghost" size="sm" @click="goBack">
                <ArrowLeft class="h-4 w-4" />
                Back to Clubs
            </Button>
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <div v-if="club.avatar_url" class="h-12 w-12 overflow-hidden rounded-full">
                        <img :src="club.avatar_url" :alt="club.name" class="h-full w-full object-cover" />
                    </div>
                    <div v-else class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700">
                        <span class="text-lg font-medium">{{ club.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold">{{ club.name }}</h1>
                        <p class="text-muted-foreground text-sm">{{ club.campus?.name }} Campus</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button @click="openAssignPresidentDialog" variant="outline" class="gap-2">
                    <Crown class="h-4 w-4" />
                    Assign President
                </Button>
                <Button @click="goToEdit" variant="outline" class="gap-2">
                    <Edit class="h-4 w-4" />
                    Edit Club
                </Button>
            </div>
        </div>

        <Tabs :model-value="currentTab" @update:model-value="handleTabChange" class="w-full">
            <TabsList class="grid w-full grid-cols-2">
                <TabsTrigger value="overview">Club Overview</TabsTrigger>
                <TabsTrigger value="members">Members Management</TabsTrigger>
            </TabsList>

            <!-- Club Overview Tab -->
            <TabsContent value="overview" class="space-y-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Club Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Users class="h-5 w-5" />
                                Club Information
                            </CardTitle>
                            <CardDescription>Basic information about this club</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Club Name</label>
                                <p class="font-medium">{{ club.name }}</p>
                            </div>
                            <div v-if="club.description">
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</label>
                                <p class="text-sm">{{ club.description }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Campus</label>
                                <div class="flex items-center gap-2">
                                    <Building class="h-4 w-4 text-gray-500" />
                                    <span>{{ club.campus?.name || 'No campus' }}</span>
                                </div>
                            </div>
                            <div v-if="club.founded_date">
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Founded</label>
                                <div class="flex items-center gap-2">
                                    <Calendar class="h-4 w-4 text-gray-500" />
                                    <span>{{ new Date(club.founded_date).toLocaleDateString() }}</span>
                                </div>
                            </div>
                            <div class="space-x-2">
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</label>
                                <Badge :variant="club.status === 'active' ? 'default' : 'secondary'">
                                    {{ club.status.charAt(0).toUpperCase() + club.status.slice(1) }}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Contact & Leadership -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Crown class="h-5 w-5" />
                                Leadership & Contact
                            </CardTitle>
                            <CardDescription>Club leadership and contact information</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">President</label>
                                <div v-if="club.president?.student" class="flex items-center gap-2">
                                    <Crown class="h-4 w-4 text-yellow-500" />
                                    <div>
                                        <p class="font-medium">{{ club.president.student.full_name }}</p>
                                        <p class="text-sm text-gray-500">{{ club.president.student.student_id }}</p>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-gray-500">No president assigned</p>
                            </div>
                            <div v-if="club.contact_email">
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Contact Email</label>
                                <div class="flex items-center gap-2">
                                    <Mail class="h-4 w-4 text-gray-500" />
                                    <a :href="`mailto:${club.contact_email}`" class="text-blue-600 hover:underline">
                                        {{ club.contact_email }}
                                    </a>
                                </div>
                            </div>
                            <div v-if="club.contact_phone">
                                <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Contact Phone</label>
                                <div class="flex items-center gap-2">
                                    <Phone class="h-4 w-4 text-gray-500" />
                                    <span>{{ club.contact_phone }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Club Statistics -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Users class="h-5 w-5" />
                            Membership Statistics
                        </CardTitle>
                        <CardDescription>Overview of club membership</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600">{{ club.active_members_count || 0 }}</div>
                                <div class="text-sm text-gray-500">Active Members</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-yellow-600">{{ club.pending_applications_count || 0 }}</div>
                                <div class="text-sm text-gray-500">Pending Applications</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600">{{ club.officers_count || 0 }}</div>
                                <div class="text-sm text-gray-500">Officers</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-600">{{ club.member_count || 0 }}</div>
                                <div class="text-sm text-gray-500">Total Members</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- Members Management Tab -->
            <TabsContent value="members" class="space-y-6">
                <!-- Members Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold">Members Management</h3>
                        <p class="text-muted-foreground text-sm">Manage club members and applications</p>
                    </div>
                    <Button @click="openAddMemberDialog" class="gap-2">
                        <UserPlus class="h-4 w-4" />
                        Add Member
                    </Button>
                </div>

                <!-- Member Filters -->
                <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4">
                    <div class="min-w-[300px] flex-1">
                        <DebouncedInput v-model="filters.search" @debounced="handleMemberSearch" placeholder="Search members by name or student ID..." />
                    </div>

                    <div class="min-w-[150px]">
                        <Select :model-value="filters.status" @update:model-value="handleStatusFilter">
                            <SelectTrigger>
                                <SelectValue placeholder="All Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Status</SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                                <SelectItem value="rejected">Rejected</SelectItem>
                                <SelectItem value="left">Left</SelectItem>
                                <SelectItem value="banned">Banned</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="min-w-[150px]">
                        <Select :model-value="filters.role" @update:model-value="handleRoleFilter">
                            <SelectTrigger>
                                <SelectValue placeholder="All Roles" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Roles</SelectItem>
                                <SelectItem value="president">President</SelectItem>
                                <SelectItem value="vice_president">Vice President</SelectItem>
                                <SelectItem value="secretary">Secretary</SelectItem>
                                <SelectItem value="treasurer">Treasurer</SelectItem>
                                <SelectItem value="member">Member</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="min-w-[100px]">
                        <Select :model-value="filters.per_page.toString()" @update:model-value="handlePerPageChange">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="10">10</SelectItem>
                                <SelectItem value="15">15</SelectItem>
                                <SelectItem value="25">25</SelectItem>
                                <SelectItem value="50">50</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Button variant="outline" size="sm" @click="resetMemberFilters" :disabled="!hasActiveFilters">
                        <X class="h-4 w-4" />
                        Clear
                    </Button>
                </div>

                <!-- Members Grouped by Status -->
                <div v-if="!filters.status" class="space-y-6">
                    <!-- Active Members -->
                    <Card v-if="groupedMembers.active.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Users class="h-5 w-5 text-green-600" />
                                    <span>{{ statusLabels.active }}</span>
                                    <Badge variant="success">{{ statusCounts.active }}</Badge>
                                </div>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DataTable :data="groupedMembers.active" :columns="memberColumns" />
                        </CardContent>
                    </Card>

                    <!-- Pending Applications -->
                    <Card v-if="groupedMembers.pending.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Users class="h-5 w-5 text-yellow-600" />
                                    <span>{{ statusLabels.pending }}</span>
                                    <Badge variant="warning">{{ statusCounts.pending }}</Badge>
                                </div>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DataTable :data="groupedMembers.pending" :columns="memberColumns" />
                        </CardContent>
                    </Card>

                    <!-- Rejected -->
                    <Card v-if="groupedMembers.rejected.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Users class="h-5 w-5 text-red-600" />
                                    <span>{{ statusLabels.rejected }}</span>
                                    <Badge variant="destructive">{{ statusCounts.rejected }}</Badge>
                                </div>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DataTable :data="groupedMembers.rejected" :columns="memberColumns" />
                        </CardContent>
                    </Card>

                    <!-- Left Club -->
                    <Card v-if="groupedMembers.left.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Users class="h-5 w-5 text-gray-600" />
                                    <span>{{ statusLabels.left }}</span>
                                    <Badge variant="secondary">{{ statusCounts.left }}</Badge>
                                </div>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DataTable :data="groupedMembers.left" :columns="memberColumns" />
                        </CardContent>
                    </Card>

                    <!-- Banned -->
                    <Card v-if="groupedMembers.banned.length > 0">
                        <CardHeader>
                            <CardTitle class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <Users class="h-5 w-5 text-red-600" />
                                    <span>{{ statusLabels.banned }}</span>
                                    <Badge variant="destructive">{{ statusCounts.banned }}</Badge>
                                </div>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <DataTable :data="groupedMembers.banned" :columns="memberColumns" />
                        </CardContent>
                    </Card>

                    <!-- No Members -->
                    <Card v-if="memberData.length === 0">
                        <CardContent class="py-12 text-center">
                            <Users class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-4 text-lg font-semibold">No members yet</h3>
                            <p class="text-muted-foreground mt-2">Start by adding members to this club.</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Filtered Members Table (when filter is applied) -->
                <div v-else>
                    <DataTable :data="memberData" :columns="memberColumns" />
                    <!-- Members Pagination -->
                    <DataPagination :pagination-data="members" @navigate="handlePaginationNavigate" @page-size-change="handlePageSizeChange" class="mt-4" />
                </div>
            </TabsContent>
        </Tabs>
    </div>

    <!-- Assign President Dialog -->
    <Dialog :open="assignPresidentDialogOpen" @update:open="assignPresidentDialogOpen = $event">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Crown class="h-5 w-5" />
                    Assign President
                </DialogTitle>
                <DialogDescription> Select a student to assign as the president of {{ club.name }}. </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitAssignPresident" class="space-y-4">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Student *</label>
                        <StudentCombobox v-model="assignPresidentForm.student_id" :error-message="assignPresidentErrors.student_id" placeholder="Search and select a student..." :in-dialog="true" @select="handleStudentSelect" />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">Assignment Reason</label>
                        <Textarea v-model="assignPresidentForm.assignment_reason" placeholder="Optional reason for this assignment..." rows="3" :disabled="isAssigningPresident" />
                    </div>
                </div>

                <DialogFooter class="mt-6">
                    <Button type="button" variant="outline" @click="closeAssignPresidentDialog" :disabled="isAssigningPresident"> Cancel </Button>
                    <Button type="submit" :disabled="!assignPresidentForm.student_id || isAssigningPresident" class="gap-2">
                        <Crown class="h-4 w-4" />
                        {{ isAssigningPresident ? 'Assigning...' : 'Assign President' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Add Member Dialog -->
    <Dialog :open="addMemberDialogOpen" @update:open="addMemberDialogOpen = $event">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <UserPlus class="h-5 w-5" />
                    Add Member
                </DialogTitle>
                <DialogDescription> Add a new member to {{ club.name }}. </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submitAddMember" class="space-y-4">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label class="text-sm font-medium">Student *</Label>
                        <StudentCombobox v-model="addMemberForm.student_id" :error-message="addMemberErrors.student_id" placeholder="Search and select a student..." :in-dialog="true" @select="handleAddMemberStudentSelect" />
                        <p v-if="addMemberErrors.student_id" class="text-destructive text-sm">
                            {{ addMemberErrors.student_id }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label class="text-sm font-medium">Role *</Label>
                        <Select v-model="addMemberForm.role" :disabled="isAddingMember">
                            <SelectTrigger>
                                <SelectValue placeholder="Select role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="member">Member</SelectItem>
                                <SelectItem value="vice_president">Vice President</SelectItem>
                                <SelectItem value="secretary">Secretary</SelectItem>
                                <SelectItem value="treasurer">Treasurer</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="addMemberErrors.role" class="text-destructive text-sm">
                            {{ addMemberErrors.role }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label class="text-sm font-medium">Notes</Label>
                        <Textarea v-model="addMemberForm.notes" placeholder="Optional notes about this member..." rows="3" :disabled="isAddingMember" />
                        <p v-if="addMemberErrors.notes" class="text-destructive text-sm">
                            {{ addMemberErrors.notes }}
                        </p>
                    </div>
                </div>

                <DialogFooter class="mt-6">
                    <Button type="button" variant="outline" @click="closeAddMemberDialog" :disabled="isAddingMember"> Cancel </Button>
                    <Button type="submit" :disabled="!addMemberForm.student_id || isAddingMember" class="gap-2">
                        <UserPlus class="h-4 w-4" />
                        {{ isAddingMember ? 'Adding...' : 'Add Member' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
