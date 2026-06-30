<script setup lang="ts">
import FileUpload from '@/components/FileUpload.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { UploadedFile } from '@/types/fileUpload';
import type { EgcIeltsRecord, EgcPanel } from '@/types/models';
import { useForm } from '@inertiajs/vue3';
import { AlertTriangle, FileText, GraduationCap, Languages, Upload } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface Props {
    egc: EgcPanel;
    canChangeStatus?: boolean;
}

withDefaults(defineProps<Props>(), {
    canChangeStatus: false,
});

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '—' : date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

// A certificate's scan is "missing" when no upload is attached, regardless of the
// explicit flag — both states mean there is nothing to view.
const scanMissing = (cert: EgcIeltsRecord): boolean => cert.upload_record === null || cert.missing_documents;

// Attach a scan onto an existing certificate that is missing one. Posts the
// pre-uploaded record id to the same write route the retired placement page used
// (still gated server-side by change_student_status).
const uploadOpen = ref(false);
const selectedCert = ref<EgcIeltsRecord | null>(null);
const uploadedFiles = ref<UploadedFile[]>([]);
const uploadForm = useForm({
    upload_record_id: null as number | null,
});

watch(uploadedFiles, (files) => {
    uploadForm.upload_record_id = files.length > 0 ? files[0].id : null;
});

const openUpload = (cert: EgcIeltsRecord): void => {
    selectedCert.value = cert;
    uploadedFiles.value = [];
    uploadForm.reset();
    uploadOpen.value = true;
};

const submitUpload = (): void => {
    if (selectedCert.value === null) {
        return;
    }

    uploadForm.post(route('students.ielts-certificates.document.upload', selectedCert.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('IELTS scan uploaded successfully');
            uploadOpen.value = false;
            selectedCert.value = null;
            uploadedFiles.value = [];
            uploadForm.reset();
        },
        onError: () => {
            toast.error('Failed to upload IELTS scan.');
        },
    });
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2 text-base">
                <Languages class="h-4 w-4" />
                EGC English level & IELTS
            </CardTitle>
            <CardDescription> English-level history and IELTS certificates sit here, out of the main timeline (ADR-0009). </CardDescription>
        </CardHeader>
        <CardContent class="space-y-5">
            <div class="flex flex-wrap gap-6">
                <div>
                    <p class="text-muted-foreground text-xs uppercase tracking-wide">Starting level</p>
                    <p class="text-lg font-semibold">{{ egc.starting_level ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-muted-foreground text-xs uppercase tracking-wide">Current level</p>
                    <p class="text-lg font-semibold">{{ egc.current_level ?? '—' }}</p>
                </div>
            </div>

            <div>
                <p class="mb-2 flex items-center gap-1.5 text-sm font-medium">
                    <GraduationCap class="h-4 w-4" />
                    Progression history
                </p>
                <div v-if="egc.history.length === 0" class="text-muted-foreground text-sm">No level changes or IELTS events recorded.</div>
                <ul v-else class="space-y-2">
                    <li v-for="row in egc.history" :key="row.id" class="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                        <div>
                            <span class="font-medium">{{ row.label }}</span>
                            <span v-if="row.from_english_level !== null && row.to_english_level !== null" class="text-muted-foreground ml-2">
                                L{{ row.from_english_level }} → L{{ row.to_english_level }}
                            </span>
                        </div>
                        <span class="text-muted-foreground">{{ formatDate(row.occurred_at) }}</span>
                    </li>
                </ul>
            </div>

            <div>
                <p class="mb-2 text-sm font-medium">IELTS certificates</p>
                <div v-if="egc.ielts.length === 0" class="text-muted-foreground text-sm">No IELTS certificates recorded.</div>
                <ul v-else class="space-y-2">
                    <li v-for="cert in egc.ielts" :key="cert.id" class="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold">{{ cert.overall_score ?? '—' }}</span>
                            <Badge v-if="scanMissing(cert)" variant="outline" class="flex items-center gap-1 border-amber-300 text-amber-700">
                                <AlertTriangle class="h-3 w-3" />
                                Scan missing
                            </Badge>
                        </div>
                        <div class="flex items-center gap-3">
                            <a
                                v-if="cert.upload_record"
                                :href="cert.upload_record.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-primary inline-flex items-center gap-1 hover:underline"
                            >
                                <FileText class="h-3.5 w-3.5" />
                                View scan
                            </a>
                            <Button
                                v-else-if="canChangeStatus"
                                type="button"
                                variant="outline"
                                size="sm"
                                class="h-7 gap-1 px-2"
                                @click="openUpload(cert)"
                            >
                                <Upload class="h-3.5 w-3.5" />
                                Upload scan
                            </Button>
                            <span class="text-muted-foreground">{{ formatDate(cert.issue_date) }}</span>
                        </div>
                    </li>
                </ul>
            </div>

            <Dialog v-model:open="uploadOpen">
                <DialogContent class="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Upload IELTS scan</DialogTitle>
                        <DialogDescription>
                            Attach the supporting document for the IELTS certificate<template v-if="selectedCert">
                                (score {{ selectedCert.overall_score ?? '—' }})</template
                            >.
                        </DialogDescription>
                    </DialogHeader>
                    <form class="space-y-4" @submit.prevent="submitUpload">
                        <FileUpload
                            v-model="uploadedFiles"
                            context="ielts_certificate"
                            :upload-immediately="true"
                            accept=".pdf,.jpg,.jpeg,.png"
                            label="Drop the IELTS certificate scan here or click to upload"
                        />
                        <DialogFooter>
                            <Button type="button" variant="outline" @click="uploadOpen = false">Cancel</Button>
                            <Button type="submit" :disabled="uploadForm.processing || !uploadForm.upload_record_id">Save scan</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </CardContent>
    </Card>
</template>
