<script setup lang="ts">
import PhotoFrameOverlay from '@/components/PhotoFrameOverlay.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { ID_PHOTO_DIMENSIONS, usePhotoCapture } from '@/composables/usePhotoCapture';
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, Camera, Grid3X3, RotateCcw, Save, Settings, X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Props {
    studentId: number;
    returnUrl?: string;
}

const props = defineProps<Props>();

const { videoRef, canvasRef, isStreamActive, capturedPhoto, error, availableCameras, selectedCameraId, getCameraDevices, startCamera, stopCamera, switchCamera, capturePhoto, clearCapturedPhoto } = usePhotoCapture();

const isCapturing = ref(false);
const isSaving = ref(false);
const showGrid = ref(false);
const videoContainerRef = ref<HTMLElement | null>(null);

// Calculate container dimensions for the overlay
const containerDimensions = computed(() => {
    if (!videoContainerRef.value) {
        return { width: 640, height: 480 };
    }
    const rect = videoContainerRef.value.getBoundingClientRect();
    return {
        width: rect.width || 640,
        height: rect.height || 480,
    };
});

onMounted(async () => {
    // First get available cameras
    await getCameraDevices();
    // Then start the camera (preferring external cameras)
    await startCamera();
});

const handleCapture = () => {
    isCapturing.value = true;
    const success = capturePhoto();
    if (success) {
        // Stop camera after successful capture
        stopCamera();
    }
    isCapturing.value = false;
};

const handleRetake = async () => {
    clearCapturedPhoto();
    await startCamera();
};

