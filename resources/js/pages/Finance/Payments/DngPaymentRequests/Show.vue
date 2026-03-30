<script setup lang="ts">
import JsonPayloadCard from '@/components/finance/JsonPayloadCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermission } from '@/composables/usePermission';
import { formatCurrency, formatDate } from '@/utils/format';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, CheckCircle2, Link2, ReceiptText, Siren, UserRound, Wallet } from 'lucide-vue-next';

interface Props {
    request: {
        id: number;
        student: {
            id: number;
            student_code: string;
            full_name: string;
            email: string | null;
        } | null;
        campus_code: string;
        student_code: string;
        fee_type: string;
        description: string | null;
        item_id: string;
        amount: number;
        status: string;
        dng_transaction_id: string | null;
        dng_payment_id: string | null;
        psp_code: string | null;
        invoice_serial_number: string | null;
        invoice_date: string | null;
        paid_at: string | null;
        created_at: string | null;
        updated_at: string | null;
        error_message: string | null;
        has_bridged_payment: boolean;
        payment: {
            id: number;
            amount: number;
            status: string;
            external_ref: string | null;
            paid_at: string | null;
        } | null;
        payloads: Record<string, unknown>;
        webhook_events: Array<{
            id: number;
            event_type: string;
            processing_status: string;
            is_valid_checksum: boolean;
            created_at: string | null;
            processed_at: string | null;
            error_message: string | null;
        }>;
    };
}

const props = defineProps<Props>();
const permission = usePermission();

const getStatusClass = (status: string) => {
    switch (status) {
        case 'failed':
            return 'bg-red-50 text-red-700 border-red-200';
        case 'reconciled':
        case 'paid_invoiced':
            return 'bg-green-50 text-green-700 border-green-200';
        case 'paid_uninvoiced':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'pushed_to_dng':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-200';
    }
};
</script>

<template>
    <div class="space-y-6">
        <Head :title="`DNG Request #${request.id}`" />

        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Link :href="route('finance.dng.payment-requests.index')">
                    <Button variant="outline" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-semibold">DNG Request #{{ request.id }}</h1>
                        <Badge variant="outline" :class="getStatusClass(request.status)">{{ request.status }}</Badge>
                    </div>
                    <p class="text-muted-foreground text-sm">Created {{ formatDate(request.created_at) }} · Updated {{ formatDate(request.updated_at) }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card>
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"><UserRound class="h-5 w-5" />Student</CardTitle></CardHeader
                >
                <CardContent class="space-y-2 text-sm">
                    <div class="font-medium">{{ request.student?.full_name || '-' }}</div>
                    <div class="text-muted-foreground">{{ request.student_code }}</div>
                    <div class="text-muted-foreground">{{ request.student?.email || 'No email' }}</div>
                    <div class="text-muted-foreground">Campus {{ request.campus_code }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"><ReceiptText class="h-5 w-5" />Identifiers</CardTitle></CardHeader
                >
                <CardContent class="space-y-2 text-sm">
                    <div><span class="text-muted-foreground">Fee type:</span> {{ request.fee_type }}</div>
                    <div><span class="text-muted-foreground">Description:</span> {{ request.description || '-' }}</div>
                    <div>
                        <span class="text-muted-foreground">Item:</span> <span class="font-mono">{{ request.item_id }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Payment ID:</span> <span class="font-mono">{{ request.dng_payment_id || '-' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Transaction ID:</span> <span class="font-mono">{{ request.dng_transaction_id || '-' }}</span>
                    </div>
                    <div><span class="text-muted-foreground">PSP:</span> {{ request.psp_code || '-' }}</div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"><Wallet class="h-5 w-5" />Payment Bridge</CardTitle></CardHeader
                >
                <CardContent class="space-y-2 text-sm">
                    <div class="text-2xl font-semibold">{{ formatCurrency(request.amount) }}</div>
                    <div><span class="text-muted-foreground">Paid at:</span> {{ formatDate(request.paid_at) }}</div>
                    <div><span class="text-muted-foreground">Invoice serial:</span> {{ request.invoice_serial_number || '-' }}</div>
                    <div><span class="text-muted-foreground">Invoice date:</span> {{ formatDate(request.invoice_date) }}</div>
                    <div v-if="request.payment" class="pt-2">
                        <Link v-if="permission.can('view_finance_payment_details')" :href="route('finance.payments.show', request.payment.id)" class="text-blue-600 hover:underline"> Linked payment #{{ request.payment.id }} </Link>
                        <div v-else class="text-sm font-medium">Linked payment #{{ request.payment.id }}</div>
                    </div>
                    <Badge v-else variant="outline">No canonical payment yet</Badge>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card v-if="request.error_message" class="border-red-200">
                <CardHeader
                    ><CardTitle class="flex items-center gap-2 text-red-700"><Siren class="h-4 w-4" />Failure</CardTitle></CardHeader
                >
                <CardContent class="text-sm text-red-700">{{ request.error_message }}</CardContent>
            </Card>
            <Card v-else-if="request.webhook_events.length === 0">
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"><AlertTriangle class="h-4 w-4 text-amber-600" />Awaiting Webhook</CardTitle></CardHeader
                >
                <CardContent class="text-muted-foreground text-sm">Request exists, but no webhook event has been linked yet.</CardContent>
            </Card>
            <Card v-else-if="!request.has_bridged_payment">
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"><Link2 class="h-4 w-4 text-blue-600" />Not Bridged Yet</CardTitle></CardHeader
                >
                <CardContent class="text-muted-foreground text-sm">Webhook arrived, but no canonical payment link is visible yet.</CardContent>
            </Card>
            <Card v-else>
                <CardHeader
                    ><CardTitle class="flex items-center gap-2"><CheckCircle2 class="h-4 w-4 text-green-600" />Bridge Complete</CardTitle></CardHeader
                >
                <CardContent class="text-muted-foreground text-sm">This DNG request has been bridged into the canonical payments ledger.</CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader><CardTitle>Linked Webhook Events</CardTitle></CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>ID</TableHead>
                            <TableHead>Created</TableHead>
                            <TableHead>Event Type</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Checksum</TableHead>
                            <TableHead>Error</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="event in request.webhook_events" :key="event.id">
                            <TableCell>#{{ event.id }}</TableCell>
                            <TableCell>{{ formatDate(event.created_at) }}</TableCell>
                            <TableCell>{{ event.event_type }}</TableCell>
                            <TableCell
                                ><Badge variant="outline">{{ event.processing_status }}</Badge></TableCell
                            >
                            <TableCell>
                                <Badge variant="outline" :class="event.is_valid_checksum ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700'">
                                    {{ event.is_valid_checksum ? 'valid' : 'invalid' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="max-w-[18rem] truncate text-sm">{{ event.error_message || '-' }}</TableCell>
                            <TableCell class="text-right">
                                <Link v-if="permission.can('view_finance_dng_webhook_events')" :href="route('finance.dng.webhook-events.show', event.id)">
                                    <Button variant="ghost" size="sm">View</Button>
                                </Link>
                                <span v-else class="text-muted-foreground text-sm">No access</span>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="request.webhook_events.length === 0">
                            <TableCell colspan="7" class="text-muted-foreground h-24 text-center">No webhook events linked.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <div class="grid gap-6 xl:grid-cols-2">
            <JsonPayloadCard title="Push Payload" :payload="request.payloads.push_payload" />
            <JsonPayloadCard title="Push Response" :payload="request.payloads.push_response" />
            <JsonPayloadCard title="Last Callback Payload" :payload="request.payloads.last_callback_payload" />
        </div>
    </div>
</template>
