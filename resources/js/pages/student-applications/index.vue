<script setup lang="ts">
import DebouncedInput from '@/components/DebouncedInput.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, Eye, MoreHorizontal, Pencil, Plus, XCircle } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface LinkedStudent {
    id: number;
    student_id: string;
    full_name: string;
}

interface ApplicationRow {
    id: number;
    full_name: string;
    student_code: string;
    email: string;
    campus_code: string;
    intake: string | null;
    status: 'pending' | 'enrolled' | 'rejected';
    created_at: string;
    student?: LinkedStudent | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Campus {
    code: string;
    name: string;
}

interface StatusOption {
    value: string;
    label: string;
}

interface Filters {
    search: string | null;
    status: string | null;
    campus_code: string | null;
    intake: string | null;
}

interface Props {
    applications: Paginator<ApplicationRow>;
    filters: Filters;
    campuses: Campus[];
    intakes: string[];
    statusOptions: StatusOption[];
}

const props = defineProps<Props>();

const filters = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? 'all',
    campus_code: props.filters.campus_code ?? 'all',
    intake: props.filters.intake ?? 'all',
});

const applyFilters = (overrides: Partial<typeof filters> = {}) => {
    const merged = { ...filters, ...overrides };
    router.get(route('student-applications.index'), merged, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const onSearch = (value: string) => {
    filters.search = value;
    applyFilters();
};

const statusBadgeClass = (status: ApplicationRow['status']): string => {
    switch (status) {
        case 'enrolled':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'rejected':
            return 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300';
        default:
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    }
};

const goShow = (id: number) => router.visit(route('student-applications.show', id));
const goEdit = (id: number) => router.visit(route('student-applications.edit', id));
const goCreate = () => router.visit(route('student-applications.create'));

const approve = (row: ApplicationRow) => {
    router.post(
        route('student-applications.approve', row.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success(`${row.full_name} approved and enrolled.`),
            onError: () => toast.error('Failed to approve application.'),
        },
    );
};

// Reject dialog state
const showRejectDialog = ref(false);
const rejectTarget = ref<ApplicationRow | null>(null);
const rejectReason = ref('');
const isSubmitting = ref(false);

const openReject = (row: ApplicationRow) => {
    rejectTarget.value = row;
    rejectReason.value = '';
    showRejectDialog.value = true;
};

const submitReject = () => {
    if (!rejectTarget.value || !rejectReason.value.trim()) {
        toast.error('A rejection reason is required.');
        return;
    }
    isSubmitting.value = true;
    router.post(
        route('student-applications.reject', rejectTarget.value.id),
        { rejected_reason: rejectReason.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Application rejected.');
                showRejectDialog.value = false;
            },
            onError: () => toast.error('Failed to reject application.'),
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const goToPage = (url: string | null) => {
    if (url) {
        router.visit(url, { preserveScroll: true, preserveState: true });
    }
};
</script>

<template>
    <Head title="Student Applications" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Student Applications</h1>
                <p class="text-muted-foreground mt-1 text-sm">Review applications, then approve to enroll or reject with a reason.</p>
            </div>
            <Button @click="goCreate">
                <Plus class="mr-2 h-4 w-4" />
                New application
            </Button>
        </div>

        <!-- Filters -->
        <Card>
            <CardContent class="grid grid-cols-1 gap-3 py-4 md:grid-cols-4">
                <DebouncedInput :model-value="filters.search" placeholder="Search name, email, code…" @update:model-value="(v: string) => onSearch(v)" />

                <Select :model-value="filters.status" @update:model-value="(v) => applyFilters({ status: v as string })">
                    <SelectTrigger><SelectValue placeholder="Status" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All statuses</SelectItem>
                        <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="filters.campus_code" @update:model-value="(v) => applyFilters({ campus_code: v as string })">
                    <SelectTrigger><SelectValue placeholder="Campus" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All campuses</SelectItem>
                        <SelectItem v-for="campus in campuses" :key="campus.code" :value="campus.code">{{ campus.name }}</SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="filters.intake" @update:model-value="(v) => applyFilters({ intake: v as string })">
                    <SelectTrigger><SelectValue placeholder="Intake" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All intakes</SelectItem>
                        <SelectItem v-for="intake in intakes" :key="intake" :value="intake">{{ intake }}</SelectItem>
                    </SelectContent>
                </Select>
            </CardContent>
        </Card>

        <!-- Table -->
        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b text-left">
                            <tr class="text-muted-foreground">
                                <th class="px-4 py-3 font-medium">Applicant</th>
                                <th class="px-4 py-3 font-medium">Code</th>
                                <th class="px-4 py-3 font-medium">Campus</th>
                                <th class="px-4 py-3 font-medium">Intake</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in applications.data" :key="row.id" class="hover:bg-muted/40 border-b last:border-0">
                                <td class="px-4 py-3">
                                    <button class="font-medium hover:underline" @click="goShow(row.id)">{{ row.full_name }}</button>
                                    <p class="text-muted-foreground text-xs">{{ row.email }}</p>
                                </td>
                                <td class="px-4 py-3">{{ row.student_code }}</td>
                                <td class="px-4 py-3">{{ row.campus_code }}</td>
                                <td class="px-4 py-3">{{ row.intake ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="statusBadgeClass(row.status)">{{ row.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <Button
                                            v-if="row.status === 'pending'"
                                            size="sm"
                                            class="bg-emerald-600 text-white hover:bg-emerald-700"
                                            @click="approve(row)"
                                        >
                                            <CheckCircle2 class="mr-1 h-4 w-4" />
                                            Approve
                                        </Button>
                                        <Button v-if="row.status === 'pending'" size="sm" variant="destructive" @click="openReject(row)">
                                            <XCircle class="mr-1 h-4 w-4" />
                                            Reject
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button size="sm" variant="ghost"><MoreHorizontal class="h-4 w-4" /></Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem @click="goShow(row.id)">
                                                    <Eye class="mr-2 h-4 w-4" />
                                                    View
                                                </DropdownMenuItem>
                                                <template v-if="row.status === 'pending'">
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem @click="goEdit(row.id)">
                                                        <Pencil class="mr-2 h-4 w-4" />
                                                        Edit
                                                    </DropdownMenuItem>
                                                </template>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="applications.data.length === 0">
                                <td colspan="6" class="text-muted-foreground px-4 py-10 text-center">No applications match these filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- Pagination -->
        <div class="flex items-center justify-between">
            <p class="text-muted-foreground text-sm">Showing {{ applications.from ?? 0 }}–{{ applications.to ?? 0 }} of {{ applications.total }}</p>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="!applications.prev_page_url" @click="goToPage(applications.prev_page_url)">Previous</Button>
                <Button variant="outline" size="sm" :disabled="!applications.next_page_url" @click="goToPage(applications.next_page_url)">Next</Button>
            </div>
        </div>
    </div>

    <!-- Reject dialog -->
    <Dialog v-model:open="showRejectDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Reject application</DialogTitle>
                <DialogDescription>
                    Record why <strong>{{ rejectTarget?.full_name }}</strong> is being rejected. No student will be created.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2 py-2">
                <Label for="reject-reason">Reason</Label>
                <Textarea id="reject-reason" v-model="rejectReason" rows="4" placeholder="Explain the reason for rejection" />
            </div>

            <DialogFooter>
                <Button variant="outline" :disabled="isSubmitting" @click="showRejectDialog = false">Cancel</Button>
                <Button variant="destructive" :disabled="isSubmitting" @click="submitReject">Confirm rejection</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
