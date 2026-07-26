<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, ExternalLink, FileText } from 'lucide-vue-next';
import { computed } from 'vue';

interface ApplicationDocument {
    id: number;
    page_index: number;
    original_name: string | null;
    link: string;
    mime_type: string | null;
    size: number | null;
    status: string | null;
}

interface DocumentGroup {
    code: string;
    name: string;
    required: boolean;
    is_missing: boolean;
    documents: ApplicationDocument[];
}

interface DocumentChecklist {
    groups: DocumentGroup[];
    missing_required: string[];
}

interface ApplicationSummary {
    id: number;
    full_name: string;
    student_code: string;
    campus_code: string;
    status: 'pending' | 'enrolled' | 'rejected';
    is_international_applicant: boolean;
}

interface Props {
    application: ApplicationSummary;
    documentChecklist: DocumentChecklist;
}

const props = defineProps<Props>();

const groups = computed<DocumentGroup[]>(() => props.documentChecklist?.groups ?? []);
const missingRequired = computed<string[]>(() => props.documentChecklist?.missing_required ?? []);
const hasDocuments = computed(() => groups.value.some((group) => group.documents.length > 0));

const statusBadgeClass = (status: ApplicationSummary['status']): string => {
    switch (status) {
        case 'enrolled':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
        case 'rejected':
            return 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300';
        default:
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    }
};

const formatFileSize = (bytes: number | null): string => {
    if (!bytes) {
        return '';
    }
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit += 1;
    }
    return `${value.toFixed(value < 10 && unit > 0 ? 1 : 0)} ${units[unit]}`;
};
</script>

<template>
    <Head :title="`Documents — ${application.full_name}`" />

    <div class="mx-auto max-w-3xl space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ application.full_name }}</h1>
                <div class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                    <span>{{ application.student_code }}</span>
                    <span>·</span>
                    <span>{{ application.campus_code }}</span>
                    <span v-if="application.is_international_applicant" class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-800 dark:bg-sky-900/40 dark:text-sky-300"> International </span>
                </div>
            </div>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize" :class="statusBadgeClass(application.status)">
                {{ application.status }}
            </span>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Documents</CardTitle>
                <CardDescription>External link references from the admissions catalog, grouped by type. Links open the source file.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-if="missingRequired.length > 0" class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50/60 p-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/10 dark:text-amber-300">
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    <span
                        ><span class="font-semibold">{{ missingRequired.length }} required document(s) missing.</span></span
                    >
                </div>

                <p v-if="groups.length === 0" class="text-muted-foreground text-sm">No document types in the catalog yet.</p>
                <p v-else-if="!hasDocuments && missingRequired.length === 0" class="text-muted-foreground text-sm">No documents recorded yet.</p>

                <div v-for="group in groups" :key="group.code" class="rounded-lg border p-4" :class="group.is_missing ? 'border-amber-300 bg-amber-50/40 dark:border-amber-700 dark:bg-amber-900/10' : ''">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-semibold">{{ group.name }}</p>
                        <Badge v-if="group.required" variant="secondary">Required</Badge>
                        <Badge v-if="group.is_missing" class="bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Missing</Badge>
                    </div>

                    <ul v-if="group.documents.length > 0" class="mt-2 space-y-1.5">
                        <li v-for="doc in group.documents" :key="doc.id" class="flex items-center gap-2 text-sm">
                            <FileText class="text-muted-foreground h-4 w-4 shrink-0" />
                            <a :href="doc.link" target="_blank" rel="noopener noreferrer" class="text-primary inline-flex items-center gap-1 font-medium hover:underline">
                                {{ doc.original_name ?? doc.link }}
                                <ExternalLink class="h-3 w-3" />
                            </a>
                            <span v-if="doc.size" class="text-muted-foreground text-xs">{{ formatFileSize(doc.size) }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-muted-foreground mt-1 text-sm">No file provided.</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