const handleSave = async () => {
    if (!capturedPhoto.value) return;

    isSaving.value = true;

    try {
        // Create FormData for the upload
        const formData = new FormData();
        formData.append('file', capturedPhoto.value.file);

        // Use fetch for API endpoints instead of Inertia router
        const response = await fetch(`/api/uploads/student-avatar/${props.studentId}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const result = await response.json();

        if (response.ok && result.success) {
            // Store successful upload data in session storage
            sessionStorage.setItem(
                'uploaded_avatar',
                JSON.stringify({
                    id: result.data.id,
                    url: result.data.url,
                    filename: result.data.filename,
                    uploadedAt: new Date().toISOString(),
                }),
            );

            // Navigate back to edit page
            const returnUrl = props.returnUrl || route('students.edit', props.studentId);
            router.visit(returnUrl);
        } else {
            // Handle API errors
            let errorMessage = 'Upload failed. Please try again.';

            if (result.errors && typeof result.errors === 'object') {
                // Handle validation errors
                const firstError = Object.values(result.errors)[0];
                errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
            } else if (result.message) {
                errorMessage = result.message;
            }

            error.value = `Upload failed: ${errorMessage}`;
        }
    } catch (uploadError) {
        console.error('Avatar upload failed:', uploadError);

        // Set error state but don't navigate away
        // Allow user to retry with the same captured image
        error.value = `Upload failed: ${uploadError.message}. Please try again.`;
    } finally {
        isSaving.value = false;
    }
};

const handleCancel = () => {
    stopCamera();
    const returnUrl = props.returnUrl || route('students.edit', props.studentId);
    router.visit(returnUrl);
};
</script>

<template>
    <Head title="Take Photo" />

    <div class="bg-background min-h-screen">
        <div class="container mx-auto max-w-4xl p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Take Photo</h1>
                    <p class="text-muted-foreground">Capture a photo for the student profile</p>
                </div>
                <Button variant="outline" @click="handleCancel">
                    <X class="mr-2 h-4 w-4" />
                    Cancel
                </Button>
            </div>

            <!-- Camera Selection -->
            <div v-if="availableCameras.length > 1 && !capturedPhoto" class="mb-4">
                <Card>
                    <CardContent class="pt-4">
                        <div class="flex items-center gap-4">
                            <Settings class="text-muted-foreground h-4 w-4" />
                            <div class="flex-1">
                                <label class="mb-2 block text-sm font-medium">Select Camera:</label>
                                <Select :model-value="selectedCameraId || ''" @update:model-value="(value) => value && switchCamera(value)">
                                    <SelectTrigger class="w-full">
                                        <SelectValue placeholder="Choose camera..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="camera in availableCameras" :key="camera.deviceId" :value="camera.deviceId">
                                            {{ camera.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Error Display -->
            <div v-if="error" class="mb-4">
                <Card class="border-destructive">
                    <CardContent class="pt-4">
                        <div class="text-destructive flex items-center gap-2">
                            <AlertCircle class="h-4 w-4" />
                            <span>{{ error }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Camera Controls -->
            <div v-if="!capturedPhoto" class="mb-4">
                <Card>
                    <CardContent class="pt-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Grid3X3 class="text-muted-foreground h-4 w-4" />
                                <label class="text-sm font-medium">Show Grid</label>
                                <Switch v-model="showGrid" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Camera/Preview Card -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Camera class="h-5 w-5" />
                        {{ capturedPhoto ? 'ID Photo Preview (3×4)' : 'ID Photo Capture (3×4)' }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex justify-center">
                        <!-- Live Camera View -->
                        <div v-if="!capturedPhoto" ref="videoContainerRef" class="relative mx-auto w-80 overflow-hidden rounded-lg bg-black" :style="{ aspectRatio: `${ID_PHOTO_DIMENSIONS.aspectRatio}` }">
                            <video ref="videoRef" autoplay playsinline muted class="h-full w-full object-cover" />

                            <!-- 4x6 ID Photo Frame Overlay -->
                            <PhotoFrameOverlay v-if="isStreamActive" :container-width="containerDimensions.width" :container-height="containerDimensions.height" :show-grid="showGrid" :show-instructions="true" />

                            <div v-if="!isStreamActive && !error" class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center text-white">
                                    <Camera class="mx-auto mb-2 h-12 w-12" />
                                    <p>Initializing camera...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Captured Photo Preview -->
                        <div v-if="capturedPhoto" class="relative">
                            <!-- Display with 3:4 aspect ratio (portrait) -->
                            <div class="mx-auto w-64" :style="{ aspectRatio: `${ID_PHOTO_DIMENSIONS.aspectRatio}` }">
                                <img :src="capturedPhoto.dataUrl" alt="Captured ID photo" class="h-full w-full rounded-lg object-cover shadow-lg" />
                            </div>

                            <!-- Photo info -->
                            <div class="mt-4 text-center">
                                <div class="bg-muted inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs">
                                    <span>{{ capturedPhoto.dimensions?.width || ID_PHOTO_DIMENSIONS.width }}×{{ capturedPhoto.dimensions?.height || ID_PHOTO_DIMENSIONS.height }}px</span>
                                    <span class="text-muted-foreground">•</span>
                                    <span>3×4 inches</span>
                                </div>
                            </div>
                        </div>

                        <!-- Hidden canvas for photo capture -->
                        <canvas ref="canvasRef" class="hidden" />
                    </div>
                </CardContent>
            </Card>

            <!-- Action Buttons -->
            <div class="mt-6 flex justify-center gap-4">
                <!-- Capture Mode -->
                <template v-if="!capturedPhoto">
                    <Button size="lg" :disabled="!isStreamActive || isCapturing || !!error" @click="handleCapture">
                        <Camera class="mr-2 h-5 w-5" />
                        {{ isCapturing ? 'Capturing...' : 'Capture' }}
                    </Button>
                </template>

                <!-- Preview Mode -->
                <template v-else>
                    <Button variant="outline" size="lg" @click="handleRetake" :disabled="isSaving">
                        <RotateCcw class="mr-2 h-5 w-5" />
                        Retake
                    </Button>
                    <Button size="lg" @click="handleSave" :disabled="isSaving">
                        <Save class="mr-2 h-5 w-5" />
                        {{ isSaving ? 'Uploading to server...' : 'Use Photo' }}
                    </Button>
                </template>
            </div>

            <!-- Instructions -->
            <div class="mt-8">
                <Card>
                    <CardContent class="pt-4">
                        <div class="text-muted-foreground text-center text-sm">
                            <template v-if="!capturedPhoto">
                                <h3 class="text-foreground mb-3 font-medium">ID Photo Requirements</h3>
                                <div class="mx-auto grid max-w-md gap-2 text-left">
                                    <p>✓ Position your face within the dashed frame</p>
                                    <p>✓ Look directly at the camera with a neutral expression</p>
                                    <p>✓ Ensure good, even lighting on your face</p>
                                    <p>✓ Keep your head straight and centered</p>
                                    <p>✓ Make sure the background is clear</p>
                                </div>
                                <p class="text-muted-foreground mt-4 text-xs">Photo will be automatically cropped to 3×4 inches (450×600 pixels)</p>
                            </template>
                            <template v-else>
                                <p class="mb-2">Review your ID photo above.</p>
                                <p>The photo has been cropped to standard 3×4 inch dimensions.</p>
                                <p class="mt-2">Click <strong>Use Photo</strong> to save or <strong>Retake</strong> to capture a new one.</p>
                            </template>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
