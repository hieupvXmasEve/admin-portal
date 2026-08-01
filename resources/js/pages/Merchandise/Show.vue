<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useApi, type ApiResponse } from '@/composables/useApiRequest';
import { usePermissions } from '@/composables/usePermissions';
import { Head, Link, router } from '@inertiajs/vue3';
import { History, Loader2, Package, Pencil } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Campus {
    id: number;
    name: string;
}

interface StockMovementEntry {
    id: number;
    change: number;
    quantity_before: number;
    quantity_after: number;
    type: string;
    note: string | null;
    performed_by: { id: number; name: string } | null;
    created_at: string | null;
}

interface MerchandiseVariant {
    id: number;
    campus_id: number;
    campus?: Campus;
    color: string | null;
    size: string | null;
    sku: string | null;
    stock_quantity: number;
    is_active: boolean;
    recentStockMovements?: StockMovementEntry[];
}

interface MerchandiseImage {
    id: number;
    path: string;
    sort_order: number;
    is_primary: boolean;
}

interface MerchandiseDetail {
    id: number;
    name: string;
    description: string | null;
    gold_price: number;
    status: string;
    images: MerchandiseImage[];
    variants: MerchandiseVariant[];
}

interface Props {
    merchandise: MerchandiseDetail;
    availabilityByCampus: Record<number, string>;
    movementTypes: string[];
}

const props = defineProps<Props>();
const { can } = usePermissions();
const api = useApi();

const getStatusVariant = (status: string): 'default' | 'destructive' | 'outline' | 'secondary' | 'success' => {
    const variants = {
        active: 'success' as const,
        coming_soon: 'secondary' as const,
        hidden: 'outline' as const,
        archived: 'destructive' as const,
    };
    return variants[status as keyof typeof variants] || 'outline';
};

const getAvailabilityBadge = (campusId: number): { label: string; variant: 'success' | 'destructive' | 'secondary' | 'outline' } => {
    const availability = props.availabilityByCampus[campusId];
    const map: Record<string, { label: string; variant: 'success' | 'destructive' | 'secondary' | 'outline' }> = {
        available: { label: 'Available', variant: 'success' },
        out_of_stock: { label: 'Out of stock', variant: 'destructive' },
        coming_soon: { label: 'Coming soon', variant: 'secondary' },
        not_visible: { label: 'Not visible', variant: 'outline' },
    };
    return map[availability] ?? { label: 'Unknown', variant: 'outline' };
};

const variantsByCampus = () => {
    const groups = new Map<number, { campus?: Campus; campusId: number; variants: MerchandiseVariant[] }>();
    for (const variant of props.merchandise.variants) {
        const existing = groups.get(variant.campus_id);
        if (existing) {
            existing.variants.push(variant);
        } else {
            groups.set(variant.campus_id, { campus: variant.campus, campusId: variant.campus_id, variants: [variant] });
        }
    }
    return [...groups.values()];
};

// ── Stock adjustment dialog ─────────────────────────────────────────────────

const adjustingVariant = ref<MerchandiseVariant | null>(null);
const adjustForm = reactive({ change: 0, type: props.movementTypes[0] ?? 'manual_increase', note: '' });
const isAdjusting = ref(false);

const openAdjustDialog = (variant: MerchandiseVariant) => {
    adjustingVariant.value = variant;
    adjustForm.change = 0;
    adjustForm.type = props.movementTypes[0] ?? 'manual_increase';
    adjustForm.note = '';
};

const submitAdjustment = async () => {
    const variant = adjustingVariant.value;
    if (!variant || adjustForm.change === 0) {
        toast.error('Enter a non-zero change amount.');
        return;
    }

    isAdjusting.value = true;

    try {
        const response = await api.post(route('merchandise.variants.stock-adjust', variant.id), {
            change: Number(adjustForm.change),
            type: adjustForm.type,
            note: adjustForm.note || null,
        });
        const body = response.data.value as ApiResponse | null;

        if (body?.success) {
            toast.success('Stock adjusted successfully');
            adjustingVariant.value = null;
            router.reload({ only: ['merchandise', 'availabilityByCampus'] });
            return;
        }

        toast.error(body?.message || 'Failed to adjust stock');
    } catch {
        toast.error('Failed to adjust stock');
    } finally {
        isAdjusting.value = false;
    }
};

// ── Full movement history dialog (paginated, fetched on demand) ────────────

interface MovementHistoryMeta {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
}

