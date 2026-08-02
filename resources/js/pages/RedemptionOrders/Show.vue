<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useApi, type ApiResponse } from '@/composables/useApiRequest';
import { Head, Link, router } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, Package } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface RedemptionOrderItem {
    id: number;
    merchandise_name: string;
    variant_label: string | null;
    gold_price_each: number;
    line_total: number;
    quantity: number;
    variant?: { merchandise?: { images?: Array<{ path: string; is_primary: boolean }> } } | null;
}

interface RedemptionOrder {
    id: number;
    code: string;
    status: string;
    previous_status: string | null;
    method: 'pickup' | 'shipping';
    total_gold: number;
    items: RedemptionOrderItem[];
    student: { student_id: string; full_name: string; email: string; phone: string | null; campus?: { name: string } | null };
    collection_location: string | null;
    ready_at: string | null;
    collection_deadline: string | null;
    collected_at: string | null;
    collected_confirmed_by?: { name: string } | null;
    collection_note: string | null;
    shipping_address: string | null;
    shipped_at: string | null;
    shipped_by?: { name: string } | null;
    shipping_note: string | null;
    reject_reason: string | null;
    cancellation_requested_at: string | null;
    cancellation_reason: string | null;
    cancellation_result: string | null;
    cancellation_handled_at: string | null;
    cancellation_note: string | null;
    cancelled_at: string | null;
    created_at: string | null;
}

const props = defineProps<{ order: RedemptionOrder }>();

const api = useApi();
const isSubmitting = ref(false);

function fmt(value: string | null) {
    if (!value) return '—';
    return new Date(value).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' });
}

function itemImage(item: RedemptionOrderItem): string | null {
    const images = item.variant?.merchandise?.images ?? [];
    return images.find((i) => i.is_primary)?.path ?? images[0]?.path ?? null;
}

const statusLabel = (status: string) => status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

const statusVariant = computed((): 'default' | 'destructive' | 'outline' | 'secondary' | 'success' => {
    const variants: Record<string, 'default' | 'destructive' | 'outline' | 'secondary' | 'success'> = {
        pending_review: 'secondary',
        approved: 'default',
        ready_for_collection: 'default',
        pickup_overdue: 'destructive',
        cancellation_requested: 'destructive',
        collected: 'success',
        shipped: 'success',
        rejected: 'destructive',
        cancelled: 'outline',
    };
    return variants[props.order.status] ?? 'outline';
});

// Every action shares one dialog shell; `kind` picks which fields render and
// which endpoint submit() calls. Keeps the state-machine gating (which
// actions are valid from which status) in one place instead of N components.
type ActionKind = 'approve' | 'reject' | 'ready-for-collection' | 'extend-deadline' | 'confirm-collected' | 'mark-shipped' | 'mark-overdue' | 'cancel-overdue' | 'accept-cancellation' | 'reject-cancellation';

interface ActionConfig {
    kind: ActionKind;
    title: string;
    description: string;
    confirmLabel: string;
    variant?: 'default' | 'destructive';
    needsReason?: boolean;
    reasonRequired?: boolean;
    needsLocation?: boolean;
    needsDeadline?: boolean;
    needsNote?: boolean;
}

const activeAction = ref<ActionConfig | null>(null);
const reasonInput = ref('');
const locationInput = ref('');
const deadlineInput = ref('');
const noteInput = ref('');

function openAction(config: ActionConfig) {
    reasonInput.value = '';
    locationInput.value = '';
    deadlineInput.value = props.order.collection_deadline ? props.order.collection_deadline.slice(0, 10) : '';
    noteInput.value = '';
    activeAction.value = config;
}

function closeAction() {
    activeAction.value = null;
}

async function submitAction() {
    const action = activeAction.value;
    if (!action) return;

    if (action.reasonRequired && reasonInput.value.trim() === '') {
        toast.error('A reason is required');
        return;
    }
    if (action.needsLocation && locationInput.value.trim() === '') {
        toast.error('A collection location is required');
        return;
    }

    isSubmitting.value = true;
    try {
        const { url, body } = buildRequest(action);
        const response = await api.post(url, body);
        const result = response.data.value as ApiResponse | null;

        if (result?.success) {
            toast.success(result.message || 'Order updated');
            closeAction();
            router.reload({ only: ['order'] });
            return;
        }

        toast.error(result?.message || 'Action failed');
    } catch {
        toast.error('Action failed');
    } finally {
        isSubmitting.value = false;
    }
}

