<script setup lang="ts">
import FileUpload from '@/components/imports/FileUpload.vue';
import ImportPreview from '@/components/imports/ImportPreview.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, Download, FileSpreadsheet, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { route } from 'ziggy-js';



// defineProps<Props>();

// State
const currentStep = ref<'upload' | 'preview' | 'results'>('upload');
const selectedFile = ref<File | null>(null);
const previewData = ref<any>(null);
const importResults = ref<any>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);

// Column mapping state
const columnMapping = ref<Record<string, string>>({});
const availableColumns = ref<string[]>([]);

// Available database fields for mapping
const databaseFields = [
    { value: 'student_id', label: 'Student ID' },
    { value: 'scholarship_code', label: 'Scholarship Code' },
    { value: 'voucher_codes', label: 'Voucher Codes' },
    { value: 'paid_amount', label: 'Paid Amount' },
    { value: 'payment_date', label: 'Payment Date' },
    { value: 'notes', label: 'Notes' },
];

// Computed
const canProceedToImport = computed(() => {
    if (!previewData.value) return false;
    
    // columnMapping has Excel column names as keys and DB field names as values
    // e.g., { "Student ID": "student_id", "Scholarship Code": "scholarship_code" }
    const mappedFields = Object.values(columnMapping.value);
    
    const hasStudentId = mappedFields.includes('student_id');
    const hasScholarshipOrPayment = mappedFields.includes('scholarship_code') || mappedFields.includes('paid_amount');
    
    return hasStudentId && hasScholarshipOrPayment;
});

const validationSummary = computed(() => {
    return previewData.value?.validation_summary || null;
});

// Methods
const handleFileSelected = async (file: File) => {
    selectedFile.value = file;
    error.value = null;

    await previewImport();
};

const handleFileClear = () => {
    selectedFile.value = null;
    previewData.value = null;
    columnMapping.value = {};
    availableColumns.value = [];
    currentStep.value = 'upload';
    error.value = null;
};

const previewImport = async () => {
    if (!selectedFile.value) return;

    isLoading.value = true;
    error.value = null;

    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);
        formData.append('preview_rows', '10');

        const response = await fetch(route('student-scholarships.imports.preview'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        const result = await response.json();

        if (result.success) {
            previewData.value = result.data;
            availableColumns.value = result.data.detected_columns;

            // Auto-map columns based on detected mapping
            if (result.data.column_mapping) {
                columnMapping.value = result.data.column_mapping;
            }

            currentStep.value = 'preview';
        } else {
            error.value = result.error || 'Failed to preview import';
        }
    } catch (err) {
        error.value = 'Network error occurred while previewing import';
        console.error('Preview error:', err);
    } finally {
        isLoading.value = false;
    }
};

const processImport = async () => {
    if (!selectedFile.value || !canProceedToImport.value) return;

    isLoading.value = true;
    error.value = null;

    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);
        
        // Send column mapping as individual form fields
        Object.entries(columnMapping.value).forEach(([excelColumn, dbField]) => {
            formData.append(`column_mapping[${excelColumn}]`, dbField as string);
        });

        const response = await fetch(route('student-scholarships.imports.import'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            const text = await response.text();
            throw new Error(`Server returned ${response.status}: ${text.substring(0, 200)}`);
        }

        const result = await response.json();

        if (result.success) {
            importResults.value = result.data;
            currentStep.value = 'results';
        } else {
            error.value = result.error || 'Failed to process import';
        }
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Network error occurred while processing import';
        console.error('Import error:', err);
    } finally {
        isLoading.value = false;
    }
};

const downloadTemplate = () => {
    window.location.href = route('student-scholarships.imports.template');
};

const startNewImport = () => {
    selectedFile.value = null;
    previewData.value = null;
    importResults.value = null;
    columnMapping.value = {};
    availableColumns.value = [];
    currentStep.value = 'upload';
    error.value = null;
};

const goBackToPreview = () => {
    currentStep.value = 'preview';
    importResults.value = null;
};
</script>

<template>
    <Head title="Student Financial Import" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="route('student-scholarships.index')">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Student Financial Import</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Import student financial data including scholarships, payments, and vouchers
                    </p>
                </div>
            </div>
            <Button @click="downloadTemplate" variant="outline" class="flex items-center space-x-2">
                <Download class="h-4 w-4" />
                <span>Download Template</span>
            </Button>
        </div>

        <!-- Permission Check -->
