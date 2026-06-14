<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/composables/usePermission';
import { formatCurrency, getChargeStatusBadgeClass, getChargeStatusLabel, getChargeTypeBadgeClass, getChargeTypeLabel, type ChargeStatus, type ChargeType, type FinanceCharge, type PaymentAllocation } from '@/types/finance';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { AlertCircle, AlertTriangle, ArrowLeft, Ban, CalendarClock, Check, CreditCard, FileText, Pencil, RotateCcw, Split, User, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import SplitInstallmentsModal from './SplitInstallmentsModal.vue';

interface InstallmentRow {
    id: number;
    installment_no: number;
    amount: number | string;
    due_date: string | null;
    status: 'pending' | 'awaiting_payment' | 'paid' | 'cancelled';
    dng_payment_request_id: number | null;
    paid_at: string | null;
    push_attempt_count: number;
    last_push_error: string | null;
    has_push_error: boolean;
}

interface InstallmentMeta {
    net_split_target: number;
    has_paid_installment: boolean;
    can_split: boolean;
}

interface Props {
    charge: FinanceCharge & {
        allocations?: PaymentAllocation[];
    };
    installments?: InstallmentRow[];
    installment_meta?: InstallmentMeta;
}

const props = withDefaults(defineProps<Props>(), {
    installments: () => [],
    installment_meta: () => ({ net_split_target: 0, has_paid_installment: false, can_split: false }),
});

const { can } = usePermission();

const isVoidDialogOpen = ref(false);

// Description inline edit
const isEditingDescription = ref(false);
const descriptionForm = useForm({
    description: props.charge.description ?? '',
});

const startEditDescription = () => {
    descriptionForm.description = props.charge.description ?? '';
    isEditingDescription.value = true;
};

const cancelEditDescription = () => {
    isEditingDescription.value = false;
    descriptionForm.reset();
};

const saveDescription = () => {
    descriptionForm.patch(route('finance.charges.update-description', props.charge.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Mô tả đã được cập nhật');
            isEditingDescription.value = false;
        },
        onError: () => {
            toast.error('Không thể cập nhật mô tả');
        },
    });
};

const voidForm = useForm({
    void_reason: '',
});

// Financial impact preview shown before confirming a void (UI-SAFE-1).
// Built entirely from existing props — voiding releases/reverses these
// allocations and can auto-reallocate freed cash to other unpaid charges.
const voidImpact = computed(() => {
    const allocations = props.charge.allocations ?? [];
    const allocatedTotal = allocations.reduce((sum, alloc) => sum + Number(alloc.allocated_amount ?? 0), 0);
    const paidInstallmentCount = props.installments.filter((i) => i.status === 'paid').length;

    return {
        amount: Number(props.charge.amount ?? 0),
        paidAmount: Number(props.charge.paid_amount ?? 0),
        allocationCount: allocations.length,
        allocatedTotal,
        paidInstallmentCount,
        hasMoneyImpact: allocations.length > 0 || paidInstallmentCount > 0,
    };
});

const handleVoid = () => {
    voidForm.post(route('finance.charges.void', props.charge.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Khoản phí đã được hủy');
            isVoidDialogOpen.value = false;
        },
        onError: () => {
            toast.error('Không thể hủy khoản phí');
        },
    });
};

const formatDate = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDateOnly = (dateStr: string | null | undefined): string => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('vi-VN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
};

// Installments UI
const isSplitModalOpen = ref(false);
const retryingInstallmentId = ref<number | null>(null);

const installmentStatusLabel = (status: InstallmentRow['status']): string => {
    return {
        pending: 'Chờ push',
        awaiting_payment: 'Đang chờ thanh toán',
        paid: 'Đã thanh toán',
        cancelled: 'Đã huỷ',
    }[status];
};

const installmentStatusClass = (status: InstallmentRow['status']): string => {
    return {
        pending: 'bg-slate-100 text-slate-700',
        awaiting_payment: 'bg-blue-100 text-blue-700',
        paid: 'bg-green-100 text-green-700',
        cancelled: 'bg-red-100 text-red-700',
    }[status];
};