function buildRequest(action: ActionConfig): { url: string; body: Record<string, unknown> } {
    const id = props.order.id;
    switch (action.kind) {
        case 'approve':
            return { url: route('redemption-orders.approve', id), body: {} };
        case 'reject':
            return { url: route('redemption-orders.reject', id), body: { reason: reasonInput.value } };
        case 'ready-for-collection':
            return {
                url: route('redemption-orders.ready-for-collection', id),
                body: { location: locationInput.value, deadline: deadlineInput.value || undefined },
            };
        case 'extend-deadline':
            return { url: route('redemption-orders.extend-deadline', id), body: { deadline: deadlineInput.value } };
        case 'confirm-collected':
            return { url: route('redemption-orders.confirm-collected', id), body: {} };
        case 'mark-shipped':
            return { url: route('redemption-orders.mark-shipped', id), body: { note: noteInput.value || undefined } };
        case 'mark-overdue':
            return { url: route('redemption-orders.mark-overdue', id), body: {} };
        case 'cancel-overdue':
            return { url: route('redemption-orders.cancel-overdue', id), body: { reason: reasonInput.value || undefined } };
        case 'accept-cancellation':
            return { url: route('redemption-orders.handle-cancellation', id), body: { accept: true, note: noteInput.value || undefined } };
        case 'reject-cancellation':
            return { url: route('redemption-orders.handle-cancellation', id), body: { accept: false, note: noteInput.value || undefined } };
    }
}
</script>

