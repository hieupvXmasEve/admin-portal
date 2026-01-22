<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCurrency } from '@/utils/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, FileUp, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import { useApi } from '@/composables/useApiRequest';

// Types
interface ImportRow {
    row: number;
    student_code: string;
    student_name: string | null;
    student_id: number | null;
    amount: number;
    paid_at: string;
    external_ref: string;
    notes: string;
    status: 'valid' | 'error';
    errors: string[];
}

interface PreviewResult {
    rows: ImportRow[];
    summary: {
        total: number;
        valid: number;
        invalid: number;
    };
}

const api = useApi();
const fileInput = ref<HTMLInputElement | null>(null);
const previewData = ref<PreviewResult | null>(null);
const isPreviewing = ref(false);
const isImporting = ref(false);

const handleFileChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        await uploadForPreview(target.files[0]);
    }
};

const uploadForPreview = async (file: File) => {
    isPreviewing.value = true;
    previewData.value = null;

    const formData = new FormData();
    formData.append('file', file);

    try {
        const { data: apiData } = await api.post<PreviewResult>(route('finance.payments.import.preview'), formData);

        if (apiData.value?.success) {
            previewData.value = apiData.value.data;
        } else {
            toast.error('Failed to preview file', {
                description: apiData.value?.message || 'An error occurred during upload.',
            });
        }
    } catch (error: any) {
        toast.error('Failed to preview file', {
            description: 'An error occurred during upload.',
        });
    } finally {
        isPreviewing.value = false;
        // Reset input to allow re-selecting same file if needed
        if (fileInput.value) fileInput.value.value = '';
    }
};

const validRows = computed(() => {
    return previewData.value?.rows.filter(r => r.status === 'valid') || [];
});

const handleImport = async () => {
    if (!previewData.value || validRows.value.length === 0) return;

    isImporting.value = true;

    try {
        const { data: apiData } = await api.post<{ imported_count: number }>(route('finance.payments.import.store'), {
            rows: validRows.value,
        });

        if (apiData.value?.success) {
            toast.success(`Successfully imported ${apiData.value.data.imported_count} payments.`);

            // Redirect to Index
            router.visit(route('finance.payments.index'));
        } else {
            toast.error('Import failed', {
                description: apiData.value?.message || 'An unknown error occurred.',
            });
        }
    } catch (error: any) {
        toast.error('Import failed', {
            description: 'An unknown error occurred.',
        });
    } finally {
        isImporting.value = false;
    }
};
</script>

<template>

    <Head title="Import Payments" />
    <div>
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Import Payments
            </h2>
            <Link :href="(route as any)('finance.payments.index')">
                <Button variant="outline">Back to List</Button>
            </Link>
        </div>
    </div>

    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle>Upload Excel File</CardTitle>
                    <CardDescription>
                        Upload an Excel file (.xlsx, .xls) containing payment data.
                        Columns should be: Student Code, Amount, Paid Date, External Ref, Notes.
                    </CardDescription>
                </div>
                <Button variant="outline" as="a" :href="(route as any)('finance.payments.import.template')">
                    <Download class="w-4 h-4 mr-2" />
                    Download Template
                </Button>
            </div>
        </CardHeader>
        <CardContent>
            <div class="flex items-center justify-center w-full">
                <label for="dropzone-file"
                    class="flex flex-col items-center justify-center w-full h-64 border-2 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 border-gray-300">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <FileUp v-if="!isPreviewing" class="w-10 h-10 mb-3 text-gray-400" />
                        <Loader2 v-else class="w-10 h-10 mb-3 text-primary animate-spin" />

                        <p class="mb-2 text-sm text-gray-500 font-semibold" v-if="!isPreviewing">
                            Click to upload
                        </p>
                        <p class="text-xs text-gray-500" v-if="!isPreviewing">XLSX, XLS (MAX. 5MB)</p>
                        <p class="text-sm text-gray-500" v-else>Processing file...</p>
                    </div>
                    <input id="dropzone-file" type="file" ref="fileInput" class="hidden" accept=".xlsx, .xls, .csv"
                        @change="handleFileChange" :disabled="isPreviewing" />
                </label>
            </div>
        </CardContent>
    </Card>

    <div v-if="previewData" class="mt-8 space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium">Total Rows</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ previewData.summary.total }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-green-600">Valid Rows</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-green-600">{{ previewData.summary.valid }}</div>
                </CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-medium text-red-600">Invalid Rows</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-red-600">{{ previewData.summary.invalid }}</div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Preview Data</CardTitle>
                <CardDescription>Review the data below. Only valid rows will be imported.</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-[50px]">Row</TableHead>
                                <TableHead>Student</TableHead>
                                <TableHead>Amount</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Ref</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Notes/Errors</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in previewData.rows" :key="row.row"
                                :class="row.status === 'error' ? 'bg-red-50' : ''">
                                <TableCell>{{ row.row }}</TableCell>
                                <TableCell>
                                    <div v-if="row.student_name">
                                        <div class="font-medium">{{ row.student_name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ row.student_code }}
                                        </div>
                                    </div>
                                    <span v-else class="text-red-600 font-bold">{{ row.student_code
                                    }}</span>
                                </TableCell>
                                <TableCell>{{ formatCurrency(row.amount) }}</TableCell>
                                <TableCell>{{ row.paid_at }}</TableCell>
                                <TableCell class="font-mono text-xs">{{ row.external_ref }}</TableCell>
                                <TableCell>
                                    <Badge :variant="row.status === 'valid' ? 'success' : 'destructive'">
                                        {{ row.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <ul v-if="row.errors.length > 0" class="list-disc list-inside text-xs text-red-600">
                                        <li v-for="err in row.errors" :key="err">{{ err }}</li>
                                    </ul>
                                    <span v-else class="text-xs text-muted-foreground">{{ row.notes
                                    }}</span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
            <CardFooter class="flex justify-between border-t p-6">
                <Button variant="ghost" @click="previewData = null">Cancel</Button>
                <Button :disabled="isImporting || validRows.length === 0" @click="handleImport">
                    <Loader2 v-if="isImporting" class="mr-2 h-4 w-4 animate-spin" />
                    Import {{ validRows.length }} Valid Rows
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>
