<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { AlertCircle, ArrowLeft, CheckCircle2, Download, Upload, XCircle } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';
import { route } from 'ziggy-js';

interface PreviewRow {
    row: number;
    student_id: string;
    amount: number | null;
    description: string;
    status: 'valid' | 'error' | 'warning';
    message?: string;
    student_name?: string;
}

interface PreviewData {
    rows: PreviewRow[];
    summary: {
        total: number;
        valid: number;
        errors: number;
        warnings: number;
    };
}

interface Props {
    preview?: PreviewData;
}

const props = defineProps<Props>();
const page = usePage();

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const isUploading = ref(false);
const isProcessing = ref(false);

const hasPreview = computed(() => !!props.preview && props.preview.rows.length > 0);
const canProcess = computed(() => hasPreview.value && props.preview!.summary.errors === 0);

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        selectedFile.value = target.files[0];
    }
};

const handleUploadClick = () => {
    fileInput.value?.click();
};

const handlePreview = () => {
    if (!selectedFile.value) return;

    isUploading.value = true;

    router.post(
        route('wallets.preview'),
        {
            file: selectedFile.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isUploading.value = false;
            },
        }
    );
};

const handleProcess = () => {
    if (!canProcess.value) return;

    isProcessing.value = true;

    router.post(
        route('wallets.process'),
        {},
        {
            preserveState: false,
            onSuccess: () => {
                router.visit(route('wallets.index'));
            },
            onFinish: () => {
                isProcessing.value = false;
            },
        }
    );
};

const handleDownloadTemplate = () => {
    window.location.href = route('wallets.template.download');
};

const handleReset = () => {
    selectedFile.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    router.visit(route('wallets.import'), {
        preserveState: false,
    });
};

const getStatusBadgeClass = (status: string) => {
    switch (status) {
        case 'valid':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'error':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'warning':
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

const previewColumns: ColumnDef<PreviewRow>[] = [
    {
        accessorKey: 'row',
        header: 'Row',
        cell: ({ row }) => h('span', { class: 'font-mono text-sm' }, row.original.row),
    },
    {
        accessorKey: 'student_id',
        header: 'Student ID',
        cell: ({ row }) => h('span', { class: 'font-mono' }, row.original.student_id),
    },
    {
        accessorKey: 'student_name',
        header: 'Student Name',
        cell: ({ row }) => row.original.student_name || '-',
    },
    {
        accessorKey: 'amount',
        header: 'Amount',
        cell: ({ row }) => {
            if (row.original.amount === null) {
                return h('span', { class: 'text-red-600' }, 'Invalid');
            }
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND',
            }).format(row.original.amount);
        },
    },
    {
        accessorKey: 'description',
        header: 'Description',
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.original.status;
            const message = row.original.message;

            return h('div', { class: 'flex items-center gap-2' }, [
                status === 'valid' && h(CheckCircle2, { class: 'h-4 w-4 text-green-600' }),
                status === 'error' && h(XCircle, { class: 'h-4 w-4 text-red-600' }),
                status === 'warning' && h(AlertCircle, { class: 'h-4 w-4 text-yellow-600' }),
                h(
                    'span',
                    {
                        class: `text-xs font-medium px-2 py-1 rounded border ${getStatusBadgeClass(status)}`,
                    },
                    message || status
                ),
            ]);
        },
    },
];
</script>

<template>
    <Head title="Bulk Import Wallets" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <Link :href="route('wallets.index')">
                    <Button variant="outline" size="icon">
                        <ArrowLeft class="h-4 w-4" />
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Bulk Import Deposits</h1>
                    <p class="text-muted-foreground">Upload Excel file to deposit funds to multiple wallets</p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Step 1: Download Template</CardTitle>
                    <CardDescription>Download the Excel template file to ensure correct format</CardDescription>
                </CardHeader>
                <CardContent>
                    <Button variant="outline" class="w-full" @click="handleDownloadTemplate">
                        <Download class="mr-2 h-4 w-4" />
                        Download Template
                    </Button>
                    <div class="mt-4 text-sm text-muted-foreground">
                        <p class="font-medium">Template includes:</p>
                        <ul class="mt-2 list-inside list-disc space-y-1">
                            <li>Student ID (required)</li>
                            <li>Amount (required, minimum 1,000 VND)</li>
                            <li>Description (optional)</li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Step 2: Upload File</CardTitle>
                    <CardDescription>Select your Excel file to preview and validate data</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="file-input">Excel File</Label>
                        <input ref="fileInput" id="file-input" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="handleFileSelect" />
                        <div class="flex items-center gap-2">
                            <Button variant="outline" class="flex-1" @click="handleUploadClick">
                                <Upload class="mr-2 h-4 w-4" />
                                {{ selectedFile ? 'Change File' : 'Select File' }}
                            </Button>
                        </div>
                        <p v-if="selectedFile" class="text-sm text-muted-foreground">Selected: {{ selectedFile.name }}</p>
                    </div>

                    <Button :disabled="!selectedFile || isUploading" class="w-full" @click="handlePreview">
                        {{ isUploading ? 'Processing...' : 'Preview Data' }}
                    </Button>
                </CardContent>
            </Card>
        </div>

        <Card v-if="hasPreview">
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>Step 3: Review & Import</CardTitle>
                        <CardDescription>Review the data and fix any errors before importing</CardDescription>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" @click="handleReset">Reset</Button>
                        <Button :disabled="!canProcess || isProcessing" @click="handleProcess">
                            {{ isProcessing ? 'Importing...' : 'Confirm Import' }}
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-4 gap-4">
                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-center">
                                <p class="text-2xl font-bold">{{ preview!.summary.total }}</p>
                                <p class="text-sm text-muted-foreground">Total Rows</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-green-600">{{ preview!.summary.valid }}</p>
                                <p class="text-sm text-muted-foreground">Valid</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-red-600">{{ preview!.summary.errors }}</p>
                                <p class="text-sm text-muted-foreground">Errors</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-6">
                            <div class="text-center">
                                <p class="text-2xl font-bold text-yellow-600">{{ preview!.summary.warnings }}</p>
                                <p class="text-sm text-muted-foreground">Warnings</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div v-if="!canProcess" class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <div class="flex items-start gap-3">
                        <XCircle class="h-5 w-5 text-red-600" />
                        <div>
                            <h3 class="font-semibold text-red-900">Cannot Import</h3>
                            <p class="text-sm text-red-700">Please fix all errors before importing. Check the table below for details.</p>
                        </div>
                    </div>
                </div>

                <DataTable :columns="previewColumns" :data="preview!.rows" />
            </CardContent>
        </Card>
    </div>
</template>
