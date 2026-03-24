<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import StudentCombobox from '@/components/StudentCombobox.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, Calendar, DollarSign, Hash, Loader2, Receipt, TicketPercent, UserPlus, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    status?: string;
}

interface Semester {
    id: number;
    code: string;
    name: string;
}

interface Invoice {
    id: number;
    invoice_number: string;
}

interface VoucherApplication {
    id: number;
    status: 'applied' | 'cancelled' | 'expired';
    applied_at: string;
    base_amount?: number | string | null;
    discount_amount?: number | string | null;
    student: Student;
    semester?: Semester | null;
    invoice?: Invoice | null;
}

interface Voucher {
    id: number;
    code: string;
    name: string;
    description: string | null;
    voucher_type: 'informational' | 'discount';
    discount_type?: 'percentage' | 'fixed_amount' | null;
    discount_value?: number | null;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
}

interface Props {
    voucher: Voucher;
    applications: VoucherApplication[];
    currentSemester: Semester | null;
    canApplyVoucher: boolean;
}

const props = defineProps<Props>();

const applyForm = useForm({
    student_id: null as number | null,
    voucher_id: props.voucher.id,
    code: props.voucher.code,
});

const selectedStudent = ref<Student | null>(null);

const formatDate = (date: string) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDateTime = (date: string) => {
    if (!date) return '';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatCurrency = (amount?: number | string | null) => {
    const normalizedAmount = Number(amount ?? 0);

    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(normalizedAmount);
};

const formatDiscountValue = () => {
    if (props.voucher.voucher_type === 'informational') {
        return 'N/A';
    }

    if (props.voucher.discount_type === 'percentage') {
        return `${props.voucher.discount_value}%`;
    }

    return formatCurrency(props.voucher.discount_value ?? 0);
};

const getStatusVariant = (): 'default' | 'destructive' | 'outline' | 'secondary' => {
    const now = new Date();
    const validFrom = new Date(props.voucher.valid_from);
    const validUntil = new Date(props.voucher.valid_until);

    if (!props.voucher.is_active) return 'secondary';
    if (now > validUntil) return 'destructive';
    if (now >= validFrom && now <= validUntil) return 'default';
    return 'outline';
};

const getStatusLabel = (): string => {
    const now = new Date();
    const validFrom = new Date(props.voucher.valid_from);
    const validUntil = new Date(props.voucher.valid_until);

    if (!props.voucher.is_active) return 'Inactive';
    if (now > validUntil) return 'Expired';
    if (now >= validFrom && now <= validUntil) return 'Active';
    return 'Upcoming';
};

const getApplicationStatusVariant = (status: VoucherApplication['status']): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'expired') return 'destructive';
    if (status === 'cancelled') return 'secondary';
    return 'default';
};

const getTypeVariant = (): 'default' | 'secondary' => {
    return props.voucher.voucher_type === 'discount' ? 'default' : 'secondary';
};

const isExpired = computed(() => {
    return new Date() > new Date(props.voucher.valid_until);
});

const canSubmitApplication = computed(() => {
    return Boolean(props.canApplyVoucher && props.currentSemester && props.voucher.is_active && !isExpired.value && applyForm.student_id && !applyForm.processing);
});

const handleStudentSelect = (student: Student | null) => {
    selectedStudent.value = student;
    applyForm.student_id = student?.id ?? null;
};

const applyVoucher = () => {
    applyForm.post(route('vouchers.redeem'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedStudent.value = null;
            applyForm.reset('student_id');
            applyForm.clearErrors();
        },
    });
};
</script>

