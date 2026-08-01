<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useApi, type ApiResponse } from '@/composables/useApiRequest';
import { Head } from '@inertiajs/vue3';
import { debounce } from 'lodash-es';
import { AlertTriangle, Boxes, Coins, History, Package, Trophy } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { route } from 'ziggy-js';

interface Campus {
    id: number;
    name: string;
}

interface OrderQueueItem {
    id: number;
    code: string;
    campus_id: number;
    status: string;
    total_gold: number;
    created_at: string;
    ready_at: string | null;
    collection_deadline: string | null;
    shipped_at: string | null;
    student: { id: number; full_name: string } | null;
}

interface GoldSummary {
    gold_used: number;
    gold_refunded: number;
}

interface RankingItem {
    merchandise_id: number;
    merchandise_name: string;
    total_quantity: number;
    order_count: number;
}

interface StockVariant {
    id: number;
    merchandise_id: number;
    campus_id: number;
    color: string | null;
    size: string | null;
    sku: string | null;
    stock_quantity: number;
    is_active: boolean;
    merchandise: { id: number; name: string } | null;
    campus: { id: number; name: string } | null;
}

interface StockMovementRow {
    id: number;
    change: number;
    quantity_before: number;
    quantity_after: number;
    type: string;
    note: string | null;
    created_at: string;
    variant: { merchandise: { name: string } | null; campus: { name: string } | null; color: string | null; size: string | null } | null;
    performed_by: { id: number; name: string } | null;
}

interface ReportPayload {
    filters: { campus_id: number | null; date_from: string | null; date_to: string | null; status: string | null };
    granted_campuses: Campus[];
    statuses: string[];
    orders_by_status: { counts: Record<string, number>; queues: Record<string, OrderQueueItem[]> };
    gold_summary: GoldSummary;
    most_redeemed: RankingItem[];
    stock: { variants: StockVariant[]; movements: StockMovementRow[] };
}

const props = defineProps<ReportPayload>();
const api = useApi();

const report = reactive<ReportPayload>({ ...props });

const filterForm = reactive({
    campus_id: props.filters.campus_id !== null ? String(props.filters.campus_id) : 'all',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    status: props.filters.status ?? 'all',
});

const loading = ref(false);
const loadError = ref<string | null>(null);

const QUEUE_LABELS: Record<string, string> = {
    pending_review: 'Pending Review',
    ready_for_collection: 'Ready for Collection',
    pickup_overdue: 'Pickup Overdue',
    shipped: 'Shipped',
};

const refresh = async () => {
    loading.value = true;
    loadError.value = null;

    try {
        const response = await api.get(route('merchandise.reports.data'), {
            campus_id: filterForm.campus_id === 'all' ? null : filterForm.campus_id,
            date_from: filterForm.date_from || null,
            date_to: filterForm.date_to || null,
            status: filterForm.status === 'all' ? null : filterForm.status,
        });
        const body = response.data.value as ApiResponse<ReportPayload> | null;

        if (body?.success && body.data) {
            Object.assign(report, body.data);
        } else {
            loadError.value = body?.message ?? 'Failed to load report data';
        }
    } catch {
        loadError.value = 'Failed to load report data';
    } finally {
        loading.value = false;
    }
};

const debouncedRefresh = debounce(refresh, 300);
</script>

