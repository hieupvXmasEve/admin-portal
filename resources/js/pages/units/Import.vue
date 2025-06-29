<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle, Download, FileSpreadsheet, Upload, X, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

interface ImportResult {
    success: boolean;
    result?: {
        summary: {
            total_rows: number;
            successful: number;
            failed: number;
            skipped: number;
            processing_time: string;
        };
        errors: Array<{
            row: number;
            error: string;
            data: any[];
        }>;
        warnings: Array<{
            row: number;
            message: string;
        }>;
    };
    error?: string;
}

interface PreviewData {
    format: string;
    sheets: Array<{
        name: string;
        headers: string[];
        data: any[][];
        total_rows: number;
    }>;
    estimated_units: number;
}

const props = defineProps<{
    maxFileSize: string;
    allowedExtensions: string[];
    availableFormats: {
        simple: string;
        detailed: string;
        complete: string;
        combined: string;
    };
}>();

// File upload state
const selectedFile = ref<File | null>(null);
const isDragOver = ref(false);
const isUploading = ref(false);
const uploadProgress = ref(0);

// Import configuration
const duplicateHandling = ref('update');
const createPrerequisites = ref(false);
const createEquivalents = ref(false);

// Import process state
const isImporting = ref(false);
const importProgress = ref(0);
const currentStep = ref<'upload' | 'configure' | 'preview' | 'process' | 'complete'>('upload');

// Data state
const uploadedFilePath = ref<string>('');
const uploadedFileName = ref<string>('');
const previewData = ref<PreviewData | null>(null);
const importResult = ref<ImportResult | null>(null);

// Computed properties
const canProceedToPreview = computed(() => uploadedFilePath.value && selectedFile.value);
const canStartImport = computed(() => previewData.value && currentStep.value === 'preview');

// File handling
const onFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        selectedFile.value = target.files[0];
        validateAndUploadFile();
    }
};

const onFileDrop = (event: DragEvent) => {
    event.preventDefault();
    isDragOver.value = false;

    if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
        selectedFile.value = event.dataTransfer.files[0];
        validateAndUploadFile();
    }
};

const onDragOver = (event: DragEvent) => {
    event.preventDefault();
    isDragOver.value = true;
};

const onDragLeave = () => {
    isDragOver.value = false;
};

const validateAndUploadFile = async () => {
    if (!selectedFile.value) return;

    // Validate file type
    const fileExtension = selectedFile.value.name.split('.').pop()?.toLowerCase();
    if (!fileExtension || !props.allowedExtensions.includes(fileExtension)) {
        toast.error(`Invalid file type. Allowed: ${props.allowedExtensions.join(', ')}`);
        selectedFile.value = null;
        return;
    }

    // Validate file size
    const maxSizeBytes = parseFloat(props.maxFileSize) * 1024 * 1024; // Convert MB to bytes
    if (selectedFile.value.size > maxSizeBytes) {
        toast.error(`File too large. Maximum size: ${props.maxFileSize}`);
        selectedFile.value = null;
        return;
    }

    await uploadFile();
};

