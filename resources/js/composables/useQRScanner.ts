import { ref } from 'vue';

interface CameraDevice {
    deviceId: string;
    label: string;
    kind: string;
}

interface QRValidationRequest {
    qr_code: string;
    event_id: number;
}

interface QRValidationResponse {
    success: boolean;
    message: string;
    data?: {
        event: {
            id: number;
            title: string;
            location: string;
            start_time: string;
            end_time: string;
            gold_reward_amount: number;
        };
    };
}

interface StudentSearchRequest {
    event_id: number;
    student_id?: string;
    name?: string;
    limit?: number;
}

interface StudentSearchResponse {
    success: boolean;
    data: Array<{
        id: number;
        student_id: string;
        full_name: string;
        email: string;
        participation_status: string;
        can_check_in: boolean;
        already_checked_in: boolean;
    }>;
}

interface CheckinRequest {
    event_id: number;
    student_id: number;
    qr_code?: string;
    force_register?: boolean;
}

interface CheckinResponse {
    success: boolean;
    message: string;
    data?: {
        participant: {
            id: number;
            status: string;
            checkin_time: string;
            student: {
                id: number;
                student_id: string;
                full_name: string;
                email: string;
            };
            event: {
                id: number;
                title: string;
                gold_reward_amount: number;
            };
        };
    };
}

export function useQRScanner() {
    const stream = ref<MediaStream | null>(null);
    const isLoading = ref(false);
    const error = ref<string | null>(null);
    const availableCameras = ref<CameraDevice[]>([]);
    const selectedCameraId = ref<string | null>(null);
    const isStreamActive = ref(false);
    const activeVideoElement = ref<HTMLVideoElement | null>(null);

    const selectPreferredCamera = (devices: CameraDevice[]): string | null => {
        if (devices.length === 0) {
            return null;
        }

        const preferred = devices.find((device) => /back|rear|environment/i.test(device.label));
        return preferred?.deviceId || devices[0].deviceId;
    };

    const updateAvailableCameras = async (): Promise<CameraDevice[]> => {
        try {
            if (!navigator.mediaDevices?.enumerateDevices) {
                return [];
            }

            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices
                .filter((device) => device.kind === 'videoinput')
                .map((device, index) => ({
                    deviceId: device.deviceId,
                    label: device.label || `Camera ${index + 1}`,
                    kind: device.kind,
                }));

            availableCameras.value = videoDevices;

            if (videoDevices.length > 0 && (!selectedCameraId.value || !videoDevices.some((device) => device.deviceId === selectedCameraId.value))) {
                selectedCameraId.value = selectPreferredCamera(videoDevices);
            }

            return videoDevices;
        } catch (err) {
            console.error('Failed to enumerate camera devices:', err);
            availableCameras.value = [];
            return [];
        }
    };

    const getCameraDevices = async (): Promise<CameraDevice[]> => {
        return await updateAvailableCameras();
    };

    /**
     * Start camera for QR scanning
     */
    const startCamera = async (videoElement: HTMLVideoElement, deviceId?: string): Promise<void> => {
        try {
            isLoading.value = true;
            error.value = null;
            activeVideoElement.value = videoElement;

            if (stream.value) {
                stopCamera();
            }

            const targetDeviceId = deviceId || selectedCameraId.value;

            // Request camera access
            const mediaStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    ...(targetDeviceId ? { deviceId: { exact: targetDeviceId } } : { facingMode: 'environment' }),
                },
            });

            stream.value = mediaStream;
            videoElement.srcObject = mediaStream;

            // Wait for video to be ready
            await new Promise<void>((resolve, reject) => {
                videoElement.onloadedmetadata = () => {
                    videoElement
                        .play()
                        .then(() => resolve())
                        .catch(reject);
                };
                videoElement.onerror = reject;
            });

            isStreamActive.value = true;

            const videoTracks = mediaStream.getVideoTracks();
            const activeTrack = videoTracks[0];
            const activeDeviceId = activeTrack?.getSettings().deviceId;

            if (activeDeviceId) {
                selectedCameraId.value = activeDeviceId;
            } else if (targetDeviceId) {
                selectedCameraId.value = targetDeviceId;
            }

            await updateAvailableCameras();
        } catch (err) {
            console.error('Failed to start camera:', err);
            error.value = 'Failed to access camera. Please check permissions.';
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Stop camera stream
     */
    const stopCamera = (): void => {
        if (stream.value) {
            stream.value.getTracks().forEach((track) => track.stop());
            stream.value = null;
        }
        if (activeVideoElement.value) {
            activeVideoElement.value.srcObject = null;
        }
        isStreamActive.value = false;
    };

    const switchCamera = async (deviceId: string): Promise<void> => {
        selectedCameraId.value = deviceId;

        if (!isStreamActive.value || !activeVideoElement.value) {
            return;
        }

        await startCamera(activeVideoElement.value, deviceId);
    };

    /**
     * Validate QR code with the server
     */
    const validateQRCode = async (qrCode: string, eventId: number): Promise<QRValidationResponse> => {
        try {
            isLoading.value = true;
            error.value = null;

            const response = await fetch('/api/events/validate-qr', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    qr_code: qrCode,
                    event_id: eventId,
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'QR validation failed');
            }

            return data;
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'QR validation failed';
            error.value = errorMessage;
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Search for students
     */
    const searchStudents = async (params: StudentSearchRequest): Promise<StudentSearchResponse> => {
        try {
            isLoading.value = true;
            error.value = null;

            const response = await fetch('/api/events/search-student', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify(params),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Student search failed');
            }

            return data;
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Student search failed';
            error.value = errorMessage;
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Check in a student
     */
    const checkinStudent = async (params: CheckinRequest): Promise<CheckinResponse> => {
        try {
            isLoading.value = true;
            error.value = null;

            const response = await fetch('/api/events/checkin', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify(params),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Check-in failed');
            }

            return data;
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Check-in failed';
            error.value = errorMessage;
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Get event participants
     */
    const getEventParticipants = async (eventId: number, filters: Record<string, any> = {}) => {
        try {
            isLoading.value = true;
            error.value = null;

            const params = new URLSearchParams(filters);
            const response = await fetch(`/api/events/${eventId}/participants?${params}`, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to get participants');
            }

            return data;
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to get participants';
            error.value = errorMessage;
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Get event statistics
     */
    const getEventStatistics = async (eventId: number) => {
        try {
            isLoading.value = true;
            error.value = null;

            const response = await fetch(`/api/events/${eventId}/statistics`, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to get statistics');
            }

            return data;
        } catch (err) {
            const errorMessage = err instanceof Error ? err.message : 'Failed to get statistics';
            error.value = errorMessage;
            throw err;
        } finally {
            isLoading.value = false;
        }
    };

    /**
     * Check if browser supports camera access
     */
    const isCameraSupported = (): boolean => {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    };

    /**
     * Request camera permissions
     */
    const requestCameraPermission = async (): Promise<boolean> => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            stream.getTracks().forEach((track) => track.stop());
            return true;
        } catch (err) {
            console.error('Camera permission denied:', err);
            return false;
        }
    };

    return {
        // State
        isLoading,
        error,
        stream,
        isStreamActive,
        availableCameras,
        selectedCameraId,

        // Camera controls
        startCamera,
        stopCamera,
        getCameraDevices,
        switchCamera,
        isCameraSupported,
        requestCameraPermission,

        // API methods
        validateQRCode,
        searchStudents,
        checkinStudent,
        getEventParticipants,
        getEventStatistics,
    };
}
