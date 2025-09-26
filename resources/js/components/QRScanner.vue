<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useQRScanner } from '@/composables/useQRScanner';
import type { Event, EventParticipant } from '@/types/event';
import { formatDateTime, formatDateTimeToShort } from '@/utils/date';
import { router } from '@inertiajs/vue3';
import { AlertCircle, Camera, CheckCircle, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { route } from 'ziggy-js';

interface Student {
    id: number;
    student_id: string;
    full_name: string;
    email: string;
    participation_status: string;
    can_check_in: boolean;
    already_checked_in: boolean;
}

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

const props = defineProps<{
    event: Event;
}>();

const emit = defineEmits<{
    checkinSuccess: [participant: EventParticipant];
}>();

// Scanner state
const videoElement = ref<HTMLVideoElement | null>(null);
const isScanning = ref(false);
const scanningStatus = ref('Position QR code within the frame');

const currentEvent = computed(() => props.event);

// Search functionality
const searchQuery = ref('');
const searchResults = ref<Student[]>([]);
const isSearching = ref(false);
const hasSearched = ref(false);

// Processing state
const isProcessing = ref(false);
const processingStudentId = ref<number | null>(null);

// Recent check-ins
const recentCheckins = ref<CheckinResult[]>([]);

// Messages
const message = ref('');
const messageType = ref<'default' | 'destructive'>('default');

// QR Scanner composable
const { startCamera, stopCamera, validateQRCode, searchStudents: apiSearchStudents, checkinStudent: apiCheckinStudent, getCameraDevices, switchCamera, availableCameras, selectedCameraId } = useQRScanner();

// Scanner controls
const startScanning = async () => {
    if (!currentEvent.value) return;

    try {
        isScanning.value = true;
        scanningStatus.value = 'Starting camera...';

        await nextTick();

        if (!videoElement.value) {
            throw new Error('Camera preview is not ready yet');
        }

        if (!availableCameras.value.length) {
            await loadAvailableCameras();
        }

        await startCamera(videoElement.value, selectedCameraId.value || undefined);
        scanningStatus.value = 'Position QR code within the frame';

        // Start QR code detection
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

    if (!isScanning.value) {
        return;
    }

    try {
        scanningStatus.value = 'Switching camera...';
        await switchCamera(deviceId);
        scanningStatus.value = 'Position QR code within the frame';
    } catch (error) {
        console.error('Failed to switch camera:', error);
        showMessage('Failed to switch camera. Please try again.', 'destructive');
        stopScanning();
    }
};

// QR Code detection
const startQRDetection = () => {
    // This would integrate with a QR code detection library
    // For now, we'll simulate the detection process
    const detectQR = () => {
        if (!isScanning.value) return;

        // In a real implementation, this would use a library like jsQR
        // to detect QR codes from the video stream

        setTimeout(detectQR, 100); // Check every 100ms
    };

    detectQR();
};

const onQRCodeDetected = async (qrCode: string) => {
    if (!currentEvent.value || isProcessing.value) return;

    try {
        isProcessing.value = true;
        scanningStatus.value = 'Validating QR code...';

        const result = await validateQRCode(qrCode, currentEvent.value.id);

        if (result.success) {
            scanningStatus.value = 'QR code validated! Processing check-in...';
            // The QR code validation would return student info
            // For now, we'll show a success message
            showMessage('QR code validated successfully!', 'default');
        } else {
            scanningStatus.value = 'Invalid QR code';
            showMessage(result.message || 'Invalid QR code', 'destructive');
        }
    } catch (error) {
        console.error('QR validation failed:', error);
        scanningStatus.value = 'Validation failed';
        showMessage('Failed to validate QR code', 'destructive');
    } finally {
        isProcessing.value = false;
        setTimeout(() => {
            if (isScanning.value) {
                scanningStatus.value = 'Position QR code within the frame';
            }
        }, 2000);
    }
};

// Student search
const onSearchInput = () => {
    if (searchQuery.value.length > 2) {
        searchStudents();
    } else {
        searchResults.value = [];
        hasSearched.value = false;
    }
};

const searchStudents = async () => {
    if (!currentEvent.value || !searchQuery.value.trim()) return;

    try {
        isSearching.value = true;
        hasSearched.value = true;

        const results = await apiSearchStudents({
            event_id: currentEvent.value.id,
            student_id: searchQuery.value,
            name: searchQuery.value,
        });

        searchResults.value = results.data || [];
    } catch (error) {
        console.error('Student search failed:', error);
        showMessage('Failed to search students', 'destructive');
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

// Check-in functionality
const checkinStudent = async (student: Student) => {
    if (!currentEvent.value || isProcessing.value) return;

    try {
        isProcessing.value = true;
        processingStudentId.value = student.id;

        const result = await apiCheckinStudent({
            event_id: currentEvent.value.id,
            student_id: student.id,
        });

        if (result.success && result.data?.participant) {
            // Add to recent check-ins
            recentCheckins.value.unshift({
                id: result.data.participant.id,
                student: result.data.participant.student,
                checkin_time: result.data.participant.checkin_time,
            });

            // Keep only last 5 check-ins
            if (recentCheckins.value.length > 5) {
                recentCheckins.value = recentCheckins.value.slice(0, 5);
            }

            // Update student status in search results
            const studentIndex = searchResults.value.findIndex((s) => s.id === student.id);
            if (studentIndex !== -1) {
                searchResults.value[studentIndex].participation_status = 'checked_in';
                searchResults.value[studentIndex].already_checked_in = true;
                searchResults.value[studentIndex].can_check_in = false;
            }

            emit('checkinSuccess', result.data.participant);
            showMessage(`${student.full_name} checked in successfully!`, 'default');
        } else {
            showMessage(result.message || 'Check-in failed', 'destructive');
        }
    } catch (error) {
        console.error('Check-in failed:', error);
        showMessage('Failed to check in student', 'destructive');
    } finally {
        isProcessing.value = false;
        processingStudentId.value = null;
    }
};

// Utility functions
const getStatusVariant = (status: string) => {
    switch (status) {
        case 'registered':
            return 'default';
        case 'checked_in':
            return 'success';
        case 'completed':
            return 'success';
        case 'cancelled':
            return 'destructive';
        default:
            return 'secondary';
    }
};

const formatStatus = (status: string) => {
    switch (status) {
        case 'not_registered':
            return 'Not Registered';
        case 'registered':
            return 'Registered';
        case 'checked_in':
            return 'Checked In';
        case 'completed':
            return 'Completed';
        case 'cancelled':
            return 'Cancelled';
        default:
            return status;
    }
};

const showMessage = (text: string, type: 'default' | 'destructive' = 'default') => {
    message.value = text;
    messageType.value = type;

    setTimeout(() => {
        clearMessage();
    }, 5000);
};

const clearMessage = () => {
    message.value = '';
};

const resetEventState = () => {
    searchResults.value = [];
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

            <!-- Recent Check-ins -->
            <div v-if="recentCheckins.length > 0" class="rounded-lg border p-4">
                <h4 class="mb-3 font-medium">Recent Check-ins</h4>
                <div class="space-y-2">
                    <div v-for="checkin in recentCheckins" :key="checkin.id" class="bg-muted flex items-center justify-between rounded p-2">
                        <div>
                            <p class="font-medium">{{ checkin.student.full_name }}</p>
                            <p class="text-muted-foreground text-sm">{{ checkin.student.student_id }} • {{ formatDateTime(checkin.checkin_time) }}</p>
                        </div>
                        <Badge variant="success"> Checked In </Badge>
                    </div>
                </div>
            </div>
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
