<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useStudentSearch } from '@/composables';
import { type ChargeType, type Semester, type StudentBasic } from '@/types/finance';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Search } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';



interface Props {
    chargeTypes: { value: string; label: string }[];
    semesters: Semester[];
    student?: StudentBasic;
}

const props = defineProps<Props>();

const form = useForm({
    student_id: props.student?.id ?? null,
    charge_type: '' as ChargeType | '',
    description: '',
    amount: null as number | null,
    semester_id: null as number | null,
    due_date: '',
    invoice_id: null as number | null,
});

// Student search
const {
    searchQuery: studentSearch,
    searchResults,
    isLoading: isSearching,
    reset: resetStudentSearch
} = useStudentSearch({ limit: 5 });

const selectedStudent = ref<StudentBasic | null>(props.student ?? null);
const selectedInvoiceId = ref<number | null>(null);

const selectStudent = (student: any) => {
    selectedStudent.value = student;
    form.student_id = student.id;
    resetStudentSearch();
};

const clearStudent = () => {
    selectedStudent.value = null;
    form.student_id = null;
    form.semester_id = null;
    selectedInvoiceId.value = null;
    form.invoice_id = null;
};


const handleSubmit = () => {
    form.post(route('finance.charges.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Khoản phí đã được tạo thành công');
        },
        onError: () => {
            toast.error('Có lỗi xảy ra khi tạo khoản phí');
        },
    });
};
</script>

<template>

    <Head title="Tạo khoản phí mới" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center gap-4">
            <Link :href="route('finance.charges.index')">
                <Button variant="outline" size="icon">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
            </Link>
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Tạo khoản phí mới</h1>
                <p class="text-muted-foreground mt-1">Thêm khoản phí hoặc tín dụng cho sinh viên</p>
            </div>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-6">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <!-- Student Selection -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Sinh viên</CardTitle>
                            <CardDescription>Chọn sinh viên cần tạo khoản phí</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="selectedStudent" class="flex items-center justify-between rounded-lg border p-4">
                                <div>
                                    <p class="font-medium">{{ selectedStudent.full_name }}</p>
                                    <p class="text-muted-foreground text-sm">{{ selectedStudent.student_id }} - {{
                                        selectedStudent.email }}</p>
                                </div>
                                <Button type="button" variant="outline" size="sm" @click="clearStudent">Thay
                                    đổi</Button>
                            </div>
                            <div v-else class="space-y-2">
                                <div class="relative">
                                    <Search
                                        class="text-muted-foreground absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                                    <Input v-model="studentSearch"
                                        placeholder="Tìm kiếm sinh viên theo tên, MSSV, email..." class="pl-10" />
                                </div>
                                <div v-if="searchResults.length > 0" class="rounded-lg border">
                                    <div v-for="student in searchResults" :key="student.id"
                                        class="cursor-pointer border-b p-3 last:border-b-0 hover:bg-muted"
                                        @click="selectStudent(student)">
                                        <p class="font-medium">{{ student.full_name }}</p>
                                        <p class="text-muted-foreground text-sm">{{ student.student_id }} - {{
                                            student.email }}</p>
                                    </div>
                                </div>
                                <p v-if="isSearching" class="text-muted-foreground text-sm">Đang tìm kiếm...</p>
                            </div>
                            <p v-if="form.errors.student_id" class="text-sm text-red-500">{{ form.errors.student_id }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Charge Details -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Chi tiết khoản phí</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="charge_type">Loại phí *</Label>
                                    <Select v-model="form.charge_type">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Chọn loại phí" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="type in chargeTypes" :key="type.value"
                                                :value="type.value">
                                                {{ type.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="form.errors.charge_type" class="text-sm text-red-500">{{
                                        form.errors.charge_type }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="semester_id">Học kỳ *</Label>
                                    <Select v-model="form.semester_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Chọn học kỳ" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="sem in semesters" :key="sem.id" :value="sem.id">
                                                {{ sem.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p v-if="form.errors.semester_id" class="text-sm text-red-500">{{
                                        form.errors.semester_id }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Mô tả *</Label>
                                <Textarea v-model="form.description" placeholder="Mô tả chi tiết về khoản phí..."
                                    rows="3" />
                                <p v-if="form.errors.description" class="text-sm text-red-500">{{
                                    form.errors.description }}</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <NumberField id="amount" :default-value="form.amount ?? 0"
                                        :model-value="form.amount ?? 0" @update:model-value="(v: number | null) => {
                                            if (v) { form.amount = v }
                                            else { form.amount = null }
                                        }" :format-options="{
                                            style: 'currency',
                                            currency: 'VND',
                                            currencyDisplay: 'code',
                                            currencySign: 'accounting',
                                        }">
                                        <Label for="amount">Số tiền (VNĐ) *</Label>
                                        <NumberFieldContent>
                                            <NumberFieldInput />
                                        </NumberFieldContent>
                                    </NumberField>

                                    <!-- <Label for="amount">Số tiền (VNĐ) *</Label> -->
                                    <!-- <Input v-model.number="form.amount" type="number" placeholder="Nhập số tiền" /> -->
                                    <p class="text-muted-foreground text-xs">Số dương = Phí, Số âm = Tín dụng/Hoàn tiền
                                    </p>
                                    <p v-if="form.errors.amount" class="text-sm text-red-500">{{ form.errors.amount }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="due_date">Ngày đến hạn</Label>
                                    <Input v-model="form.due_date" type="date" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Hành động</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Button type="submit" class="w-full"
                                :disabled="form.processing || !form.student_id || !form.charge_type || !form.semester_id">
                                Tạo khoản phí
                            </Button>
                            <Link :href="route('finance.charges.index')">
                                <Button type="button" variant="outline" class="w-full">Hủy bỏ</Button>
                            </Link>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Hướng dẫn</CardTitle>
                        </CardHeader>
                        <CardContent class="text-muted-foreground space-y-2 text-sm">
                            <p>• Số tiền <strong>dương</strong>: Khoản phí sinh viên cần đóng</p>
                            <p>• Số tiền <strong>âm</strong>: Tín dụng/hoàn tiền cho sinh viên</p>
                            <p>• Mọi khoản phí sẽ được gán vào hóa đơn của học kỳ</p>
                            <p>• Nếu chưa có hóa đơn, hệ thống sẽ tự động tạo mới</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    </div>
</template>
