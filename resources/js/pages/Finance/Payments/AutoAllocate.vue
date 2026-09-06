<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useApi } from '@/composables/useApiRequest';
import { financeRoutes } from '@/utils/routes';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowLeft, ArrowUp, CheckCircle2, Loader2, Sparkles, Users, Wallet, Zap } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

interface AllocationDetail {
    charge_id: number;
    charge_type: string;
    charge_description: string;
    amount: number;
}

interface PaymentAllocation {
    payment_id: number;
    payment_amount: number;
    payment_unapplied: number;
    payment_date: string;
    payment_external_ref: string | null;
    to_allocate: AllocationDetail[];
}

interface StudentPreview {
    student_id: number;
    student_code: string;
    student_name: string;
    payments: number[];
    allocations: PaymentAllocation[];
    total_to_allocate: number;
}

interface PreviewSummary {
    total_students: number;
    total_payments_affected: number;
    total_allocations: number;
    total_amount: number;
    invoices_to_update: number;
}

interface PreviewData {
    students: StudentPreview[];
    summary: PreviewSummary;
}

interface AllocationPriorityOption {
    id: string;
    label: string;
}

const props = defineProps<{
    allocation_priority_options?: AllocationPriorityOption[];
}>();

const currentStep = ref<'priority' | 'preview' | 'complete'>('priority');
const isLoading = ref(false);
const isExecuting = ref(false);
const previewData = ref<PreviewData | null>(null);

const priorities = ref<AllocationPriorityOption[]>([...(props.allocation_priority_options ?? [])]);

const moveUp = (index: number) => {
    if (index === 0) return;
    const temp = priorities.value[index];
    priorities.value[index] = priorities.value[index - 1];
    priorities.value[index - 1] = temp;
};

const moveDown = (index: number) => {
    if (index === priorities.value.length - 1) return;
    const temp = priorities.value[index];
    priorities.value[index] = priorities.value[index + 1];
    priorities.value[index + 1] = temp;
};

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value);
};

const api = useApi();

const handlePreview = async () => {
    isLoading.value = true;
    try {
        const { data } = await api.post<PreviewData>(route('finance.payments.auto-allocate.preview'), {
            priority_order: priorities.value.map((p) => p.id),
        });
        const result = data.value as unknown as PreviewData | null;
        if (result && 'summary' in result) {
            previewData.value = result;
            currentStep.value = 'preview';
        } else {
            toast.error('Không thể tải xem trước. Vui lòng thử lại.');
        }
    } catch {
        toast.error('Không thể tải xem trước. Vui lòng thử lại.');
    } finally {
        isLoading.value = false;
    }
};

const handleExecute = () => {
    isExecuting.value = true;
    router.post(
        route('finance.payments.auto-allocate'),
        {
            priority_order: priorities.value.map((p) => p.id),
        },
        {
            onSuccess: () => {
                toast.success('Phân bổ tự động hoàn tất');
                currentStep.value = 'complete';
            },
            onError: () => {
                toast.error('Phân bổ tự động thất bại');
            },
            onFinish: () => {
                isExecuting.value = false;
            },
        },
    );
};

const goBack = () => {
    if (currentStep.value === 'preview') {
        currentStep.value = 'priority';
        previewData.value = null;
    }
};

const expandedStudents = ref<Set<number>>(new Set());

const toggleStudent = (studentId: number) => {
    if (expandedStudents.value.has(studentId)) {
        expandedStudents.value.delete(studentId);
    } else {
        expandedStudents.value.add(studentId);
    }
};
</script>

