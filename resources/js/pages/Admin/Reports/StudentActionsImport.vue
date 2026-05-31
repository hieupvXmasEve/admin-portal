<script setup lang="ts">
import FileUpload from '@/components/FileUpload.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { UploadedFile } from '@/types/fileUpload';
import { studentRoutes } from '@/utils/routes';
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Download, FileSpreadsheet, Loader2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
interface ImportRowResult {
    row_number: number;
    status: 'valid' | 'error' | 'success';
    errors: string[];
    student?: {
        id?: number | null;
        student_code?: string | null;
        full_name?: string | null;
    };
    action_type?: string | null;
    reason?: string | null;
    decision_info?: {
        decision_number?: string | null;
        decision_signed_at?: string | null;
        decision_signer?: string | null;
    };
    semester_info?: {
        from_semester_code?: string | null;
        return_semester_code?: string | null;
        egc_defer_from_block_number?: number | null;
        dropout_semester_code?: string | null;
        effective_at?: string | null;
    };
    preserve_preview?: {
        source?: 'existing_defer_case' | 'estimated_from_active_charges';
        preserve_amount?: number;
        fee_policy?: string;
    } | null;
    import_mode?: 'create' | 'append';
    existing_action_log_id?: number | null;
}
interface ImportResult {
    summary: {
        total: number;
        valid: number;
        invalid: number;
        success: number;
        failed: number;
    };
    rows: ImportRowResult[];
    global_errors: string[];
    preview_token?: string;
}
const excelFile = ref<File | null>(null);
const sharedDecisionFile = ref<UploadedFile[]>([]);
const previewResult = ref<ImportResult | null>(null);
const isPreviewing = ref(false);
const isExecuting = ref(false);
const previewToken = ref<string | null>(null);
const formatCurrency = (amount?: number) => {
    if (amount === null || amount === undefined) return '-';
    return amount.toLocaleString('vi-VN');
};
const canSubmit = computed(() => !!excelFile.value);
const sharedUploadRecordId = computed(() => sharedDecisionFile.value[0]?.id ?? null);
const onExcelChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    excelFile.value = input.files?.[0] ?? null;
    previewResult.value = null;
    previewToken.value = null;
};
const submitImport = async (endpoint: string): Promise<ImportResult | null> => {
    if (!excelFile.value) {
        toast.error('Please upload Excel file.');
        return null;
    }
    const formData = new FormData();
    formData.append('file', excelFile.value);
    if (sharedUploadRecordId.value !== null) {
        formData.append('shared_upload_record_id', String(sharedUploadRecordId.value));
    }
    if (endpoint === studentRoutes.studentStatusActionImportExecute()) {
        if (!previewToken.value) {
            toast.error('Missing preview token. Please run preview again.');
            return null;
        }
        formData.append('preview_token', previewToken.value);
    }
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: formData,
        });
        const payload = await response.json();
        if (!response.ok || !payload.success) {
            throw new Error(payload.message ?? 'Import request failed.');
        }
        return payload.data as ImportResult;
    } catch (error: any) {
        toast.error(error.message ?? 'Request failed.');
        return null;
    }
};
const handlePreview = async () => {
    isPreviewing.value = true;
    const result = await submitImport(studentRoutes.studentStatusActionImportPreview());
    isPreviewing.value = false;
    if (!result) return;
    previewResult.value = result;
    previewToken.value = result.preview_token ?? null;
    if (result.global_errors.length > 0) {
        toast.error(result.global_errors[0]);
        return;
    }
    toast.success('Preview completed.');
};
const handleExecute = async () => {
    if (!previewResult.value || previewResult.value.summary.valid === 0) {
        toast.error('Please run a valid preview before execute.');
        return;
    }
    isExecuting.value = true;
    const result = await submitImport(studentRoutes.studentStatusActionImportExecute());
    isExecuting.value = false;
    if (!result) return;
    previewResult.value = result;
    toast.success(`Import done: ${result.summary.success} success, ${result.summary.failed} failed.`);
};
</script>
<template>
    <Head title="Student Actions Import" />
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <Link :href="studentRoutes.studentStatusAction()">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Back to Audit
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Student Actions Import</h1>
                    <p class="text-muted-foreground">Import Excel with strict template. Shared decision document is optional.</p>
                </div>
            </div>
            <a :href="studentRoutes.studentStatusActionImportTemplate()">
                <Button variant="outline">
                    <Download class="mr-2 h-4 w-4" />
                    Download Template
                </Button>
            </a>
        </div>
        <Card>
            <CardHeader>
                <CardTitle>Import Source Files</CardTitle>
                <CardDescription> `action_type` drives required columns. `ADMISSION_DEFERRAL` is not supported in import. </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="space-y-2">
                    <Label for="excel-file">Excel File (.xlsx/.xls) *</Label>
                    <Input id="excel-file" type="file" accept=".xlsx,.xls" @change="onExcelChange" />
                    <p v-if="excelFile" class="text-muted-foreground text-sm">Selected: {{ excelFile.name }}</p>
                </div>
                <div class="space-y-2">
                    <Label>Shared Decision Document</Label>
                    <FileUpload
                        context="action_attachment"
                        :multiple="false"
                        :upload-immediately="true"
                        :max-size="20 * 1024 * 1024"
                        :allowed-types="['application/pdf', 'image/jpeg', 'image/png']"
                        accept=".pdf,.jpg,.jpeg,.png"
                        label="Upload one shared decision document"
                        @update:model-value="sharedDecisionFile = $event"
                    />
                </div>
                <div class="flex gap-3">
                    <Button :disabled="!canSubmit || isPreviewing || isExecuting" @click="handlePreview">
                        <Loader2 v-if="isPreviewing" class="mr-2 h-4 w-4 animate-spin" />
                        <FileSpreadsheet v-else class="mr-2 h-4 w-4" />
                        Preview
                    </Button>
                    <Button :disabled="!canSubmit || !previewResult || previewResult.summary.valid === 0 || isExecuting" @click="handleExecute">
                        <Loader2 v-if="isExecuting" class="mr-2 h-4 w-4 animate-spin" />
                        <Upload v-else class="mr-2 h-4 w-4" />
                        Execute Import
                    </Button>
                </div>
            </CardContent>
        </Card>
        <Card v-if="previewResult">
            <CardHeader>
                <CardTitle>Import Result</CardTitle>
                <CardDescription>
                    Total: {{ previewResult.summary.total }}, Valid: {{ previewResult.summary.valid }}, Invalid: {{ previewResult.summary.invalid }}, Success: {{ previewResult.summary.success }}, Failed: {{ previewResult.summary.failed }}
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <Alert v-if="previewResult.global_errors.length > 0" variant="destructive">
                    <AlertTriangle class="h-4 w-4" />
                    <AlertTitle>Global Error</AlertTitle>
                    <AlertDescription>{{ previewResult.global_errors[0] }}</AlertDescription>
                </Alert>
                <div v-if="previewResult.rows.length > 0" class="space-y-3">
                    <div v-for="row in previewResult.rows" :key="row.row_number" class="rounded-lg border p-3">
                        <div class="flex items-center gap-2">
                            <Badge :class="row.status === 'error' ? 'bg-red-100 text-red-800' : row.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'">
                                {{ row.status.toUpperCase() }}
                            </Badge>
                            <span class="font-medium">Row {{ row.row_number }}</span>
                            <Badge v-if="row.import_mode === 'append'" variant="outline">Append File</Badge>
                        </div>
                        <div class="text-muted-foreground mt-2 grid gap-1 text-sm md:grid-cols-2">
                            <div>
                                Student:
                                <span class="text-foreground font-medium"> {{ row.student?.student_code ?? '-' }} - {{ row.student?.full_name ?? 'Unknown' }} </span>
                            </div>
                            <div>
                                Action Type:
                                <span class="text-foreground font-medium">{{ row.action_type ?? '-' }}</span>
                            </div>
                            <div>
                                Semester:
                                <span class="text-foreground font-medium">
                                    {{ row.semester_info?.from_semester_code ?? row.semester_info?.return_semester_code ?? row.semester_info?.dropout_semester_code ?? row.semester_info?.effective_at ?? '-' }}
                                </span>
                            </div>
                            <div>
                                EGC From Block:
                                <span class="text-foreground font-medium">
                                    {{ row.semester_info?.egc_defer_from_block_number ? `Block ${row.semester_info.egc_defer_from_block_number}` : '-' }}
                                </span>
                            </div>
                            <div>
                                Reason:
                                <span class="text-foreground font-medium">{{ row.reason ?? '-' }}</span>
                            </div>
                            <div>
                                Decision Number:
                                <span class="text-foreground font-medium">{{ row.decision_info?.decision_number ?? '-' }}</span>
                            </div>
                            <div>
                                Decision Signed At:
                                <span class="text-foreground font-medium">{{ row.decision_info?.decision_signed_at ?? '-' }}</span>
                            </div>
                            <div>
                                Decision Signer:
                                <span class="text-foreground font-medium">{{ row.decision_info?.decision_signer ?? '-' }}</span>
                            </div>
                        </div>
                        <div v-if="row.preserve_preview" class="mt-2 rounded-md bg-emerald-50 p-2 text-sm text-emerald-800">
                            Preserve Amount:
                            <span class="font-semibold">{{ formatCurrency(row.preserve_preview.preserve_amount) }}</span>
                            <span class="ml-1 text-xs"> ({{ row.preserve_preview.source === 'existing_defer_case' ? 'from existing defer_case' : 'estimated from active charges' }}) </span>
                        </div>
                        <ul v-if="row.errors.length > 0" class="mt-2 list-disc pl-5 text-sm text-red-600">
                            <li v-for="err in row.errors" :key="err">{{ err }}</li>
                        </ul>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
