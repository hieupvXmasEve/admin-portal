<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import DataTable from '@/components/DataTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useApi } from '@/composables/useApiRequest';
import { createColumns } from '@/lib/table-utils';
import type { PaginatedResponse, User } from '@/types';
import type { EmailLog } from '@/types/models';
import { Head, router } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { Eye, Filter, Loader2, RotateCcw, Search } from 'lucide-vue-next';
import { computed, h, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import EmailLogDetailModal from './components/EmailLogDetailModal.vue';

interface StatusOption {
    value: string;
    label: string;
}

interface Filters {
    status?: string;
    recipient?: string;
    batch_id?: string;
    per_page?: number;
}

interface Props {
    emailLogs: PaginatedResponse<EmailLog>;
    filters: Filters;
    statusOptions: StatusOption[];
}

const props = defineProps<Props>();
const api = useApi();
console.log('%c props', 'color: red', props.emailLogs);
// Reactive data
const retryingIds = ref<Set<number>>(new Set());
const isModalOpen = ref(false);
const selectedEmailLog = ref<EmailLog | null>(null);
const isLoadingEmailLog = ref(false);

// Initialize filters from props
const filters = reactive<Filters>({
    status: props.filters.status || '',
    recipient: props.filters.recipient || '',
    batch_id: props.filters.batch_id || '',
    per_page: props.filters.per_page || 15,
});

// Computed
const hasFilters = computed(() => filters.status || filters.recipient || filters.batch_id);

// Status badge variant mapping
const getStatusVariant = (status: string) => {
    switch (status) {
        case 'sent':
        case 'delivered':
            return 'default';
        case 'failed':
        case 'bounced':
        case 'rejected':
            return 'destructive';
        case 'pending':
        case 'queued':
        case 'sending':
            return 'secondary';
        default:
            return 'outline';
    }
};

// Check if email can be retried
const canRetry = (emailLog: EmailLog) => {
    return emailLog.status === 'failed' && (emailLog.retry_count || 0) < 3;
};

// Apply filters by navigating to the same route with updated params
const applyFilters = () => {
    const params: Record<string, any> = {};

    if (filters.status) params.status = filters.status;
    if (filters.recipient) params.recipient = filters.recipient;
    if (filters.batch_id) params.batch_id = filters.batch_id;
    if (filters.per_page !== 15) params.per_page = filters.per_page;

    router.get(route('system.email-history.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Retry failed email
const retryEmail = async (emailLog: EmailLog) => {
    if (!canRetry(emailLog) || retryingIds.value.has(emailLog.id)) {
        return;
    }

    try {
        retryingIds.value.add(emailLog.id);

        const response = await api.post(`/api/emails/logs/${emailLog.id}/retry`, {});

        if (response.data.value?.success) {
            toast.success('Email has been queued for retry');

            // Refresh the page to show updated status
            router.reload({ only: ['emailLogs'] });
        } else {
            throw new Error(response.data.value?.message || 'Failed to retry email');
        }
    } catch (error: any) {
        toast.error(error.message || 'Failed to retry email');
    } finally {
        retryingIds.value.delete(emailLog.id);
    }
};

// Clear all filters
const clearFilters = () => {
    filters.status = '';
    filters.recipient = '';
    filters.batch_id = '';

    router.get(
        route('system.email-history.index'),
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

// Handle navigation from pagination component
const handleNavigation = (url: string) => {
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Handle page size change
const handlePageSizeChange = (pageSize: number) => {
    filters.per_page = pageSize;

    const params: Record<string, any> = { per_page: pageSize };
    if (filters.status) params.status = filters.status;
    if (filters.recipient) params.recipient = filters.recipient;
    if (filters.batch_id) params.batch_id = filters.batch_id;

    router.get(route('system.email-history.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};
// Show email log details
const showEmailLog = async (emailLog: EmailLog) => {
    try {
        isLoadingEmailLog.value = true;
        selectedEmailLog.value = null;
        isModalOpen.value = true;

        const response = await api.get(`/systems/email-history/${emailLog.id}`);

        if (response.data.value?.success) {
            selectedEmailLog.value = response.data.value.data;
        } else {
            throw new Error(response.data.value?.message || 'Failed to load email details');
        }
    } catch (error: any) {
        toast.error(error.message || 'Failed to load email details');
        isModalOpen.value = false;
    } finally {
        isLoadingEmailLog.value = false;
    }
};

// Data table columns configuration
const columns = createColumns<EmailLog>([
    {
        accessorKey: 'recipient',
        header: 'Recipient',
        cell: ({ row }) => {
            const recipient = row.getValue('recipient') as string;
            return recipient.length > 30 ? recipient.substring(0, 30) + '...' : recipient;
        },
    },
    {
        accessorKey: 'user',
        header: 'User',
        cell: ({ row }) => {
            const user = row.getValue('user') as User;
            return user?.name?.length > 50 ? user.name.substring(0, 50) + '...' : user?.name;
        },
    },
    {
        accessorKey: 'sender',
        header: 'Email Sender',
        cell: ({ row }) => {
            const sender = row.getValue('sender') as string;
            return sender?.length > 50 ? sender.substring(0, 50) + '...' : sender;
        },
    },
    {
        accessorKey: 'subject',
        header: 'Subject',
        cell: ({ row }) => {
            const subject = row.getValue('subject') as string;
            return subject?.length > 50 ? subject.substring(0, 50) + '...' : subject;
        },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.getValue('status') as string;
            return h(
                Badge,
                {
                    variant: getStatusVariant(status),
                },
                () => status.charAt(0).toUpperCase() + status.slice(1),
            );
        },
    },
    {
        accessorKey: 'retry_count',
        header: 'Retries',
        cell: ({ row }) => row.getValue('retry_count') || 0,
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        cell: ({ row }) => {
            const date = new Date(row.getValue('created_at') as string);
            return formatDistanceToNow(date, { addSuffix: true });
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: 'actions',
    },
]);
</script>

<template>
    <div class="">
        <Head title="Email Logs" />

        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Email Logs</h1>
                <p class="text-muted-foreground mt-2">Monitor and manage email delivery status</p>
            </div>
        </div>

        <!-- Filters Card -->
        <Card class="mb-6">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Filter class="h-5 w-5" />
                    Filters
                </CardTitle>
                <CardDescription> Filter email logs by status, recipient, or batch ID </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <!-- Status Filter -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Status</label>
                        <Select v-model="filters.status">
                            <SelectTrigger>
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="option in props.statusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Recipient Filter -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Recipient</label>
                        <Input v-model="filters.recipient" placeholder="Search by email..." type="email" />
                    </div>

                    <!-- Batch ID Filter -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Batch ID</label>
                        <Input v-model="filters.batch_id" placeholder="Enter batch ID..." />
                    </div>

                    <!-- Actions -->
                    <div class="space-y-2">
                        <label class="text-sm font-medium">&nbsp;</label>
                        <div class="flex gap-2">
                            <Button @click="applyFilters" class="flex-1">
                                <Search class="mr-2 h-4 w-4" />
                                Search
                            </Button>
                            <Button variant="outline" @click="clearFilters" :disabled="!hasFilters"> Clear </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Email Logs Table -->
        <Card>
            <CardHeader>
                <CardTitle>Email Logs</CardTitle>
                <CardDescription> Showing {{ props.emailLogs.from || 0 }} to {{ props.emailLogs.to || 0 }} of {{ props.emailLogs.total }} emails </CardDescription>
            </CardHeader>
            <CardContent>
                <DataTable :data="props.emailLogs.data" :columns="columns" class="w-full">
                    <template #cell-actions="{ row }">
                        <div class="flex items-center gap-2">
                            <!-- Retry button - only show for failed emails that can be retried -->
                            <Button v-if="canRetry(row.original)" size="sm" variant="outline" :disabled="retryingIds.has(row.original.id)" @click="retryEmail(row.original)">
                                <Loader2 v-if="retryingIds.has(row.original.id)" class="mr-2 h-4 w-4 animate-spin" />
                                <RotateCcw v-else class="mr-2 h-4 w-4" />
                                Retry
                            </Button>

                            <!-- View details button -->
                            <TooltipProvider :delay-duration="0" ignore-non-keyboard-focus disable-hoverable-content>
                                <Tooltip>
                                    <TooltipTrigger as-child>
                                        <Button size="sm" variant="ghost" @click="showEmailLog(row.original)">
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>View details</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        </div>
                    </template>
                </DataTable>

                <!-- Pagination -->
                <DataPagination
                    v-if="props.emailLogs.data.length > 0"
                    :pagination-data="{
                        current_page: props.emailLogs.current_page,
                        last_page: props.emailLogs.last_page,
                        total: props.emailLogs.total,
                        per_page: props.emailLogs.per_page,
                        from: props.emailLogs.from,
                        to: props.emailLogs.to,
                        prev_page_url: props.emailLogs.prev_page_url,
                        next_page_url: props.emailLogs.next_page_url,
                        links: props.emailLogs.links,
                    }"
                    item-name="emails"
                    @navigate="handleNavigation"
                    @page-size-change="handlePageSizeChange"
                />

                <!-- Empty state -->
                <div v-if="props.emailLogs.data.length === 0" class="py-12 text-center">
                    <div class="text-muted-foreground">
                        <p class="text-lg font-medium">No email logs found</p>
                        <p class="mt-2 text-sm">
                            {{ hasFilters ? 'Try adjusting your filters' : 'No emails have been sent yet' }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Email Log Detail Modal -->
        <EmailLogDetailModal v-model:is-open="isModalOpen" :email-log="selectedEmailLog" />
    </div>
</template>