<!--        <div class="rounded-md bg-yellow-50 p-4">-->
<!--            <div class="flex">-->
<!--                <AlertCircle class="h-5 w-5 text-yellow-400" />-->
<!--                <div class="ml-3">-->
<!--                    <h3 class="text-sm font-medium text-yellow-800">Access Restricted</h3>-->
<!--                    <div class="mt-2 text-sm text-yellow-700">-->
<!--                        You don't have permission to import student financial data. Please contact your administrator.-->
<!--                    </div>-->
<!--                </div>-->
<!--            </div>-->
<!--        </div>-->

        <!-- Main Content -->
        <div class="space-y-6">
            <!-- Step 1: File Upload -->
            <Card v-if="currentStep === 'upload'">
                <CardHeader>
                    <CardTitle class="flex items-center space-x-2">
                        <FileSpreadsheet class="h-5 w-5" />
                        <span>Step 1: Upload File</span>
                    </CardTitle>
                    <CardDescription>
                        Select an Excel or CSV file containing student financial data
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <FileUpload
                        :loading="isLoading"
                        :allowed-extensions="['xlsx', 'xls', 'csv']"
                        max-file-size="10MB"
                        @file-selected="handleFileSelected"
                        @file-cleared="handleFileClear"
                    />

                    <!-- Error Display -->
                    <div v-if="error" class="mt-4 rounded-md bg-red-50 p-4">
                        <div class="flex">
                            <AlertCircle class="h-5 w-5 text-red-400" />
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Upload Error</h3>
                                <div class="mt-2 text-sm text-red-700">{{ error }}</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Step 2: Preview and Column Mapping -->
            <Card v-if="currentStep === 'preview'">
                <CardHeader>
                    <CardTitle class="flex items-center space-x-2">
                        <Upload class="h-5 w-5" />
                        <span>Step 2: Review and Map Columns</span>
                    </CardTitle>
                    <CardDescription>
                        Review the detected data and map columns to the correct fields
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- File Info -->
                    <div v-if="previewData?.file_info" class="rounded-lg bg-gray-50 p-4">
                        <h4 class="font-medium text-gray-900">File Information</h4>
                        <div class="mt-2 grid grid-cols-2 gap-4 text-sm text-gray-600">
                            <div>
                                <span class="font-medium">Name:</span> {{ previewData.file_info.name }}
                            </div>
                            <div>
                                <span class="font-medium">Size:</span> {{ Math.round(previewData.file_info.size / 1024) }} KB
                            </div>
                            <div>
                                <span class="font-medium">Total Rows:</span> {{ previewData.total_rows }}
                            </div>
                            <div v-if="validationSummary">
                                <span class="font-medium">Valid Rows:</span>
                                <span :class="validationSummary.valid_rows > 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ validationSummary.valid_rows }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Column Mapping -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-900">Column Mapping</h4>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div v-for="column in availableColumns" :key="column" class="space-y-2">
                                <Label :for="`mapping-${column}`" class="text-sm font-medium">
                                    {{ column }}
                                </Label>
                                <Select v-model="columnMapping[column]">
                                    <SelectTrigger :id="`mapping-${column}`">
                                        <SelectValue placeholder="Select field..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">-- Skip Column --</SelectItem>
                                        <SelectItem
                                            v-for="field in databaseFields"
                                            :key="field.value"
                                            :value="field.value"
                                        >
                                            {{ field.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    <!-- Validation Summary -->
                    <div v-if="validationSummary" class="rounded-lg border p-4">
                        <h4 class="font-medium text-gray-900">Validation Summary</h4>
                        <div class="mt-2 grid grid-cols-2 gap-4 text-sm">
                            <div class="flex items-center space-x-2">
                                <div class="h-3 w-3 rounded-full bg-green-500"></div>
                                <span>Valid: {{ validationSummary.valid_rows }}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="h-3 w-3 rounded-full bg-red-500"></div>
                                <span>Invalid: {{ validationSummary.invalid_rows }}</span>
                            </div>
                        </div>
                        <div v-if="validationSummary.warnings?.length > 0" class="mt-3">
                            <p class="text-sm font-medium text-yellow-800">Warnings:</p>
                            <ul class="mt-1 list-inside list-disc text-sm text-yellow-700">
                                <li v-for="warning in validationSummary.warnings.slice(0, 3)" :key="warning">
                                    {{ warning }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Preview Data -->
                    <ImportPreview
                        v-if="previewData?.preview_data"
                        :preview-data="previewData.preview_data"
                        :validation-summary="previewData.validation_summary"
                        :total-rows="previewData.total_rows"
                    />

                    <!-- Action Buttons -->
                    <div class="flex justify-between">
                        <Button @click="handleFileClear" variant="outline">
                            Back to Upload
                        </Button>
                        <Button
                            @click="processImport"
                            :disabled="!canProceedToImport || isLoading"
                            class="flex items-center space-x-2"
                        >
                            <Upload class="h-4 w-4" />
                            <span>{{ isLoading ? 'Processing...' : 'Import Data' }}</span>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Step 3: Results -->
            <Card v-if="currentStep === 'results'">
                <CardHeader>
                    <CardTitle>Import Results</CardTitle>
                    <CardDescription>
                        Review the import results and any errors that occurred
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- Custom Results for Student Financial Import -->
                    <div v-if="importResults" class="space-y-6">
                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div class="rounded-lg bg-blue-50 p-4 text-center">
                                <FileSpreadsheet class="mx-auto mb-2 h-8 w-8 text-blue-600" />
                                <p class="text-sm font-medium text-blue-900">Total Rows</p>
                                <p class="text-2xl font-bold text-blue-600">{{ importResults.total_rows || 0 }}</p>
                            </div>

                            <div class="rounded-lg bg-green-50 p-4 text-center">
                                <Upload class="mx-auto mb-2 h-8 w-8 text-green-600" />
                                <p class="text-sm font-medium text-green-900">Successful</p>
                                <p class="text-2xl font-bold text-green-600">{{ importResults.successful || 0 }}</p>
                            </div>

                            <div class="rounded-lg bg-yellow-50 p-4 text-center">
                                <AlertCircle class="mx-auto mb-2 h-8 w-8 text-yellow-600" />
                                <p class="text-sm font-medium text-yellow-900">Scholarships</p>
                                <p class="text-2xl font-bold text-yellow-600">{{ importResults.scholarships_assigned || 0 }}</p>
                            </div>

                            <div class="rounded-lg bg-purple-50 p-4 text-center">
                                <Download class="mx-auto mb-2 h-8 w-8 text-purple-600" />
                                <p class="text-sm font-medium text-purple-900">Payments</p>
                                <p class="text-2xl font-bold text-purple-600">{{ importResults.payments_processed || 0 }}</p>
                            </div>
                        </div>

                        <!-- Processing Time -->
                        <div v-if="importResults.execution_time" class="text-center text-sm text-gray-500">
                            Completed in {{ importResults.execution_time }}
                        </div>

                        <!-- Errors -->
                        <div v-if="importResults.errors?.length > 0" class="rounded-lg bg-red-50 p-4">
                            <h4 class="font-medium text-red-800 mb-3">Errors ({{ importResults.errors.length }})</h4>
                            <div class="space-y-2 max-h-60 overflow-y-auto">
                                <div
                                    v-for="(error, index) in importResults.errors"
                                    :key="index"
                                    class="text-sm text-red-700 p-2 bg-white rounded border border-red-200"
                                >
                                    {{ error }}
                                </div>
                            </div>
                        </div>

                        <!-- Warnings -->
                        <div v-if="importResults.warnings?.length > 0" class="rounded-lg bg-yellow-50 p-4">
                            <h4 class="font-medium text-yellow-800 mb-3">Warnings ({{ importResults.warnings.length }})</h4>
                            <div class="space-y-2 max-h-60 overflow-y-auto">
                                <div
                                    v-for="(warning, index) in importResults.warnings"
                                    :key="index"
                                    class="text-sm text-yellow-700 p-2 bg-white rounded border border-yellow-200"
                                >
                                    {{ warning }}
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex justify-between">
                            <Button @click="goBackToPreview" variant="outline">
                                Back to Preview
                            </Button>
                            <Button @click="startNewImport" class="flex items-center space-x-2">
                                <Upload class="h-4 w-4" />
                                <span>Import Another File</span>
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
