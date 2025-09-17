<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { formatHtml } from '@/lib/utils';
import type { EmailLog } from '@/types/models';
import { formatDistanceToNow } from 'date-fns';
import { Calendar, Mail } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    emailLog: EmailLog | null;
    isOpen: boolean;
}

interface Emits {
    (e: 'update:isOpen', value: boolean): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
// Computed
const isModalOpen = computed({
    get: () => props.isOpen,
    set: (value: boolean) => emit('update:isOpen', value),
});

// Get metadata content if available
const metadata = computed(() => {
    if (!props.emailLog?.metadata) return null;

    try {
        return props.emailLog.metadata;
    } catch {
        return null;
    }
});

const emailContent = computed(() => {
    if (!metadata.value?.content) return '';
    return formatHtml(metadata.value.content);
});

const attachments = computed(() => {
    return metadata.value?.attachments || [];
});

const isBulkSend = computed(() => {
    return metadata.value?.bulk_send || false;
});

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

// Format date
const formatDate = (dateString: string | null) => {
    if (!dateString) return 'N/A';
    return formatDistanceToNow(new Date(dateString), { addSuffix: true });
};
</script>

<template>
    <Dialog v-model:open="isModalOpen">
        <DialogContent class="max-h-[90vh] !max-w-7xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Mail class="h-5 w-5" />
                    Email Log Details
                </DialogTitle>
                <DialogDescription> Detailed information about the email log entry </DialogDescription>
            </DialogHeader>

            <div v-if="emailLog" class="space-y-6">
                <!-- Basic Information -->
                <div class="space-y-4">
                    <h3 class="flex items-center gap-2 text-lg font-semibold">
                        <Mail class="h-4 w-4" />
                        Basic Information
                    </h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Recipient</label>
                            <p class="text-sm">{{ emailLog.recipient }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Sender</label>
                            <p class="text-sm">{{ emailLog.sender }}</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <label class="text-muted-foreground text-sm font-medium">Subject</label>
                            <p class="text-sm font-medium">{{ emailLog.subject }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Status</label>
                            <Badge :variant="getStatusVariant(emailLog.status)">
                                {{ emailLog.status.charAt(0).toUpperCase() + emailLog.status.slice(1) }}
                            </Badge>
                        </div>

                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Retry Count</label>
                            <p class="text-sm">{{ emailLog.retry_count || 0 }}</p>
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Timestamps -->
                <div class="space-y-4">
                    <h3 class="flex items-center gap-2 text-lg font-semibold">
                        <Calendar class="h-4 w-4" />
                        Timeline
                    </h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Created</label>
                            <p class="text-sm">{{ formatDate(emailLog.created_at) }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Queued</label>
                            <p class="text-sm">{{ formatDate(emailLog.queued_at || null) }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Sent</label>
                            <p class="text-sm">{{ formatDate(emailLog.sent_at || null) }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Delivered</label>
                            <p class="text-sm">{{ formatDate(emailLog.delivered_at || null) }}</p>
                        </div>

                        <div class="space-y-2" v-if="emailLog.failed_at">
                            <label class="text-muted-foreground text-sm font-medium">Failed</label>
                            <p class="text-sm">{{ formatDate(emailLog.failed_at || null) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Error Information -->
                <div v-if="emailLog.error_message" class="space-y-4">
                    <Separator />
                    <h3 class="text-destructive text-lg font-semibold">Error Information</h3>
                    <div class="bg-destructive/10 border-destructive/20 rounded-lg border p-4">
                        <p class="text-destructive text-sm font-medium">{{ emailLog.error_message }}</p>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="space-y-4">
                    <Separator />
                    <h3 class="text-lg font-semibold">Additional Information</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Message ID</label>
                            <p class="font-mono text-sm">{{ emailLog.message_id || 'N/A' }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Batch ID</label>
                            <p class="font-mono text-sm">{{ emailLog.batch_id || 'N/A' }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Template ID</label>
                            <p class="text-sm">{{ emailLog.template_id || 'N/A' }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">User ID</label>
                            <p class="text-sm">{{ emailLog.user_id || 'N/A' }}</p>
                        </div>

                        <div class="space-y-2">
                            <label class="text-muted-foreground text-sm font-medium">Bulk Send</label>
                            <Badge :variant="isBulkSend ? 'default' : 'secondary'">
                                {{ isBulkSend ? 'Yes' : 'No' }}
                            </Badge>
                        </div>

                        <div class="space-y-2" v-if="attachments.length > 0">
                            <label class="text-muted-foreground text-sm font-medium">Attachments</label>
                            <p class="text-sm">{{ attachments.length }} file(s)</p>
                        </div>
                    </div>
                </div>

                <!-- Email Content -->
                <div v-if="emailContent" class="space-y-4">
                    <Separator />
                    <h3 class="text-lg font-semibold">Email Content</h3>

                    <div class="bg-muted/30 rounded-lg border p-4">
                        <div class="max-h-96 overflow-y-auto">
                            <div class="prose prose-sm max-w-none" v-html="emailContent"></div>
                        </div>
                    </div>
                </div>

                <!-- Raw Metadata (for debugging) -->
                <div v-if="metadata" class="space-y-4">
                    <Separator />
                    <details class="group">
                        <summary class="text-muted-foreground hover:text-foreground cursor-pointer text-sm font-medium">Raw Metadata (Debug)</summary>
                        <div class="bg-muted mt-2 rounded-lg p-4">
                            <pre class="overflow-x-auto text-xs">{{ JSON.stringify(metadata, null, 2) }}</pre>
                        </div>
                    </details>
                </div>
            </div>

            <!-- Loading state -->
            <div v-else class="flex items-center justify-center py-8">
                <div class="text-center">
                    <div class="border-primary mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-b-2"></div>
                    <p class="text-muted-foreground text-sm">Loading email details...</p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
