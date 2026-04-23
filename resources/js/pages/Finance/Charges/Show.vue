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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import {
    formatCurrency,
    getChargeStatusBadgeClass,
    getChargeStatusLabel,
    getChargeTypeBadgeClass,
    getChargeTypeLabel,
    type ChargeStatus,
    type ChargeType,
    type FinanceCharge,
    type PaymentAllocation,
} from '@/types/finance';
import { usePermission } from '@/composables/usePermission';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Ban, Check, CreditCard, FileText, Pencil, User, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    charge: FinanceCharge & {
        allocations?: PaymentAllocation[];
    };
}

const props = defineProps<Props>();

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
                            <DialogDescription>
                                Bạn có chắc chắn muốn hủy khoản phí này? Hành động này không thể hoàn tác.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <Label for="void_reason">Lý do hủy *</Label>
                                <Textarea v-model="voidForm.void_reason" placeholder="Nhập lý do hủy khoản phí..."
                                    rows="3" />
                                <p v-if="voidForm.errors.void_reason" class="text-sm text-red-500">{{
                                    voidForm.errors.void_reason }}</p>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button variant="outline" @click="isVoidDialogOpen = false">Hủy bỏ</Button>
                            <Button variant="destructive" :disabled="voidForm.processing || !voidForm.void_reason"
                                @click="handleVoid">
                                Xác nhận hủy
                            </Button>
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
                                <Button
                                    v-if="can('create_finance_charges') && !isEditingDescription"
                                    variant="ghost"
                                    size="icon"
                                    class="h-5 w-5"
                                    @click="startEditDescription"
                                >
                                    <Pencil class="h-3 w-3" />
                                </Button>
                            </div>
                            <div v-if="isEditingDescription" class="mt-1 flex items-start gap-2">
                                <Textarea
                                    v-model="descriptionForm.description"
                                    class="min-h-[80px] flex-1"
                                    rows="3"
                                />
                                <div class="flex flex-col gap-1">
                                    <Button
                                        size="icon"
                                        class="h-7 w-7"
                                        :disabled="descriptionForm.processing"
                                        @click="saveDescription"
                                    >
                                        <Check class="h-3 w-3" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="h-7 w-7"
                                        @click="cancelEditDescription"
                                    >
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
                            <p class="text-muted-foreground mt-1 text-sm">{{ formatDate(charge.voided_at) }} bởi {{
                                charge.voided_by?.name ?? 'N/A' }}</p>
                            <p class="mt-2 text-sm">{{ charge.void_reason }}</p>
                        </div>
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
                                        <Link :href="route('finance.payments.show', alloc.payment_id)"
                                            class="text-primary hover:underline">
                                            #{{ alloc.payment_id }}
                                        </Link>
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
                            <span class="font-medium text-green-600">{{ formatCurrency(charge.paid_amount ?? 0)
                                }}</span>
                        </div>
                        <hr />
                        <div class="flex justify-between">
                            <span class="font-medium">Còn lại</span>
                            <span class="text-lg font-bold"
                                :class="(charge.balance ?? 0) > 0 ? 'text-orange-600' : 'text-green-600'">
                                {{ formatCurrency(charge.balance ?? 0) }}
                            </span>
                        </div>
                        <div v-if="charge.is_fully_paid" class="rounded-lg bg-green-50 p-3 text-center text-green-800">
                            ✓ Đã thanh toán đủ
                        </div>
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
    </div>
</template>
