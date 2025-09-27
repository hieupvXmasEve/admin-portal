<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useQRScanner } from '@/composables/useQRScanner';
import type { Event, EventParticipant } from '@/types/event';
import { formatDateTimeToShort } from '@/utils/date';
import { router } from '@inertiajs/vue3';
import { AlertCircle, Camera, CheckCircle, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

interface CheckinResult {
    id: number;
    student: {
        id: number;
        student_id: string;
        full_name: string;
        email: string;
    };
    checkin_time: string;
}

interface ScanResultPayload {
    success: boolean;
    message: string;
    participant?: EventParticipant;
    fromScanner?: boolean;
}

const props = defineProps<{
    event: Event;
}>();

const emit = defineEmits<{
    scan: [qrCode: string];
    checkinSuccess: [participant: EventParticipant];
}>();

// Scanner state
const videoElement = ref<HTMLVideoElement | null>(null);
const isScanning = ref(false);
const scanningStatus = ref('Position QR code within the frame');

const currentEvent = computed(() => props.event);
// Processing state
const isProcessing = ref(false);
const processingStudentId = ref<number | null>(null);

// Recent check-ins
const recentCheckins = ref<CheckinResult[]>([]);

// Messages
const message = ref('');
const messageType = ref<'default' | 'destructive'>('default');

// QR Scanner composable
const { startCamera, stopCamera, getCameraDevices, switchCamera, availableCameras, selectedCameraId, startDecoding, isDecoding } = useQRScanner();

// Scanner controls
const startScanning = async () => {
    if (!currentEvent.value) return;

    try {
        isScanning.value = true;
        scanningStatus.value = 'Starting camera...';
        console.debug('[QRScanner] startScanning');

        await nextTick();

        if (!videoElement.value) {
            throw new Error('Camera preview is not ready yet');
        }

        if (!availableCameras.value.length) {
            await loadAvailableCameras();
        }
        console.debug('[QRScanner] selectedCameraId before start', selectedCameraId.value);

        await startCamera(videoElement.value, selectedCameraId.value || undefined);
        scanningStatus.value = 'Position QR code within the frame';

        startQRDetection();
    } catch (error) {
        console.error('Failed to start camera:', error);
        showMessage('Failed to access camera. Please check permissions.', 'destructive');
        isScanning.value = false;
    }
};

const stopScanning = () => {
    isScanning.value = false;
    scanningStatus.value = 'Scanner stopped';
    console.debug('[QRScanner] stopScanning');
    stopCamera();
};

const loadAvailableCameras = async () => {
    try {
        await getCameraDevices();
    } catch (error) {
        console.error('Failed to load camera devices:', error);
    }
};

const onCameraChange = async (deviceId: string | undefined) => {
    if (!deviceId) return;

    selectedCameraId.value = deviceId;
    console.debug('[QRScanner] onCameraChange', deviceId);

    if (!isScanning.value) {
        return;
    }

    try {
        scanningStatus.value = 'Switching camera...';
        await switchCamera(deviceId);
        scanningStatus.value = 'Position QR code within the frame';

        startQRDetection();
    } catch (error) {
        console.error('Failed to switch camera:', error);
        showMessage('Failed to switch camera. Please try again.', 'destructive');
        stopScanning();
    }
};

// QR Code detection
const startQRDetection = () => {
    if (!isScanning.value || !videoElement.value || isDecoding.value) {
        return;
    }

    console.debug('[QRScanner] startQRDetection');
    startDecoding(
        videoElement.value,
        (qrText) => {
            if (!currentEvent.value) {
                return;
            }

            isProcessing.value = true;
            isScanning.value = false;
            scanningStatus.value = 'QR code detected, processing...';
            console.debug('[QRScanner] decoded QR text', qrText);
            emit('scan', qrText);
        },
        (decodeError) => {
            console.error('QR decoding failed:', decodeError);
            scanningStatus.value = 'Unable to read QR code. Adjust lighting and try again.';

            setTimeout(() => {
                if (!isProcessing.value) {
                    scanningStatus.value = 'Position QR code within the frame';
                }
            }, 1500);
        },
    );
};

const handleScanResult = ({ success, message: feedbackMessage, participant, fromScanner }: ScanResultPayload) => {
    isProcessing.value = false;
    processingStudentId.value = null;

    if (success) {
        if (participant && participant.student) {
            recentCheckins.value.unshift({
                id: participant.id,
                student: {
                    id: participant.student.id,
                    student_id: participant.student.student_id,
                    full_name: participant.student.full_name,
                    email: participant.student.email,
                },
                checkin_time: participant.checkin_time || new Date().toISOString(),
            });

            if (recentCheckins.value.length > 5) {
                recentCheckins.value = recentCheckins.value.slice(0, 5);
            }

            emit('checkinSuccess', participant);
        } else {
            console.warn('[QRScanner] Successful check-in missing participant/student data');
        }
    }

    showMessage(feedbackMessage, success ? 'default' : 'destructive');

    if (fromScanner !== false) {
        scanningStatus.value = success ? 'Scan complete. Start the scanner again for the next attendee.' : 'Scan failed. Restart the scanner to try again.';

        setTimeout(() => {
            if (!isProcessing.value) {
                scanningStatus.value = 'Position QR code within the frame';
            }
        }, 2000);
    }
};

const showMessage = (text: string, type: 'default' | 'destructive' = 'default') => {
    message.value = text;
    messageType.value = type;
    if (type === 'default') {
        toast.success(text, { duration: 5000 });
    } else {
        toast.error(text, { duration: 5000 });
    }
    // setTimeout(() => {
    //     clearMessage();
    // }, 5000);
};

const clearMessage = () => {
    message.value = '';
};

const resetEventState = () => {
    recentCheckins.value = [];
    clearMessage();
};

watch(
    () => props.event,
    () => {
        if (isScanning.value) {
            stopScanning();
        }
        resetEventState();
        void loadAvailableCameras();
    },
);

// Lifecycle
onMounted(() => {
    resetEventState();
    void loadAvailableCameras();
});

onUnmounted(() => {
    if (isScanning.value) {
        stopScanning();
    }
});

defineExpose({
    handleScanResult,
    startScanning,
    stopScanning,
});
</script>

<template>
    <div class="qr-scanner">
        <!-- Scanner Header -->
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold">QR Code Scanner</h3>
                <p class="text-muted-foreground text-sm">Scan student QR codes to check them into the event</p>
            </div>
            <Button v-if="!isScanning" @click="startScanning" :disabled="!currentEvent" class="flex items-center gap-2">
                <Camera class="h-4 w-4" />
                Start Scanner
            </Button>
            <Button v-else @click="stopScanning" variant="destructive" class="flex items-center gap-2">
                <X class="h-4 w-4" />
                Stop Scanner
            </Button>
        </div>

        <!-- Selected Event Info -->
        <div v-if="currentEvent" class="bg-muted mb-6 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="font-medium">{{ currentEvent.title }}</h4>
                    <p class="text-muted-foreground text-sm">{{ currentEvent.location }} • {{ formatDateTimeToShort(currentEvent.start_time) }}</p>
                </div>
                <Button variant="outline" size="sm" @click="router.visit(route('events.show', currentEvent.id))"> View Event </Button>
            </div>
        </div>

        <!-- Camera Selection -->
        <div v-if="currentEvent && availableCameras.length > 1" class="mb-6">
            <Label for="camera-select">Select Camera</Label>
            <Select :model-value="selectedCameraId || ''" @update:model-value="onCameraChange">
                <SelectTrigger>
                    <SelectValue placeholder="Choose a camera" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="camera in availableCameras" :key="camera.deviceId" :value="camera.deviceId">
                        {{ camera.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Scanner Interface -->
        <div v-if="currentEvent" class="space-y-6">
            <!-- Camera View -->
            <div class="relative">
                <div v-if="isScanning" class="relative overflow-hidden rounded-lg bg-black" style="aspect-ratio: 4/3">
                    <video ref="videoElement" class="h-full w-full object-cover" autoplay muted playsinline></video>

                    <!-- Scanning Overlay -->
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="relative h-64 w-64 rounded-lg border-2 border-white">
                            <div class="border-primary absolute top-0 left-0 h-8 w-8 rounded-tl-lg border-t-4 border-l-4"></div>
                            <div class="border-primary absolute top-0 right-0 h-8 w-8 rounded-tr-lg border-t-4 border-r-4"></div>
                            <div class="border-primary absolute bottom-0 left-0 h-8 w-8 rounded-bl-lg border-b-4 border-l-4"></div>
                            <div class="border-primary absolute right-0 bottom-0 h-8 w-8 rounded-br-lg border-r-4 border-b-4"></div>
                        </div>
                    </div>

                    <!-- Scanning Status -->
                    <div class="absolute right-4 bottom-4 left-4">
                        <div class="rounded-lg bg-black/70 px-4 py-2 text-center text-white">
                            <p class="text-sm">
                                {{ scanningStatus }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Camera Placeholder -->
                <div v-else class="bg-muted flex items-center justify-center rounded-lg" style="aspect-ratio: 4/3">
                    <div class="text-center">
                        <Camera class="text-muted-foreground mx-auto mb-4 h-16 w-16" />
                        <p class="text-muted-foreground">Camera will appear here when scanning</p>
                    </div>
                </div>
            </div>

            <!-- Manual Student Search -->
            <!-- <div class="rounded-lg border p-4">
                <h4 class="mb-3 font-medium">Manual Check-in</h4>
                <div class="flex gap-2">
                    <div class="flex-1">
                        <Input v-model="searchQuery" placeholder="Search by student ID or name..." @input="onSearchInput" :disabled="isProcessing" />
                    </div>
                    <Button @click="searchStudents" :disabled="!searchQuery.trim() || isProcessing" :loading="isSearching">
                        <Search class="h-4 w-4" />
                    </Button>
                </div>

                <div v-if="searchResults.length > 0" class="mt-4 space-y-2">
                    <div v-for="student in searchResults" :key="student.id" class="flex items-center justify-between rounded-lg border p-3">
                        <div>
                            <p class="font-medium">{{ student.full_name }}</p>
                            <p class="text-muted-foreground text-sm">{{ student.student_id }} • {{ student.email }}</p>
                            <Badge :variant="getStatusVariant(student.participation_status)" class="mt-1">
                                {{ formatStatus(student.participation_status) }}
                            </Badge>
                        </div>
                        <Button @click="checkinStudent(student)" :disabled="!student.can_check_in || student.already_checked_in || isProcessing" :loading="processingStudentId === student.id" size="sm">
                            {{ student.already_checked_in ? 'Already Checked In' : 'Check In' }}
                        </Button>
                    </div>
                </div>

                <div v-else-if="hasSearched && !isSearching" class="text-muted-foreground mt-4 text-center">
                    <p>No students found matching your search.</p>
                </div>
            </div> -->
        </div>

        <!-- Success/Error Messages -->
        <div v-if="message" class="mt-4">
            <Alert :variant="messageType">
                <AlertCircle v-if="messageType === 'destructive'" class="h-4 w-4" />
                <CheckCircle v-else class="h-4 w-4" />
                <AlertTitle>
                    {{ messageType === 'destructive' ? 'Error' : 'Success' }}
                </AlertTitle>
                <AlertDescription>
                    {{ message }}
                </AlertDescription>
            </Alert>
        </div>
    </div>
</template>

<style scoped>
/* .qr-scanner {
  @apply max-w-4xl mx-auto;
} */
</style>