<template>
    <Head title="Merchandise Reports" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-xl leading-tight font-semibold text-gray-800">Merchandise Reports</h2>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Filters</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div class="space-y-2">
                        <Label for="report-campus">Campus</Label>
                        <Select v-model="filterForm.campus_id" @update:model-value="refresh">
                            <SelectTrigger id="report-campus">
                                <SelectValue placeholder="All granted campuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All granted campuses</SelectItem>
                                <SelectItem v-for="campus in report.granted_campuses" :key="campus.id" :value="String(campus.id)">
                                    {{ campus.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="report-date-from">From</Label>
                        <Input id="report-date-from" v-model="filterForm.date_from" type="date" @update:model-value="debouncedRefresh" />
                    </div>

                    <div class="space-y-2">
                        <Label for="report-date-to">To</Label>
                        <Input id="report-date-to" v-model="filterForm.date_to" type="date" @update:model-value="debouncedRefresh" />
                    </div>

                    <div class="space-y-2">
                        <Label for="report-status">Queue status</Label>
                        <Select v-model="filterForm.status" @update:model-value="refresh">
                            <SelectTrigger id="report-status">
                                <SelectValue placeholder="All queues" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All queues</SelectItem>
                                <SelectItem v-for="status in report.statuses" :key="status" :value="status">
                                    {{ status }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <p v-if="loadError" class="text-destructive mt-3 text-sm">{{ loadError }}</p>
            </CardContent>
        </Card>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <Card>
                <CardHeader class="flex flex-row items-center gap-2 space-y-0">
                    <Coins class="text-muted-foreground h-5 w-5" />
                    <div>
                        <CardTitle class="text-base">Gold Used</CardTitle>
                        <CardDescription>Non-reversed orders</CardDescription>
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">{{ report.gold_summary.gold_used.toLocaleString() }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center gap-2 space-y-0">
                    <Coins class="text-muted-foreground h-5 w-5" />
                    <div>
                        <CardTitle class="text-base">Gold Refunded</CardTitle>
                        <CardDescription>Rejected / cancelled orders</CardDescription>
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">{{ report.gold_summary.gold_refunded.toLocaleString() }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center gap-2 space-y-0">
                    <AlertTriangle class="text-muted-foreground h-5 w-5" />
                    <div>
                        <CardTitle class="text-base">Order Counts by Status</CardTitle>
                    </div>
                </CardHeader>
                <CardContent class="flex flex-wrap gap-2">
                    <Badge v-for="(count, status) in report.orders_by_status.counts" :key="status" variant="outline"> {{ status }}: {{ count }} </Badge>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="flex flex-row items-center gap-2 space-y-0">
                <Package class="text-muted-foreground h-5 w-5" />
                <CardTitle>Operational Queues</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <div v-for="(items, queueStatus) in report.orders_by_status.queues" :key="queueStatus">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ QUEUE_LABELS[queueStatus] ?? queueStatus }} ({{ items.length }})</h3>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Student</TableHead>
                                <TableHead>Gold</TableHead>
                                <TableHead>Created</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="item in items" :key="item.id">
                                <TableCell>{{ item.code }}</TableCell>
                                <TableCell>{{ item.student?.full_name ?? '—' }}</TableCell>
                                <TableCell>{{ item.total_gold }}</TableCell>
                                <TableCell>{{ new Date(item.created_at).toLocaleString() }}</TableCell>
                            </TableRow>
                            <TableRow v-if="items.length === 0">
                                <TableCell colspan="4" class="text-muted-foreground text-center">No orders in this queue.</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center gap-2 space-y-0">
                <Trophy class="text-muted-foreground h-5 w-5" />
                <CardTitle>Most Redeemed Merchandise</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Merchandise</TableHead>
                            <TableHead>Total Quantity</TableHead>
                            <TableHead>Orders</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="row in report.most_redeemed" :key="row.merchandise_id">
                            <TableCell>{{ row.merchandise_name }}</TableCell>
                            <TableCell>{{ row.total_quantity }}</TableCell>
                            <TableCell>{{ row.order_count }}</TableCell>
                        </TableRow>
                        <TableRow v-if="report.most_redeemed.length === 0">
                            <TableCell colspan="3" class="text-muted-foreground text-center">No redemptions yet.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center gap-2 space-y-0">
                <Boxes class="text-muted-foreground h-5 w-5" />
                <CardTitle>Stock by Campus / Variant</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Merchandise</TableHead>
                            <TableHead>Campus</TableHead>
                            <TableHead>Variant</TableHead>
                            <TableHead>Stock</TableHead>
                            <TableHead>Active</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="variant in report.stock.variants" :key="variant.id">
                            <TableCell>{{ variant.merchandise?.name ?? '—' }}</TableCell>
                            <TableCell>{{ variant.campus?.name ?? '—' }}</TableCell>
                            <TableCell>{{ [variant.color, variant.size].filter(Boolean).join(' / ') || '—' }}</TableCell>
                            <TableCell>{{ variant.stock_quantity }}</TableCell>
                            <TableCell>{{ variant.is_active ? 'Yes' : 'No' }}</TableCell>
                        </TableRow>
                        <TableRow v-if="report.stock.variants.length === 0">
                            <TableCell colspan="5" class="text-muted-foreground text-center">No variants found.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex flex-row items-center gap-2 space-y-0">
                <History class="text-muted-foreground h-5 w-5" />
                <CardTitle>Recent Stock Movements</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Merchandise</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Change</TableHead>
                            <TableHead>Before → After</TableHead>
                            <TableHead>By</TableHead>
                            <TableHead>When</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="movement in report.stock.movements" :key="movement.id">
                            <TableCell>{{ movement.variant?.merchandise?.name ?? '—' }} ({{ movement.variant?.campus?.name ?? '—' }})</TableCell>
                            <TableCell>{{ movement.type }}</TableCell>
                            <TableCell>{{ movement.change > 0 ? '+' : '' }}{{ movement.change }}</TableCell>
                            <TableCell>{{ movement.quantity_before }} → {{ movement.quantity_after }}</TableCell>
                            <TableCell>{{ movement.performed_by?.name ?? '—' }}</TableCell>
                            <TableCell>{{ new Date(movement.created_at).toLocaleString() }}</TableCell>
                        </TableRow>
                        <TableRow v-if="report.stock.movements.length === 0">
                            <TableCell colspan="6" class="text-muted-foreground text-center">No stock movements in range.</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
