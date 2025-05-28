<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Upload, Download, FileSpreadsheet, AlertCircle, CheckCircle, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps<{
    maxFileSize: string;
    allowedExtensions: string[];
    availableFormats: Record<string, string>;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/users',
    },
    {
        title: 'Import Users',
        href: '/users/import',
    },
];

// State management
const selectedFile = ref<File | null>(null);
const uploadProgress = ref(0);
const isUploading = ref(false);
const isProcessing = ref(false);
const uploadedFilePath = ref('');
const previewData = ref<any>(null);
const importResult = ref<any>(null);
const duplicateHandling = ref('update');
const createMissingCampuses = ref(false);
const error = ref('');
const success = ref('');

// File selection
const fileInput = ref<HTMLInputElement>();

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        selectedFile.value = target.files[0];
        error.value = '';
        previewData.value = null;
        importResult.value = null;
    }
};

const handleDrop = (event: DragEvent) => {
    event.preventDefault();
    if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
        selectedFile.value = event.dataTransfer.files[0];
        error.value = '';
        previewData.value = null;
        importResult.value = null;
    }
};

const handleDragOver = (event: DragEvent) => {
    event.preventDefault();
};

// File validation
const isValidFile = computed(() => {
    if (!selectedFile.value) return false;

    const extension = selectedFile.value.name.split('.').pop()?.toLowerCase();
    return props.allowedExtensions.includes(extension || '');
});

const fileSizeValid = computed(() => {
    if (!selectedFile.value) return true;

    const maxSizeBytes = convertToBytes(props.maxFileSize);
    return selectedFile.value.size <= maxSizeBytes;
});

const convertToBytes = (size: string): number => {
    const unit = size.slice(-2).toUpperCase();
    const value = parseInt(size.slice(0, -2));

    switch (unit) {
        case 'KB':
            return value * 1024;
        case 'MB':
            return value * 1024 * 1024;
        case 'GB':
            return value * 1024 * 1024 * 1024;
        default:
            return parseInt(size);
    }
};

// File upload
const uploadFile = async () => {
    if (!selectedFile.value || !isValidFile.value || !fileSizeValid.value) {
        error.value = 'Please select a valid file';
        return;
    }

    isUploading.value = true;
    uploadProgress.value = 0;
    error.value = '';

    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);
        formData.append('duplicate_handling', duplicateHandling.value);

        const response = await axios.post('/users/import/upload', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
            onUploadProgress: (progressEvent) => {
                if (progressEvent.total) {
                    uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                }
            },
        });

        if (response.data.success) {
            uploadedFilePath.value = response.data.file_path;
            previewData.value = response.data.preview;
            success.value = 'File uploaded successfully. Review the preview below.';
        } else {
            error.value = response.data.error || 'Upload failed';
        }
    } catch (err: any) {
        error.value = err.response?.data?.error || 'Upload failed';
    } finally {
        isUploading.value = false;
        uploadProgress.value = 0;
    }
};

// Process import
const processImport = async () => {
    if (!uploadedFilePath.value) {
        error.value = 'No file uploaded';
        return;
    }

    isProcessing.value = true;
    error.value = '';
    success.value = '';

    try {
        const response = await axios.post('/users/import/process', {
            file_path: uploadedFilePath.value,
            duplicate_handling: duplicateHandling.value,
            create_missing_campuses: createMissingCampuses.value,
        });

        if (response.data.success) {
            importResult.value = response.data.result;
            success.value = 'Import completed successfully!';

            // Clear uploaded file data
            uploadedFilePath.value = '';
            previewData.value = null;
            selectedFile.value = null;
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        } else {
            error.value = response.data.error || 'Import failed';
        }
    } catch (err: any) {
        error.value = err.response?.data?.error || 'Import failed';
    } finally {
        isProcessing.value = false;
    }
};

// Template download
const downloadTemplate = (format: string) => {
    window.open(`/users/templates/${format}`, '_blank');
};

