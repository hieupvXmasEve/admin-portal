<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useApi } from '@/composables/useApiRequest';
import { Head, Link, router } from '@inertiajs/vue3';
import { formatDistanceToNow, format } from 'date-fns';
import { ArrowLeft, Loader2, RotateCcw } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface DeliveryInfo {
    id: number;
    channel: string;
    status: string;
    attempts: number;
    last_error: string | null;
    sent_at: string | null;
}

interface MessageInfo {
    id: number;
    event_id: string;
    type_key: string;
    title: string;
    body: string;
    read_at: string | null;
    status: string;
    created_at: string;
    recipient?: {
        id: number;
        name: string;
        email: string;
    };
    deliveries?: DeliveryInfo[];
}

interface OutboxEntry {
    id: number;
    event_id: string;
    event_name: string;
    event_version: number;
    occurred_at: string;
    aggregate_type: string;
    aggregate_id: string;
    campus_id: number;
    actor_user_id: number | null;
    payload: Record<string, any>;
    status: 'pending' | 'processing' | 'dispatched' | 'failed';
    attempts: number;
    last_error: string | null;
    last_attempt_at: string | null;
    created_at: string;
    updated_at: string;
    messages?: MessageInfo[];
}

interface Props {
    outbox: OutboxEntry;
}

const props = defineProps<Props>();
const api = useApi();
const isRetrying = ref(false);

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'dispatched':
        case 'sent':
            return 'default';
        case 'failed':
            return 'destructive';
        case 'pending':
        case 'processing':
            return 'secondary';
        default:
            return 'outline';
    }
};

const canRetry = (entry: OutboxEntry) => {
    return entry.status === 'failed' || entry.status === 'pending';
};

const retryOutbox = async () => {
    if (!canRetry(props.outbox) || isRetrying.value) return;

    try {
        isRetrying.value = true;
        const response = await api.post(route('admin.notifications.ops.outbox.retry', props.outbox.id), {});

        if (response.data.value?.success) {
            toast.success('Outbox entry queued for retry');
            router.reload();
        } else {
            throw new Error(response.data.value?.message || 'Failed to retry');
        }
    } catch (error: unknown) {
        const message = error instanceof Error ? error.message : 'Failed to retry outbox entry';
        toast.error(message);
    } finally {
        isRetrying.value = false;
    }
};
</script>

<template>
    <div>
        <Head :title="`Outbox Detail - ${outbox.event_id.substring(0, 8)}`" />

        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Link :href="route('admin.notifications.ops.outbox')" as="button">
                    <Button variant="ghost" size="icon">
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Outbox Detail</h1>
                    <p class="text-muted-foreground mt-1">Event ID: {{ outbox.event_id }}</p>
                </div>
            </div>
            <Button v-if="canRetry(outbox)" @click="retryOutbox" :disabled="isRetrying">
                <Loader2 v-if="isRetrying" class="mr-2 h-4 w-4 animate-spin" />
                <RotateCcw v-else class="mr-2 h-4 w-4" />
                Retry
            </Button>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <!-- Event Info -->
            <Card>
                <CardHeader>
                    <CardTitle>Event Information</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-muted-foreground">Event Name</p>
                            <p class="font-medium">{{ outbox.event_name }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Status</p>
                            <Badge :variant="getStatusVariant(outbox.status)">{{ outbox.status }}</Badge>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Version</p>
                            <p class="font-medium">{{ outbox.event_version }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Attempts</p>
                            <p class="font-medium">{{ outbox.attempts }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Aggregate Type</p>
                            <p class="font-medium">{{ outbox.aggregate_type }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Aggregate ID</p>
                            <p class="font-mono text-xs">{{ outbox.aggregate_id }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Occurred At</p>
                            <p class="font-medium">{{ format(new Date(outbox.occurred_at), 'PPpp') }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Campus ID</p>
                            <p class="font-medium">{{ outbox.campus_id }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Error Info -->
            <Card v-if="outbox.last_error">
                <CardHeader>
                    <CardTitle class="text-destructive">Last Error</CardTitle>
                    <CardDescription v-if="outbox.last_attempt_at">
                        {{ formatDistanceToNow(new Date(outbox.last_attempt_at), { addSuffix: true }) }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <pre class="bg-muted overflow-auto rounded p-4 text-xs">{{ outbox.last_error }}</pre>
                </CardContent>
            </Card>

            <!-- Payload -->
            <Card class="md:col-span-2">
                <CardHeader>
                    <CardTitle>Payload</CardTitle>
                </CardHeader>
                <CardContent>
                    <pre class="bg-muted overflow-auto rounded p-4 text-xs">{{ JSON.stringify(outbox.payload, null, 2) }}</pre>
                </CardContent>
            </Card>

            <!-- Messages -->
            <Card v-if="outbox.messages && outbox.messages.length > 0" class="md:col-span-2">
                <CardHeader>
                    <CardTitle>Generated Messages ({{ outbox.messages.length }})</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="message in outbox.messages" :key="message.id" class="border-border rounded-lg border p-4">
                            <div class="mb-3 flex items-start justify-between">
                                <div>
                                    <p class="font-medium">{{ message.title }}</p>
                                    <p class="text-muted-foreground text-sm">
                                        {{ message.recipient?.name || message.recipient?.email || 'Unknown' }}
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <Badge :variant="getStatusVariant(message.status)">{{ message.status }}</Badge>
                                    <Badge v-if="message.read_at" variant="outline">Read</Badge>
                                </div>
                            </div>
                            <p class="text-muted-foreground mb-3 text-sm">{{ message.body }}</p>

                            <!-- Deliveries -->
                            <div v-if="message.deliveries && message.deliveries.length > 0" class="mt-3 border-t pt-3">
                                <p class="mb-2 text-xs font-medium">Deliveries:</p>
                                <div class="flex flex-wrap gap-2">
                                    <div
                                        v-for="delivery in message.deliveries"
                                        :key="delivery.id"
                                        class="bg-muted flex items-center gap-2 rounded px-2 py-1 text-xs"
                                    >
                                        <Badge :variant="getStatusVariant(delivery.status)" class="text-xs">
                                            {{ delivery.channel }}: {{ delivery.status }}
                                        </Badge>
                                        <span v-if="delivery.attempts > 1">({{ delivery.attempts }} attempts)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