<template>
    <Head title="Phân bổ tự động" />

    <div class="flex items-center gap-4">
        <Link :href="route('finance.payments.index')">
            <Button variant="ghost" size="icon">
                <ArrowLeft class="h-4 w-4" />
            </Button>
        </Link>
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Phân bổ thanh toán tự động</h1>
            <p class="text-muted-foreground">Tự động áp dụng các khoản thanh toán chưa phân bổ vào các khoản phí còn thiếu</p>
        </div>
    </div>

    <div class="flex items-center gap-2">
        <div class="flex h-8 w-8 items-center justify-center rounded-full" :class="currentStep === 'priority' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'">1</div>
        <span :class="currentStep === 'priority' ? 'font-medium' : 'text-muted-foreground'"> Chọn thứ tự ưu tiên </span>
        <Separator class="w-8" />
        <div class="flex h-8 w-8 items-center justify-center rounded-full" :class="currentStep === 'preview' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'">2</div>
        <span :class="currentStep === 'preview' ? 'font-medium' : 'text-muted-foreground'"> Xem trước </span>
        <Separator class="w-8" />
        <div class="flex h-8 w-8 items-center justify-center rounded-full" :class="currentStep === 'complete' ? 'bg-green-600 text-white' : 'bg-muted text-muted-foreground'">
            <CheckCircle2 v-if="currentStep === 'complete'" class="h-5 w-5" />
            <span v-else>3</span>
        </div>
        <span :class="currentStep === 'complete' ? 'font-medium text-green-600' : 'text-muted-foreground'"> Hoàn tất </span>
    </div>

    <template v-if="currentStep === 'priority'">
        <Card class="max-w-xl">
            <CardHeader>
                <CardTitle>Thứ tự ưu tiên</CardTitle>
                <CardDescription> Sắp xếp loại phí theo thứ tự ưu tiên. Khoản thanh toán sẽ được phân bổ vào loại phí có thứ tự cao hơn trước. </CardDescription>
            </CardHeader>
            <CardContent>
                <Label class="mb-4 block">Kéo để sắp xếp (Cao → Thấp)</Label>
                <div class="space-y-2">
                    <div v-for="(item, index) in priorities" :key="item.id" class="bg-background flex items-center justify-between rounded-md border p-3">
                        <span class="text-sm font-medium"> {{ index + 1 }}. {{ item.label }} </span>
                        <div class="flex gap-1">
                            <Button variant="ghost" size="icon" class="h-8 w-8" :disabled="index === 0" @click="moveUp(index)">
                                <ArrowUp class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8" :disabled="index === priorities.length - 1" @click="moveDown(index)">
                                <ArrowDown class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <Button @click="handlePreview" :disabled="isLoading || priorities.length === 0">
                        <Loader2 v-if="isLoading" class="mr-2 h-4 w-4 animate-spin" />
                        <Sparkles v-else class="mr-2 h-4 w-4" />
                        Xem trước phân bổ
                    </Button>
                </div>
            </CardContent>
        </Card>
    </template>

    <template v-else-if="currentStep === 'preview' && previewData">
        <div class="grid grid-cols-4 gap-4">
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Số sinh viên</CardDescription>
                    <CardTitle class="flex items-center gap-2 text-2xl">
                        <Users class="h-5 w-5 text-blue-500" />
                        {{ previewData.summary.total_students }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Số khoản thanh toán</CardDescription>
                    <CardTitle class="flex items-center gap-2 text-2xl">
                        <Wallet class="h-5 w-5 text-green-500" />
                        {{ previewData.summary.total_payments_affected }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Số lần phân bổ</CardDescription>
                    <CardTitle class="flex items-center gap-2 text-2xl">
                        <Zap class="h-5 w-5 text-yellow-500" />
                        {{ previewData.summary.total_allocations }}
                    </CardTitle>
                </CardHeader>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardDescription>Tổng số tiền</CardDescription>
                    <CardTitle class="text-2xl text-green-600">
                        {{ formatCurrency(previewData.summary.total_amount) }}
                    </CardTitle>
                </CardHeader>
            </Card>
        </div>

        <Card v-if="previewData.students.length === 0">
            <CardContent class="py-10 text-center">
                <p class="text-muted-foreground">Không có khoản thanh toán nào cần phân bổ.</p>
            </CardContent>
        </Card>

        <Card v-else>
            <CardHeader>
                <CardTitle>Chi tiết phân bổ theo sinh viên</CardTitle>
                <CardDescription> Nhấn vào sinh viên để xem chi tiết từng khoản phân bổ </CardDescription>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Mã SV</TableHead>
                            <TableHead>Họ tên</TableHead>
                            <TableHead class="text-center">Số thanh toán</TableHead>
                            <TableHead class="text-center">Số lần phân bổ</TableHead>
                            <TableHead class="text-right">Tổng phân bổ</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <template v-for="student in previewData.students" :key="student.student_id">
                            <TableRow class="hover:bg-muted/50 cursor-pointer" @click="toggleStudent(student.student_id)">
                                <TableCell class="font-medium">
                                    {{ student.student_code }}
                                </TableCell>
                                <TableCell>{{ student.student_name }}</TableCell>
                                <TableCell class="text-center">
                                    {{ student.payments.length }}
                                </TableCell>
                                <TableCell class="text-center">
                                    {{ student.allocations.reduce((sum, a) => sum + a.to_allocate.length, 0) }}
                                </TableCell>
                                <TableCell class="text-right font-medium text-green-600">
                                    {{ formatCurrency(student.total_to_allocate) }}
                                </TableCell>
                            </TableRow>

                            <TableRow v-if="expandedStudents.has(student.student_id)">
                                <TableCell colspan="5" class="bg-muted/30 p-4">
                                    <div class="space-y-4">
                                        <div v-for="allocation in student.allocations" :key="allocation.payment_id" class="bg-background rounded-lg border p-4">
                                            <div class="mb-3 flex items-center justify-between">
                                                <div>
                                                    <span class="font-medium"> Payment #{{ allocation.payment_id }} </span>
                                                    <span class="text-muted-foreground ml-2 text-sm">
                                                        {{ allocation.payment_date }}
                                                    </span>
                                                </div>
                                                <Badge variant="outline"> Còn: {{ formatCurrency(allocation.payment_unapplied) }} </Badge>
                                            </div>
                                            <div class="space-y-2">
                                                <div v-for="detail in allocation.to_allocate" :key="detail.charge_id" class="bg-muted/50 flex items-center justify-between rounded px-3 py-2 text-sm">
                                                    <div>
                                                        <span class="font-medium">
                                                            {{ detail.charge_type }}
                                                        </span>
                                                        <span v-if="detail.charge_description" class="text-muted-foreground ml-2"> - {{ detail.charge_description }} </span>
                                                    </div>
                                                    <span class="font-medium text-green-600">
                                                        {{ formatCurrency(detail.amount) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </template>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <div class="flex justify-between">
            <Button variant="outline" @click="goBack">
                <ArrowLeft class="mr-2 h-4 w-4" />
                Quay lại
            </Button>
            <Button @click="handleExecute" :disabled="isExecuting || previewData.students.length === 0">
                <Loader2 v-if="isExecuting" class="mr-2 h-4 w-4 animate-spin" />
                <Zap v-else class="mr-2 h-4 w-4" />
                Thực hiện phân bổ
            </Button>
        </div>
    </template>

    <template v-else-if="currentStep === 'complete'">
        <Card class="max-w-xl">
            <CardContent class="pt-6">
                <div class="flex flex-col items-center gap-4 text-center">
                    <CheckCircle2 class="h-16 w-16 text-green-600" />
                    <h2 class="text-xl font-semibold">Phân bổ hoàn tất!</h2>
                    <p class="text-muted-foreground">Tất cả các khoản thanh toán đã được phân bổ thành công vào các khoản phí tương ứng.</p>
                    <div class="mt-4 flex gap-3">
                        <Link :href="route('finance.payments.index')">
                            <Button variant="outline">Về danh sách thanh toán</Button>
                        </Link>
                        <Link :href="financeRoutes.cockpit.index()">
                            <Button>Về Hôm nay</Button>
                        </Link>
                    </div>
                </div>
            </CardContent>
        </Card>
    </template>
</template>
