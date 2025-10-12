<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import { AlertCircle, CheckCircle, Download, Upload, XCircle, ArrowLeft } from 'lucide-vue-next';

interface BillingCycle {
    id: number;
    name: string;
    semester: {
        id: number;
        name: string;
    };
}

interface PreviewRow {
    row_number: number;
    student_id: string;
    voucher_codes: string[];
    is_valid: boolean;
    errors: string[];
    warnings: string[];
}

interface ImportResult {
    success: boolean;
    stats: {
        total: number;
        created: number;
        skipped: number;
        errors: number;
    };
    details: Array<{
        row: number;
        student_id: string;
        voucher_code: string;
        status: 'success' | 'skipped' | 'error';
        message: string;
    }>;
}

interface Props {
    billingCycles: BillingCycle[];
    preview?: PreviewRow[];
    result?: ImportResult;
}

const props = defineProps<Props>();
const page = usePage();
const flash = computed(() => page.props.flash as any);

const uploadForm = reactive({
    billing_cycle_id: null as number | null,
    file: null as File | null,
});

const previewData = ref<PreviewRow[]>([]);
const importResult = ref<ImportResult | null>(null);
const isUploading = ref(false);
const isImporting = ref(false);
const showPreview = ref(false);
const showResult = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

onMounted(() => {
    // Load preview/result data from props if available
    if (props.preview && props.preview.length > 0) {
        previewData.value = props.preview;
        showPreview.value = true;
    }
    
    if (props.result) {
        importResult.value = props.result;
        showResult.value = true;
    }

    if (flash.value.success) {
        toast.success(flash.value.success);
    }
    if (flash.value.error) {
        toast.error(flash.value.error);
    }
});

watch(flash, (newFlash) => {
    if (newFlash.success) {
        toast.success(newFlash.success);
    }
    if (newFlash.error) {
        toast.error(newFlash.error);
    }
}, { deep: true });

// Watch for props changes to update preview/result
watch(() => props.preview, (newPreview) => {
    if (newPreview && newPreview.length > 0) {
        previewData.value = newPreview;
        showPreview.value = true;
        showResult.value = false;
    }
}, { deep: true });

watch(() => props.result, (newResult) => {
    if (newResult) {
        importResult.value = newResult;
        showResult.value = true;
        showPreview.value = false;
    }
}, { deep: true });

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        uploadForm.file = target.files[0];
    }
};

const handleUpload = async () => {
    if (!uploadForm.billing_cycle_id) {
        toast.error('Please select a billing cycle');
        return;
    }

    if (!uploadForm.file) {
        toast.error('Please select a file to upload');
        return;
    }

    isUploading.value = true;

    const formData = new FormData();
    formData.append('billing_cycle_id', uploadForm.billing_cycle_id.toString());
    formData.append('file', uploadForm.file);

    router.post(route('vouchers.import.upload'), formData, {
        preserveScroll: true,
        replace: true,
        onSuccess: (page) => {
            isUploading.value = false;
            
            // Manually update to show preview
            if (page.props.preview) {
                previewData.value = page.props.preview as PreviewRow[];
                showPreview.value = true;
                showResult.value = false;
                toast.success('File uploaded successfully. Please review the data below.');
                
                // Navigate back to clean URL
                window.history.replaceState({}, '', route('vouchers.import'));
            }
        },
        onError: (errors) => {
            isUploading.value = false;
            const errorMessage = errors.error || 'Failed to upload file';
            toast.error(errorMessage);
        },
    });
};

const handleConfirmImport = () => {
    if (!uploadForm.billing_cycle_id) {
        toast.error('Please select a billing cycle');
        return;
    }

    if (!uploadForm.file) {
        toast.error('Please upload a file first');
        return;
    }

    isImporting.value = true;

    const formData = new FormData();
    formData.append('billing_cycle_id', uploadForm.billing_cycle_id.toString());
    formData.append('file', uploadForm.file);

    router.post(route('vouchers.import.process'), formData, {
        preserveScroll: true,
        replace: true,
        onSuccess: (page) => {
            isImporting.value = false;
            
            // Manually update to show results
            if (page.props.result) {
                importResult.value = page.props.result as ImportResult;
                showResult.value = true;
                showPreview.value = false;
                toast.success('Import completed successfully');
                
                // Navigate back to clean URL
                window.history.replaceState({}, '', route('vouchers.import'));
            }
        },
        onError: (errors) => {
            isImporting.value = false;
            const errorMessage = errors.error || 'Failed to import vouchers';
            toast.error(errorMessage);
        },
    });
};