// Clear all data
const clearAll = () => {
    selectedFile.value = null;
    uploadedFilePath.value = '';
    previewData.value = null;
    importResult.value = null;
    error.value = '';
    success.value = '';
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

// Go back to users list
const goBackToUsers = () => {
    router.visit('/users');
};
</script>

<template>
    <Head title="Import Users" />
    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Import Users</h1>
                    <p class="text-muted-foreground">Import users with their campus assignments and roles from Excel files</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button @click="clearAll" variant="outline" v-if="selectedFile || previewData || importResult">
                        <X class="h-4 w-4 mr-2" />
                        Clear All
                    </Button>
                    <Button @click="goBackToUsers" variant="outline">
                        Back to Users
                    </Button>
                </div>
            </div>

            <!-- Alerts -->
            <div v-if="error" class="flex items-center gap-2 p-4 border border-red-200 bg-red-50 rounded-lg">
                <AlertCircle class="h-4 w-4 text-red-600" />
                <span class="text-red-800">{{ error }}</span>
            </div>

            <div v-if="success" class="flex items-center gap-2 p-4 border border-green-200 bg-green-50 rounded-lg">
                <CheckCircle class="h-4 w-4 text-green-600" />
                <span class="text-green-800">{{ success }}</span>
            </div>

            <!-- Template Downloads -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Download class="h-5 w-5" />
                        Download Templates
                    </CardTitle>
                    <CardDescription>
                        Download Excel templates to ensure your data is formatted correctly
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div v-for="(description, format) in availableFormats" :key="format" class="border rounded-lg p-4">
                            <h4 class="font-medium mb-2">{{ description }}</h4>
                            <p class="text-sm text-muted-foreground mb-3">
                                <span v-if="format === 'simple'">Single sheet with comma-separated campus codes and roles</span>
                                <span v-else-if="format === 'detailed'">Multiple sheets for users and relationships</span>
                                <span v-else>One row per user-campus-role relationship</span>
                            </p>
                            <Button @click="downloadTemplate(format)" variant="outline" size="sm" class="w-full">
                                <FileSpreadsheet class="h-4 w-4 mr-2" />
                                Download {{ format.charAt(0).toUpperCase() + format.slice(1) }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- File Upload -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Upload class="h-5 w-5" />
                        Upload Excel File
                    </CardTitle>
                    <CardDescription>
                        Select an Excel file (.xlsx, .xls) to import user data. Maximum file size: {{ maxFileSize }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <!-- File Drop Zone -->
                        <div
                            @drop="handleDrop"
                            @dragover="handleDragOver"
                            class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-gray-400 transition-colors"
                            :class="{ 'border-red-300': selectedFile && (!isValidFile || !fileSizeValid) }"
                        >
                            <Upload class="h-12 w-12 mx-auto text-gray-400 mb-4" />
                            <div class="space-y-2">
                                <p class="text-lg font-medium">Drop your Excel file here</p>
                                <p class="text-muted-foreground">or</p>
                                <Button @click="fileInput?.click()" variant="outline">
                                    Choose File
                                </Button>
                                <input
                                    ref="fileInput"
                                    type="file"
                                    @change="handleFileSelect"
                                    accept=".xlsx,.xls"
                                    class="hidden"
                                />
                            </div>
                        </div>

                        <!-- Selected File Info -->
                        <div v-if="selectedFile" class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">{{ selectedFile.name }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ (selectedFile.size / 1024 / 1024).toFixed(2) }} MB
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span v-if="!isValidFile" class="text-red-600 text-sm">Invalid file type</span>
                                    <span v-else-if="!fileSizeValid" class="text-red-600 text-sm">File too large</span>
                                    <span v-else class="text-green-600 text-sm">Valid file</span>
                                </div>
                            </div>
                        </div>

                        <!-- Import Options -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label>Duplicate Handling</Label>
                                <select v-model="duplicateHandling" class="w-full p-2 border border-gray-300 rounded-md">
                                    <option value="update">Update existing users</option>
                                    <option value="skip">Skip existing users</option>
                                    <option value="error">Error on duplicates</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        v-model="createMissingCampuses"
                                        class="rounded border-gray-300"
                                    />
                                    Create missing campuses
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    Automatically create campuses that don't exist
                                </p>
                            </div>
                        </div>

                        <!-- Upload Progress -->
                        <div v-if="isUploading" class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium">Uploading...</span>
                                <span class="text-sm text-muted-foreground">{{ uploadProgress }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="{ width: uploadProgress + '%' }"></div>
                            </div>
                        </div>

                        <!-- Upload Button -->
                        <Button
                            @click="uploadFile"
                            :disabled="!selectedFile || !isValidFile || !fileSizeValid || isUploading"
                            class="w-full"
                        >
                            <Upload class="h-4 w-4 mr-2" />
                            {{ isUploading ? 'Uploading...' : 'Upload and Preview' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Preview Data -->
            <Card v-if="previewData">
                <CardHeader>
                    <CardTitle>Import Preview</CardTitle>
                    <CardDescription>
                        Format: {{ previewData.format }} | Estimated users: {{ previewData.estimated_users }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="(sheet, index) in previewData.sheets" :key="index" class="border rounded-lg p-4">
                            <h4 class="font-medium mb-3">{{ sheet.name }} ({{ sheet.total_rows }} rows)</h4>

                            <!-- Headers -->
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b">
                                            <th v-for="header in sheet.headers" :key="header" class="text-left p-2 font-medium">
                                                {{ header }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(row, rowIndex) in sheet.data.slice(0, 5)" :key="rowIndex" class="border-b">
                                            <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="p-2">
                                                {{ cell || '-' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <p v-if="sheet.data.length > 5" class="text-sm text-muted-foreground mt-2">
                                Showing first 5 rows of {{ sheet.total_rows }} total rows
                            </p>
                        </div>

                        <!-- Process Import Button -->
                        <Button
                            @click="processImport"
                            :disabled="isProcessing"
                            class="w-full"
                            size="lg"
                        >
                            {{ isProcessing ? 'Processing Import...' : 'Process Import' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Import Results -->
            <Card v-if="importResult">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <CheckCircle class="h-5 w-5 text-green-600" />
                        Import Results
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <!-- Summary -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-4 bg-blue-50 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">{{ importResult.summary.total_rows }}</div>
                                <div class="text-sm text-blue-800">Total Rows</div>
                            </div>
                            <div class="text-center p-4 bg-green-50 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">{{ importResult.summary.successful }}</div>
                                <div class="text-sm text-green-800">Successful</div>
                            </div>
                            <div class="text-center p-4 bg-red-50 rounded-lg">
                                <div class="text-2xl font-bold text-red-600">{{ importResult.summary.failed }}</div>
                                <div class="text-sm text-red-800">Failed</div>
                            </div>
                            <div class="text-center p-4 bg-yellow-50 rounded-lg">
                                <div class="text-2xl font-bold text-yellow-600">{{ importResult.summary.skipped }}</div>
                                <div class="text-sm text-yellow-800">Skipped</div>
                            </div>
                        </div>

                        <div class="text-sm text-muted-foreground">
                            Processing time: {{ importResult.summary.processing_time }}
                        </div>

                        <!-- Errors -->
                        <div v-if="importResult.errors && importResult.errors.length > 0" class="space-y-2">
                            <h4 class="font-medium text-red-600">Errors ({{ importResult.errors.length }})</h4>
                            <div class="max-h-40 overflow-y-auto space-y-1">
                                <div v-for="error in importResult.errors" :key="error.row" class="text-sm bg-red-50 p-2 rounded">
                                    <strong>Row {{ error.row }}:</strong> {{ error.error }}
                                </div>
                            </div>
                        </div>

                        <!-- Warnings -->
                        <div v-if="importResult.warnings && importResult.warnings.length > 0" class="space-y-2">
                            <h4 class="font-medium text-yellow-600">Warnings ({{ importResult.warnings.length }})</h4>
                            <div class="max-h-40 overflow-y-auto space-y-1">
                                <div v-for="warning in importResult.warnings" :key="warning.row" class="text-sm bg-yellow-50 p-2 rounded">
                                    <strong>Row {{ warning.row }}:</strong> {{ warning.message }}
                                </div>
                            </div>
                        </div>

                        <!-- Success Actions -->
                        <div class="flex gap-2">
                            <Button @click="goBackToUsers" class="flex-1">
                                View Users
                            </Button>
                            <Button @click="clearAll" variant="outline">
                                Import More
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
