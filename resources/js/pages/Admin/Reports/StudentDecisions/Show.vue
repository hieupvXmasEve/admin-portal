<script setup lang="ts">
import ServerPaginatedDataTable from '@/components/tables/ServerPaginatedDataTable.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useApi } from '@/composables/useApiRequest';
import { useServerTableQuery } from '@/composables/useServerTableQuery';
import type { PaginatedResponse } from '@/types';
import type { StudentActionLog } from '@/types/student-action';
import type { StudentDecision } from '@/types/student-decision';
import { studentRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { AlertTriangle, ExternalLink, Loader2, SearchCheck, UserPlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface LinkedActionFilters {
    per_page?: number;
    page?: number;
}

interface Props {
    decision: StudentDecision;
    linkedActions: PaginatedResponse<StudentActionLog>;
    actionTypes: Array<{
        value: string;
        label: string;
        labelEn: string;
        description: string;
    }>;
    filters: LinkedActionFilters;
}

const props = defineProps<Props>();
const api = useApi();

interface BulkDecisionActionLog {
    id: number;
    action_type: string;
    action_type_label: string;
    reason: string | null;
    created_at: string | null;
}

interface BulkDecisionStudentPreviewRow {
    code: string;
    student: {
        id: number;
        student_id: string;
        full_name: string;
        status: string | null;
        campus: {
            id: number;
            name: string;
            code: string;
        } | null;
    };
    action_logs: BulkDecisionActionLog[];
    linkable_action_count: number;
    total_action_count: number;
    already_linked_to_this_count: number;
    already_linked_to_other_count: number;
    skipped_reason: string | null;
}

interface BulkDecisionPreview {
    decision_id: number;
    action_type: string;
    students: BulkDecisionStudentPreviewRow[];
    unmatched_codes: string[];
    summary: {
        input_count: number;
        unique_count: number;
        duplicate_count: number;
        matched_count: number;
        unmatched_count: number;
        linkable_students_count: number;
        skipped_students_count: number;
        linkable_action_count: number;
    };
}

interface BulkDecisionLinkResult {
    decision_id: number;
    action_type: string;
    linked_action_count: number;
    requested_action_count: number;
    preview: BulkDecisionPreview;
}

const { handlePageChange, handlePageSizeChange } = useServerTableQuery<LinkedActionFilters>({
    baseUrl: studentRoutes.studentDecisionsShow(props.decision.id),
    initialFilters: {
        page: props.filters.page ?? 1,
        per_page: props.filters.per_page ?? 10,
    },
    emptyFilters: {
        page: 1,
        per_page: 10,
    },
    defaultValues: {
        page: 1,
        per_page: 10,
    },
    only: ['linkedActions', 'filters'],
});

const isBulkDialogOpen = ref(false);
const bulkActionType = ref('');
const bulkStudentCodes = ref('');
const bulkPreview = ref<BulkDecisionPreview | null>(null);
const bulkError = ref<string | null>(null);
const isPreviewingBulk = ref(false);
const isLinkingBulk = ref(false);

const canConfirmBulkLink = computed(() => (bulkPreview.value?.summary.linkable_action_count ?? 0) > 0 && !isPreviewingBulk.value && !isLinkingBulk.value);

const formatDateOnly = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('vi-VN');
};

const openBulkDialog = () => {
    bulkActionType.value = '';
    bulkStudentCodes.value = '';
    bulkPreview.value = null;
    bulkError.value = null;
    isBulkDialogOpen.value = true;
};

const previewBulkStudents = async () => {
    if (!bulkActionType.value) {
        bulkError.value = 'Select the action type that this decision should link to.';
        return;
    }

    if (!bulkStudentCodes.value.trim()) {
        bulkError.value = 'Enter at least one student code.';
        return;
    }

    isPreviewingBulk.value = true;
    bulkError.value = null;

    try {
        const response = await api.post<BulkDecisionPreview>(studentRoutes.studentDecisionsPreviewStudents(props.decision.id), {
            action_type: bulkActionType.value,
            student_codes: bulkStudentCodes.value,
        });

        if (response.data.value?.success && response.data.value.data) {
            bulkPreview.value = response.data.value.data;
            return;
        }

        bulkPreview.value = null;
        bulkError.value = response.data.value?.message ?? 'Failed to preview student codes.';
    } catch {
        bulkPreview.value = null;
        bulkError.value = 'Failed to preview student codes.';
    } finally {
        isPreviewingBulk.value = false;
    }
};

const confirmBulkLink = async () => {
    if (!canConfirmBulkLink.value) return;

    isLinkingBulk.value = true;
    bulkError.value = null;

    try {
        const response = await api.post<BulkDecisionLinkResult>(studentRoutes.studentDecisionsBulkLinkStudents(props.decision.id), {
            action_type: bulkActionType.value,
            student_codes: bulkStudentCodes.value,
        });

        if (response.data.value?.success && response.data.value.data) {
            toast.success(response.data.value.message ?? `Linked ${response.data.value.data.linked_action_count} action(s)`);
            isBulkDialogOpen.value = false;
            router.reload({
                only: ['decision', 'linkedActions', 'filters'],
            });
            return;
        }

        bulkError.value = response.data.value?.message ?? 'Failed to link students to this decision.';
    } catch {
        bulkError.value = 'Failed to link students to this decision.';
    } finally {
        isLinkingBulk.value = false;
    }
};

watch([bulkActionType, bulkStudentCodes], () => {
    bulkPreview.value = null;
    bulkError.value = null;
});

const columns: ColumnDef<StudentActionLog>[] = [
    {
        header: 'Student',
        id: 'student',
        cell: ({ row }) => `${row.original.student?.student_id || '-'} - ${row.original.student?.full_name || '-'}`,
    },
    {
        header: 'Action Type',
        accessorKey: 'action_type',
    },
    {
        header: 'Changed At',
        accessorKey: 'created_at',
        cell: ({ row }) => formatDateOnly(row.original.created_at),
    },
    {
        header: 'Reason',
        accessorKey: 'reason',
    },
    {
        header: 'Details',
        id: 'details',
        cell: 'details',
    },
];
</script>

<template>
    <Head :title="`Decision ${decision.decision_number}`" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Decision Detail</h1>
                <p class="text-muted-foreground mt-1">{{ decision.decision_number }} - {{ decision.decision_name }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Button @click="openBulkDialog">
                    <UserPlus class="mr-2 h-4 w-4" />
                    Add Students
                </Button>
                <Link :href="studentRoutes.studentDecisionsIndex()">
                    <Button variant="outline">Back to List</Button>
                </Link>
                <a v-if="decision.upload_record?.url" :href="decision.upload_record.url" target="_blank" rel="noopener noreferrer">
                    <Button variant="outline">
                        <ExternalLink class="mr-2 h-4 w-4" />
                        View File
                    </Button>
                </a>
            </div>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Decision Information</CardTitle>
                <CardDescription>Metadata and linked statistics.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <p class="text-muted-foreground text-sm">Signer</p>
                        <p class="font-medium">{{ decision.decision_signer }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Issued</p>
                        <p class="font-medium">{{ formatDateOnly(decision.issued_at) }}</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-sm">Expires</p>
                        <p class="font-medium">{{ formatDateOnly(decision.expires_at) }}</p>
                    </div>
                </div>
                <div class="mt-4 flex gap-3">
                    <Badge variant="outline">Linked Actions: {{ decision.total_linked_actions ?? 0 }}</Badge>
                    <Badge variant="outline">Linked Students: {{ decision.total_linked_students ?? 0 }}</Badge>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Linked Student Actions</CardTitle>
                <CardDescription>Open action detail from each row.</CardDescription>
            </CardHeader>
            <CardContent>
                <ServerPaginatedDataTable :data="linkedActions.data" :columns="columns" :pagination-data="linkedActions" item-name="actions" @page-change="handlePageChange" @page-size-change="handlePageSizeChange">
                    <template #cell-details="{ row }">
                        <Link :href="studentRoutes.studentStatusActionShow(row.original.id)">
                            <Button size="sm" variant="outline">View Action</Button>
                        </Link>
                    </template>
                </ServerPaginatedDataTable>
            </CardContent>
        </Card>

        <Dialog v-model:open="isBulkDialogOpen">
            <DialogContent class="!max-h-[85vh] !max-w-5xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Bulk Add Students</DialogTitle>
                    <DialogDescription>Paste student codes, preview the matched action logs, then link all valid rows to this decision.</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div class="space-y-2">
                        <Label for="bulk-action-type">Action Type</Label>
                        <Select v-model="bulkActionType" :disabled="isPreviewingBulk || isLinkingBulk">
                            <SelectTrigger id="bulk-action-type">
                                <SelectValue placeholder="Select matching action type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="actionType in actionTypes" :key="actionType.value" :value="actionType.value">
                                    {{ actionType.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-muted-foreground text-xs">Only students with an existing action log of this type can be linked.</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="bulk-student-codes">Student Codes</Label>
                        <Textarea id="bulk-student-codes" v-model="bulkStudentCodes" rows="6" placeholder="SE900001&#10;SE900002&#10;SE900003" :disabled="isPreviewingBulk || isLinkingBulk" />
                        <p class="text-muted-foreground text-xs">Separate codes with new lines, spaces, commas, or semicolons. Maximum 100 unique codes.</p>
                    </div>

                    <Alert v-if="bulkError" variant="destructive">
                        <AlertTriangle class="h-4 w-4" />
                        <AlertTitle>Bulk add failed</AlertTitle>
                        <AlertDescription>{{ bulkError }}</AlertDescription>
                    </Alert>

                    <div class="flex items-center justify-between gap-3">
                        <div v-if="bulkPreview" class="flex flex-wrap gap-2">
                            <Badge variant="outline">Input: {{ bulkPreview.summary.input_count }}</Badge>
                            <Badge variant="outline">Matched: {{ bulkPreview.summary.matched_count }}</Badge>
                            <Badge variant="outline">Unmatched: {{ bulkPreview.summary.unmatched_count }}</Badge>
                            <Badge variant="outline">Linkable actions: {{ bulkPreview.summary.linkable_action_count }}</Badge>
                        </div>
                        <div v-else class="text-muted-foreground text-sm">Preview required before linking.</div>

                        <Button type="button" variant="outline" :disabled="isPreviewingBulk || isLinkingBulk || !bulkActionType || !bulkStudentCodes.trim()" @click="previewBulkStudents">
                            <Loader2 v-if="isPreviewingBulk" class="mr-2 h-4 w-4 animate-spin" />
                            <SearchCheck v-else class="mr-2 h-4 w-4" />
                            Preview
                        </Button>
                    </div>

                    <Alert v-if="bulkPreview?.unmatched_codes.length">
                        <AlertTriangle class="h-4 w-4" />
                        <AlertTitle>Unmatched Codes</AlertTitle>
                        <AlertDescription>{{ bulkPreview.unmatched_codes.join(', ') }}</AlertDescription>
                    </Alert>

                    <div v-if="bulkPreview" class="overflow-x-auto rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-[260px]">Student</TableHead>
                                    <TableHead class="w-[120px]">Actions</TableHead>
                                    <TableHead>Action Logs To Link</TableHead>
                                    <TableHead class="w-[220px]">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="row in bulkPreview.students" :key="row.code">
                                    <TableCell>
                                        <div class="space-y-1">
                                            <div class="font-medium">{{ row.student.student_id }} - {{ row.student.full_name }}</div>
                                            <div class="text-muted-foreground text-xs">{{ row.student.campus?.code ?? '-' }} · {{ row.student.status ?? '-' }}</div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <Badge :variant="row.linkable_action_count > 0 ? 'default' : 'outline'">{{ row.linkable_action_count }}</Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div v-if="row.action_logs.length" class="space-y-2">
                                            <div v-for="action in row.action_logs" :key="action.id" class="rounded-md border p-2 text-sm">
                                                <div class="font-medium">#{{ action.id }} · {{ action.action_type_label }}</div>
                                                <div class="text-muted-foreground mt-1 line-clamp-2 text-xs">{{ action.reason ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <span v-else class="text-muted-foreground text-sm">No linkable action logs</span>
                                    </TableCell>
                                    <TableCell>
                                        <span v-if="row.skipped_reason" class="text-muted-foreground text-sm">{{ row.skipped_reason }}</span>
                                        <span v-else class="text-sm font-medium">Ready to link</span>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="isLinkingBulk" @click="isBulkDialogOpen = false">Cancel</Button>
                    <Button type="button" :disabled="!canConfirmBulkLink" @click="confirmBulkLink">
                        <Loader2 v-if="isLinkingBulk" class="mr-2 h-4 w-4 animate-spin" />
                        <UserPlus v-else class="mr-2 h-4 w-4" />
                        Link {{ bulkPreview?.summary.linkable_action_count ?? 0 }} Action(s)
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