const uploadFile = async () => {
    if (!selectedFile.value) return;

    isUploading.value = true;
    uploadProgress.value = 0;

    const formData = new FormData();
    formData.append('file', selectedFile.value);
    formData.append('duplicate_handling', duplicateHandling.value);

    try {
        const response = await fetch('/units/import/upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        const result = await response.json();

        if (result.success) {
            uploadedFilePath.value = result.file_path;
            uploadedFileName.value = result.filename;
            previewData.value = result.preview;
            currentStep.value = 'configure';
            toast.success('File uploaded successfully');
        } else {
            throw new Error(result.error || 'Upload failed');
        }
    } catch (error) {
        console.error('Upload error:', error);
        toast.error('Failed to upload file');
        selectedFile.value = null;
    } finally {
        isUploading.value = false;
        uploadProgress.value = 0;
    }
};

const proceedToPreview = () => {
    currentStep.value = 'preview';
};

const startImport = async () => {
    if (!uploadedFilePath.value) return;

    isImporting.value = true;
    importProgress.value = 0;
    currentStep.value = 'process';

    try {
        const response = await fetch('/units/import/process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                file_path: uploadedFilePath.value,
                duplicate_handling: duplicateHandling.value,
                create_prerequisites: createPrerequisites.value,
                create_equivalents: createEquivalents.value,
            }),
        });

        const result = await response.json();
        importResult.value = result;

        if (result.success) {
            currentStep.value = 'complete';
            toast.success('Import completed successfully');
        } else {
            throw new Error(result.error || 'Import failed');
        }
    } catch (error) {
        console.error('Import error:', error);
        toast.error('Import failed');
        currentStep.value = 'preview';
    } finally {
        isImporting.value = false;
        importProgress.value = 0;
    }
};

const resetImport = () => {
    selectedFile.value = null;
    uploadedFilePath.value = '';
    uploadedFileName.value = '';
    previewData.value = null;
    importResult.value = null;
    currentStep.value = 'upload';
    duplicateHandling.value = 'update';
    createPrerequisites.value = false;
    createEquivalents.value = false;
};

