<script setup lang="ts">
import StudentAvatar from '@/components/ui/avatar/StudentAvatar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxList, ComboboxTrigger, ComboboxViewport } from '@/components/ui/combobox';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useAddressData } from '@/composables/useAddressData';
import type { Campus, CurriculumVersion, Program, Specialization, Student } from '@/types/models';
import { relationshipOptions } from '@/types/student';
import { studentRoutes } from '@/utils/routes';
import { capitalizeFirst } from '@/utils/string';
import { Head, useForm } from '@inertiajs/vue3';
import { ChevronsUpDown, GraduationCap, Mail, Phone, Save, User, Users, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface Props {
    student: Student;
    campuses: Campus[];
    programs: (Program & { specializations?: Specialization[] })[];
    curriculumVersions: CurriculumVersion[];
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

const form = useForm({
    full_name: props.student.full_name,
    email: props.student.email,
    phone: props.student.phone || '',
    avatar_url: props.student.avatar_url || '',
    date_of_birth: props.student.date_of_birth || '',
    gender: props.student.gender || '',
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
    emergency_contact_name_1: props.student.emergency_contact_name_1 || '',
    emergency_contact_phone_1: props.student.emergency_contact_phone_1 || '',
    emergency_contact_email_1: props.student.emergency_contact_email_1 || '',
    emergency_contact_relationship_1: props.student.emergency_contact_relationship_1 || '',
    high_school_name: props.student.high_school_name || '',
    high_school_graduation_year: props.student.high_school_graduation_year?.toString() || '',
    entrance_exam_score: props.student.entrance_exam_score?.toString() || '',
    admission_notes: props.student.admission_notes || '',
    parent_name: props.student.parent_user?.name || '',
    parent_email: props.student.parent_user?.email || '',
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
    const wards = getWardsByProvince.value(form.current_province);
    if (!currentWardSearch.value) return wards;
    return wards.filter((ward) => ward.name.toLowerCase().includes(currentWardSearch.value.toLowerCase()));
});

// Computed filtered wards for CCCD address
const cccdWards = computed(() => {
    const wards = getWardsByProvince.value(form.cccd_province);
    if (!cccdWardSearch.value) return wards;
    return wards.filter((ward) => ward.name.toLowerCase().includes(cccdWardSearch.value.toLowerCase()));
});

// Watch province changes to reset ward
watch(
    () => form.current_province,
    () => {
        form.current_ward = '';
        currentWardSearch.value = '';
    },
);

watch(
    () => form.cccd_province,
    () => {
        form.cccd_ward = '';
        cccdWardSearch.value = '';
    },
);

// Load data on mount
onMounted(() => {
    loadAll();
});

// Computed specializations filtered by selected program (same as Create.vue)
// const filteredSpecializations = computed(() => {
//     if (!form.program_id) return [];
//     const selectedProgram = props.programs.find((p) => p.id.toString() === form.program_id);
//     return selectedProgram?.specializations || [];
// });

const onSubmit = () => {
    form.put(route('students.update', props.student.id), {
        onSuccess: () => form.defaults(),
    });
};

const handleCancel = () => {
    history.back();
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

    <form @submit.prevent="onSubmit" class="space-y-6">
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

                    <div class="grid gap-1.5">
                        <Label for="full_name">Full Name *</Label>
                        <Input id="full_name" v-model="form.full_name" placeholder="Enter full name" :class="{ 'border-destructive': form.errors.full_name }" />
                        <InputError :message="form.errors.full_name" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="email" class="flex items-center gap-2">
                                <Mail class="h-4 w-4" />
                                Email *
                            </Label>
                            <Input id="email" v-model="form.email" type="email" placeholder="Enter email address" :class="{ 'border-destructive': form.errors.email }" />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="phone" class="flex items-center gap-2">
                                <Phone class="h-4 w-4" />
                                Phone
                            </Label>
                            <Input id="phone" v-model="form.phone" placeholder="Enter phone number" :class="{ 'border-destructive': form.errors.phone }" />
                            <InputError :message="form.errors.phone" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="date_of_birth">Date of Birth</Label>
                            <Input id="date_of_birth" v-model="form.date_of_birth" type="date" :class="{ 'border-destructive': form.errors.date_of_birth }" />
                            <InputError :message="form.errors.date_of_birth" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="gender">Gender</Label>
                            <Select v-model="form.gender">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.gender }">
                                    <SelectValue placeholder="Select gender" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="male">Male</SelectItem>
                                    <SelectItem value="female">Female</SelectItem>
                                    <SelectItem value="other">Other</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.gender" />
                        </div>
                    </div>
                </div>

                <!-- Identity  -->
                <div class="space-y-4 border-t pt-4">
                    <h3 class="text-sm font-semibold text-gray-700">Identity</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="nationality">Nationality</Label>
                            <Input id="nationality" v-model="form.nationality" placeholder="Enter nationality" :class="{ 'border-destructive': form.errors.nationality }" />
                            <InputError :message="form.errors.nationality" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label>Ethnicity (Dân tộc)</Label>
                            <Combobox v-model="form.ethnicity" v-model:search-term="ethnicitySearch">
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
                            <InputError :message="form.errors.ethnicity" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="national_id">National ID</Label>
                            <Input id="national_id" v-model="form.national_id" placeholder="Enter national ID" :class="{ 'border-destructive': form.errors.national_id }" />
                            <InputError :message="form.errors.national_id" />
                        </div>
                    </div>
                </div>

                <!-- Current Address Information -->
                <div class="space-y-4 border-t pt-4">
                    <h3 class="text-sm font-semibold text-gray-700">Current Address</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="current_address_line">Address Line (Số nhà, tên đường)</Label>
                            <Input id="current_address_line" v-model="form.current_address_line" placeholder="Enter address line" :class="{ 'border-destructive': form.errors.current_address_line }" />
                            <InputError :message="form.errors.current_address_line" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label>Province (Tỉnh / Thành phố)</Label>
                            <Combobox v-model="form.current_province" v-model:search-term="currentProvinceSearch">
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
                            <InputError :message="form.errors.current_province" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label>Ward (Xã / Phường)</Label>
                            <Combobox v-model="form.current_ward" v-model:search-term="currentWardSearch" :disabled="!form.current_province">
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
                            <InputError :message="form.errors.current_ward" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="current_country">Country</Label>
                            <Input id="current_country" v-model="form.current_country" placeholder="Enter country" :class="{ 'border-destructive': form.errors.current_country }" />
                            <InputError :message="form.errors.current_country" />
                        </div>
                    </div>
                </div>

                <!-- CCCD Address Information -->
                <div class="space-y-4 border-t pt-4">
                    <h3 class="text-sm font-semibold text-gray-700">CCCD Address</h3>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="cccd_address_line">Address Line (Số nhà, tên đường)</Label>
                            <Input id="cccd_address_line" v-model="form.cccd_address_line" placeholder="Enter CCCD address line" :class="{ 'border-destructive': form.errors.cccd_address_line }" />
                            <InputError :message="form.errors.cccd_address_line" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label>Province (Tỉnh / Thành phố)</Label>
                            <Combobox v-model="form.cccd_province" v-model:search-term="cccdProvinceSearch">
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
                            <InputError :message="form.errors.cccd_province" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label>Ward (Xã / Phường)</Label>
                            <Combobox v-model="form.cccd_ward" v-model:search-term="cccdWardSearch" :disabled="!form.cccd_province">
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
                            <InputError :message="form.errors.cccd_ward" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="cccd_country">Country</Label>
                            <Input id="cccd_country" v-model="form.cccd_country" placeholder="Enter CCCD country" :class="{ 'border-destructive': form.errors.cccd_country }" />
                            <InputError :message="form.errors.cccd_country" />
                        </div>
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
                        <div class="grid gap-1.5">
                            <Label for="emergency_contact_name">Contact Name</Label>
                            <Input id="emergency_contact_name" v-model="form.emergency_contact_name" placeholder="Enter emergency contact name" :class="{ 'border-destructive': form.errors.emergency_contact_name }" />
                            <InputError :message="form.errors.emergency_contact_name" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="emergency_contact_phone">Contact Phone</Label>
                            <Input id="emergency_contact_phone" v-model="form.emergency_contact_phone" placeholder="Enter emergency contact phone" :class="{ 'border-destructive': form.errors.emergency_contact_phone }" />
                            <InputError :message="form.errors.emergency_contact_phone" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="emergency_contact_email">Contact Email</Label>
                            <Input id="emergency_contact_email" v-model="form.emergency_contact_email" placeholder="Enter emergency contact email" :class="{ 'border-destructive': form.errors.emergency_contact_email }" />
                            <InputError :message="form.errors.emergency_contact_email" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label>Relationship</Label>
                            <Select v-model="form.emergency_contact_relationship">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.emergency_contact_relationship }">
                                    <SelectValue placeholder="Select relationship" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in relationshipOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.emergency_contact_relationship" />
                        </div>
                    </div>
                    <div class="gap-2 space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="emergency_contact_name_1">Contact Name 2</Label>
                            <Input id="emergency_contact_name_1" v-model="form.emergency_contact_name_1" placeholder="Enter emergency contact name 2" :class="{ 'border-destructive': form.errors.emergency_contact_name_1 }" />
                            <InputError :message="form.errors.emergency_contact_name_1" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="emergency_contact_phone_1">Contact Phone 2</Label>
                            <Input id="emergency_contact_phone_1" v-model="form.emergency_contact_phone_1" placeholder="Enter emergency contact phone 2" :class="{ 'border-destructive': form.errors.emergency_contact_phone_1 }" />
                            <InputError :message="form.errors.emergency_contact_phone_1" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="emergency_contact_email_1">Contact Email 2</Label>
                            <Input id="emergency_contact_email_1" v-model="form.emergency_contact_email_1" placeholder="Enter emergency contact email 2" :class="{ 'border-destructive': form.errors.emergency_contact_email_1 }" />
                            <InputError :message="form.errors.emergency_contact_email_1" />
                        </div>

                        <div class="grid gap-1.5">
                            <Label>Relationship 2</Label>
                            <Select v-model="form.emergency_contact_relationship_1">
                                <SelectTrigger :class="{ 'border-destructive': form.errors.emergency_contact_relationship_1 }">
                                    <SelectValue placeholder="Select relationship" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="option in relationshipOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.emergency_contact_relationship_1" />
                        </div>
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
                    <div class="grid gap-1.5">
                        <Label for="parent_name" class="flex items-center gap-2">
                            <User class="h-4 w-4" />
                            Parent Name
                        </Label>
                        <Input id="parent_name" v-model="form.parent_name" placeholder="Enter parent name" :class="{ 'border-destructive': form.errors.parent_name }" />
                        <InputError :message="form.errors.parent_name" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="parent_email" class="flex items-center gap-2">
                            <Mail class="h-4 w-4" />
                            Parent Email
                        </Label>
                        <Input id="parent_email" v-model="form.parent_email" type="email" placeholder="Enter parent email" :class="{ 'border-destructive': form.errors.parent_email }" />
                        <InputError :message="form.errors.parent_email" />
                    </div>
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
                <div class="grid gap-1.5">
                    <Label for="high_school_name">High School Name</Label>
                    <Input id="high_school_name" v-model="form.high_school_name" placeholder="Enter high school name" :class="{ 'border-destructive': form.errors.high_school_name }" />
                    <InputError :message="form.errors.high_school_name" />
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="high_school_graduation_year">Graduation Year</Label>
                        <Input id="high_school_graduation_year" v-model="form.high_school_graduation_year" type="number" placeholder="Enter graduation year" min="1900" :max="new Date().getFullYear() + 1" :class="{ 'border-destructive': form.errors.high_school_graduation_year }" />
                        <InputError :message="form.errors.high_school_graduation_year" />
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="entrance_exam_score">Entrance Exam Score</Label>
                        <Input id="entrance_exam_score" v-model="form.entrance_exam_score" type="number" placeholder="Enter exam score" min="0" max="100" step="0.01" :class="{ 'border-destructive': form.errors.entrance_exam_score }" />
                        <InputError :message="form.errors.entrance_exam_score" />
                    </div>
                </div>

                <div class="grid gap-1.5">
                    <Label for="admission_notes">Admission Notes</Label>
                    <Textarea id="admission_notes" v-model="form.admission_notes" placeholder="Enter any additional notes" :class="{ 'border-destructive': form.errors.admission_notes }" />
                    <InputError :message="form.errors.admission_notes" />
                </div>
            </CardContent>
        </Card>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-4">
            <Button type="button" variant="outline" @click="handleCancel">
                <X class="mr-2 h-4 w-4" />
                Cancel
            </Button>
            <Button type="submit" :disabled="form.processing">
                <Save class="mr-2 h-4 w-4" />
                {{ form.processing ? 'Saving...' : 'Update Student' }}
            </Button>
        </div>
    </form>
</template>
