<script setup lang="ts">
import StudentAvatar from '@/components/ui/avatar/StudentAvatar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList, ComboboxTrigger, ComboboxViewport } from '@/components/ui/combobox';
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useAddressData } from '@/composables/useAddressData';
import type { Campus, CurriculumVersion, Program, Specialization, Student } from '@/types/models';
import { relationshipOptions } from '@/types/student';
import { ValidationRules } from '@/types/validation';
import { studentRoutes } from '@/utils/routes';
import { capitalizeFirst } from '@/utils/string';
import { Head, router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { ChevronsUpDown, GraduationCap, Mail, Phone, Save, User, Users, X } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { z } from 'zod';

interface Props {
    student: Student;
    campuses: Campus[];
    programs: (Program & { specializations?: Specialization[] })[];
    curriculumVersions: CurriculumVersion[];
    errors: Record<string, string[] | undefined>;
}

const props = defineProps<Props>();

// Load address data
const { ethnicities, provinces, loadAll, getWardsByProvince } = useAddressData();

// Search terms for comboboxes
const ethnicitySearch = ref('');
const currentProvinceSearch = ref('');
const currentWardSearch = ref('');
const cccdProvinceSearch = ref('');
const cccdWardSearch = ref('');

// Form validation schema
const formSchema = toTypedSchema(
    z.object({
        full_name: z.string().min(ValidationRules.student.firstName.minLength, 'First name is required').max(ValidationRules.student.firstName.maxLength, 'First name is too long'),
        email: z.string().email('Invalid email format').max(ValidationRules.student.email.maxLength, 'Email is too long'),
        phone: z.string().max(ValidationRules.student.phone.maxLength, 'Phone number is too long').optional(),
        avatar_url: z.string().url('Invalid URL format').optional().or(z.literal('')),
        date_of_birth: z.string().optional(),
        gender: z.enum(['male', 'female', 'other']).optional(),
        nationality: z.string().max(ValidationRules.student.nationality.maxLength, 'Nationality is too long').optional(),
        ethnicity: z.string().max(100, 'Ethnicity is too long').optional(),
        national_id: z.string().max(ValidationRules.student.nationalId.maxLength, 'National ID is too long').optional(),
        current_address_line: z.string().max(255, 'Current address line is too long').optional(),
        current_ward: z.string().max(100, 'Current ward is too long').optional(),
        current_province: z.string().max(100, 'Current province is too long').optional(),
        current_country: z.string().max(30, 'Current country is too long').optional(),
        cccd_address_line: z.string().max(255, 'CCCD address line is too long').optional(),
        cccd_ward: z.string().max(100, 'CCCD ward is too long').optional(),
        cccd_province: z.string().max(100, 'CCCD province is too long').optional(),
        cccd_country: z.string().max(100, 'CCCD country is too long').optional(),
        emergency_contact_name: z.string().max(ValidationRules.student.emergencyContactName.maxLength, 'Emergency contact name is too long').optional(),
        emergency_contact_phone: z.string().max(ValidationRules.student.emergencyContactPhone.maxLength, 'Emergency contact phone is too long').optional(),
        emergency_contact_email: z.string().optional(),
        emergency_contact_relationship: z.string().max(100, 'Emergency contact relationship is too long').optional(),
        emergency_contact_name_1: z.string().max(ValidationRules.student.emergencyContactName.maxLength, 'Emergency contact name is too long').optional(),
        emergency_contact_phone_1: z.string().max(ValidationRules.student.emergencyContactPhone.maxLength, 'Emergency contact phone is too long').optional(),
        emergency_contact_email_1: z.string().optional(),
        emergency_contact_relationship_1: z.string().max(100, 'Emergency contact relationship is too long').optional(),
        high_school_name: z.string().max(ValidationRules.student.highSchoolName.maxLength, 'High school name is too long').optional(),
        high_school_graduation_year: z.string().optional(),
        entrance_exam_score: z.string().optional(),
        admission_notes: z.string().optional(),
        parent_name: z.string().max(255, 'Parent name is too long').optional(),
        parent_email: z.string().email('Invalid email format').max(255, 'Parent email is too long').optional(),
    }),
);

const { handleSubmit, isSubmitting, setFieldValue, values } = useForm({
    validationSchema: formSchema,
    initialValues: {
        full_name: props.student.full_name,
        email: props.student.email,
        phone: props.student.phone || '',
        avatar_url: props.student.avatar_url || '',
        date_of_birth: props.student.date_of_birth || '',
        gender: props.student.gender,
        nationality: props.student.nationality || '',
        ethnicity: props.student.ethnicity || '',
        national_id: props.student.national_id || '',
        current_address_line: props.student.current_address_line || '',
        current_ward: props.student.current_ward || '',
        current_province: props.student.current_province || '',
        current_country: props.student.current_country || '',
        cccd_address_line: props.student.cccd_address_line || '',
        cccd_ward: props.student.cccd_ward || '',
        cccd_province: props.student.cccd_province || '',
        cccd_country: props.student.cccd_country || '',
        emergency_contact_name: props.student.emergency_contact_name || '',
        emergency_contact_phone: props.student.emergency_contact_phone || '',
        emergency_contact_email: props.student.emergency_contact_email || '',
        emergency_contact_relationship: props.student.emergency_contact_relationship || '',
        high_school_name: props.student.high_school_name || '',
        high_school_graduation_year: props.student.high_school_graduation_year?.toString() || '',
        entrance_exam_score: props.student.entrance_exam_score?.toString() || '',
        admission_notes: props.student.admission_notes || '',
        parent_name: props.student.parent_user?.name || '',
        parent_email: props.student.parent_user?.email || '',
    },
});

// Computed filtered provinces for current address
const filteredCurrentProvinces = computed(() => {
    if (!currentProvinceSearch.value) return provinces.value;
    return provinces.value.filter((province) => province.name.toLowerCase().includes(currentProvinceSearch.value.toLowerCase()));
});

// Computed filtered provinces for CCCD address
const filteredCccdProvinces = computed(() => {
    if (!cccdProvinceSearch.value) return provinces.value;
    return provinces.value.filter((province) => province.name.toLowerCase().includes(cccdProvinceSearch.value.toLowerCase()));
});

// Computed filtered ethnicities
const filteredEthnicities = computed(() => {
    if (!ethnicitySearch.value) return ethnicities.value;
    return ethnicities.value.filter((ethnicity) => ethnicity.toLowerCase().includes(ethnicitySearch.value.toLowerCase()));
});

// Computed filtered wards for current address
const currentWards = computed(() => {
    const province = values.current_province;
    const wards = getWardsByProvince.value(province);
    if (!currentWardSearch.value) return wards;
    return wards.filter((ward) => ward.name.toLowerCase().includes(currentWardSearch.value.toLowerCase()));
});

// Computed filtered wards for CCCD address
const cccdWards = computed(() => {
    const province = values.cccd_province;
    const wards = getWardsByProvince.value(province);
    if (!cccdWardSearch.value) return wards;
    return wards.filter((ward) => ward.name.toLowerCase().includes(cccdWardSearch.value.toLowerCase()));
});

// Watch province changes to reset ward
watch(
    () => values.current_province,
    () => {
        setFieldValue('current_ward', '');
        currentWardSearch.value = '';
    },
);

watch(
    () => values.cccd_province,
    () => {
        setFieldValue('cccd_ward', '');
        cccdWardSearch.value = '';
    },
);

// Load data on mount
onMounted(() => {
    loadAll();
});

// Computed specializations filtered by selected program (same as Create.vue)
// const filteredSpecializations = computed(() => {
//     if (!values.program_id) return [];
//     const selectedProgram = props.programs.find((p) => p.id.toString() === values.program_id);
//     return selectedProgram?.specializations || [];
// });

watch(
    () => props.errors,
    (newErrors: Record<string, string[] | undefined>) => {
        if (newErrors.parent_email) {
            console.log('newErrors.parent_email', newErrors.parent_email);
            toast.error(newErrors.parent_email || 'Failed to update student');
        }
    },
);

// Watch for specialization changes to fetch curriculum versions (same as Create.vue)
// watch(
//     () => values.specialization_id,
//     (newSpecializationId, oldSpecializationId) => {
//         if (newSpecializationId !== oldSpecializationId) {
//             setFieldValue('curriculum_version_id', '');
//             fetchCurriculumVersions();
//         }
//     },
// );

const onSubmit = handleSubmit((formData) => {
    // Convert string values back to appropriate types
    // Only include fields that are displayed on UI and allowed to be updated
    const submitData = {
        full_name: formData.full_name,
        email: formData.email,
        phone: formData.phone || null,
        avatar_url: formData.avatar_url || null,
        date_of_birth: formData.date_of_birth || null,
        gender: formData.gender || null,
        nationality: formData.nationality || null,
        ethnicity: formData.ethnicity || null,
        national_id: formData.national_id || null,
        current_address_line: formData.current_address_line || null,
        current_ward: formData.current_ward || null,
        current_province: formData.current_province || null,
        current_country: formData.current_country || null,
        cccd_address_line: formData.cccd_address_line || null,
        cccd_ward: formData.cccd_ward || null,
        cccd_province: formData.cccd_province || null,
        cccd_country: formData.cccd_country || null,
        emergency_contact_name: formData.emergency_contact_name || null,
        emergency_contact_email: formData.emergency_contact_email || null,
        emergency_contact_phone: formData.emergency_contact_phone || null,
        emergency_contact_relationship: formData.emergency_contact_relationship || null,
        emergency_contact_name_1: formData.emergency_contact_name_1 || null,
        emergency_contact_email_1: formData.emergency_contact_email_1 || null,
        emergency_contact_phone_1: formData.emergency_contact_phone_1 || null,
        emergency_contact_relationship_1: formData.emergency_contact_relationship_1 || null,
        high_school_name: formData.high_school_name || null,
        high_school_graduation_year: formData.high_school_graduation_year ? parseInt(formData.high_school_graduation_year) : null,
        entrance_exam_score: formData.entrance_exam_score ? parseFloat(formData.entrance_exam_score) : null,
        admission_notes: formData.admission_notes || null,
        parent_name: formData.parent_name || null,
        parent_email: formData.parent_email || null,
    };
    router.put(route('students.update', props.student.id), submitData, {
        onSuccess: () => {
            toast.success('Student updated successfully');
        },
        onError: (errors) => {
            console.error('Validation errors:', errors);
        },
    });
});

const handleCancel = () => {
    router.visit(studentRoutes.studentAcademicSummary(props.student.id));
};

// Photo/Avatar handling
const selectedPhoto = ref<File | null>(null);
const photoPreviewUrl = ref<string | null>(null);
const uploadedAvatarUrl = ref<string | null>(null);
const showSuccessMessage = ref(false);

const handlePhotoSelected = (file: File, dataUrl: string) => {
    selectedPhoto.value = file;
    photoPreviewUrl.value = dataUrl;
};

const handleAvatarUploaded = (avatarData: any) => {
    // Update the form field with the new avatar URL
    setFieldValue('avatar_url', avatarData.url);

    // Update preview URL for display
    uploadedAvatarUrl.value = avatarData.url;
    photoPreviewUrl.value = avatarData.url;

    // Show success message
    showSuccessMessage.value = true;
    setTimeout(() => {
        showSuccessMessage.value = false;
    }, 5000); // Hide after 5 seconds
};
</script>

<template>
    <Head title="Edit Student" />
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="bg-primary/10 flex h-16 w-16 items-center justify-center rounded-full">
                <User class="text-primary h-8 w-8" />
            </div>
            <div>
                <h1 class="text-3xl font-bold">Edit Student - {{ student.student_id }}</h1>
                <p class="text-muted-foreground text-lg">{{ student.full_name }}</p>
            </div>
        </div>
    </div>

    <!-- Success Message for Avatar Upload -->
    <div v-if="showSuccessMessage" class="mb-6">
        <Card class="border-green-200 bg-green-50">
            <CardContent class="pt-4">
                <div class="flex items-center gap-2 text-green-800">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">Avatar uploaded successfully!</span>
                    <span class="text-green-600">Click "Update Student" to save the new avatar to the student record.</span>
                </div>
            </CardContent>
        </Card>
    </div>

    <form @submit="onSubmit" class="space-y-6">
        <!-- Personal Information -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <User class="h-5 w-5" />
                    Personal Information
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Avatar Section -->
                <div class="mb-6">
                    <StudentAvatar :student-id="student.id" :current-avatar="photoPreviewUrl || student.avatar_url" size="xl" @photo-selected="handlePhotoSelected" @avatar-uploaded="handleAvatarUploaded" />
                </div>

                <!-- Basic Information -->
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700">Basic Information</h3>

                    <FormField v-slot="{ componentField }" name="full_name">
                        <FormItem>
                            <FormLabel>Full Name *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="Enter full name" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ componentField }" name="email">
                            <FormItem>
                                <FormLabel class="flex items-center gap-2">
                                    <Mail class="h-4 w-4" />
                                    Email *
                                </FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="email" placeholder="Enter email address" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="phone">
                            <FormItem>
                                <FormLabel class="flex items-center gap-2">
                                    <Phone class="h-4 w-4" />
                                    Phone
                                </FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter phone number" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ componentField }" name="date_of_birth">
                            <FormItem>
                                <FormLabel>Date of Birth</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" type="date" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="gender">
                            <FormItem>
                                <FormLabel>Gender</FormLabel>
                                <Select v-bind="componentField">
                                    <FormControl>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select gender" />
                                        </SelectTrigger>
                                    </FormControl>
                                    <SelectContent>
                                        <SelectItem value="male">Male</SelectItem>
                                        <SelectItem value="female">Female</SelectItem>
                                        <SelectItem value="other">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </div>

                <!-- Identity  -->
                <div class="space-y-4 border-t pt-4">
                    <h3 class="text-sm font-semibold text-gray-700">Identity</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ componentField }" name="nationality">
                            <FormItem>
                                <FormLabel>Nationality</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter nationality" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="ethnicity">
                            <FormItem>
                                <FormLabel>Ethnicity (Dân tộc)</FormLabel>
                                <FormControl>
                                    <Combobox v-bind="componentField" v-model:search-term="ethnicitySearch">
                                        <ComboboxAnchor>
                                            <div class="relative w-full items-center">
                                                <ComboboxInput v-model="ethnicitySearch" placeholder="Search ethnicity..." :display-value="(value) => capitalizeFirst(value) || ''" />
                                                <ComboboxTrigger class="absolute inset-y-0 end-0 flex items-center justify-center px-3">
                                                    <ChevronsUpDown class="text-muted-foreground size-4" />
                                                </ComboboxTrigger>
                                            </div>
                                        </ComboboxAnchor>
                                        <ComboboxList class="max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-y-auto">
                                            <ComboboxViewport>
                                                <ComboboxEmpty v-if="filteredEthnicities.length === 0">No ethnicity found</ComboboxEmpty>
                                                <ComboboxItem v-for="ethnicity in filteredEthnicities" :key="ethnicity" :value="ethnicity" class="cursor-pointer">
                                                    {{ capitalizeFirst(ethnicity) }}
                                                </ComboboxItem>
                                            </ComboboxViewport>
                                        </ComboboxList>
                                    </Combobox>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="national_id">
                            <FormItem>
                                <FormLabel>National ID</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter national ID" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </div>

                <!-- Current Address Information -->
                <div class="space-y-4 border-t pt-4">
                    <h3 class="text-sm font-semibold text-gray-700">Current Address</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ componentField }" name="current_address_line">
                            <FormItem>
                                <FormLabel>Address Line (Số nhà, tên đường)</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter address line" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="current_province">
                            <FormItem>
                                <FormLabel>Province (Tỉnh / Thành phố)</FormLabel>
                                <FormControl>
                                    <Combobox v-bind="componentField" v-model:search-term="currentProvinceSearch">
                                        <ComboboxAnchor>
                                            <div class="relative w-full items-center">
                                                <ComboboxInput v-model="currentProvinceSearch" placeholder="Search province..." :display-value="(value) => capitalizeFirst(value) || ''" />
                                                <ComboboxTrigger class="absolute inset-y-0 end-0 flex items-center justify-center px-3">
                                                    <ChevronsUpDown class="text-muted-foreground size-4" />
                                                </ComboboxTrigger>
                                            </div>
                                        </ComboboxAnchor>
                                        <ComboboxList class="max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-y-auto">
                                            <ComboboxViewport>
                                                <ComboboxEmpty v-if="filteredCurrentProvinces.length === 0">No province found</ComboboxEmpty>
                                                <ComboboxItem v-for="province in filteredCurrentProvinces" :key="province.code" :value="province.name" class="cursor-pointer">
                                                    {{ capitalizeFirst(province.name) }}
                                                </ComboboxItem>
                                            </ComboboxViewport>
                                        </ComboboxList>
                                    </Combobox>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="current_ward">
                            <FormItem>
                                <FormLabel>Ward (Xã / Phường)</FormLabel>
                                <FormControl>
                                    <Combobox v-bind="componentField" v-model:search-term="currentWardSearch" :disabled="!values.current_province">
                                        <ComboboxAnchor>
                                            <div class="relative w-full items-center">
                                                <ComboboxInput v-model="currentWardSearch" placeholder="Search ward..." :display-value="(value) => capitalizeFirst(value) || ''" />
                                                <ComboboxTrigger class="absolute inset-y-0 end-0 flex items-center justify-center px-3">
                                                    <ChevronsUpDown class="text-muted-foreground size-4" />
                                                </ComboboxTrigger>
                                            </div>
                                        </ComboboxAnchor>
                                        <ComboboxList class="max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-y-auto">
                                            <ComboboxViewport>
                                                <ComboboxEmpty v-if="currentWards.length === 0">No ward found</ComboboxEmpty>
                                                <ComboboxItem v-for="ward in currentWards" :key="ward.code" :value="ward.name" class="cursor-pointer">
                                                    {{ capitalizeFirst(ward.name) }}
                                                </ComboboxItem>
                                            </ComboboxViewport>
                                        </ComboboxList>
                                    </Combobox>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="current_country">
                            <FormItem>
                                <FormLabel>Country</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter country" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </div>

                <!-- CCCD Address Information -->
                <div class="space-y-4 border-t pt-4">
                    <h3 class="text-sm font-semibold text-gray-700">CCCD Address</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField v-slot="{ componentField }" name="cccd_address_line">
                            <FormItem>
                                <FormLabel>Address Line (Số nhà, tên đường)</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter CCCD address line" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="cccd_province">
                            <FormItem>
                                <FormLabel>Province (Tỉnh / Thành phố)</FormLabel>
                                <FormControl>
                                    <Combobox v-bind="componentField" v-model:search-term="cccdProvinceSearch">
                                        <ComboboxAnchor>
                                            <div class="relative w-full items-center">
                                                <ComboboxInput v-model="cccdProvinceSearch" placeholder="Search province..." :display-value="(value) => capitalizeFirst(value) || ''" />
                                                <ComboboxTrigger class="absolute inset-y-0 end-0 flex items-center justify-center px-3">
                                                    <ChevronsUpDown class="text-muted-foreground size-4" />
                                                </ComboboxTrigger>
                                            </div>
                                        </ComboboxAnchor>
                                        <ComboboxList class="max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-y-auto">
                                            <ComboboxViewport>
                                                <ComboboxEmpty v-if="filteredCccdProvinces.length === 0">No province found</ComboboxEmpty>
                                                <ComboboxItem v-for="province in filteredCccdProvinces" :key="province.code" :value="province.name" class="cursor-pointer">
                                                    {{ capitalizeFirst(province.name) }}
                                                </ComboboxItem>
                                            </ComboboxViewport>
                                        </ComboboxList>
                                    </Combobox>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="cccd_ward">
                            <FormItem>
                                <FormLabel>Ward (Xã / Phường)</FormLabel>
                                <FormControl>
                                    <Combobox v-bind="componentField" v-model:search-term="cccdWardSearch" :disabled="!values.cccd_province">
                                        <ComboboxAnchor>
                                            <div class="relative w-full items-center">
                                                <ComboboxInput v-model="cccdWardSearch" placeholder="Search ward..." :display-value="(value) => capitalizeFirst(value) || ''" />
                                                <ComboboxTrigger class="absolute inset-y-0 end-0 flex items-center justify-center px-3">
                                                    <ChevronsUpDown class="text-muted-foreground size-4" />
                                                </ComboboxTrigger>
                                            </div>
                                        </ComboboxAnchor>
                                        <ComboboxList class="max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-y-auto">
                                            <ComboboxViewport>
                                                <ComboboxEmpty v-if="cccdWards.length === 0">No ward found</ComboboxEmpty>
                                                <ComboboxItem v-for="ward in cccdWards" :key="ward.code" :value="ward.name" class="cursor-pointer">
                                                    {{ capitalizeFirst(ward.name) }}
                                                </ComboboxItem>
                                            </ComboboxViewport>
                                        </ComboboxList>
                                    </Combobox>
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="cccd_country">
                            <FormItem>
                                <FormLabel>Country</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter CCCD country" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Emergency Contact Information -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Phone class="h-5 w-5" />
                    Emergency Contact
                </CardTitle>
            </CardHeader>
            <CardContent class="">
                <div class="grid grid-cols-1 gap-4 space-y-4 md:grid-cols-2">
                    <div class="gap-2 space-y-4">
                        <FormField v-slot="{ componentField }" name="emergency_contact_name">
                            <FormItem>
                                <FormLabel>Contact Name</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter emergency contact name" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="emergency_contact_phone">
                            <FormItem>
                                <FormLabel>Contact Phone</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter emergency contact phone" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="emergency_contact_email">
                            <FormItem>
                                <FormLabel>Contact Email</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter emergency contact email" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="emergency_contact_relationship">
                            <FormItem>
                                <FormLabel>Relationship</FormLabel>
                                <Select v-bind="componentField">
                                    <FormControl>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select relationship" />
                                        </SelectTrigger>
                                    </FormControl>
                                    <SelectContent>
                                        <SelectItem v-for="version in relationshipOptions" :key="version.value" :value="version.value">
                                            {{ version.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                    <div class="gap-2 space-y-4">
                        <FormField v-slot="{ componentField }" name="emergency_contact_name_1">
                            <FormItem>
                                <FormLabel>Contact Name 2</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter emergency contact name 1" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="emergency_contact_phone_1">
                            <FormItem>
                                <FormLabel>Contact Phone 2</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter emergency contact phone 1" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="emergency_contact_email_1">
                            <FormItem>
                                <FormLabel>Contact Email 2</FormLabel>
                                <FormControl>
                                    <Input v-bind="componentField" placeholder="Enter emergency contact email 1" />
                                </FormControl>
                                <FormMessage />
                            </FormItem>
                        </FormField>

                        <FormField v-slot="{ componentField }" name="emergency_contact_relationship_1">
                            <FormItem>
                                <FormLabel>Relationship 2</FormLabel>
                                <Select v-bind="componentField">
                                    <FormControl>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select relationship" />
                                        </SelectTrigger>
                                    </FormControl>
                                    <SelectContent>
                                        <SelectItem v-for="version in relationshipOptions" :key="version.value" :value="version.value">
                                            {{ version.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Parent User Information -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Users class="h-5 w-5" />
                    Parent Login Information
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="parent_name">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <User class="h-4 w-4" />
                                Parent Name
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="Enter parent name" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="parent_email">
                        <FormItem>
                            <FormLabel class="flex items-center gap-2">
                                <Mail class="h-4 w-4" />
                                Parent Email
                            </FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="email" placeholder="Enter parent email" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>
            </CardContent>
        </Card>

        <!-- Academic Background -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <GraduationCap class="h-5 w-5" />
                    Academic Background
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <FormField v-slot="{ componentField }" name="high_school_name">
                    <FormItem>
                        <FormLabel>High School Name</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" placeholder="Enter high school name" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField v-slot="{ componentField }" name="high_school_graduation_year">
                        <FormItem>
                            <FormLabel>Graduation Year</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="number" placeholder="Enter graduation year" min="1900" :max="new Date().getFullYear() + 1" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField v-slot="{ componentField }" name="entrance_exam_score">
                        <FormItem>
                            <FormLabel>Entrance Exam Score</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" type="number" placeholder="Enter exam score" min="0" max="100" step="0.01" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <FormField v-slot="{ componentField }" name="admission_notes">
                    <FormItem>
                        <FormLabel>Admission Notes</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" placeholder="Enter any additional notes" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </CardContent>
        </Card>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-4">
            <Button type="button" variant="outline" @click="handleCancel">
                <X class="mr-2 h-4 w-4" />
                Cancel
            </Button>
            <Button type="submit" :disabled="isSubmitting">
                <Save class="mr-2 h-4 w-4" />
                Update Student
            </Button>
        </div>
    </form>
</template>
