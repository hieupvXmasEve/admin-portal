<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList, ComboboxTrigger, ComboboxViewport } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useAddressData } from '@/composables/useAddressData';
import { capitalizeFirst } from '@/utils/string';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronsUpDown } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface CodeOption {
    id: number;
    code: string;
    name: string;
}

interface Props {
    campuses: CodeOption[];
    programs: CodeOption[];
    semesters: CodeOption[];
}

defineProps<Props>();

// Subject codes mirror CrmApplicationMapper's school-report / national-exam sets.
const SCHOOL_REPORT_SUBJECTS: { code: string; label: string }[] = [
    { code: 'toan', label: 'Toán' },
    { code: 'ly', label: 'Vật lý' },
    { code: 'hoa', label: 'Hóa học' },
    { code: 'sinh', label: 'Sinh học' },
    { code: 'tin_hoc', label: 'Tin học' },
    { code: 'van', label: 'Ngữ văn' },
    { code: 'lich_su', label: 'Lịch sử' },
    { code: 'dia_ly', label: 'Địa lý' },
    { code: 'tieng_anh', label: 'Tiếng Anh' },
    { code: 'giao_duc_cong_dan', label: 'GDCD' },
    { code: 'giao_duc_quoc_phong', label: 'GDQP' },
    { code: 'cong_nghe', label: 'Công nghệ' },
    { code: 'kt_pl', label: 'Kinh tế & Pháp luật' },
];

const NATIONAL_EXAM_SUBJECTS: { code: string; label: string }[] = [
    { code: 'thithpt_toan', label: 'Toán (THPT)' },
    { code: 'thithpt_van', label: 'Văn (THPT)' },
    { code: 'thithpt_option1', label: 'Tự chọn 1' },
    { code: 'thithpt_option2', label: 'Tự chọn 2' },
];

const blankScores = (subjects: { code: string }[]): Record<string, string> =>
    Object.fromEntries(subjects.map((s) => [s.code, '']));

// Mirrors the CRM sync payload (CrmApplicationMapper): every top-level column a
// sync would populate is editable here. Required set matches the prior form;
// everything else is optional.
const form = useForm({
    // Applicant identity
    full_name: '',
    student_code: '',
    gender: '' as '' | 'male' | 'female' | 'other',
    ethnicity: '',
    birth_day: '',
    birth_month: '',
    birth_year: '',
    birth_place: '',
    nationality: '',
    religion: '',
    national_id: '',
    id_card_place_of_issue: '',
    // Contact
    email: '',
    phone: '',
    // Address — current address is structured (street + ward + province selects);
    // permanent (CCCD) address stays freeform. `address` is composed on submit.
    permanent_address: '',
    new_province: '',
    new_street: '',
    new_ward: '',
    // Admission intent
    campus_code: '',
    intended_program: '',
    intake: '',
    crm_campus: '',
    crm_major: '',
    scholarship: '',
    pathway_gateway: '',
    uu_dai_gc: '',
    is_international_applicant: false,
    registration_form: false,
    crm_paid_amount: '',
    // Academic background
    school: '',
    graduation_year: '',
    gpa: '',
    gpa_type: '',
    // English test
    english_test_type: '',
    exam_date: '',
    listening: '',
    reading: '',
    writing: '',
    speaking: '',
    overall: '',
    english_qualifications: '',
    study_link_status: '',
    sut_id: '',
    // Other
    health_information: '',
    exception_units: '',
    // Subject scores (relation, not columns) — keyed by subject_code.
    school_scores: blankScores(SCHOOL_REPORT_SUBJECTS),
    exam_scores: blankScores(NATIONAL_EXAM_SUBJECTS),
});

// Vietnamese province/ward datasets (post-merger), same source as Students/Edit.
const { provinces, loadAll, getWardsByProvince } = useAddressData();
const provinceSearch = ref('');
const wardSearch = ref('');

