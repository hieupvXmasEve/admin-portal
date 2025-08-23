<script setup lang="ts">
import { ref } from 'vue';
import type { UploadRecord } from '@/types/imageUpload';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import MultipleImageUpload from '@/components/MultipleImageUpload.vue';
import ImagePreview from '@/components/ImagePreview.vue';

// Refs
const uploadedFiles = ref<UploadRecord[]>([]);
const uploadProgress = ref(0);
const isUploading = ref(false);

// Event handlers
const handleUploadStart = () => {
    isUploading.value = true;
    uploadProgress.value = 0;
};

const handleUploadProgress = (percentage: number) => {
    uploadProgress.value = percentage;
};

const handleUploadSuccess = (files: UploadRecord[]) => {
    uploadedFiles.value.push(...files);
    isUploading.value = false;
};

const handleUploadError = (error: string) => {
    console.error('Upload error:', error);
    isUploading.value = false;
};

const handleFileUploaded = (file: UploadRecord) => {
    // Add individual file to the list if not already present
    if (!uploadedFiles.value.find(f => f.id === file.id)) {
        uploadedFiles.value.push(file);
    }
};

const handleBatchComplete = (stats: any) => {
    console.log('Batch upload complete:', stats);
    isUploading.value = false;
};

const removeUploadedFile = (id: string) => {
    const index = uploadedFiles.value.findIndex(f => f.id === id);
    if (index !== -1) {
        uploadedFiles.value.splice(index, 1);
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Multiple Image Upload Example -->
        <Card>
            <CardHeader>
                <CardTitle>Multiple Image Upload</CardTitle>
                <CardDescription>
                    Upload multiple images with individual progress tracking and batch management.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <MultipleImageUpload
                    context="example"
                    :max-files="5"
                    :max-size="5 * 1024 * 1024"
                    :allowed-types="['image/jpeg', 'image/png', 'image/webp']"
                    @upload-start="handleUploadStart"
                    @upload-progress="handleUploadProgress"
                    @upload-success="handleUploadSuccess"
                    @upload-error="handleUploadError"
                    @file-uploaded="handleFileUploaded"
                    @batch-complete="handleBatchComplete"
                />
            </CardContent>
        </Card>

        <!-- Uploaded Files Gallery -->
        <Card v-if="uploadedFiles.length > 0">
            <CardHeader>
                <CardTitle>Uploaded Images ({{ uploadedFiles.length }})</CardTitle>
                <CardDescription>
                    Successfully uploaded images with preview and management options.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div
                        v-for="file in uploadedFiles"
                        :key="file.id"
                        class="relative group"
                    >
                        <ImagePreview
                            :upload="file"
                            :show-metadata="true"
                            :show-copy-button="true"
                            thumbnail-size="lg"
                            class="h-full"
                        />

                        <!-- Remove button -->
                        <button
                            class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                            @click="removeUploadedFile(file.id)"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Upload Statistics -->
        <Card v-if="uploadedFiles.length > 0">
            <CardHeader>
                <CardTitle>Upload Statistics</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="space-y-1">
                        <p class="text-2xl font-bold text-green-600">{{ uploadedFiles.length }}</p>
                        <p class="text-sm text-gray-600">Total Uploads</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-2xl font-bold text-blue-600">
                            {{ Math.round(uploadedFiles.reduce((sum, file) => sum + file.size, 0) / 1024 / 1024 * 100) / 100 }}MB
                        </p>
                        <p class="text-sm text-gray-600">Total Size</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-2xl font-bold text-purple-600">
                            {{ [...new Set(uploadedFiles.map(f => f.mime_type))].length }}
                        </p>
                        <p class="text-sm text-gray-600">File Types</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-2xl font-bold text-orange-600">
                            {{ [...new Set(uploadedFiles.map(f => f.context))].length }}
                        </p>
                        <p class="text-sm text-gray-600">Contexts</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
