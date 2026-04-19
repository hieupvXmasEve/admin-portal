---
phase: 3
title: Frontend — Vue Page EgcRetake.vue
status: pending
---

# Phase 3: Frontend

## Overview

Tạo `resources/js/Pages/Finance/Operations/EgcRetake.vue` theo pattern của `BatchDng.vue` và `GenerateCharges.vue`.

**UX flow:**
1. Admin chọn semester → hệ thống tự load preview danh sách eligible students
2. Admin xem bảng: student, level, chuyên cần %, retake amount, credit amount
3. Admin nhấn "Xử lý" → POST → kết quả hiện toast + refresh

---

## 3.1 EgcRetake.vue

**File:** `resources/js/Pages/Finance/Operations/EgcRetake.vue`

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, RefreshCw, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Semester {
    id: number;
    name: string;
    is_active: boolean;
}

interface RetakePreviewItem {
    student_id: number;
    student_code: string;
    student_name: string;
    level: number;
    attendance_percentage: number;
    level_fee: number;
    retake_amount: number;
    credit_amount: number;
    voided_charge_id: number;
    can_process: boolean;
}

interface Props {
    semesters: Semester[];
    currentSemester: Semester | null;
    preview: RetakePreviewItem[];
    filters: { semester_id: string | null };
}

// ─── Setup ────────────────────────────────────────────────────────────────────

defineOptions({ layout: AppLayout });

const props = defineProps<Props>();

const selectedSemesterId = ref<string>(props.filters.semester_id ?? String(props.currentSemester?.id ?? ''));
const isProcessing = ref(false);

// ─── Computed ─────────────────────────────────────────────────────────────────

const totalCredit = computed(() =>
    props.preview.reduce((sum, item) => sum + item.credit_amount, 0),
);

const totalRetake = computed(() =>
    props.preview.reduce((sum, item) => sum + item.retake_amount, 0),
);

// ─── Semester filter ──────────────────────────────────────────────────────────

watch(selectedSemesterId, (val) => {
    router.visit(route('finance.operations.egc-retake'), {
        data: { semester_id: val },
        preserveScroll: true,
        replace: true,
    });
});

// ─── Process ──────────────────────────────────────────────────────────────────

const form = useForm({ semester_id: selectedSemesterId });

function handleProcess() {
    if (!selectedSemesterId.value || props.preview.length === 0) {
        return;
    }

    isProcessing.value = true;
    form.semester_id = selectedSemesterId.value;

    form.post(route('finance.operations.egc-retake.process'), {
        onSuccess: () => {
            toast.success('Xử lý retake fee thành công');
            // Reload preview (sẽ trống sau khi process)
            router.reload({ only: ['preview'] });
        },
        onError: (errors) => {
            toast.error('Xử lý thất bại: ' + Object.values(errors).join(', '));
        },
        onFinish: () => {
            isProcessing.value = false;
        },
    });
}

// ─── Utils ────────────────────────────────────────────────────────────────────

