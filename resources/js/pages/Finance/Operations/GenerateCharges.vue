<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DatePicker from '@/components/ui/DatePicker.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useApi } from '@/composables/useApiRequest';
import { type Semester } from '@/types/finance';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Download, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface FeeTypeOption {
    value: string;
    label: string;
}

interface CurrentCampus {
    id: number;
    name: string | null;
}

interface GenerateResult {
    created: { student_code: string; charge_id: number }[];
    skipped: { student_code: string; reason: string }[];
    summary: { total: number; created: number; skipped: number };
}

interface Props {
    feeTypes: FeeTypeOption[];
    semesters: Semester[];
    currentCampus: CurrentCampus | null;
}

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------

const props = defineProps<Props>();

// Form fields
const selectedFeeType = ref('');
const selectedSemesterId = ref('');
const amount = ref('');
const dueDate = ref('');
const note = ref('');
const csvFile = ref<File | null>(null);

// State
const isSubmitting = ref(false);
const result = ref<GenerateResult | null>(null);
const api = useApi();

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function onFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    csvFile.value = input.files?.[0] ?? null;
}

async function onSubmit() {
    if (!selectedFeeType.value || !selectedSemesterId.value || !amount.value || !dueDate.value || !csvFile.value) {
        toast.error('Vui lòng điền đầy đủ thông tin và tải lên file CSV.');
        return;
    }

    isSubmitting.value = true;
    result.value = null;

    const formData = new FormData();
    formData.append('fee_type', selectedFeeType.value);
    formData.append('semester_id', selectedSemesterId.value);
    formData.append('amount', amount.value);
    formData.append('due_date', dueDate.value);
    formData.append('note', note.value);
    formData.append('csv_file', csvFile.value);

    try {
        // useApi.post() accepts FormData directly; the transport layer (beforeFetch)
        // detects FormData and lets the browser set multipart/form-data + boundary.
        // ApiResponse envelope: { success, message, data: { created, skipped, summary } } (F4)
        const response = await api.post<GenerateResult>(
            route('api.finance.operations.generate-non-academic-charges'),
            formData,
        );

        if (response.error.value) {
            // Network-level or non-JSON error captured by onFetchError
            toast.error('Lỗi kết nối. Vui lòng thử lại.');
            return;
        }

        const json = response.data.value;

        if (json?.success && json.data) {
            result.value = json.data;
            toast.success(
                `Hoàn thành: ${json.data.summary.created} charges tạo, ${json.data.summary.skipped} bỏ qua.`,
            );
        } else {
            toast.error(json?.message ?? 'Có lỗi xảy ra.');
        }
    } catch {
        toast.error('Lỗi kết nối. Vui lòng thử lại.');
    } finally {
        isSubmitting.value = false;
    }
}

function downloadTemplate() {
    window.location.href = route('finance.operations.non-academic-charges-template');
}
</script>

<template>
    <Head title="Generate Non-Academic Charges" />

    <div class="mx-auto max-w-3xl space-y-6 px-4 py-6">
        <div>
            <h1 class="text-2xl font-bold">Tạo Non-Academic Charges</h1>
            <p class="text-muted-foreground mt-1 text-sm">
                Upload danh sách mã sinh viên (CSV) để tạo phí hàng loạt.
                <span v-if="props.currentCampus" class="font-medium">
                    Campus: {{ props.currentCampus.name ?? props.currentCampus.id }}
                </span>
            </p>
        </div>

        <!-- Form card -->
        <Card>
            <CardHeader>
                <CardTitle>Thông tin phí</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-5" @submit.prevent="onSubmit">
                    <!-- Fee Type -->
                    <div class="space-y-1.5">
                        <Label for="fee-type">Loại phí <span class="text-destructive">*</span></Label>
                        <Select v-model="selectedFeeType">
                            <SelectTrigger id="fee-type">
                                <SelectValue placeholder="Chọn loại phí" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="ft in props.feeTypes"
                                    :key="ft.value"
                                    :value="ft.value"
                                >
                                    {{ ft.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Semester -->
                    <div class="space-y-1.5">
                        <Label for="semester">Học kỳ <span class="text-destructive">*</span></Label>
                        <Select v-model="selectedSemesterId">
                            <SelectTrigger id="semester">
                                <SelectValue placeholder="Chọn học kỳ" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="s in props.semesters"
                                    :key="s.id"
                                    :value="String(s.id)"
                                >
                                    {{ s.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Amount -->
                    <div class="space-y-1.5">
                        <Label for="amount">Số tiền (VND) <span class="text-destructive">*</span></Label>
                        <Input
                            id="amount"
                            v-model="amount"
                            type="number"
                            min="1"
                            step="1"
                            placeholder="Ví dụ: 500000"
                        />
                    </div>

                    <!-- Due Date -->
                    <div class="space-y-1.5">
                        <Label>Ngày hết hạn <span class="text-destructive">*</span></Label>
                        <DatePicker v-model="dueDate" placeholder="Chọn ngày hết hạn" />
                    </div>

                    <!-- Note -->
                    <div class="space-y-1.5">
                        <Label for="note">Ghi chú</Label>
                        <Input
                            id="note"
                            v-model="note"
                            type="text"
                            maxlength="255"
                            placeholder="Ghi chú tùy chọn (tối đa 255 ký tự)"
                        />
                    </div>

                    <!-- CSV Upload -->
                    <div class="space-y-1.5">
                        <Label for="csv-file">
                            File CSV danh sách mã sinh viên <span class="text-destructive">*</span>
                        </Label>
                        <div class="flex items-center gap-2">
                            <Input
                                id="csv-file"
                                type="file"
                                accept=".csv,.txt"
                                class="flex-1"
                                @change="onFileChange"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="downloadTemplate"
                            >
                                <Download class="mr-1 h-4 w-4" />
                                Template
                            </Button>
                        </div>
                        <p class="text-muted-foreground text-xs">
                            Cột duy nhất: <code>student_code</code>. Tối đa 1 000 dòng.
                        </p>
                    </div>

                    <!-- Submit -->
                    <Button type="submit" :disabled="isSubmitting" class="w-full">
                        <Upload class="mr-2 h-4 w-4" />
                        {{ isSubmitting ? 'Đang xử lý...' : 'Tạo Charges' }}
                    </Button>
                </form>
            </CardContent>
        </Card>

        <!-- Result summary — lost on refresh per D9 -->
        <Card v-if="result">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <CheckCircle2 class="h-5 w-5 text-green-600" />
                    Kết quả tạo charges
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap gap-3 text-sm">
                    <span class="rounded-md bg-green-50 px-3 py-1 font-medium text-green-700">
                        Tạo mới: {{ result.summary.created }}
                    </span>
                    <span class="rounded-md bg-yellow-50 px-3 py-1 font-medium text-yellow-700">
                        Bỏ qua: {{ result.summary.skipped }}
                    </span>
                    <span class="rounded-md bg-gray-50 px-3 py-1 text-gray-600">
                        Tổng: {{ result.summary.total }}
                    </span>
                </div>

                <div v-if="result.skipped.length > 0">
                    <p class="mb-2 flex items-center gap-1 text-sm font-medium text-yellow-700">
                        <AlertTriangle class="h-4 w-4" />
                        Danh sách bỏ qua
                    </p>
                    <div class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Mã sinh viên</TableHead>
                                    <TableHead>Lý do</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(row, i) in result.skipped" :key="i">
                                    <TableCell class="font-mono">{{ row.student_code }}</TableCell>
                                    <TableCell class="text-muted-foreground">{{ row.reason }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
