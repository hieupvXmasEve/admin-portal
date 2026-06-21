<script setup lang="ts">
import DataPagination from '@/components/DataPagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDataTable } from '@/composables/useDataTable';
import { useFinanceSemester } from '@/composables/useFinanceSemester';
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, RefreshCw, Search } from 'lucide-vue-next';
import { computed, ref, watchEffect } from 'vue';

interface EgcBlock {
    id: number;
    student: { id: number; full_name: string; student_id: string };
    block_number: number;
    level_number: number;
    result: 'pending' | 'pass' | 'fail';
    attendance_rate: string | null;
    is_retake: boolean;
    synced_at: string | null;
}

interface Semester {
    id: number;
    name: string;
}

interface EgcBlockPagination {
    data: EgcBlock[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
    prev_page_url?: string | null;
    next_page_url?: string | null;
    links?: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface TargetOption {
    id: number;
    description: string;
    amount: number;
    semester_id: number;
    semester_name: string;
    invoice_id: number;
    invoice_number: string;
    target_block_number: number | null;
    target_level_number: number | null;
}

interface AdjustmentBlock {
    id: number;
    student_id: number;
    student_name: string;
    student_code: string;
    block_number: number;
    level_number: number;
    result: string;
    attendance_rate: string | null;
    is_retake: boolean;
    retake_discount_id: number | null;
    available_targets: TargetOption[];
}

interface EgcAdjustments {
    eligible_with_targets: AdjustmentBlock[];
    eligible_no_targets: AdjustmentBlock[];
    ineligible: AdjustmentBlock[];
    already_discounted: AdjustmentBlock[];
}

interface EgcReconciliationFilters {
    search: string;
    result: string;
    per_page: number;
    page: number;
}

const props = defineProps<{
    blocks: EgcBlockPagination | EgcBlock[];
    adjustments: EgcAdjustments;
    semesters: Semester[];
    currentSemester: Semester | null;
    filters: { semester_id: string | null; search: string; result: string; per_page?: number; page?: number };
}>();

const { selectedId, selectedLabel } = useFinanceSemester();

const { filters, setFilter, handleSearch, handlePaginationNavigate, handlePageSizeChange } = useDataTable<EgcReconciliationFilters>({
    baseUrl: route('finance.egc.block-results.index'),
    initialFilters: {
        search: props.filters.search ?? '',
        result: props.filters.result ?? 'all',
        per_page: props.filters.per_page ?? 50,
        page: props.filters.page ?? 1,
    },
    defaultValues: {
        search: '',
        result: 'all',
        per_page: 50,
        page: 1,
    },
    only: ['blocks', 'adjustments', 'filters', 'currentSemester'],
    immediateFields: ['result', 'per_page'],
});

const syncForm = useForm({ semester_id: 0 });
const discountForm = useForm({ egc_block_id: 0, target_charge_id: 0 });
const selectedTargets = ref<Record<number, string>>({});
const confirmDialog = ref(false);
const confirmingBlock = ref<AdjustmentBlock | null>(null);
const confirmingTarget = ref<TargetOption | null>(null);

const blockRows = computed(() => ('data' in props.blocks ? props.blocks.data : props.blocks));
const adjustmentTotal = computed(() => props.adjustments.eligible_with_targets.length + props.adjustments.eligible_no_targets.length + props.adjustments.ineligible.length + props.adjustments.already_discounted.length);

watchEffect(() => {
    for (const block of props.adjustments.eligible_with_targets) {
        if (!selectedTargets.value[block.id] && block.available_targets.length > 0) {
            selectedTargets.value[block.id] = String(block.available_targets[0].id);
        }
    }
});

function handleResultChange(value: string) {
    setFilter('result', value);
}

function handleSearchChange(value: string | number) {
    handleSearch(value);
}

function syncResults() {
    if (!selectedId.value) {
        return;
    }

    syncForm.semester_id = selectedId.value;
    syncForm.post(route('finance.egc.block-results.sync'));
}

function openConfirm(block: AdjustmentBlock) {
    const targetId = selectedTargets.value[block.id];
    if (!targetId) return;

    confirmingBlock.value = block;
    confirmingTarget.value = block.available_targets.find((target) => target.id === Number(targetId)) ?? null;
    confirmDialog.value = true;
}

function applyDiscount() {
    if (!confirmingBlock.value || !confirmingTarget.value) return;

    discountForm.egc_block_id = confirmingBlock.value.id;
    discountForm.target_charge_id = confirmingTarget.value.id;
    discountForm.post(route('finance.egc.retake-adjustments.store'), {
        onSuccess: () => {
            confirmDialog.value = false;
        },
    });
}

function resultBadgeVariant(result: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (result === 'pass') return 'default';
    if (result === 'fail') return 'destructive';
    return 'secondary';
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';

    return new Date(dateStr).toLocaleDateString('vi-VN');
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

function formatTargetLabel(target: TargetOption): string {
    const blockLabel = target.target_block_number ? `Block ${target.target_block_number}` : 'Block ?';

    return `${target.semester_name} - ${blockLabel} - ${target.description}`;
}
</script>

<template>
    <Head title="EGC - Kết quả & học lại" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold">EGC · Kết quả & học lại</h1>
                <p class="text-muted-foreground text-sm">Sync block results, reconcile generated charges, and handle retake discounts in one workflow · {{ selectedLabel }}</p>
            </div>
            <Button :disabled="!selectedId || syncForm.processing" variant="outline" @click="syncResults">
                <RefreshCw :class="['mr-2 h-4 w-4', syncForm.processing && 'animate-spin']" />
                {{ syncForm.processing ? 'Syncing...' : 'Sync Results' }}
            </Button>
        </div>

        <div class="grid gap-3 md:grid-cols-4">
            <div class="border-border rounded-lg border p-4">
                <div class="text-muted-foreground text-xs font-medium">Blocks</div>
                <div class="text-2xl font-semibold">{{ 'total' in blocks ? blocks.total : blockRows.length }}</div>
            </div>
            <div class="border-border rounded-lg border p-4">
                <div class="text-muted-foreground text-xs font-medium">Ready to apply</div>
                <div class="text-2xl font-semibold">{{ adjustments.eligible_with_targets.length }}</div>
            </div>
            <div class="border-border rounded-lg border p-4">
                <div class="text-muted-foreground text-xs font-medium">Waiting/manual repair</div>
                <div class="text-2xl font-semibold">{{ adjustments.eligible_no_targets.length }}</div>
            </div>
            <div class="border-border rounded-lg border p-4">
                <div class="text-muted-foreground text-xs font-medium">Applied / ineligible</div>
                <div class="text-2xl font-semibold">{{ adjustments.already_discounted.length + adjustments.ineligible.length }}</div>
            </div>
        </div>

        <Card>
            <CardContent class="flex flex-wrap gap-4 pt-4">
                <Select :model-value="filters.result" @update:model-value="handleResultChange">
                    <SelectTrigger class="w-36">
                        <SelectValue placeholder="Result" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Results</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="pass">Pass</SelectItem>
                        <SelectItem value="fail">Fail</SelectItem>
                    </SelectContent>
                </Select>

                <div class="relative min-w-56 flex-1">
                    <Search class="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                    <Input :model-value="filters.search" class="pl-8" placeholder="Search student..." @update:model-value="handleSearchChange" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Block Results</CardTitle>
                <CardDescription v-if="'total' in blocks">{{ blocks.total }} blocks for the selected semester</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Student</TableHead>
                            <TableHead>Block</TableHead>
                            <TableHead>Level</TableHead>
                            <TableHead>Result</TableHead>
                            <TableHead>Attendance</TableHead>
                            <TableHead>Retake</TableHead>
                            <TableHead>Synced At</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="block in blockRows" :key="block.id">
                            <TableCell>
                                <div class="font-medium">{{ block.student?.full_name }}</div>
                                <div class="text-muted-foreground text-xs">{{ block.student?.student_id }}</div>
                            </TableCell>
                            <TableCell>Block {{ block.block_number }}</TableCell>
                            <TableCell>Level {{ block.level_number }}</TableCell>
                            <TableCell>
                                <Badge :variant="resultBadgeVariant(block.result)">
                                    {{ block.result.charAt(0).toUpperCase() + block.result.slice(1) }}
                                </Badge>
                            </TableCell>
                            <TableCell>{{ block.attendance_rate != null ? `${block.attendance_rate}%` : '-' }}</TableCell>
                            <TableCell>
                                <Badge v-if="block.is_retake" variant="outline">Retake</Badge>
                                <span v-else class="text-muted-foreground">-</span>
                            </TableCell>
                            <TableCell class="text-muted-foreground text-sm">{{ formatDate(block.synced_at) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <DataPagination
                    v-if="'last_page' in blocks && blocks.last_page > 1"
                    :pagination-data="blocks"
                    :page-size-options="[20, 50, 100]"
                    item-name="blocks"
                    class="mt-4"
                    @navigate="handlePaginationNavigate"
                    @page-size-change="handlePageSizeChange"
                />
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Retake Reconciliation</CardTitle>
                <CardDescription>{{ adjustmentTotal }} failed blocks classified for discount or repair</CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <section v-if="adjustments.eligible_with_targets.length > 0" class="space-y-3">
                    <div>
                        <h2 class="text-base font-semibold">Eligible - target available</h2>
                        <p class="text-muted-foreground text-sm">{{ adjustments.eligible_with_targets.length }} blocks can be applied manually if auto reconcile did not consume them.</p>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student</TableHead>
                                <TableHead>Block / Level</TableHead>
                                <TableHead>Attendance</TableHead>
                                <TableHead>Target Charge</TableHead>
                                <TableHead>Discount</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="block in adjustments.eligible_with_targets" :key="block.id">
                                <TableCell>
                                    <div class="font-medium">{{ block.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ block.student_code }}</div>
                                </TableCell>
                                <TableCell>Block {{ block.block_number }} / Level {{ block.level_number }}</TableCell>
                                <TableCell>{{ block.attendance_rate }}%</TableCell>
                                <TableCell>
                                    <Select :model-value="selectedTargets[block.id]" @update:model-value="(value) => (selectedTargets[block.id] = value)">
                                        <SelectTrigger class="w-64">
                                            <SelectValue placeholder="Select target charge" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="target in block.available_targets" :key="target.id" :value="String(target.id)">
                                                {{ formatTargetLabel(target) }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </TableCell>
                                <TableCell class="font-mono">{{ formatCurrency(7_500_000) }}</TableCell>
                                <TableCell>
                                    <Button size="sm" :disabled="!selectedTargets[block.id]" @click="openConfirm(block)"> Apply </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </section>

                <section v-if="adjustments.eligible_no_targets.length > 0" class="space-y-3">
                    <div>
                        <h2 class="text-base font-semibold">Waiting / manual repair</h2>
                        <p class="text-muted-foreground text-sm">Target charge or reconciled retake block is not available yet.</p>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student</TableHead>
                                <TableHead>Block / Level</TableHead>
                                <TableHead>Attendance</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="block in adjustments.eligible_no_targets" :key="block.id">
                                <TableCell>
                                    <div class="font-medium">{{ block.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ block.student_code }}</div>
                                </TableCell>
                                <TableCell>Block {{ block.block_number }} / Level {{ block.level_number }}</TableCell>
                                <TableCell>{{ block.attendance_rate }}%</TableCell>
                                <TableCell>
                                    <Badge variant="outline">Waiting for target charge</Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </section>

                <section v-if="adjustments.already_discounted.length > 0" class="space-y-3">
                    <div>
                        <h2 class="text-base font-semibold">Applied / auto-reconciled</h2>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student</TableHead>
                                <TableHead>Block / Level</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="block in adjustments.already_discounted" :key="block.id">
                                <TableCell>
                                    <div class="font-medium">{{ block.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ block.student_code }}</div>
                                </TableCell>
                                <TableCell>Block {{ block.block_number }} / Level {{ block.level_number }}</TableCell>
                                <TableCell>
                                    <Badge>
                                        <CheckCircle2 class="mr-1 h-3 w-3" />
                                        Discount Applied
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </section>

                <section v-if="adjustments.ineligible.length > 0" class="space-y-3">
                    <div>
                        <h2 class="text-muted-foreground text-base font-semibold">Ineligible attendance</h2>
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Student</TableHead>
                                <TableHead>Block / Level</TableHead>
                                <TableHead>Attendance</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="block in adjustments.ineligible" :key="block.id">
                                <TableCell>
                                    <div class="font-medium">{{ block.student_name }}</div>
                                    <div class="text-muted-foreground text-xs">{{ block.student_code }}</div>
                                </TableCell>
                                <TableCell>Block {{ block.block_number }} / Level {{ block.level_number }}</TableCell>
                                <TableCell class="text-destructive">{{ block.attendance_rate }}%</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </section>

                <div v-if="adjustmentTotal === 0" class="text-muted-foreground rounded-lg border p-6 text-center text-sm">No failed EGC blocks need retake reconciliation for this semester.</div>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="confirmDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Confirm Retake Discount</DialogTitle>
                <DialogDescription v-if="confirmingBlock && confirmingTarget">
                    Apply a 50% discount ({{ formatCurrency(7_500_000) }}) to
                    <strong>{{ confirmingTarget.description }}</strong>
                    ({{ confirmingTarget.semester_name }}) for student <strong>{{ confirmingBlock.student_name }}</strong
                    >?
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="confirmDialog = false">Cancel</Button>
                <Button :disabled="discountForm.processing" @click="applyDiscount">
                    {{ discountForm.processing ? 'Applying...' : 'Confirm' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