const filteredProvinces = computed(() =>
    provinceSearch.value
        ? provinces.value.filter((p) => p.name.toLowerCase().includes(provinceSearch.value.toLowerCase()))
        : provinces.value,
);

const wards = computed(() => {
    const list = getWardsByProvince.value(form.new_province);
    return wardSearch.value ? list.filter((w) => w.name.toLowerCase().includes(wardSearch.value.toLowerCase())) : list;
});

// Reset ward when province changes.
watch(
    () => form.new_province,
    () => {
        form.new_ward = '';
        wardSearch.value = '';
    },
);

onMounted(() => loadAll());

const goBack = () => window.history.back();

const submit = () => {
    // Empty strings → null so nullable rules pass; subject scores fold into the
    // CRM-shaped academic_scores list (only the ones actually entered).
    form.transform((data) => {
        const { school_scores, exam_scores, ...rest } = data;
        const out: Record<string, unknown> = {};
        for (const [key, value] of Object.entries(rest)) {
            out[key] = value === '' ? null : value;
        }
        const scores: { subject_code: string; score: string; source: string }[] = [];
        for (const [code, value] of Object.entries(school_scores)) {
            if (value !== '') scores.push({ subject_code: code, score: value, source: 'school_report' });
        }
        for (const [code, value] of Object.entries(exam_scores)) {
            if (value !== '') scores.push({ subject_code: code, score: value, source: 'national_exam' });
        }
        out.academic_scores = scores;
        // Compose the freeform `address` column from the structured current-address
        // parts so it (and the student record on convert) carries a full address.
        const addressParts = [rest.new_street, rest.new_ward, rest.new_province].filter((v) => v !== '' && v != null);
        out.address = addressParts.length > 0 ? addressParts.join(', ') : null;
        return out;
    }).post(route('student-applications.store'), {
        // No onSuccess toast: the controller server-flashes the success message,
        // rendered once by useFlashToast (AppLayout). A second toast here would
        // double it. onError stays — validation 422s carry no server flash.
        onError: () => toast.error('Vui lòng kiểm tra lại các trường được đánh dấu.'),
    });
};
</script>