const handleReset = async () => {
    uploadForm.billing_cycle_id = null;
    uploadForm.file = null;
    previewData.value = [];
    importResult.value = null;
    showPreview.value = false;
    showResult.value = false;
    
    // Reset file input properly
    await nextTick();
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const validRowsCount = computed(() => {
    return previewData.value.filter(row => row.is_valid).length;
});

const invalidRowsCount = computed(() => {
    return previewData.value.filter(row => !row.is_valid).length;
});

const totalVouchersCount = computed(() => {
    return previewData.value.reduce((sum, row) => sum + row.voucher_codes.length, 0);
});

const getStatusBadgeVariant = (status: string) => {
    switch (status) {
        case 'success': return 'default';
        case 'skipped': return 'secondary';
        case 'error': return 'destructive';
        default: return 'outline';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'success': return CheckCircle;
        case 'skipped': return AlertCircle;
        case 'error': return XCircle;
        default: return AlertCircle;
    }
};
</script>

<template>
    <Head title="Import Vouchers" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="route('vouchers.index')">
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 class="text-3xl font-bold tracking-tight">Import Vouchers</h1>
                </div>
                <p class="text-muted-foreground mt-1">Upload an Excel file to import voucher redemptions</p>
            </div>
        </div>

        <!-- Upload Form -->
        <Card v-if="!showResult">
            <CardHeader>
                <CardTitle>Upload File</CardTitle>
                <CardDescription>
                    Select a billing cycle and upload an Excel file with student IDs and voucher codes
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="billing_cycle">Billing Cycle *</Label>
                        <Select v-model="uploadForm.billing_cycle_id" :disabled="isUploading || isImporting || showPreview">
                            <SelectTrigger id="billing_cycle">
                                <SelectValue placeholder="Select billing cycle" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="cycle in billingCycles" :key="cycle.id" :value="cycle.id">
                                    {{ cycle.name }} ({{ cycle.semester.name }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="space-y-2">
                        <Label for="file">Excel File *</Label>
                        <Input
                            id="file"
                            ref="fileInputRef"
                            type="file"
                            accept=".xlsx,.xls"
                            :disabled="isUploading || isImporting || showPreview"
                            @change="handleFileChange"
                        />
                    </div>
                </div>

                <Alert>
                    <Download class="h-4 w-4" />
                    <AlertDescription>
                        <div class="space-y-2">
                            <p class="font-medium">Expected Format:</p>
                            <p class="text-sm">Column 1: student_id | Column 2: voucher_codes (comma-separated)</p>
                            <p class="text-sm text-muted-foreground">Example: 2030001 | DISC20,FREE50,EGC100</p>
                        </div>
                    </AlertDescription>
                </Alert>

                <div class="flex gap-2">
                    <Button 
                        @click="handleUpload" 
                        :disabled="isUploading || isImporting || showPreview || !uploadForm.billing_cycle_id || !uploadForm.file"
                    >
                        <Upload class="mr-2 h-4 w-4" />
                        {{ isUploading ? 'Uploading...' : 'Upload & Preview' }}
                    </Button>
                    <Button 
                        v-if="showPreview" 
                        variant="outline" 
                        @click="handleReset"
                    >
                        Reset
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Preview Data -->
        <Card v-if="showPreview && previewData.length > 0">
            <CardHeader>
                <CardTitle>Preview Data</CardTitle>
                <CardDescription>
                    Review the data below before importing. Fix any errors in your Excel file if needed.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <Alert>
                        <CheckCircle class="h-4 w-4 text-green-600" />
                        <AlertDescription>
                            <p class="font-medium">Valid Rows</p>
                            <p class="text-2xl font-bold">{{ validRowsCount }}</p>
                        </AlertDescription>
                    </Alert>
                    <Alert variant="destructive" v-if="invalidRowsCount > 0">
                        <XCircle class="h-4 w-4" />
                        <AlertDescription>
                            <p class="font-medium">Invalid Rows</p>
                            <p class="text-2xl font-bold">{{ invalidRowsCount }}</p>
                        </AlertDescription>
                    </Alert>
                    <Alert>
                        <AlertCircle class="h-4 w-4" />
                        <AlertDescription>
                            <p class="font-medium">Total Vouchers</p>
                            <p class="text-2xl font-bold">{{ totalVouchersCount }}</p>
                        </AlertDescription>
                    </Alert>
                </div>

                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-20">Row</TableHead>
                                <TableHead>Student ID</TableHead>
                                <TableHead>Voucher Codes</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Messages</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in previewData" :key="row.row_number" :class="{ 'bg-destructive/10': !row.is_valid }">
                                <TableCell class="font-medium">{{ row.row_number }}</TableCell>
                                <TableCell>{{ row.student_id }}</TableCell>
                                <TableCell>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge v-for="(code, index) in row.voucher_codes" :key="index" variant="outline">
                                            {{ code }}
                                        </Badge>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="row.is_valid ? 'default' : 'destructive'">
                                        {{ row.is_valid ? 'Valid' : 'Invalid' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="space-y-1">
                                        <p v-for="(error, index) in row.errors" :key="`error-${index}`" class="text-sm text-destructive">
                                            • {{ error }}
                                        </p>
                                        <p v-for="(warning, index) in row.warnings" :key="`warning-${index}`" class="text-sm text-yellow-600">
                                            ⚠ {{ warning }}
                                        </p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div class="flex gap-2">
                    <Button 
                        @click="handleConfirmImport" 
                        :disabled="isImporting || invalidRowsCount > 0 || validRowsCount === 0"
                    >
                        {{ isImporting ? 'Importing...' : 'Confirm Import' }}
                    </Button>
                    <Button variant="outline" @click="handleReset" :disabled="isImporting">
                        Cancel
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Import Results -->
        <Card v-if="showResult && importResult">
            <CardHeader>
                <CardTitle>Import Results</CardTitle>
                <CardDescription>
                    Summary of the import operation
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-4 md:grid-cols-4">
                    <Alert>
                        <AlertCircle class="h-4 w-4" />
                        <AlertDescription>
                            <p class="font-medium">Total</p>
                            <p class="text-2xl font-bold">{{ importResult.stats.total }}</p>
                        </AlertDescription>
                    </Alert>
                    <Alert>
                        <CheckCircle class="h-4 w-4 text-green-600" />
                        <AlertDescription>
                            <p class="font-medium">Created</p>
                            <p class="text-2xl font-bold text-green-600">{{ importResult.stats.created }}</p>
                        </AlertDescription>
                    </Alert>
                    <Alert>
                        <AlertCircle class="h-4 w-4 text-yellow-600" />
                        <AlertDescription>
                            <p class="font-medium">Skipped</p>
                            <p class="text-2xl font-bold text-yellow-600">{{ importResult.stats.skipped }}</p>
                        </AlertDescription>
                    </Alert>
                    <Alert variant="destructive" v-if="importResult.stats.errors > 0">
                        <XCircle class="h-4 w-4" />
                        <AlertDescription>
                            <p class="font-medium">Errors</p>
                            <p class="text-2xl font-bold">{{ importResult.stats.errors }}</p>
                        </AlertDescription>
                    </Alert>
                </div>

                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-20">Row</TableHead>
                                <TableHead>Student ID</TableHead>
                                <TableHead>Voucher Code</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Message</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(detail, index) in importResult.details" :key="index">
                                <TableCell class="font-medium">{{ detail.row }}</TableCell>
                                <TableCell>{{ detail.student_id }}</TableCell>
                                <TableCell>
                                    <Badge variant="outline">{{ detail.voucher_code }}</Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="getStatusBadgeVariant(detail.status)">
                                        <component :is="getStatusIcon(detail.status)" class="mr-1 h-3 w-3" />
                                        {{ detail.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <span :class="{
                                        'text-green-600': detail.status === 'success',
                                        'text-yellow-600': detail.status === 'skipped',
                                        'text-destructive': detail.status === 'error'
                                    }">
                                        {{ detail.message }}
                                    </span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div class="flex gap-2">
                    <Button as-child>
                        <Link :href="route('vouchers.index')">
                            Back to Vouchers
                        </Link>
                    </Button>
                    <Button variant="outline" @click="handleReset">
                        Import Another File
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