const downloadTemplate = (format: string) => {
    const link = document.createElement('a');
    link.href = `/units/import/template/${format}`;
    link.download = `units_${format}_template.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const goBackToUnits = () => {
    router.visit('/units');
};
</script>

<template>
    <Head title="Import Units" />
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Import Units</h1>
            <p class="text-muted-foreground text-sm">Import units from Excel files with support for prerequisites and equivalents</p>
        </div>
        <Button variant="outline" @click="goBackToUnits">
            <X class="mr-2 h-4 w-4" />
            Back to Units
        </Button>
    </div>

    <!-- Progress Indicator -->
    <div class="flex items-center justify-center space-x-4">
        <div class="flex items-center space-x-2">
            <div
                :class="[
                    'flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium',
                    currentStep === 'upload'
                        ? 'bg-primary text-primary-foreground'
                        : ['configure', 'preview', 'process', 'complete'].includes(currentStep)
                          ? 'bg-green-500 text-white'
                          : 'bg-gray-200',
                ]"
            >
                1
            </div>
            <span class="text-sm">Upload</span>
        </div>
        <div class="h-px w-12 bg-gray-200"></div>
        <div class="flex items-center space-x-2">
            <div
                :class="[
                    'flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium',
                    currentStep === 'configure'
                        ? 'bg-primary text-primary-foreground'
                        : ['preview', 'process', 'complete'].includes(currentStep)
                          ? 'bg-green-500 text-white'
                          : 'bg-gray-200',
                ]"
            >
                2
            </div>
            <span class="text-sm">Configure</span>
        </div>
        <div class="h-px w-12 bg-gray-200"></div>
        <div class="flex items-center space-x-2">
            <div
                :class="[
                    'flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium',
                    currentStep === 'preview'
                        ? 'bg-primary text-primary-foreground'
                        : ['process', 'complete'].includes(currentStep)
                          ? 'bg-green-500 text-white'
                          : 'bg-gray-200',
                ]"
            >
                3
            </div>
            <span class="text-sm">Preview</span>
        </div>
        <div class="h-px w-12 bg-gray-200"></div>
        <div class="flex items-center space-x-2">
            <div
                :class="[
                    'flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium',
                    currentStep === 'process'
                        ? 'bg-primary text-primary-foreground'
                        : currentStep === 'complete'
                          ? 'bg-green-500 text-white'
                          : 'bg-gray-200',
                ]"
            >
                4
            </div>
            <span class="text-sm">Import</span>
        </div>
    </div>

    <!-- Step 1: Upload -->
    <div v-if="currentStep === 'upload'" class="space-y-6">
        <!-- Template Downloads -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Download class="h-5 w-5" />
                    Download Templates
                </CardTitle>
                <CardDescription> Download Excel templates to ensure your data is formatted correctly </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <div v-for="(description, format) in availableFormats" :key="format" class="space-y-2">
                        <h4 class="font-medium">{{ description }}</h4>
                        <Button variant="outline" size="sm" @click="downloadTemplate(format)" class="w-full">
                            <FileSpreadsheet class="mr-2 h-4 w-4" />
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
                    Upload File
                </CardTitle>
                <CardDescription> Select an Excel file (.xlsx, .xls) to import units. Maximum file size: {{ maxFileSize }} </CardDescription>
            </CardHeader>
            <CardContent>
                <div
                    @drop="onFileDrop"
                    @dragover="onDragOver"
                    @dragleave="onDragLeave"
                    :class="[
                        'rounded-lg border-2 border-dashed p-8 text-center transition-colors',
                        isDragOver ? 'border-primary bg-primary/10' : 'border-gray-300',
                        isUploading ? 'pointer-events-none opacity-50' : 'hover:border-primary hover:bg-primary/5 cursor-pointer',
                    ]"
                >
                    <input type="file" accept=".xlsx,.xls" @change="onFileSelect" class="hidden" id="fileInput" :disabled="isUploading" />
                    <label for="fileInput" class="cursor-pointer">
                        <Upload class="mx-auto mb-4 h-12 w-12 text-gray-400" />
                        <p class="mb-2 text-lg font-medium">
                            {{ selectedFile ? selectedFile.name : 'Choose a file or drag it here' }}
                        </p>
                        <p class="text-muted-foreground text-sm">Supported formats: {{ allowedExtensions.join(', ').toUpperCase() }}</p>
                    </label>
                </div>

                <div v-if="isUploading" class="mt-4">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                        <div :style="{ width: `${uploadProgress}%` }" class="bg-primary h-2 rounded-full"></div>
                    </div>
                    <p class="text-muted-foreground mt-2 text-center text-sm">Uploading... {{ uploadProgress }}%</p>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Step 2: Configure -->
    <div v-if="currentStep === 'configure'" class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>Import Configuration</CardTitle>
                <CardDescription> Configure how the import should handle duplicates and relationships </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="space-y-3">
                    <Label>Duplicate Handling</Label>
                    <RadioGroup v-model="duplicateHandling">
                        <div class="flex items-center space-x-2">
                            <RadioGroupItem value="update" id="update" />
                            <Label for="update">Update existing units</Label>
                        </div>
                        <div class="flex items-center space-x-2">
                            <RadioGroupItem value="skip" id="skip" />
                            <Label for="skip">Skip existing units</Label>
                        </div>
                        <div class="flex items-center space-x-2">
                            <RadioGroupItem value="error" id="error" />
                            <Label for="error">Error on duplicates</Label>
                        </div>
                    </RadioGroup>
                </div>

                <div class="flex items-center space-x-2">
                    <input id="createPrerequisites" type="checkbox" v-model="createPrerequisites" class="rounded border-gray-300" />
                    <Label for="createPrerequisites">Create prerequisite relationships (if Prerequisites sheet is present)</Label>
                </div>

                <div class="flex items-center space-x-2">
                    <input id="createEquivalents" type="checkbox" v-model="createEquivalents" class="rounded border-gray-300" />
                    <Label for="createEquivalents">Create equivalent relationships (if Equivalents sheet is present)</Label>
                </div>

                <div class="flex justify-between">
                    <Button variant="outline" @click="resetImport">
                        <X class="mr-2 h-4 w-4" />
                        Start Over
                    </Button>
                    <Button @click="proceedToPreview" :disabled="!canProceedToPreview"> Continue to Preview </Button>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Step 3: Preview -->
    <div v-if="currentStep === 'preview' && previewData" class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>Import Preview</CardTitle>
                <CardDescription> Review the data that will be imported. Format: {{ previewData.format.toUpperCase() }} </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="text-center">
                        <div class="text-2xl font-bold">{{ previewData.estimated_units }}</div>
                        <div class="text-muted-foreground text-sm">Estimated Units</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold">{{ previewData.sheets.length }}</div>
                        <div class="text-muted-foreground text-sm">Worksheets</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold">{{ previewData.format.toUpperCase() }}</div>
                        <div class="text-muted-foreground text-sm">Format</div>
                    </div>
                </div>

                <!-- Sheet previews -->
                <div v-for="sheet in previewData.sheets" :key="sheet.name" class="space-y-2">
                    <h4 class="font-medium">{{ sheet.name }} ({{ sheet.total_rows }} rows)</h4>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        v-for="header in sheet.headers"
                                        :key="header"
                                        class="px-3 py-2 text-left text-xs font-medium tracking-wider text-gray-500 uppercase"
                                    >
                                        {{ header }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="(row, index) in sheet.data" :key="index">
                                    <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="px-3 py-2 text-sm whitespace-nowrap text-gray-900">
                                        {{ cell }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex justify-between">
                    <Button variant="outline" @click="currentStep = 'configure'"> Back to Configuration </Button>
                    <Button @click="startImport" :disabled="!canStartImport"> Start Import </Button>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Step 4: Processing -->
    <div v-if="currentStep === 'process'" class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>Importing Units</CardTitle>
                <CardDescription> Please wait while your units are being imported... </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                        <div :style="{ width: `${importProgress}%` }" class="bg-primary h-2 rounded-full"></div>
                    </div>
                    <p class="text-muted-foreground text-center text-sm">Processing units...</p>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Step 5: Complete -->
    <div v-if="currentStep === 'complete' && importResult" class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <CheckCircle v-if="importResult.success" class="h-5 w-5 text-green-500" />
                    <XCircle v-else class="h-5 w-5 text-red-500" />
                    Import {{ importResult.success ? 'Completed' : 'Failed' }}
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-if="importResult.success && importResult.result" class="grid gap-4 md:grid-cols-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold">{{ importResult.result.summary.total_rows }}</div>
                        <div class="text-muted-foreground text-sm">Total Rows</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600">{{ importResult.result.summary.successful }}</div>
                        <div class="text-muted-foreground text-sm">Successful</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-red-600">{{ importResult.result.summary.failed }}</div>
                        <div class="text-muted-foreground text-sm">Failed</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-600">{{ importResult.result.summary.skipped }}</div>
                        <div class="text-muted-foreground text-sm">Skipped</div>
                    </div>
                </div>

                <!-- Errors -->
                <div v-if="importResult.result?.errors && importResult.result.errors.length > 0" class="space-y-2">
                    <h4 class="font-medium text-red-600">Errors</h4>
                    <div class="max-h-48 space-y-1 overflow-y-auto">
                        <div v-for="error in importResult.result.errors" :key="error.row" class="rounded border border-red-200 bg-red-50 p-2 text-sm">
                            <strong>Row {{ error.row }}:</strong> {{ error.error }}
                        </div>
                    </div>
                </div>

                <!-- Warnings -->
                <div v-if="importResult.result?.warnings && importResult.result.warnings.length > 0" class="space-y-2">
                    <h4 class="font-medium text-yellow-600">Warnings</h4>
                    <div class="max-h-48 space-y-1 overflow-y-auto">
                        <div
                            v-for="warning in importResult.result.warnings"
                            :key="warning.row"
                            class="rounded border border-yellow-200 bg-yellow-50 p-2 text-sm"
                        >
                            <strong>Row {{ warning.row }}:</strong> {{ warning.message }}
                        </div>
                    </div>
                </div>

                <div class="flex justify-between">
                    <Button variant="outline" @click="resetImport"> Import Another File </Button>
                    <Button @click="goBackToUnits"> Go to Units </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