const formatCurrency = (val: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
</script>

<template>
    <Head title="EGC Retake Fee Processing" />

    <div class="space-y-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Xử lý Học phí Học lại EGC Block 1</h1>
                <p class="text-muted-foreground mt-1 text-sm">
                    Tự động giảm 50% học phí cho sinh viên trượt Block 1 và đủ điều kiện chuyên cần (≥ 80%).
                </p>
            </div>
        </div>

        <!-- Semester Selector -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base">Chọn kỳ học</CardTitle>
            </CardHeader>
            <CardContent>
                <Select v-model="selectedSemesterId">
                    <SelectTrigger class="w-72">
                        <SelectValue placeholder="Chọn kỳ học..." />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="sem in semesters"
                            :key="sem.id"
                            :value="String(sem.id)"
                        >
                            {{ sem.name }}
                            <Badge v-if="sem.is_active" variant="secondary" class="ml-2 text-xs">Hiện tại</Badge>
                        </SelectItem>
                    </SelectContent>
                </Select>
            </CardContent>
        </Card>

        <!-- Summary Stats -->
        <div v-if="preview.length > 0" class="grid grid-cols-3 gap-4">
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-3">
                        <Users class="text-muted-foreground h-5 w-5" />
                        <div>
                            <p class="text-muted-foreground text-sm">Sinh viên đủ điều kiện</p>
                            <p class="text-2xl font-bold">{{ preview.length }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-3">
                        <CheckCircle2 class="h-5 w-5 text-green-500" />
                        <div>
                            <p class="text-muted-foreground text-sm">Tổng học phí học lại</p>
                            <p class="text-2xl font-bold">{{ formatCurrency(totalRetake) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-6">
                    <div class="flex items-center gap-3">
                        <RefreshCw class="h-5 w-5 text-blue-500" />
                        <div>
                            <p class="text-muted-foreground text-sm">Tổng credit carry-forward</p>
                            <p class="text-2xl font-bold text-blue-600">{{ formatCurrency(totalCredit) }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Preview Table -->
        <Card>
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle class="text-base">
                    Danh sách sinh viên
                    <span v-if="preview.length > 0" class="text-muted-foreground ml-2 font-normal">
                        ({{ preview.length }} sinh viên)
                    </span>
                </CardTitle>
                <Button
                    v-if="preview.length > 0"
                    :disabled="isProcessing || form.processing"
                    @click="handleProcess"
                >
                    <RefreshCw v-if="isProcessing || form.processing" class="mr-2 h-4 w-4 animate-spin" />
                    Xử lý {{ preview.length }} sinh viên
                </Button>
            </CardHeader>
            <CardContent>
                <!-- Empty state -->
                <div v-if="!selectedSemesterId" class="py-12 text-center">
                    <p class="text-muted-foreground text-sm">Vui lòng chọn kỳ học để xem danh sách.</p>
                </div>
                <div v-else-if="preview.length === 0" class="py-12 text-center">
                    <CheckCircle2 class="mx-auto mb-3 h-10 w-10 text-green-400" />
                    <p class="text-muted-foreground text-sm">
                        Không có sinh viên nào đủ điều kiện hoặc đã được xử lý trong kỳ này.
                    </p>
                </div>

                <!-- Data table -->
                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Mã SV</TableHead>
                            <TableHead>Họ tên</TableHead>
                            <TableHead class="text-center">Level</TableHead>
                            <TableHead class="text-right">Chuyên cần</TableHead>
                            <TableHead class="text-right">HP gốc (Level X+1)</TableHead>
                            <TableHead class="text-right">HP học lại (50%)</TableHead>
                            <TableHead class="text-right">Credit carry-forward</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in preview" :key="item.student_id">
                            <TableCell class="font-mono text-sm">{{ item.student_code }}</TableCell>
                            <TableCell>{{ item.student_name }}</TableCell>
                            <TableCell class="text-center">
                                <Badge variant="outline">Level {{ item.level }}</Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <span :class="item.attendance_percentage >= 80 ? 'text-green-600' : 'text-red-500'">
                                    {{ item.attendance_percentage.toFixed(1) }}%
                                </span>
                            </TableCell>
                            <TableCell class="text-muted-foreground text-right line-through">
                                {{ formatCurrency(item.level_fee) }}
                            </TableCell>
                            <TableCell class="text-right font-medium">
                                {{ formatCurrency(item.retake_amount) }}
                            </TableCell>
                            <TableCell class="text-right font-semibold text-blue-600">
                                {{ formatCurrency(item.credit_amount) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <!-- Warning note -->
        <div v-if="preview.length > 0" class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-4">
            <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
            <p class="text-sm text-amber-800">
                Sau khi xử lý, charge học phí Level X+1 sẽ bị void và charge học lại Level X (50%) sẽ được tạo.
                Khoản chênh lệch <strong>{{ formatCurrency(totalCredit) }}</strong> sẽ tự động được chuyển sang invoice kỳ sau
                thông qua cơ chế payment carry-forward.
            </p>
        </div>
    </div>
</template>
```

---

## 3.2 Navigation Link (optional)

Nếu có sidebar navigation cho Finance Operations, thêm link đến trang mới. Tìm file navigation và thêm:

```ts
{
    label: 'EGC Retake Fee',
    href: route('finance.operations.egc-retake'),
    icon: RefreshCw,
}
```

Tìm file: `grep -r "generate-charges\|egc" resources/js --include="*.vue" --include="*.ts" -l`

---

## Todo

- [ ] Tạo `EgcRetake.vue` theo template trên
- [ ] Verify `route('finance.operations.egc-retake')` hoạt động (chạy `pnpm run type-check`)
- [ ] Kiểm tra `ziggy-js` route type generation: `php artisan ziggy:generate`
- [ ] Start dev server và test thủ công: chọn semester → xem preview → nhấn xử lý
- [ ] Verify sau xử lý: reload → danh sách trống (idempotency)
- [ ] Verify settlement: invoice amount giảm, unapplied credit tăng
- [ ] Dark mode check (Tailwind `dark:` classes nếu app hỗ trợ)
- [ ] Chạy `pnpm run lint` + `pnpm run format:check`

## Success Criteria

- Trang load được, không lỗi TypeScript
- Dropdown semester filter hoạt động, preview reload khi đổi semester
- Bảng hiển thị đúng: level, chuyên cần %, credit amount
- Button "Xử lý" disabled khi processing
- Toast success/error hiển thị đúng
- Sau process: preview trống (no more eligible students)