<template>
    <Head :title="`Order ${order.code}`" />

    <div class="space-y-6">
        <Link :href="route('redemption-orders.index')" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="h-4 w-4" />
            Back to Redemption Orders
        </Link>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ order.code }}</h1>
                <p class="text-muted-foreground">Placed {{ fmt(order.created_at) }}</p>
            </div>
            <Badge :variant="statusVariant" class="text-sm">{{ statusLabel(order.status) }}</Badge>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Items</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-for="item in order.items" :key="item.id" class="flex items-center gap-4 rounded-xl border p-3">
                            <div class="size-16 shrink-0 overflow-hidden rounded-lg bg-muted">
                                <img v-if="itemImage(item)" :src="itemImage(item)!" :alt="item.merchandise_name" class="h-full w-full object-cover" />
                                <div v-else class="flex h-full w-full items-center justify-center">
                                    <Package class="h-6 w-6 text-muted-foreground" />
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ item.merchandise_name }}</p>
                                <p class="text-sm text-muted-foreground">{{ item.variant_label ?? 'Default' }} &middot; x{{ item.quantity }}</p>
                            </div>
                            <p class="shrink-0 font-semibold">{{ item.line_total }} Gold</p>
                        </div>
                        <div class="flex items-center justify-between border-t pt-3 text-lg font-bold">
                            <span>Total</span>
                            <span>{{ order.total_gold }} Gold</span>
                        </div>
                    </CardContent>
                </Card>

                <Alert v-if="order.status === 'rejected'" variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertTitle>Order Rejected</AlertTitle>
                    <AlertDescription>{{ order.reject_reason ?? 'No reason was given.' }} Gold and stock were refunded.</AlertDescription>
                </Alert>

                <Alert v-if="order.status === 'cancelled' || order.cancellation_requested_at" :variant="order.status === 'cancellation_requested' ? 'destructive' : 'default'">
                    <AlertCircle class="h-4 w-4" />
                    <AlertTitle>
                        Cancellation {{ order.cancellation_result ?? (order.status === 'cancellation_requested' ? 'requested' : 'completed') }}
                    </AlertTitle>
                    <AlertDescription class="space-y-1">
                        <p v-if="order.cancellation_reason">Reason: {{ order.cancellation_reason }}</p>
                        <p v-if="order.cancellation_note">Staff note: {{ order.cancellation_note }}</p>
                        <p v-if="order.status === 'cancelled'" class="font-medium text-foreground">Gold and stock were refunded.</p>
                        <p class="text-muted-foreground">
                            <template v-if="order.cancellation_requested_at">Requested {{ fmt(order.cancellation_requested_at) }}</template>
                            <template v-else-if="order.cancelled_at">Cancelled {{ fmt(order.cancelled_at) }}</template>
                            <template v-if="order.cancellation_handled_at"> &middot; Handled {{ fmt(order.cancellation_handled_at) }}</template>
                        </p>
                    </AlertDescription>
                </Alert>

                <!-- Actions, gated by current status — mirrors RedemptionService's transition() allow-lists. -->
                <Card>
                    <CardHeader>
                        <CardTitle>Actions</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-2">
                        <template v-if="order.status === 'pending_review'">
                            <Button @click="openAction({ kind: 'approve', title: 'Approve order?', description: 'The student will be notified their order is approved.', confirmLabel: 'Approve' })">
                                Approve
                            </Button>
                            <Button
                                variant="destructive"
                                @click="
                                    openAction({
                                        kind: 'reject',
                                        title: 'Reject order?',
                                        description: 'Gold and stock will be refunded to the student.',
                                        confirmLabel: 'Reject',
                                        variant: 'destructive',
                                        needsReason: true,
                                        reasonRequired: true,
                                    })
                                "
                            >
                                Reject
                            </Button>
                        </template>

                        <template v-else-if="order.status === 'approved' && order.method === 'pickup'">
                            <Button
                                @click="
                                    openAction({
                                        kind: 'ready-for-collection',
                                        title: 'Mark ready for collection',
                                        description: 'Sets the pickup location and deadline the student sees.',
                                        confirmLabel: 'Mark Ready',
                                        needsLocation: true,
                                        needsDeadline: true,
                                    })
                                "
                            >
                                Ready for Collection
                            </Button>
                        </template>

                        <template v-else-if="order.status === 'approved' && order.method === 'shipping'">
                            <Button
                                @click="
                                    openAction({
                                        kind: 'mark-shipped',
                                        title: 'Mark as shipped',
                                        description: 'The student will be notified their order has shipped.',
                                        confirmLabel: 'Mark Shipped',
                                        needsNote: true,
                                    })
                                "
                            >
                                Mark Shipped
                            </Button>
                        </template>

                        <template v-else-if="order.status === 'ready_for_collection'">
                            <Button @click="openAction({ kind: 'confirm-collected', title: 'Confirm collected?', description: 'Marks this order as picked up by the student.', confirmLabel: 'Confirm' })">
                                Confirm Collected
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    openAction({
                                        kind: 'extend-deadline',
                                        title: 'Extend pickup deadline',
                                        description: 'Push back the collection deadline.',
                                        confirmLabel: 'Save',
                                        needsDeadline: true,
                                    })
                                "
                            >
                                Extend Deadline
                            </Button>
                            <Button
                                variant="destructive"
                                @click="openAction({ kind: 'mark-overdue', title: 'Mark pickup overdue?', description: 'Flags this order as overdue for collection.', confirmLabel: 'Mark Overdue', variant: 'destructive' })"
                            >
                                Mark Overdue
                            </Button>
                        </template>

                        <template v-else-if="order.status === 'pickup_overdue'">
                            <Button
                                @click="
                                    openAction({
                                        kind: 'ready-for-collection',
                                        title: 'Reopen for collection',
                                        description: 'Resets the pickup location and deadline.',
                                        confirmLabel: 'Reopen',
                                        needsLocation: true,
                                        needsDeadline: true,
                                    })
                                "
                            >
                                Reopen for Collection
                            </Button>
                            <Button @click="openAction({ kind: 'confirm-collected', title: 'Confirm collected?', description: 'Marks this order as picked up by the student.', confirmLabel: 'Confirm' })">
                                Confirm Collected
                            </Button>
                            <Button
                                variant="destructive"
                                @click="
                                    openAction({
                                        kind: 'cancel-overdue',
                                        title: 'Cancel this order?',
                                        description: 'Gold and stock will be refunded to the student.',
                                        confirmLabel: 'Cancel Order',
                                        variant: 'destructive',
                                        needsReason: true,
                                    })
                                "
                            >
                                Cancel Order
                            </Button>
                        </template>

                        <template v-else-if="order.status === 'cancellation_requested'">
                            <Button
                                @click="
                                    openAction({
                                        kind: 'accept-cancellation',
                                        title: 'Accept cancellation?',
                                        description: 'Gold and stock will be refunded to the student.',
                                        confirmLabel: 'Accept',
                                        needsNote: true,
                                    })
                                "
                            >
                                Accept Cancellation
                            </Button>
                            <Button
                                variant="outline"
                                @click="
                                    openAction({
                                        kind: 'reject-cancellation',
                                        title: 'Reject cancellation request?',
                                        description: 'The order reverts to its previous status — no refund.',
                                        confirmLabel: 'Reject Request',
                                        needsNote: true,
                                    })
                                "
                            >
                                Reject Request
                            </Button>
                        </template>

                        <p v-else class="text-sm text-muted-foreground">No further actions — this order is in a final state.</p>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Student</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-1 text-sm">
                        <p class="font-medium">{{ order.student.full_name }}</p>
                        <p class="text-muted-foreground">{{ order.student.student_id }}</p>
                        <p class="text-muted-foreground">{{ order.student.email }}</p>
                        <p v-if="order.student.phone" class="text-muted-foreground">{{ order.student.phone }}</p>
                        <p v-if="order.student.campus" class="text-muted-foreground">Campus: {{ order.student.campus.name }}</p>
                    </CardContent>
                </Card>

                <Card v-if="order.method === 'pickup'">
                    <CardHeader>
                        <CardTitle>Collection</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p><span class="text-muted-foreground">Location:</span> {{ order.collection_location ?? '—' }}</p>
                        <p><span class="text-muted-foreground">Ready at:</span> {{ fmt(order.ready_at) }}</p>
                        <p><span class="text-muted-foreground">Deadline:</span> {{ fmt(order.collection_deadline) }}</p>
                        <p v-if="order.collected_at"><span class="text-muted-foreground">Collected at:</span> {{ fmt(order.collected_at) }}</p>
                        <p v-if="order.collected_confirmed_by"><span class="text-muted-foreground">Confirmed by:</span> {{ order.collected_confirmed_by.name }}</p>
                    </CardContent>
                </Card>

                <Card v-else>
                    <CardHeader>
                        <CardTitle>Shipping</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p><span class="text-muted-foreground">Address:</span> {{ order.shipping_address ?? '—' }}</p>
                        <p v-if="order.shipped_at"><span class="text-muted-foreground">Shipped at:</span> {{ fmt(order.shipped_at) }}</p>
                        <p v-if="order.shipped_by"><span class="text-muted-foreground">Shipped by:</span> {{ order.shipped_by.name }}</p>
                        <p v-if="order.shipping_note"><span class="text-muted-foreground">Note:</span> {{ order.shipping_note }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog :open="activeAction !== null" @update:open="(open) => !open && closeAction()">
            <DialogContent v-if="activeAction">
                <DialogHeader>
                    <DialogTitle>{{ activeAction.title }}</DialogTitle>
                    <DialogDescription>{{ activeAction.description }}</DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <div v-if="activeAction.needsReason" class="space-y-2">
                        <Label for="action-reason">Reason{{ activeAction.reasonRequired ? '' : ' (optional)' }}</Label>
                        <Textarea id="action-reason" v-model="reasonInput" rows="3" />
                    </div>
                    <div v-if="activeAction.needsLocation" class="space-y-2">
                        <Label for="action-location">Collection location</Label>
                        <Input id="action-location" v-model="locationInput" placeholder="e.g. Campus front desk" />
                    </div>
                    <div v-if="activeAction.needsDeadline" class="space-y-2">
                        <Label for="action-deadline">Deadline{{ activeAction.kind === 'extend-deadline' ? '' : ' (optional, defaults to 14 days)' }}</Label>
                        <Input id="action-deadline" v-model="deadlineInput" type="date" />
                    </div>
                    <div v-if="activeAction.needsNote" class="space-y-2">
                        <Label for="action-note">Note (optional)</Label>
                        <Textarea id="action-note" v-model="noteInput" rows="3" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" :disabled="isSubmitting" @click="closeAction">Cancel</Button>
                    <Button :variant="activeAction.variant ?? 'default'" :disabled="isSubmitting" @click="submitAction">
                        {{ isSubmitting ? 'Saving…' : activeAction.confirmLabel }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
