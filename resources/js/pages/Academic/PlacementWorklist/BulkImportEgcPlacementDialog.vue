<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/vue3';
import { AlertTriangle, Loader2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface ImportRow {
    row_number: number;
    status: 'valid' | 'skip' | 'success' | 'error';
    skip_reason?: string | null;
    error?: string | null;
    student?: { id?: number | null; student_id?: string | null; full_name?: string | null };
    level_raw?: string | null;
    level?: number | null;
}

interface ImportResult {
    summary: {
        total: number;
        valid: number;
        skipped: number;
        skip_counts: Record<string, number>;
        success: number;
        failed: number;
    };
    rows: ImportRow[];
    global_errors: string[];
    preview_token?: string;
}

const emit = defineEmits<{ close: [] }>();

const isOpen = ref(true);
const file = ref<File | null>(null);
const previewToken = ref<string | null>(null);
const previewResult = ref<ImportResult | null>(null);
const isPreviewing = ref(false);
const isExecuting = ref(false);

const canPreview = computed(() => !!file.value);
const canExecute = computed(() => !!previewResult.value && !!previewToken.value && previewResult.value.summary.valid > 0);

const skipReasonLabels: Record<string, string> = {
    student_id_missing: 'Missing Student ID',
    student_not_found: 'Student not found',
    level_missing: 'Missing Level',
    level_excluded_gcs: 'GCS excluded',
    level_unmapped: 'Unmapped level',
};

const rowBadgeClass = (row: ImportRow): string => {
    if (row.status === 'error') return 'bg-red-100 text-red-800';
    if (row.status === 'success') return 'bg-green-100 text-green-800';
    if (row.status === 'skip') return 'bg-muted text-muted-foreground';
    return 'bg-blue-100 text-blue-800';
};

const onFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    file.value = input.files?.[0] ?? null;
    previewResult.value = null;
    previewToken.value = null;
};

const submit = async (endpoint: string, includeToken: boolean): Promise<ImportResult | null> => {
    if (!file.value) {
        toast.error('Please choose a CSV file.');
        return null;
    }
    const formData = new FormData();
    formData.append('file', file.value);
    if (includeToken) {
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

const handlePreview = async (): Promise<void> => {
    isPreviewing.value = true;
    const result = await submit(route('students.placement-worklist.bulk-import.preview'), false);
    isPreviewing.value = false;
    if (!result) return;
    previewResult.value = result;
    previewToken.value = result.preview_token ?? null;
    if (result.global_errors.length > 0) {
        toast.error(result.global_errors[0]);
        return;
    }
    toast.success(`Preview: ${result.summary.valid} valid, ${result.summary.skipped} skipped.`);
};

const handleExecute = async (): Promise<void> => {
    isExecuting.value = true;
    const result = await submit(route('students.placement-worklist.bulk-import.execute'), true);
    isExecuting.value = false;
    if (!result) return;
    previewResult.value = result;
    if (result.global_errors.length > 0) {
        toast.error(result.global_errors[0]);
        return;
    }
    toast.success(`Import done: ${result.summary.success} placed, ${result.summary.failed} failed.`);
    if (result.summary.success > 0) {
        router.reload({ only: ['students'] });
    }
};

const close = (): void => {
    isOpen.value = false;
    emit('close');
};
</script>

<template>
    <Dialog v-model:open="isOpen" @update:open="(open) => !open && close()">
        <DialogContent class="max-h-[90vh] !max-w-4xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Bulk Import EGC Placement</DialogTitle>
                <DialogDescription> Upload a CSV with `Student ID` and `Level` columns. Preview before placing students. </DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <Label for="bulk-egc-file">CSV / Excel File *</Label>
                <Input id="bulk-egc-file" type="file" accept=".csv,.txt,.xlsx,.xls" @change="onFileChange" />
                <p v-if="file" class="text-muted-foreground text-sm">Selected: {{ file.name }}</p>
            </div>

            <Alert v-if="previewResult && previewResult.global_errors.length > 0" variant="destructive">
                <AlertTriangle class="h-4 w-4" />
                <AlertTitle>Import Error</AlertTitle>
                <AlertDescription>{{ previewResult.global_errors[0] }}</AlertDescription>
            </Alert>

            <div v-if="previewResult" class="space-y-3">
                <div class="flex flex-wrap gap-2 text-sm">
                    <Badge variant="secondary">Total: {{ previewResult.summary.total }}</Badge>
                    <Badge class="bg-blue-100 text-blue-800">Valid: {{ previewResult.summary.valid }}</Badge>
                    <Badge class="bg-muted text-muted-foreground">Skipped: {{ previewResult.summary.skipped }}</Badge>
                    <Badge v-if="previewResult.summary.success > 0" class="bg-green-100 text-green-800"> Placed: {{ previewResult.summary.success }} </Badge>
                    <Badge v-if="previewResult.summary.failed > 0" class="bg-red-100 text-red-800"> Failed: {{ previewResult.summary.failed }} </Badge>
                </div>
                <div v-if="Object.keys(previewResult.summary.skip_counts).length > 0" class="text-muted-foreground flex flex-wrap gap-2 text-xs">
                    <span v-for="(count, reason) in previewResult.summary.skip_counts" :key="reason">
                        {{ skipReasonLabels[reason] ?? reason }}: {{ count }}
                    </span>
                </div>

                <div class="max-h-96 overflow-y-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 sticky top-0">
                            <tr class="border-b">
                                <th class="px-3 py-2 text-left font-medium">Row</th>
                                <th class="px-3 py-2 text-left font-medium">Student ID</th>
                                <th class="px-3 py-2 text-left font-medium">Name</th>
                                <th class="px-3 py-2 text-left font-medium">Level</th>
                                <th class="px-3 py-2 text-left font-medium">Status</th>
                                <th class="px-3 py-2 text-left font-medium">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in previewResult.rows" :key="row.row_number" class="border-b" :class="row.status === 'skip' ? 'text-muted-foreground' : ''">
                                <td class="px-3 py-2">{{ row.row_number }}</td>
                                <td class="px-3 py-2">{{ row.student?.student_id ?? '-' }}</td>
                                <td class="px-3 py-2">{{ row.student?.full_name ?? '-' }}</td>
                                <td class="px-3 py-2">{{ row.level_raw ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <Badge :class="rowBadgeClass(row)">{{ row.status.toUpperCase() }}</Badge>
                                </td>
                                <td class="px-3 py-2">{{ row.error ?? (row.skip_reason ? (skipReasonLabels[row.skip_reason] ?? row.skip_reason) : '-') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="close">Close</Button>
                <Button type="button" :disabled="!canPreview || isPreviewing || isExecuting" @click="handlePreview">
                    <Loader2 v-if="isPreviewing" class="mr-2 h-4 w-4 animate-spin" />
                    Preview
                </Button>
                <Button type="button" :disabled="!canExecute || isExecuting" @click="handleExecute">
                    <Loader2 v-if="isExecuting" class="mr-2 h-4 w-4 animate-spin" />
                    <Upload v-else class="mr-2 h-4 w-4" />
                    Execute Import
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