<template>
    <Head title="Hồ sơ tuyển sinh mới" />

    <div class="mx-auto max-w-3xl">
        <Button variant="ghost" class="mb-4" @click="goBack">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back
        </Button>

        <div class="mb-6">
            <h1 class="text-3xl font-bold tracking-tight">Hồ sơ tuyển sinh mới</h1>
            <p class="text-muted-foreground mt-1 text-sm">Nhập thủ công theo đúng các trường đồng bộ CRM. Hồ sơ khởi tạo ở trạng thái <strong>chờ duyệt</strong> cho tới khi được duyệt.</p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle>Thông tin ứng viên</CardTitle>
                    <CardDescription>Nhân thân và mã sinh viên do CRM cấp</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for="full_name">Họ và tên *</Label>
                        <Input id="full_name" v-model="form.full_name" placeholder="Họ và tên" />
                        <p v-if="form.errors.full_name" class="text-destructive text-xs">{{ form.errors.full_name }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="student_code">Mã sinh viên *</Label>
                        <Input id="student_code" v-model="form.student_code" placeholder="e.g. S1234567" />
                        <p v-if="form.errors.student_code" class="text-destructive text-xs">{{ form.errors.student_code }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="gender">Giới tính</Label>
                        <Select v-model="form.gender">
                            <SelectTrigger id="gender">
                                <SelectValue placeholder="Chọn giới tính" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="male">Nam</SelectItem>
                                <SelectItem value="female">Nữ</SelectItem>
                                <SelectItem value="other">Khác</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1">
                        <Label for="ethnicity">Dân tộc</Label>
                        <Input id="ethnicity" v-model="form.ethnicity" placeholder="Dân tộc" />
                        <p v-if="form.errors.ethnicity" class="text-destructive text-xs">{{ form.errors.ethnicity }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 sm:col-span-2">
                        <div class="space-y-1">
                            <Label for="birth_day">Ngày sinh</Label>
                            <Input id="birth_day" v-model="form.birth_day" type="number" min="1" max="31" placeholder="DD" />
                            <p v-if="form.errors.birth_day" class="text-destructive text-xs">{{ form.errors.birth_day }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label for="birth_month">Tháng sinh</Label>
                            <Input id="birth_month" v-model="form.birth_month" type="number" min="1" max="12" placeholder="MM" />
                            <p v-if="form.errors.birth_month" class="text-destructive text-xs">{{ form.errors.birth_month }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label for="birth_year">Năm sinh</Label>
                            <Input id="birth_year" v-model="form.birth_year" type="number" min="1900" placeholder="YYYY" />
                            <p v-if="form.errors.birth_year" class="text-destructive text-xs">{{ form.errors.birth_year }}</p>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <Label for="birth_place">Nơi sinh</Label>
                        <Input id="birth_place" v-model="form.birth_place" placeholder="Nơi sinh" />
                        <p v-if="form.errors.birth_place" class="text-destructive text-xs">{{ form.errors.birth_place }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="nationality">Quốc tịch</Label>
                        <Input id="nationality" v-model="form.nationality" placeholder="Quốc tịch" />
                        <p v-if="form.errors.nationality" class="text-destructive text-xs">{{ form.errors.nationality }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="religion">Tôn giáo</Label>
                        <Input id="religion" v-model="form.religion" placeholder="Tôn giáo" />
                        <p v-if="form.errors.religion" class="text-destructive text-xs">{{ form.errors.religion }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="national_id">Số CCCD/CMND</Label>
                        <Input id="national_id" v-model="form.national_id" placeholder="Số CCCD/CMND" />
                        <p v-if="form.errors.national_id" class="text-destructive text-xs">{{ form.errors.national_id }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="id_card_place_of_issue">Nơi cấp CCCD</Label>
                        <Input id="id_card_place_of_issue" v-model="form.id_card_place_of_issue" placeholder="Nơi cấp" />
                        <p v-if="form.errors.id_card_place_of_issue" class="text-destructive text-xs">{{ form.errors.id_card_place_of_issue }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Liên hệ</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for="email">Email *</Label>
                        <Input id="email" v-model="form.email" type="email" placeholder="Email" />
                        <p v-if="form.errors.email" class="text-destructive text-xs">{{ form.errors.email }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="phone">Số điện thoại</Label>
                        <Input id="phone" v-model="form.phone" placeholder="Số điện thoại" />
                        <p v-if="form.errors.phone" class="text-destructive text-xs">{{ form.errors.phone }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Địa chỉ</CardTitle>
                    <CardDescription>
                        <strong>Địa chỉ hiện tại</strong> (số nhà, phường/xã, tỉnh/thành phố) được ghi vào hồ sơ sinh viên khi duyệt.
                        Địa chỉ thường trú theo CCCD chỉ lưu trên hồ sơ tuyển sinh.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <p class="mb-2 text-sm font-medium">Địa chỉ hiện tại</p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="space-y-1">
                                <Label for="new_street">Số nhà, tên đường</Label>
                                <Input id="new_street" v-model="form.new_street" placeholder="Số nhà, tên đường" />
                                <p v-if="form.errors.new_street" class="text-destructive text-xs">{{ form.errors.new_street }}</p>
                            </div>
                            <div class="space-y-1">
                                <Label>Tỉnh/Thành phố</Label>
                                <Combobox v-model="form.new_province" v-model:search-term="provinceSearch">
                                    <ComboboxAnchor>
                                        <div class="relative w-full items-center">
                                            <ComboboxInput v-model="provinceSearch" placeholder="Tìm tỉnh/thành phố..." :display-value="(value) => capitalizeFirst(value) || ''" />
                                            <ComboboxTrigger class="absolute inset-y-0 end-0 flex items-center justify-center px-3">
                                                <ChevronsUpDown class="text-muted-foreground size-4" />
                                            </ComboboxTrigger>
                                        </div>
                                    </ComboboxAnchor>
                                    <ComboboxList class="max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-y-auto">
                                        <ComboboxViewport>
                                            <ComboboxEmpty v-if="filteredProvinces.length === 0">Không tìm thấy tỉnh/thành phố</ComboboxEmpty>
                                            <ComboboxItem v-for="province in filteredProvinces" :key="province.code" :value="province.name" class="cursor-pointer">
                                                {{ capitalizeFirst(province.name) }}
                                            </ComboboxItem>
                                        </ComboboxViewport>
                                    </ComboboxList>
                                </Combobox>
                                <p v-if="form.errors.new_province" class="text-destructive text-xs">{{ form.errors.new_province }}</p>
                            </div>
                            <div class="space-y-1">
                                <Label>Phường/Xã</Label>
                                <Combobox v-model="form.new_ward" v-model:search-term="wardSearch" :disabled="!form.new_province">
                                    <ComboboxAnchor>
                                        <div class="relative w-full items-center">
                                            <ComboboxInput v-model="wardSearch" placeholder="Tìm phường/xã..." :display-value="(value) => capitalizeFirst(value) || ''" />
                                            <ComboboxTrigger class="absolute inset-y-0 end-0 flex items-center justify-center px-3">
                                                <ChevronsUpDown class="text-muted-foreground size-4" />
                                            </ComboboxTrigger>
                                        </div>
                                    </ComboboxAnchor>
                                    <ComboboxList class="max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-y-auto">
                                        <ComboboxViewport>
                                            <ComboboxEmpty v-if="wards.length === 0">Không tìm thấy phường/xã</ComboboxEmpty>
                                            <ComboboxItem v-for="ward in wards" :key="ward.code" :value="ward.name" class="cursor-pointer">
                                                {{ capitalizeFirst(ward.name) }}
                                            </ComboboxItem>
                                        </ComboboxViewport>
                                    </ComboboxList>
                                </Combobox>
                                <p v-if="form.errors.new_ward" class="text-destructive text-xs">{{ form.errors.new_ward }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <Label for="permanent_address">Địa chỉ thường trú (theo CCCD)</Label>
                        <Textarea id="permanent_address" v-model="form.permanent_address" rows="2" placeholder="Địa chỉ thường trú" />
                        <p v-if="form.errors.permanent_address" class="text-destructive text-xs">{{ form.errors.permanent_address }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Nguyện vọng tuyển sinh</CardTitle>
                    <CardDescription>Ánh xạ nội bộ kèm text cơ sở/ngành gốc từ CRM</CardDescription>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for="campus_code">Cơ sở *</Label>
                        <Select v-model="form.campus_code">
                            <SelectTrigger id="campus_code">
                                <SelectValue placeholder="Chọn cơ sở" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="campus in campuses" :key="campus.code" :value="campus.code"> {{ campus.name }} ({{ campus.code }}) </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.campus_code" class="text-destructive text-xs">{{ form.errors.campus_code }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="intended_program">Ngành đăng ký *</Label>
                        <Select v-model="form.intended_program">
                            <SelectTrigger id="intended_program">
                                <SelectValue placeholder="Chọn ngành" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="program in programs" :key="program.code" :value="program.code"> {{ program.name }} ({{ program.code }}) </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.intended_program" class="text-destructive text-xs">{{ form.errors.intended_program }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="intake">Kỳ nhập học *</Label>
                        <Select v-model="form.intake">
                            <SelectTrigger id="intake">
                                <SelectValue placeholder="Chọn kỳ nhập học" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="semester in semesters" :key="semester.code" :value="semester.code"> {{ semester.name }} ({{ semester.code }}) </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.intake" class="text-destructive text-xs">{{ form.errors.intake }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="crm_campus">Cơ sở (CRM, gốc)</Label>
                        <Input id="crm_campus" v-model="form.crm_campus" placeholder="Text cơ sở từ CRM" />
                        <p v-if="form.errors.crm_campus" class="text-destructive text-xs">{{ form.errors.crm_campus }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="crm_major">Ngành (CRM, gốc)</Label>
                        <Input id="crm_major" v-model="form.crm_major" placeholder="Text ngành từ CRM" />
                        <p v-if="form.errors.crm_major" class="text-destructive text-xs">{{ form.errors.crm_major }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="scholarship">Học bổng</Label>
                        <Input id="scholarship" v-model="form.scholarship" placeholder="Học bổng" />
                        <p v-if="form.errors.scholarship" class="text-destructive text-xs">{{ form.errors.scholarship }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="pathway_gateway">Lộ trình / gateway</Label>
                        <Input id="pathway_gateway" v-model="form.pathway_gateway" placeholder="Lộ trình / gateway" />
                        <p v-if="form.errors.pathway_gateway" class="text-destructive text-xs">{{ form.errors.pathway_gateway }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="uu_dai_gc">Ưu đãi GC</Label>
                        <Input id="uu_dai_gc" v-model="form.uu_dai_gc" placeholder="Ưu đãi GC" />
                        <p v-if="form.errors.uu_dai_gc" class="text-destructive text-xs">{{ form.errors.uu_dai_gc }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="crm_paid_amount">Số tiền đã đóng (CRM)</Label>
                        <Input id="crm_paid_amount" v-model="form.crm_paid_amount" type="number" step="0.01" min="0" placeholder="0.00" />
                        <p v-if="form.errors.crm_paid_amount" class="text-destructive text-xs">{{ form.errors.crm_paid_amount }}</p>
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input id="is_international_applicant" v-model="form.is_international_applicant" type="checkbox" class="border-input h-4 w-4 rounded" />
                        <Label for="is_international_applicant">Ứng viên quốc tế</Label>
                    </div>
                    <div class="flex items-center gap-2 sm:pt-6">
                        <input id="registration_form" v-model="form.registration_form" type="checkbox" class="border-input h-4 w-4 rounded" />
                        <Label for="registration_form">Đã nhận phiếu đăng ký</Label>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Học vấn</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for="school">Trường THPT</Label>
                        <Input id="school" v-model="form.school" placeholder="Trường THPT" />
                        <p v-if="form.errors.school" class="text-destructive text-xs">{{ form.errors.school }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="graduation_year">Năm tốt nghiệp</Label>
                        <Input id="graduation_year" v-model="form.graduation_year" placeholder="e.g. 2025" />
                        <p v-if="form.errors.graduation_year" class="text-destructive text-xs">{{ form.errors.graduation_year }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="gpa">GPA</Label>
                        <Input id="gpa" v-model="form.gpa" type="number" step="0.01" min="0" placeholder="GPA" />
                        <p v-if="form.errors.gpa" class="text-destructive text-xs">{{ form.errors.gpa }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="gpa_type">Thang điểm GPA</Label>
                        <Input id="gpa_type" v-model="form.gpa_type" placeholder="e.g. 10, 4.0" />
                        <p v-if="form.errors.gpa_type" class="text-destructive text-xs">{{ form.errors.gpa_type }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Chứng chỉ tiếng Anh</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <Label for="english_test_type">Loại chứng chỉ</Label>
                        <Input id="english_test_type" v-model="form.english_test_type" placeholder="e.g. IELTS" />
                        <p v-if="form.errors.english_test_type" class="text-destructive text-xs">{{ form.errors.english_test_type }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="exam_date">Ngày thi</Label>
                        <Input id="exam_date" v-model="form.exam_date" type="date" />
                        <p v-if="form.errors.exam_date" class="text-destructive text-xs">{{ form.errors.exam_date }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="listening">Nghe</Label>
                        <Input id="listening" v-model="form.listening" type="number" step="0.01" min="0" placeholder="0.0" />
                        <p v-if="form.errors.listening" class="text-destructive text-xs">{{ form.errors.listening }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="reading">Đọc</Label>
                        <Input id="reading" v-model="form.reading" type="number" step="0.01" min="0" placeholder="0.0" />
                        <p v-if="form.errors.reading" class="text-destructive text-xs">{{ form.errors.reading }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="writing">Viết</Label>
                        <Input id="writing" v-model="form.writing" type="number" step="0.01" min="0" placeholder="0.0" />
                        <p v-if="form.errors.writing" class="text-destructive text-xs">{{ form.errors.writing }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="speaking">Nói</Label>
                        <Input id="speaking" v-model="form.speaking" type="number" step="0.01" min="0" placeholder="0.0" />
                        <p v-if="form.errors.speaking" class="text-destructive text-xs">{{ form.errors.speaking }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="overall">Tổng</Label>
                        <Input id="overall" v-model="form.overall" type="number" step="0.01" min="0" placeholder="0.0" />
                        <p v-if="form.errors.overall" class="text-destructive text-xs">{{ form.errors.overall }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="english_qualifications">Bằng cấp tiếng Anh</Label>
                        <Input id="english_qualifications" v-model="form.english_qualifications" placeholder="Bằng cấp tiếng Anh" />
                        <p v-if="form.errors.english_qualifications" class="text-destructive text-xs">{{ form.errors.english_qualifications }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="study_link_status">Trạng thái StudyLink</Label>
                        <Input id="study_link_status" v-model="form.study_link_status" placeholder="Trạng thái StudyLink" />
                        <p v-if="form.errors.study_link_status" class="text-destructive text-xs">{{ form.errors.study_link_status }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="sut_id">SUT ID</Label>
                        <Input id="sut_id" v-model="form.sut_id" placeholder="SUT ID" />
                        <p v-if="form.errors.sut_id" class="text-destructive text-xs">{{ form.errors.sut_id }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Điểm các môn cấp 3</CardTitle>
                    <CardDescription>Bỏ trống môn nếu không có điểm</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div>
                        <p class="mb-2 text-sm font-medium">Điểm học bạ</p>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                            <div v-for="subject in SCHOOL_REPORT_SUBJECTS" :key="subject.code" class="space-y-1">
                                <Label :for="`school_${subject.code}`">{{ subject.label }}</Label>
                                <Input
                                    :id="`school_${subject.code}`"
                                    v-model="form.school_scores[subject.code]"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="—"
                                />
                            </div>
                        </div>
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-medium">Điểm thi THPT</p>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                            <div v-for="subject in NATIONAL_EXAM_SUBJECTS" :key="subject.code" class="space-y-1">
                                <Label :for="`exam_${subject.code}`">{{ subject.label }}</Label>
                                <Input
                                    :id="`exam_${subject.code}`"
                                    v-model="form.exam_scores[subject.code]"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="—"
                                />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Khác</CardTitle>
                </CardHeader>
                <CardContent class="grid grid-cols-1 gap-4">
                    <div class="space-y-1">
                        <Label for="health_information">Thông tin sức khỏe</Label>
                        <Textarea id="health_information" v-model="form.health_information" rows="2" placeholder="Thông tin sức khỏe" />
                        <p v-if="form.errors.health_information" class="text-destructive text-xs">{{ form.errors.health_information }}</p>
                    </div>
                    <div class="space-y-1">
                        <Label for="exception_units">Miễn giảm học phần</Label>
                        <Textarea id="exception_units" v-model="form.exception_units" rows="2" placeholder="Miễn giảm học phần" />
                        <p v-if="form.errors.exception_units" class="text-destructive text-xs">{{ form.errors.exception_units }}</p>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-2">
                <Button type="button" variant="outline" @click="goBack">Hủy</Button>
                <Button type="submit" :disabled="form.processing">Tạo hồ sơ</Button>
            </div>
        </form>
    </div>
</template>
