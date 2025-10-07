<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertCircle, Calendar, DollarSign, Hash, Loader2, Trash2, Users } from 'lucide-vue-next';
import { route } from 'ziggy-js';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { ref } from 'vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
}

interface Invoice {
    id: number;
    invoice_number: string;
    total_amount: number;
}

interface VoucherRedemption {
    id: number;
    voucher_id: number;
    student_id: number;
    invoice_id: number | null;
    redeemed_at: string;
    student: Student;
    invoice?: Invoice;
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
    redemptions: VoucherRedemption[];
}

const props = defineProps<Props>();

const deleteForm = useForm({});
const isDeleting = ref(false);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDiscountValue = () => {
    if (props.voucher.voucher_type === 'informational') {
        return 'N/A';
    }
    if (props.voucher.discount_type === 'percentage') {
        return `${props.voucher.discount_value}%`;
    }
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(props.voucher.discount_value || 0);
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

const getTypeVariant = (): 'default' | 'secondary' => {
    return props.voucher.voucher_type === 'discount' ? 'default' : 'secondary';
};

const isExpired = (): boolean => {
    const now = new Date();
    const validUntil = new Date(props.voucher.valid_until);
    return now > validUntil;
};

const deleteVoucher = () => {
    isDeleting.value = true;
    deleteForm.delete(route('vouchers.destroy', props.voucher.id), {
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Voucher: ${voucher.code}`" />

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-foreground text-xl leading-tight font-semibold">Voucher Details</h2>
            <p class="text-muted-foreground mt-1 text-sm">View and manage voucher information</p>
        </div>
        <div class="flex gap-2">
            <Button variant="outline" as-child>
                <Link :href="route('vouchers.index')">Back to Vouchers</Link>
            </Button>
            <Button variant="outline" as-child>
                <Link :href="route('vouchers.edit', voucher.id)">Edit Voucher</Link>
            </Button>
            <AlertDialog>
                <AlertDialogTrigger as-child>
                    <Button variant="destructive" :disabled="isDeleting">
                        <Loader2 v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                        <Trash2 v-else class="mr-2 h-4 w-4" />
                        Delete
                    </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Are you sure?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete the voucher "{{ voucher.code }}". This action cannot be undone.
                            <span v-if="redemptions.length > 0" class="mt-2 block font-semibold text-destructive">
                                Warning: This voucher has {{ redemptions.length }} redemption(s).
                            </span>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction @click="deleteVoucher" class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Delete Voucher
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </div>

    <Alert v-if="isExpired()" variant="destructive" class="mt-6">
        <AlertCircle class="h-4 w-4" />
        <AlertDescription>
            This voucher has expired and can no longer be redeemed by students.
        </AlertDescription>
    </Alert>

    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <!-- Basic Information -->
        <Card>
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

        <!-- Discount Details -->
        <Card>
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

                <div v-else class="text-muted-foreground py-4 text-center text-sm">
                    This is an informational voucher with no discount value.
                </div>

                <div class="space-y-1">
                    <p class="text-muted-foreground text-sm">Valid Period</p>
                    <div class="flex items-center gap-2">
                        <Calendar class="text-muted-foreground h-4 w-4" />
                        <p class="text-sm">
                            {{ formatDate(voucher.valid_from) }} - {{ formatDate(voucher.valid_until) }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Redemption History -->
    <Card class="mt-6">
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle>Redemption History</CardTitle>
                    <CardDescription>Students who have redeemed this voucher</CardDescription>
                </div>
                <div class="flex items-center gap-2">
                    <Users class="text-muted-foreground h-4 w-4" />
                    <span class="text-muted-foreground text-sm">{{ redemptions.length }} redemption(s)</span>
                </div>
            </div>
        </CardHeader>
        <CardContent>
            <div v-if="redemptions.length > 0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Student ID</TableHead>
                            <TableHead>Student Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Invoice</TableHead>
                            <TableHead>Redeemed At</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="redemption in redemptions" :key="redemption.id">
                            <TableCell class="font-mono">{{ redemption.student.student_id }}</TableCell>
                            <TableCell>{{ redemption.student.full_name }}</TableCell>
                            <TableCell>{{ redemption.student.email }}</TableCell>
                            <TableCell>
                                <span v-if="redemption.invoice" class="font-mono text-sm">
                                    {{ redemption.invoice.invoice_number }}
                                </span>
                                <span v-else class="text-muted-foreground text-sm">N/A</span>
                            </TableCell>
                            <TableCell class="text-sm">{{ formatDateTime(redemption.redeemed_at) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
            <div v-else class="text-muted-foreground py-8 text-center">
                <p>No redemptions yet.</p>
                <p class="mt-2 text-sm">This voucher has not been redeemed by any students.</p>
            </div>
        </CardContent>
    </Card>
</template>
