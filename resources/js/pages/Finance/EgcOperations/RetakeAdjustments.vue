<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
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
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import { ref, watchEffect } from 'vue';
import { toast } from 'vue-sonner';

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

interface Semester {
    id: number;
    name: string;
}

const props = defineProps<{
    adjustments: {
        eligible_with_targets: AdjustmentBlock[];
        eligible_no_targets: AdjustmentBlock[];
        ineligible: AdjustmentBlock[];
        already_discounted: AdjustmentBlock[];
    };
    semesters: Semester[];
    currentSemester: Semester | null;
    filters: { semester_id: string | null };
}>();

const { filters } = useInertiaFilters({
    baseUrl: route('finance.egc.retake-adjustments.index'),
    initialFilters: {
        semester_id: props.filters.semester_id ?? String(props.currentSemester?.id ?? ''),
    },
    defaultValues: {
        semester_id: String(props.currentSemester?.id ?? ''),
    },
    only: ['adjustments', 'filters'],
});

const selectedTargets = ref<Record<number, string>>({});

// Confirm dialog state
const confirmDialog = ref(false);
const confirmingBlock = ref<AdjustmentBlock | null>(null);
const confirmingTarget = ref<TargetOption | null>(null);

const discountForm = useForm({ egc_block_id: 0, target_charge_id: 0 });

watchEffect(() => {
    for (const block of props.adjustments.eligible_with_targets) {
        if (!selectedTargets.value[block.id] && block.available_targets.length > 0) {
            selectedTargets.value[block.id] = String(block.available_targets[0].id);
        }
    }
});

function openConfirm(block: AdjustmentBlock) {
    const targetId = selectedTargets.value[block.id];
    if (!targetId) return;
    confirmingBlock.value = block;
    confirmingTarget.value = block.available_targets.find((t) => t.id === Number(targetId)) ?? null;
    confirmDialog.value = true;
}

function applyDiscount() {
    if (!confirmingBlock.value || !confirmingTarget.value) return;
    discountForm.egc_block_id = confirmingBlock.value.id;
    discountForm.target_charge_id = confirmingTarget.value.id;
    discountForm.post(route('finance.egc.retake-adjustments.store'), {
        onSuccess: () => {
            confirmDialog.value = false;
            toast.success('Retake discount applied successfully.');
        },
    });
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
}

function formatTargetLabel(target: TargetOption): string {
    const blockLabel = target.target_block_number ? `Block ${target.target_block_number}` : 'Block ?';

    return `${target.semester_name} — ${blockLabel} — ${target.description}`;
}
</script>

<template>
    <Head title="EGC — Retake Adjustments" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">EGC Retake Adjustments</h1>
                <p class="text-muted-foreground text-sm">Apply retake discounts for failed EGC blocks with valid target charges</p>
            </div>
        </div>

        <!-- Semester Selector -->
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

        <!-- Eligible with targets -->
        <Card v-if="adjustments.eligible_with_targets.length > 0">
            <CardHeader>
                <CardTitle>Eligible — Target Available</CardTitle>
                <CardDescription>{{ adjustments.eligible_with_targets.length }} blocks with available target charges</CardDescription>
            </CardHeader>
            <CardContent>
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
                                <Select
                                    :model-value="selectedTargets[block.id]"
                                    @update:model-value="(v) => selectedTargets[block.id] = v"
                                >
                                    <SelectTrigger class="w-52">
                                        <SelectValue placeholder="Select target charge" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="t in block.available_targets" :key="t.id" :value="String(t.id)">
                                            {{ formatTargetLabel(t) }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </TableCell>
                            <TableCell class="font-mono">{{ formatCurrency(7_500_000) }}</TableCell>
                            <TableCell>
                                <Button
                                    size="sm"
                                    :disabled="!selectedTargets[block.id]"
                                    @click="openConfirm(block)"
                                >
                                    Apply
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Eligible but no targets -->
        <Card v-if="adjustments.eligible_no_targets.length > 0">
            <CardHeader>
                <CardTitle>Eligible — No Target Available</CardTitle>
                <CardDescription>Target charge or invoice not yet generated</CardDescription>
            </CardHeader>
            <CardContent>
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
            </CardContent>
        </Card>

        <!-- Already discounted -->
        <Card v-if="adjustments.already_discounted.length > 0">
            <CardHeader>
                <CardTitle>Already Discounted</CardTitle>
            </CardHeader>
            <CardContent>
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
            </CardContent>
        </Card>

        <!-- Ineligible -->
        <Card v-if="adjustments.ineligible.length > 0">
            <CardHeader>
                <CardTitle class="text-muted-foreground">Ineligible (Attendance &lt; 80%)</CardTitle>
            </CardHeader>
            <CardContent>
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
            </CardContent>
        </Card>
    </div>

    <!-- Confirm Discount Dialog -->
    <Dialog v-model:open="confirmDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Confirm Retake Discount</DialogTitle>
                <DialogDescription v-if="confirmingBlock && confirmingTarget">
                    Apply a 50% discount ({{ formatCurrency(7_500_000) }}) to
                    <strong>{{ confirmingTarget.description }}</strong>
                    ({{ confirmingTarget.semester_name }})
                    for student <strong>{{ confirmingBlock.student_name }}</strong>?
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
