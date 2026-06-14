<script setup lang="ts">
import JsonPayloadCard from '@/components/finance/JsonPayloadCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useGlobalConfirmDialog } from '@/composables/useGlobalConfirmDialog';
import { usePermission } from '@/composables/usePermission';
import { formatCurrency, formatDate } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, CheckCircle2, Info, Link2, ReceiptText, RotateCw, ShieldAlert, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    event: {
        id: number;
        created_at: string | null;
        processed_at: string | null;
        dng_payment_id: string | null;
        event_type: string;
        processing_status: string;
        is_valid_checksum: boolean;
        error_message: string | null;
        payload_hash: string;
        request: {
            id: number;
            status: string;
            campus_code: string;
            student_code: string;
            item_id: string;
            amount: number;
            student: {
                id: number;
                student_code: string;
                full_name: string;
                email: string | null;
            } | null;
            payment: {
                id: number;
                amount: number;
                status: string;
                external_ref: string | null;
                paid_at: string | null;
            } | null;
        } | null;
        payload_facts: Record<string, string | number | null>;
        headers: unknown;
        payload: unknown;
    };
}

const props = defineProps<Props>();

const permission = usePermission();
const { showConfirmDialog } = useGlobalConfirmDialog();

const isRetrying = ref(false);

const canRetry = computed(() => props.event.processing_status !== 'processed' && permission.can('create_finance_payments'));

type DiagnosisVariant = 'success' | 'skipped' | 'error' | 'pending';

const diagnosisVariant = computed<DiagnosisVariant | null>(() => {
    const status = props.event.processing_status;
    if (status === 'processed') return 'success';
    if (status === 'skipped') return 'skipped';
    if (status === 'mismatch' || status === 'failed_terminal' || status === 'failed_retryable') return 'error';
    if ((status === 'received' || status === 'processing') && !props.event.is_valid_checksum && props.event.error_message) return 'error';
    return null;
});

const runRetry = () => {
    isRetrying.value = true;
    router.post(
        route('finance.dng.webhook-events.retry', props.event.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isRetrying.value = false;
            },
        },
    );
};

const handleRetry = () => {
    if (!canRetry.value || isRetrying.value) return;

    showConfirmDialog(
        {
            title: 'Chạy lại webhook',
            message: `Chạy lại webhook event #${props.event.id}? Service sẽ xử lý lại đồng bộ và có thể thay đổi trạng thái thanh toán.`,
            confirmText: 'Chạy lại',
            cancelText: 'Huỷ bỏ',
        },
        {
            onConfirm: () => runRetry(),
        },
    );
};
</script>