<template>
    <Head :title="`Voucher: ${voucher.code}`" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Voucher Details</h2>
            <p class="text-muted-foreground mt-1 text-sm">View, apply, and monitor voucher usage</p>
        </div>
        <div class="flex gap-2">
            <Button variant="outline" as-child>
                <Link :href="route('vouchers.index')">Back to Vouchers</Link>
            </Button>
            <Button variant="outline" as-child>
                <Link :href="route('vouchers.edit', voucher.id)">Edit Voucher</Link>
            </Button>
        </div>
    </div>

    <Alert v-if="isExpired" variant="destructive" class="mt-6">
        <AlertCircle class="h-4 w-4" />
        <AlertDescription>This voucher has expired and can no longer be applied to students.</AlertDescription>
    </Alert>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <Card class="xl:col-span-1">
            <CardHeader>
                <CardTitle>Basic Information</CardTitle>
                <CardDescription>Core voucher details</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <p class="text-muted-foreground text-sm">Voucher Code</p>
                        <div class="flex items-center gap-2">
                            <Hash class="text-muted-foreground h-4 w-4" />
                            <p class="font-mono text-lg font-semibold">{{ voucher.code }}</p>
                        </div>
                    </div>
                    <Badge :variant="getStatusVariant()">{{ getStatusLabel() }}</Badge>
                </div>

                <div class="space-y-1">
                    <p class="text-muted-foreground text-sm">Voucher Name</p>
                    <p class="font-medium">{{ voucher.name }}</p>
                </div>

                <div v-if="voucher.description" class="space-y-1">
                    <p class="text-muted-foreground text-sm">Description</p>
                    <p class="text-sm">{{ voucher.description }}</p>
                </div>

                <div class="space-y-1">
                    <p class="text-muted-foreground text-sm">Voucher Type</p>
                    <Badge :variant="getTypeVariant()">
                        {{ voucher.voucher_type === 'informational' ? 'Informational' : 'Discount' }}
                    </Badge>
                </div>
            </CardContent>
        </Card>

        <Card class="xl:col-span-1">
            <CardHeader>
                <CardTitle>Discount Details</CardTitle>
                <CardDescription>Discount type and amount</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-if="voucher.voucher_type === 'discount'" class="space-y-4">
                    <div class="space-y-1">
                        <p class="text-muted-foreground text-sm">Discount Type</p>
                        <Badge variant="outline">
                            {{ voucher.discount_type === 'percentage' ? 'Percentage' : 'Fixed Amount' }}
                        </Badge>
                    </div>

                    <div class="space-y-1">
                        <p class="text-muted-foreground text-sm">Discount Value</p>
                        <div class="flex items-center gap-2">
                            <DollarSign class="text-muted-foreground h-4 w-4" />
                            <p class="text-lg font-semibold">{{ formatDiscountValue() }}</p>
                        </div>
                    </div>
                </div>

                <div v-else class="text-muted-foreground py-4 text-center text-sm">This is an informational voucher. It tracks usage but does not create a discount amount.</div>

                <div class="space-y-1">
                    <p class="text-muted-foreground text-sm">Valid Period</p>
                    <div class="flex items-center gap-2">
                        <Calendar class="text-muted-foreground h-4 w-4" />
                        <p class="text-sm">{{ formatDate(voucher.valid_from) }} - {{ formatDate(voucher.valid_until) }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card class="xl:col-span-1">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <UserPlus class="h-4 w-4" />
                    Apply Voucher
                </CardTitle>
                <CardDescription>Assign this voucher to one student for the active semester</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <Alert v-if="!canApplyVoucher" variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>You do not have permission to apply this voucher.</AlertDescription>
                </Alert>

                <Alert v-else-if="!currentSemester" variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>No active semester is configured. Voucher apply is blocked until one semester is marked active.</AlertDescription>
                </Alert>

                <Alert v-else-if="!voucher.is_active || isExpired" variant="destructive">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>This voucher is not currently eligible for manual apply.</AlertDescription>
                </Alert>

                <div class="space-y-2">
                    <p class="text-sm font-medium">Student</p>
                    <StudentCombobox
                        v-model="applyForm.student_id"
                        :disabled="!canApplyVoucher || !currentSemester || !voucher.is_active || isExpired || applyForm.processing"
                        :error-message="applyForm.errors.student_id"
                        @select="handleStudentSelect"
                    />
                    <InputError :message="applyForm.errors.student_id" />
                </div>

                <div class="bg-muted/40 rounded-lg border p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-muted-foreground text-xs uppercase">Active Semester</p>
                            <p class="font-medium">
                                {{ currentSemester ? `${currentSemester.code} - ${currentSemester.name}` : 'Not configured' }}
                            </p>
                        </div>
                        <Calendar class="text-muted-foreground h-4 w-4" />
                    </div>
                </div>

                <div class="bg-muted/40 rounded-lg border p-4">
                    <div class="flex items-center gap-2">
                        <TicketPercent class="text-muted-foreground h-4 w-4" />
                        <p class="text-sm font-medium">Apply Behavior</p>
                    </div>
                    <p class="text-muted-foreground mt-2 text-sm">
                        <span v-if="voucher.voucher_type === 'informational'"> Informational voucher: usage is tracked, discount amount stays 0. </span>
                        <span v-else-if="voucher.discount_type === 'percentage'"> Percentage voucher: discount amount is calculated for the active semester when applied. </span>
                        <span v-else> Fixed amount voucher: the configured discount value is applied directly. </span>
                    </p>
                </div>

                <div v-if="selectedStudent" class="rounded-lg border border-dashed p-4">
                    <p class="text-muted-foreground text-xs uppercase">Selected Student</p>
                    <p class="mt-1 font-medium">{{ selectedStudent.full_name }}</p>
                    <p class="text-muted-foreground text-sm">{{ selectedStudent.student_id }} · {{ selectedStudent.email }}</p>
                </div>

                <InputError :message="applyForm.errors.voucher_id || applyForm.errors.code" />

                <div class="flex justify-end">
                    <Button :disabled="!canSubmitApplication" @click="applyVoucher">
                        <Loader2 v-if="applyForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Apply Voucher
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>

    <Card class="mt-6">
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle>Usage History</CardTitle>
                    <CardDescription>Students and semesters that currently hold this voucher</CardDescription>
                </div>
                <div class="flex items-center gap-2">
                    <Users class="text-muted-foreground h-4 w-4" />
                    <span class="text-muted-foreground text-sm">{{ applications.length }} usage record(s)</span>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="applications.length > 0" class="overflow-x-auto">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Student</TableHead>
                            <TableHead>Semester</TableHead>
                            <TableHead>Amount</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Invoice</TableHead>
                            <TableHead>Applied At</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="application in applications" :key="application.id">
                            <TableCell>
                                <div class="space-y-1">
                                    <div class="font-medium">{{ application.student.full_name }}</div>
                                    <div class="text-muted-foreground font-mono text-xs">
                                        {{ application.student.student_id }}
                                    </div>
                                    <div class="text-muted-foreground text-xs">{{ application.student.email }}</div>
                                </div>
                            </TableCell>
                            <TableCell>
                                <span v-if="application.semester" class="text-sm"> {{ application.semester.code }} - {{ application.semester.name }} </span>
                                <span v-else class="text-muted-foreground text-sm">N/A</span>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2 text-sm">
                                    <Receipt class="text-muted-foreground h-4 w-4" />
                                    <span v-if="Number(application.discount_amount ?? 0) > 0">
                                        {{ formatCurrency(application.discount_amount) }}
                                    </span>
                                    <span v-else class="text-muted-foreground">Info only</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="getApplicationStatusVariant(application.status)">
                                    {{ application.status }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <span v-if="application.invoice" class="font-mono text-sm">
                                    {{ application.invoice.invoice_number }}
                                </span>
                                <span v-else class="text-muted-foreground text-sm">Pending invoice</span>
                            </TableCell>
                            <TableCell class="text-sm">{{ formatDateTime(application.applied_at) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            <div v-else class="text-muted-foreground py-8 text-center">
                <p>No usage history yet.</p>
                <p class="mt-2 text-sm">Apply this voucher to a student from the card above to create the first canonical usage record.</p>
            </div>
        </CardContent>
    </Card>
</template>
