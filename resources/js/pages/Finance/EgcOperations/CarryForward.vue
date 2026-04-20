<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useInertiaFilters } from '@/composables/useInertiaFilters';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ArrowRightLeft, CheckCircle2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Semester {
    id: number;
    name: string;
}

interface ConsumedBlock {
    id: number;
    semester_id: number;
    semester_name: string | null;
    block_number: number;
    level_number: number;
    finance_charge_id: number | null;
}

interface UnusedCharge {
    id: number;
    description: string;
    semester_id: number;
    semester_name: string | null;
    amount: number;
    paid_amount: number;
    releaseable_amount: number;
    invoice_id: number | null;
    invoice_number: string | null;
    invoice_line_id: number;
}

interface Candidate {
    student_id: number;
    student_name: string;
    student_code: string;
    student_status: string;
    semester_id: number;
    charged_levels_count: number;
    consumed_levels_count: number;
    current_unapplied_balance: number;
    eligible_release_amount: number;
    status: 'eligible' | 'needs_data_repair' | 'ineligible';
    reason: string;
    issues: string[];
    unused_charges: UnusedCharge[];
    consumed_blocks: ConsumedBlock[];
}

const props = defineProps<{
    candidates: {
        eligible: Candidate[];
        needs_data_repair: Candidate[];
        ineligible: Candidate[];
    };
    semesters: Semester[];
    currentSemester: Semester | null;
    filters: { semester_id: string | null };
}>();

const { filters } = useInertiaFilters({
    baseUrl: route('finance.egc.carry-forward.index'),
    initialFilters: {
        semester_id: props.filters.semester_id ?? String(props.currentSemester?.id ?? ''),
    },
    defaultValues: {
        semester_id: String(props.currentSemester?.id ?? ''),
    },
    only: ['candidates', 'filters'],
});

const confirmDialog = ref(false);
const confirmingCandidate = ref<Candidate | null>(null);
const carryForwardForm = useForm({ student_id: 0, semester_id: 0 });

const totalEligibleRelease = computed(() =>
    props.candidates.eligible.reduce((sum, candidate) => sum + candidate.eligible_release_amount, 0),
);

function openConfirm(candidate: Candidate) {
    confirmingCandidate.value = candidate;
    confirmDialog.value = true;
}