const retryPush = (installment: InstallmentRow) => {
    retryingInstallmentId.value = installment.id;
    router.post(
        route('finance.charges.installments.retry-push', [props.charge.id, installment.id]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(`Đợt ${installment.installment_no} đã được push lại.`);
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                toast.error(typeof first === 'string' ? first : 'Retry thất bại.');
            },
            onFinish: () => {
                retryingInstallmentId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head :title="`Charge #${charge.id}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Link :href="route('finance.charges.index')">
                    <Button variant="outline" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Chi tiết khoản phí #{{ charge.id }}</h1>
                    <p class="text-muted-foreground mt-1">{{ charge.description }}</p>
                </div>
            </div>
            <div class="flex gap-2">
                <Dialog v-model:open="isVoidDialogOpen" v-if="charge.status === 'active'">
                    <DialogTrigger as-child>
                        <Button variant="destructive">
                            <Ban class="mr-2 h-4 w-4" />
                            Hủy khoản phí
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Hủy khoản phí</DialogTitle>
                            <DialogDescription> Bạn có chắc chắn muốn hủy khoản phí này? Hành động này không thể hoàn tác. </DialogDescription>
                        </DialogHeader>
                        <div class="space-y-4">
                            <!-- Financial impact preview (UI-SAFE-1) -->
                            <div class="rounded-lg border p-4" :class="voidImpact.hasMoneyImpact ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-slate-50'">
                                <div class="flex items-center gap-2 font-medium" :class="voidImpact.hasMoneyImpact ? 'text-amber-800' : 'text-slate-700'">
                                    <AlertTriangle class="h-4 w-4" />
                                    Tác động tài chính
                                </div>
                                <dl class="mt-3 space-y-1.5 text-sm">
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-muted-foreground">Tổng tiền khoản phí</dt>
                                        <dd class="font-medium">{{ formatCurrency(voidImpact.amount) }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-muted-foreground">Đã thu</dt>
                                        <dd class="font-medium text-green-700">{{ formatCurrency(voidImpact.paidAmount) }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-muted-foreground">Phân bổ sẽ bị thu hồi</dt>
                                        <dd class="font-medium">
                                            {{ voidImpact.allocationCount }} khoản
                                            <span v-if="voidImpact.allocatedTotal > 0"> ({{ formatCurrency(voidImpact.allocatedTotal) }}) </span>
                                        </dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-muted-foreground">Đợt đã thanh toán</dt>
                                        <dd class="font-medium">{{ voidImpact.paidInstallmentCount }} đợt</dd>
                                    </div>
                                </dl>
                                <p v-if="voidImpact.hasMoneyImpact" class="mt-3 text-xs text-amber-800">
                                    Hủy khoản phí sẽ đảo (reverse) các phân bổ trên. Tiền đã thu được giải phóng và có thể được tự động phân bổ lại sang các khoản phí còn nợ của sinh viên.
                                </p>
                                <p v-else class="text-muted-foreground mt-3 text-xs">Khoản phí này chưa có thanh toán nào được phân bổ.</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="void_reason">Lý do hủy *</Label>
                                <Textarea v-model="voidForm.void_reason" placeholder="Nhập lý do hủy khoản phí..." rows="3" />
                                <p v-if="voidForm.errors.void_reason" class="text-sm text-red-500">{{ voidForm.errors.void_reason }}</p>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button variant="outline" @click="isVoidDialogOpen = false">Hủy bỏ</Button>
                            <Button variant="destructive" :disabled="voidForm.processing || !voidForm.void_reason" @click="handleVoid"> Xác nhận hủy </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Main Info -->
            <div class="space-y-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Thông tin khoản phí</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-muted-foreground text-sm">Loại phí</p>
                                <Badge :class="getChargeTypeBadgeClass(charge.charge_type as ChargeType)" class="mt-1">
                                    {{ getChargeTypeLabel(charge.charge_type as ChargeType) }}
                                </Badge>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-sm">Trạng thái</p>
                                <Badge :class="getChargeStatusBadgeClass(charge.status as ChargeStatus)" class="mt-1">
                                    {{ getChargeStatusLabel(charge.status as ChargeStatus) }}
                                </Badge>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-sm">Học kỳ</p>
                                <p class="font-medium">{{ charge.semester?.name ?? '-' }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-sm">Ngày tạo</p>
                                <p class="font-medium">{{ formatDate(charge.created_at) }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-sm">Ngày đến hạn</p>
                                <p class="font-medium">{{ formatDateOnly(charge.due_date) }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-sm">Người tạo</p>
                                <p class="font-medium">{{ charge.created_by?.name ?? charge.voided_by?.name ?? 'Hệ thống' }}</p>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-muted-foreground text-sm">Mô tả</p>
                                <Button v-if="can('create_finance_charges') && !isEditingDescription" variant="ghost" size="icon" class="h-5 w-5" @click="startEditDescription">
                                    <Pencil class="h-3 w-3" />
                                </Button>
                            </div>
                            <div v-if="isEditingDescription" class="mt-1 flex items-start gap-2">
                                <Textarea v-model="descriptionForm.description" class="min-h-[80px] flex-1" rows="3" />
                                <div class="flex flex-col gap-1">
                                    <Button size="icon" class="h-7 w-7" :disabled="descriptionForm.processing" @click="saveDescription">
                                        <Check class="h-3 w-3" />
                                    </Button>
                                    <Button variant="outline" size="icon" class="h-7 w-7" @click="cancelEditDescription">
                                        <X class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                            <p v-else class="mt-1">{{ charge.description }}</p>
                        </div>

                        <div v-if="charge.status === 'void'" class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <div class="flex items-center gap-2 text-red-800">
                                <Ban class="h-4 w-4" />
                                <span class="font-medium">Đã hủy</span>
                            </div>
                            <p class="text-muted-foreground mt-1 text-sm">{{ formatDate(charge.voided_at) }} bởi {{ charge.voided_by?.name ?? 'N/A' }}</p>
                            <p class="mt-2 text-sm">{{ charge.void_reason }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Installments -->
                <Card v-if="installments.length > 0 || installment_meta.can_split">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <CardTitle class="flex items-center gap-2"> <CalendarClock class="h-5 w-5" /> Đợt thanh toán </CardTitle>
                                <CardDescription>
                                    <template v-if="installments.length === 0"> Chưa có kế hoạch đợt. Mặc định 1 đợt = toàn bộ khoản phí khi push DNG. </template>
                                    <template v-else>
                                        {{ installments.length }} đợt ({{ formatCurrency(installment_meta.net_split_target) }} cần thu)
                                        <span v-if="installment_meta.has_paid_installment" class="text-amber-700"> — kế hoạch đã khoá vì có đợt đã thanh toán </span>
                                    </template>
                                </CardDescription>
                            </div>
                            <Button v-if="can('split_installment_finance_charges') && installment_meta.can_split" size="sm" @click="isSplitModalOpen = true">
                                <Split class="mr-1 h-4 w-4" />
                                {{ installments.length > 1 ? 'Sửa kế hoạch đợt' : 'Tách đợt' }}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent v-if="installments.length > 0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12">#</TableHead>
                                    <TableHead>Số tiền</TableHead>
                                    <TableHead>Hạn thanh toán</TableHead>
                                    <TableHead>Trạng thái</TableHead>
                                    <TableHead>DNG</TableHead>
                                    <TableHead>Đã thanh toán</TableHead>
                                    <TableHead class="text-right">Thao tác</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="i in installments" :key="i.id">
                                    <TableCell class="font-medium">{{ i.installment_no }}</TableCell>
                                    <TableCell>{{ formatCurrency(Number(i.amount)) }}</TableCell>
                                    <TableCell>{{ formatDateOnly(i.due_date) }}</TableCell>
                                    <TableCell>
                                        <Badge :class="installmentStatusClass(i.status)">
                                            {{ installmentStatusLabel(i.status) }}
                                        </Badge>
                                        <div v-if="i.has_push_error" class="mt-1 flex items-center gap-1 text-xs text-red-600">
                                            <AlertCircle class="h-3 w-3" />
                                            <span :title="i.last_push_error ?? ''"> Push lỗi ({{ i.push_attempt_count }} lần) </span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span v-if="i.dng_payment_request_id" class="text-muted-foreground text-xs"> #{{ i.dng_payment_request_id }} </span>
                                        <span v-else class="text-muted-foreground text-xs">—</span>
                                    </TableCell>
                                    <TableCell>{{ formatDate(i.paid_at) }}</TableCell>
                                    <TableCell class="text-right">
                                        <Button v-if="i.has_push_error && can('split_installment_finance_charges')" size="sm" variant="outline" :disabled="retryingInstallmentId === i.id" @click="retryPush(i)">
                                            <RotateCcw class="mr-1 h-3 w-3" />
                                            {{ retryingInstallmentId === i.id ? 'Đang...' : 'Thử push lại' }}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <!-- Payment Allocations -->
                <Card>
                    <CardHeader>
                        <CardTitle>Lịch sử thanh toán</CardTitle>
                        <CardDescription>Các khoản thanh toán đã được phân bổ cho phí này</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table v-if="charge.allocations && charge.allocations.length > 0">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Mã thanh toán</TableHead>
                                    <TableHead>Ngày</TableHead>
                                    <TableHead class="text-right">Số tiền</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="alloc in charge.allocations" :key="alloc.id">
                                    <TableCell>
                                        <Link :href="route('finance.payments.show', alloc.payment_id)" class="text-primary hover:underline"> #{{ alloc.payment_id }} </Link>
                                    </TableCell>
                                    <TableCell>{{ formatDate(alloc.allocated_at) }}</TableCell>
                                    <TableCell class="text-right font-medium text-green-600">
                                        {{ formatCurrency(alloc.allocated_amount) }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <div v-else class="text-muted-foreground py-8 text-center">
                            <CreditCard class="mx-auto h-12 w-12 opacity-50" />
                            <p class="mt-2">Chưa có thanh toán nào</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Amount Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>Tóm tắt số tiền</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Tổng số tiền</span>
                            <span class="font-bold" :class="charge.amount < 0 ? 'text-green-600' : 'text-red-600'">
                                {{ formatCurrency(charge.amount) }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Đã thanh toán</span>
                            <span class="font-medium text-green-600">{{ formatCurrency(charge.paid_amount ?? 0) }}</span>
                        </div>
                        <hr />
                        <div class="flex justify-between">
                            <span class="font-medium">Còn lại</span>
                            <span class="text-lg font-bold" :class="(charge.balance ?? 0) > 0 ? 'text-orange-600' : 'text-green-600'">
                                {{ formatCurrency(charge.balance ?? 0) }}
                            </span>
                        </div>
                        <div v-if="charge.is_fully_paid" class="rounded-lg bg-green-50 p-3 text-center text-green-800">✓ Đã thanh toán đủ</div>
                    </CardContent>
                </Card>

                <!-- Student Info -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <User class="h-4 w-4" />
                            Thông tin sinh viên
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div>
                            <p class="text-muted-foreground text-sm">Họ tên</p>
                            <p class="font-medium">{{ charge.student?.full_name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm">Mã sinh viên</p>
                            <p class="font-medium">{{ charge.student?.student_id ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-sm">Email</p>
                            <p class="font-medium">{{ charge.student?.email ?? 'N/A' }}</p>
                        </div>
                        <Link v-if="charge.student" :href="route('finance.students.charges', charge.student.id)">
                            <Button variant="outline" class="mt-2 w-full">
                                <FileText class="mr-2 h-4 w-4" />
                                Xem tất cả phí
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Split installments modal -->
        <SplitInstallmentsModal v-model:open="isSplitModalOpen" :charge-id="charge.id" :net-split-target="installment_meta.net_split_target" :current-plan-count="installments.length" />
    </div>
</template>