const historyVariant = ref<MerchandiseVariant | null>(null);
const historyMovements = ref<StockMovementEntry[]>([]);
const historyMeta = ref<MovementHistoryMeta | null>(null);
const isLoadingHistory = ref(false);

const loadHistory = async (variant: MerchandiseVariant, page = 1) => {
    isLoadingHistory.value = true;

    try {
        const response = await api.get(route('merchandise.variants.stock-movements', variant.id), { page });
        const body = response.data.value as (ApiResponse<StockMovementEntry[]> & { meta?: MovementHistoryMeta }) | null;

        if (body?.success) {
            historyMovements.value = body.data ?? [];
            historyMeta.value = body.meta ?? null;
        } else {
            toast.error(body?.message || 'Failed to load movement history');
        }
    } catch {
        toast.error('Failed to load movement history');
    } finally {
        isLoadingHistory.value = false;
    }
};

const openHistoryDialog = (variant: MerchandiseVariant) => {
    historyVariant.value = variant;
    loadHistory(variant, 1);
};
</script>

<template>
    <Head :title="merchandise.name" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">{{ merchandise.name }}</h2>
            <div class="mt-1 flex items-center gap-2">
                <Badge :variant="getStatusVariant(merchandise.status)">{{ merchandise.status }}</Badge>
                <span class="text-muted-foreground text-sm">{{ merchandise.gold_price }} gold</span>
            </div>
        </div>
        <div class="flex gap-2">
            <Button variant="outline" as-child>
                <Link :href="route('merchandise.index')"> Back to List </Link>
            </Button>
            <Button v-if="can('edit_merchandise')" as-child class="gap-2">
                <Link :href="route('merchandise.edit', merchandise.id)">
                    <Pencil class="h-4 w-4" />
                    Edit
                </Link>
            </Button>
        </div>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <CardTitle>Details</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <p v-if="merchandise.description" class="text-muted-foreground text-sm">{{ merchandise.description }}</p>

            <div v-if="merchandise.images.length > 0" class="flex flex-wrap gap-3">
                <div v-for="image in [...merchandise.images].sort((a, b) => a.sort_order - b.sort_order)" :key="image.id" class="relative">
                    <img :src="image.path" :alt="merchandise.name" width="120" height="120" loading="lazy" class="h-28 w-28 rounded-md border object-cover" />
                    <Badge v-if="image.is_primary" variant="success" class="absolute top-2 left-2">Primary</Badge>
                </div>
            </div>
            <p v-else class="text-muted-foreground flex items-center gap-2 text-sm">
                <Package class="h-4 w-4" />
                No images uploaded.
            </p>
        </CardContent>
    </Card>

    <Card v-for="group in variantsByCampus()" :key="group.campusId" class="mt-6">
        <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle>{{ group.campus?.name ?? `Campus #${group.campusId}` }}</CardTitle>
            <Badge :variant="getAvailabilityBadge(group.campusId).variant">{{ getAvailabilityBadge(group.campusId).label }}</Badge>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Color</TableHead>
                        <TableHead>Size</TableHead>
                        <TableHead>SKU</TableHead>
                        <TableHead>Stock</TableHead>
                        <TableHead>Active</TableHead>
                        <TableHead></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="variant in group.variants" :key="variant.id">
                        <TableCell>{{ variant.color ?? '—' }}</TableCell>
                        <TableCell>{{ variant.size ?? '—' }}</TableCell>
                        <TableCell>{{ variant.sku ?? '—' }}</TableCell>
                        <TableCell class="font-medium">{{ variant.stock_quantity }}</TableCell>
                        <TableCell>
                            <Badge :variant="variant.is_active ? 'success' : 'outline'">{{ variant.is_active ? 'Active' : 'Inactive' }}</Badge>
                        </TableCell>
                        <TableCell>
                            <div class="flex justify-end gap-2">
                                <Button v-if="can('adjust_merchandise_stock')" size="sm" variant="outline" @click="openAdjustDialog(variant)"> Adjust Stock </Button>
                                <Button v-if="can('view_merchandise_audit')" size="icon" variant="ghost" title="Movement history" @click="openHistoryDialog(variant)">
                                    <History class="h-4 w-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="can('view_merchandise_audit')" class="mt-4 space-y-3">
                <div v-for="variant in group.variants" :key="`recent-${variant.id}`">
                    <p v-if="variant.recentStockMovements && variant.recentStockMovements.length > 0" class="text-muted-foreground mb-1 text-xs font-medium">
                        Recent movements — {{ variant.color ?? 'Default' }} / {{ variant.size ?? '—' }}
                    </p>
                    <Table v-if="variant.recentStockMovements && variant.recentStockMovements.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Change</TableHead>
                                <TableHead>Before → After</TableHead>
                                <TableHead>By</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="movement in variant.recentStockMovements" :key="movement.id">
                                <TableCell>{{ movement.created_at ? new Date(movement.created_at).toLocaleString() : '—' }}</TableCell>
                                <TableCell>{{ movement.type }}</TableCell>
                                <TableCell :class="movement.change >= 0 ? 'text-green-600' : 'text-red-600'">{{ movement.change >= 0 ? '+' : '' }}{{ movement.change }}</TableCell>
                                <TableCell>{{ movement.quantity_before }} → {{ movement.quantity_after }}</TableCell>
                                <TableCell>{{ movement.performed_by?.name ?? '—' }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </CardContent>
    </Card>

    <p v-if="merchandise.variants.length === 0" class="text-muted-foreground mt-6 text-sm">No variants configured for any campus yet.</p>

    <!-- Stock adjustment dialog -->
    <Dialog :open="adjustingVariant !== null" @update:open="(open) => !open && (adjustingVariant = null)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Adjust Stock</DialogTitle>
                <DialogDescription v-if="adjustingVariant"> {{ adjustingVariant.color ?? 'Default' }} / {{ adjustingVariant.size ?? '—' }} — current stock {{ adjustingVariant.stock_quantity }} </DialogDescription>
            </DialogHeader>
            <div class="space-y-4 py-2">
                <div class="space-y-2">
                    <Label for="adjust-change">Change (use a negative number to decrease)</Label>
                    <Input id="adjust-change" v-model.number="adjustForm.change" type="number" step="1" />
                </div>
                <div class="space-y-2">
                    <Label for="adjust-type">Type</Label>
                    <Select v-model="adjustForm.type">
                        <SelectTrigger id="adjust-type"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="type in movementTypes" :key="type" :value="type">{{ type }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="space-y-2">
                    <Label for="adjust-note">Note (optional)</Label>
                    <Textarea id="adjust-note" v-model="adjustForm.note" rows="2" />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="adjustingVariant = null">Cancel</Button>
                <Button :disabled="isAdjusting" class="gap-2" @click="submitAdjustment">
                    <Loader2 v-if="isAdjusting" class="h-4 w-4 animate-spin" />
                    Adjust
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Full movement history dialog -->
    <Dialog :open="historyVariant !== null" @update:open="(open) => !open && (historyVariant = null)">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Movement History</DialogTitle>
                <DialogDescription v-if="historyVariant"> {{ historyVariant.color ?? 'Default' }} / {{ historyVariant.size ?? '—' }} </DialogDescription>
            </DialogHeader>
            <div class="max-h-96 space-y-3 overflow-y-auto py-2">
                <Loader2 v-if="isLoadingHistory" class="text-muted-foreground mx-auto h-6 w-6 animate-spin" />
                <Table v-else-if="historyMovements.length > 0">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Change</TableHead>
                            <TableHead>Before → After</TableHead>
                            <TableHead>By</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="movement in historyMovements" :key="movement.id">
                            <TableCell>{{ movement.created_at ? new Date(movement.created_at).toLocaleString() : '—' }}</TableCell>
                            <TableCell>{{ movement.type }}</TableCell>
                            <TableCell :class="movement.change >= 0 ? 'text-green-600' : 'text-red-600'">{{ movement.change >= 0 ? '+' : '' }}{{ movement.change }}</TableCell>
                            <TableCell>{{ movement.quantity_before }} → {{ movement.quantity_after }}</TableCell>
                            <TableCell>{{ movement.performed_by?.name ?? '—' }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p v-else class="text-muted-foreground text-sm">No stock movements recorded yet.</p>
            </div>
            <DialogFooter v-if="historyMeta && historyMeta.total_pages > 1" class="justify-between sm:justify-between">
                <Button variant="outline" size="sm" :disabled="historyMeta.page <= 1 || isLoadingHistory" @click="historyVariant && loadHistory(historyVariant, historyMeta.page - 1)"> Previous </Button>
                <span class="text-muted-foreground text-xs">Page {{ historyMeta.page }} of {{ historyMeta.total_pages }}</span>
                <Button variant="outline" size="sm" :disabled="historyMeta.page >= historyMeta.total_pages || isLoadingHistory" @click="historyVariant && loadHistory(historyVariant, historyMeta.page + 1)"> Next </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