function applyCarryForward() {
    if (!confirmingCandidate.value) return;

    carryForwardForm.student_id = confirmingCandidate.value.student_id;
    carryForwardForm.semester_id = confirmingCandidate.value.semester_id;
    carryForwardForm.post(route('finance.egc.carry-forward.store'), {
        onSuccess: () => {
            confirmDialog.value = false;
            toast.success('Unused EGC balance released to unapplied successfully.');
        },
    });
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

function formatUnusedCharges(candidate: Candidate): string {
    return candidate.unused_charges.map((charge) => `#${charge.id} ${charge.description}`).join(', ');
}
</script>

<template>
    <Head title="EGC — Carry Forward" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">EGC Carry Forward</h1>
                <p class="text-muted-foreground text-sm">Release unused paid EGC charges back to unapplied balance for transitioned students</p>
            </div>
            <div class="text-right">
                <div class="text-muted-foreground text-sm">Eligible release total</div>
                <div class="text-lg font-semibold">{{ formatCurrency(totalEligibleRelease) }}</div>
            </div>
        </div>

        <Card>
            <CardContent class="pt-4">
                <Select :model-value="filters.semester_id" @update:model-value="(value) => (filters.semester_id = value)">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Select semester" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="sem in semesters" :key="sem.id" :value="String(sem.id)">
                            {{ sem.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </CardContent>
        </Card>

        <Card v-if="candidates.eligible.length > 0">
            <CardHeader>
                <CardTitle>Eligible</CardTitle>
                <CardDescription>Unused paid EGC charges that can be released to unapplied balance</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Student</TableHead>
                            <TableHead>Charged / Consumed</TableHead>
                            <TableHead>Unused Charges</TableHead>
                            <TableHead>Paid On Unused</TableHead>
                            <TableHead>Unapplied</TableHead>
                            <TableHead>Reason</TableHead>
                            <TableHead></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="candidate in candidates.eligible" :key="candidate.student_id">
                            <TableCell>
                                <div class="font-medium">{{ candidate.student_name }}</div>
                                <div class="text-muted-foreground text-xs">{{ candidate.student_code }} · {{ candidate.student_status }}</div>
                            </TableCell>
                            <TableCell>{{ candidate.charged_levels_count }} / {{ candidate.consumed_levels_count }}</TableCell>
                            <TableCell class="max-w-sm text-sm">{{ formatUnusedCharges(candidate) }}</TableCell>
                            <TableCell class="font-mono">{{ formatCurrency(candidate.eligible_release_amount) }}</TableCell>
                            <TableCell class="font-mono">{{ formatCurrency(candidate.current_unapplied_balance) }}</TableCell>
                            <TableCell class="max-w-xs text-sm">{{ candidate.reason }}</TableCell>
                            <TableCell>
                                <Button size="sm" @click="openConfirm(candidate)">Preview</Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card v-if="candidates.needs_data_repair.length > 0">
            <CardHeader>
                <CardTitle>Needs Data Repair</CardTitle>
                <CardDescription>These rows need mapping or discount cleanup before carry-forward can run safely</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Student</TableHead>
                            <TableHead>Unused Charges</TableHead>
                            <TableHead>Reason</TableHead>
                            <TableHead></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="candidate in candidates.needs_data_repair" :key="candidate.student_id">
                            <TableCell>
                                <div class="font-medium">{{ candidate.student_name }}</div>
                                <div class="text-muted-foreground text-xs">{{ candidate.student_code }}</div>
                            </TableCell>
                            <TableCell class="max-w-sm text-sm">{{ formatUnusedCharges(candidate) }}</TableCell>
                            <TableCell class="max-w-xs text-sm">
                                <div>{{ candidate.reason }}</div>
                                <div v-if="candidate.issues.length > 0" class="text-muted-foreground mt-1 text-xs">
                                    {{ candidate.issues.join(' ') }}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Link :href="route('students.academic-summary.fees', candidate.student_id)">
                                    <Button size="sm" variant="outline">Open Fees</Button>
                                </Link>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card v-if="candidates.ineligible.length > 0">
            <CardHeader>
                <CardTitle>Ineligible</CardTitle>
                <CardDescription>Students with no unused paid EGC amount available for carry-forward</CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Student</TableHead>
                            <TableHead>Charged / Consumed</TableHead>
                            <TableHead>Reason</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="candidate in candidates.ineligible" :key="candidate.student_id">
                            <TableCell>
                                <div class="font-medium">{{ candidate.student_name }}</div>
                                <div class="text-muted-foreground text-xs">{{ candidate.student_code }} · {{ candidate.student_status }}</div>
                            </TableCell>
                            <TableCell>{{ candidate.charged_levels_count }} / {{ candidate.consumed_levels_count }}</TableCell>
                            <TableCell class="max-w-lg text-sm">{{ candidate.reason }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="confirmDialog">
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Preview Carry Forward</DialogTitle>
                <DialogDescription v-if="confirmingCandidate">
                    Release unused paid EGC charges for <strong>{{ confirmingCandidate.student_name }}</strong> and return the amount to unapplied balance.
                </DialogDescription>
            </DialogHeader>

            <div v-if="confirmingCandidate" class="space-y-4">
                <div class="grid gap-3 md:grid-cols-3">
                    <Card>
                        <CardContent class="pt-4">
                            <div class="text-muted-foreground text-xs">Release Amount</div>
                            <div class="font-semibold">{{ formatCurrency(confirmingCandidate.eligible_release_amount) }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-4">
                            <div class="text-muted-foreground text-xs">Current Unapplied</div>
                            <div class="font-semibold">{{ formatCurrency(confirmingCandidate.current_unapplied_balance) }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-4">
                            <div class="text-muted-foreground text-xs">Consumed Blocks</div>
                            <div class="font-semibold">{{ confirmingCandidate.consumed_levels_count }}</div>
                        </CardContent>
                    </Card>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                                Consumed Blocks
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <div v-for="block in confirmingCandidate.consumed_blocks" :key="block.id" class="rounded border p-2">
                                {{ block.semester_name }} · Block {{ block.block_number }} · Level {{ block.level_number }}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <ArrowRightLeft class="h-4 w-4 text-orange-600" />
                                Unused Charges To Void
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <div v-for="charge in confirmingCandidate.unused_charges" :key="charge.id" class="rounded border p-2">
                                <div class="font-medium">#{{ charge.id }} · {{ charge.description }}</div>
                                <div class="text-muted-foreground text-xs">
                                    {{ charge.invoice_number }} · paid {{ formatCurrency(charge.paid_amount) }} · release {{ formatCurrency(charge.releaseable_amount) }}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="rounded-lg border border-orange-200 bg-orange-50 p-3 text-sm">
                    <div class="flex items-center gap-2 font-medium text-orange-900">
                        <AlertTriangle class="h-4 w-4" />
                        Action summary
                    </div>
                    <div class="mt-1 text-orange-900">
                        Active unused EGC charges will be voided without auto-reallocation. Paid amounts on those charges will return to unapplied balance for later use.
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="confirmDialog = false">Cancel</Button>
                <Button :disabled="carryForwardForm.processing" @click="applyCarryForward">
                    {{ carryForwardForm.processing ? 'Applying...' : 'Apply Carry Forward' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