<template>
    <div class="space-y-6">
        <Head :title="`DNG Webhook Event #${event.id}`" />

        <div class="flex items-center gap-3">
            <Link :href="route('finance.dng.webhook-events.index')">
                <Button variant="outline" size="icon">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
            </Link>
            <div class="flex-1">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">DNG Webhook Event #{{ event.id }}</h1>
                    <Badge variant="outline">{{ event.processing_status }}</Badge>
                    <Badge variant="outline" :class="event.is_valid_checksum ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'">
                        {{ event.is_valid_checksum ? 'valid checksum' : 'invalid checksum' }}
                    </Badge>
                </div>
                <p class="text-muted-foreground text-sm">Created {{ formatDate(event.created_at) }} · Processed {{ formatDate(event.processed_at) }}</p>
            </div>
            <Button v-if="canRetry" variant="outline" :disabled="isRetrying" @click="handleRetry">
                <RotateCw class="mr-2 h-4 w-4" :class="{ 'animate-spin': isRetrying }" />
                {{ isRetrying ? 'Retrying...' : 'Retry' }}
            </Button>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card>
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"><ReceiptText class="h-5 w-5" />Processing</CardTitle></CardHeader
                >
                <CardContent class="space-y-2 text-sm">
                    <div><span class="text-muted-foreground">Event type:</span> {{ event.event_type }}</div>
                    <div>
                        <span class="text-muted-foreground">Payment ID:</span> <span class="font-mono">{{ event.dng_payment_id || '-' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Payload hash:</span> <span class="font-mono text-xs">{{ event.payload_hash }}</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"><Link2 class="h-5 w-5" />Linked Request</CardTitle></CardHeader
                >
                <CardContent v-if="event.request" class="space-y-2 text-sm">
                    <Link v-if="permission.can('view_finance_dng_payment_requests')" :href="route('finance.dng.payment-requests.show', event.request.id)" class="text-blue-600 hover:underline"> Request #{{ event.request.id }} </Link>
                    <div v-else class="text-sm font-medium">Request #{{ event.request.id }}</div>
                    <div><span class="text-muted-foreground">Status:</span> {{ event.request.status }}</div>
                    <div><span class="text-muted-foreground">Student:</span> {{ event.request.student?.full_name || event.request.student_code }}</div>
                    <div>
                        <span class="text-muted-foreground">Item:</span> <span class="font-mono">{{ event.request.item_id }}</span>
                    </div>
                    <div><span class="text-muted-foreground">Amount:</span> {{ formatCurrency(event.request.amount) }}</div>
                </CardContent>
                <CardContent v-else class="text-sm text-red-700"> No linked request. This event is currently orphaned in admin view. </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"><Wallet class="h-5 w-5" />Canonical Payment</CardTitle></CardHeader
                >
                <CardContent v-if="event.request?.payment" class="space-y-2 text-sm">
                    <Link v-if="permission.can('view_finance_payment_details')" :href="route('finance.payments.show', event.request.payment.id)" class="text-blue-600 hover:underline"> Payment #{{ event.request.payment.id }} </Link>
                    <div v-else class="text-sm font-medium">Payment #{{ event.request.payment.id }}</div>
                    <div><span class="text-muted-foreground">Status:</span> {{ event.request.payment.status }}</div>
                    <div><span class="text-muted-foreground">Amount:</span> {{ formatCurrency(event.request.payment.amount) }}</div>
                    <div><span class="text-muted-foreground">Paid at:</span> {{ formatDate(event.request.payment.paid_at) }}</div>
                </CardContent>
                <CardContent v-else class="text-muted-foreground text-sm"> No canonical payment linked yet. </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"><CheckCircle2 class="h-4 w-4 text-green-600" />Extracted Facts</CardTitle></CardHeader
                >
                <CardContent class="grid gap-2 text-sm">
                    <div v-for="(value, key) in event.payload_facts" :key="key" class="flex justify-between gap-4 border-b pb-2 last:border-b-0">
                        <span class="text-muted-foreground">{{ key }}</span>
                        <span class="max-w-[60%] text-right font-mono text-xs break-all">{{ value ?? '-' }}</span>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="diagnosisVariant === 'success'" class="border-green-200">
                <CardHeader
                    ><CardTitle class="flex items-center gap-2 text-green-700"><CheckCircle2 class="h-4 w-4" />Processed</CardTitle></CardHeader
                >
                <CardContent class="text-sm text-green-700">Event processed successfully — payment created/updated and request transitioned forward.</CardContent>
            </Card>
            <Card v-else-if="diagnosisVariant === 'skipped'" class="border-blue-200">
                <CardHeader
                    ><CardTitle class="flex items-center gap-2 text-blue-700"><Info class="h-4 w-4" />Skipped (idempotent)</CardTitle></CardHeader
                >
                <CardContent class="space-y-1 text-sm text-blue-700">
                    <p>{{ event.error_message || 'Event was skipped because the request had already progressed past this state.' }}</p>
                    <p class="text-xs text-blue-600">This is expected behaviour for duplicate or late callbacks. No action required.</p>
                </CardContent>
            </Card>
            <Card v-else-if="diagnosisVariant === 'error'" class="border-red-200">
                <CardHeader
                    ><CardTitle class="flex items-center gap-2 text-red-700"><AlertTriangle class="h-4 w-4" />Diagnosis</CardTitle></CardHeader
                >
                <CardContent class="text-sm text-red-700">{{ event.error_message || 'Event failed without an explicit error message.' }}</CardContent>
            </Card>
            <Card v-else-if="!event.is_valid_checksum && event.processing_status !== 'received'" class="border-red-200">
                <CardHeader
                    ><CardTitle class="flex items-center gap-2 text-red-700"><ShieldAlert class="h-4 w-4" />Checksum Failure</CardTitle></CardHeader
                >
                <CardContent class="text-sm text-red-700">Checksum did not match the expected DNG signature string.</CardContent>
            </Card>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <JsonPayloadCard title="Headers" :payload="event.headers" />
            <JsonPayloadCard title="Payload" :payload="event.payload" />
        </div>
    </div>
</template>
