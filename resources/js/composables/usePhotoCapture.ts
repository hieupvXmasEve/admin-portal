import { ref, onUnmounted } from 'vue';

export interface CapturedPhoto {
    blob: Blob;
    dataUrl: string;
    file: File;
    dimensions?: {
        width: number;
        height: number;
    };
}

export interface PhotoDimensions {
    width: number;
    height: number;
    aspectRatio: number;
}

// 3x4 inches at 150 DPI for ID photos (portrait orientation)
export const ID_PHOTO_DIMENSIONS: PhotoDimensions = {
    width: 450,  // 3 inches * 150 DPI
    height: 600, // 4 inches * 150 DPI
    aspectRatio: 3/4 // 3:4 ratio (portrait)
};

export interface CameraDevice {
    deviceId: string;
    label: string;
    kind: string;
}

export const usePhotoCapture = () => {
    const videoRef = ref<HTMLVideoElement | null>(null);
    const canvasRef = ref<HTMLCanvasElement | null>(null);
    const streamRef = ref<MediaStream | null>(null);
    const isStreamActive = ref(false);
    const capturedPhoto = ref<CapturedPhoto | null>(null);
    const error = ref<string | null>(null);
    const availableCameras = ref<CameraDevice[]>([]);
    const selectedCameraId = ref<string | null>(null);

    const getCameraDevices = async (): Promise<CameraDevice[]> => {
        try {
            // First request permission to get device labels
            await navigator.mediaDevices.getUserMedia({ video: true });
            
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices
                .filter(device => device.kind === 'videoinput')
                .map(device => ({
                    deviceId: device.deviceId,
                    label: device.label || `Camera ${device.deviceId.slice(0, 8)}`,
                    kind: device.kind
                }));
            
            availableCameras.value = videoDevices;
            
            // Set default camera (prefer external cameras over built-in)
            if (videoDevices.length > 0 && !selectedCameraId.value) {
                // Try to find an external camera first
                const externalCamera = videoDevices.find(device => 
                    !device.label.toLowerCase().includes('facetime') && 
                    !device.label.toLowerCase().includes('built-in')
                );
                selectedCameraId.value = externalCamera?.deviceId || videoDevices[0].deviceId;
            }
            
            return videoDevices;
        } catch (err) {
            console.error('Error getting camera devices:', err);
            error.value = 'Failed to access camera devices';
            return [];
        }
    };

    const startCamera = async (deviceId?: string): Promise<boolean> => {
        try {
            error.value = null;
            
            // Stop current stream if running
            if (streamRef.value) {
                stopCamera();
            }
            
            // Use provided deviceId or the selected one
            const cameraId = deviceId || selectedCameraId.value;
            
            const constraints: MediaStreamConstraints = {
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    ...(cameraId ? { deviceId: { exact: cameraId } } : { facingMode: 'user' })
                },
                audio: false
            };

            const stream = await navigator.mediaDevices.getUserMedia(constraints);

            if (videoRef.value) {
                videoRef.value.srcObject = stream;
                // Ensure video is properly loaded before playing
                videoRef.value.onloadedmetadata = () => {
                    videoRef.value?.play().catch(err => {
                        console.error('Error playing video:', err);
                        error.value = 'Failed to start video playback';
                    });
                };
            }

            streamRef.value = stream;
            isStreamActive.value = true;
            
            // Update selected camera ID if a specific device was used
            if (deviceId) {
                selectedCameraId.value = deviceId;
            }
            
            return true;
        } catch (err) {
            console.error('Error accessing camera:', err);
            if (err instanceof Error) {
                error.value = `Camera access denied: ${err.message}`;
            } else {
                error.value = 'Camera access denied';
            }
            return false;
        }
    };

    const switchCamera = async (deviceId: string): Promise<boolean> => {
        selectedCameraId.value = deviceId;
        return await startCamera(deviceId);
    };

    const stopCamera = () => {
        if (streamRef.value) {
            streamRef.value.getTracks().forEach(track => track.stop());
            streamRef.value = null;
        }
        isStreamActive.value = false;
    };

    const calculateCropDimensions = (videoWidth: number, videoHeight: number) => {
        const videoAspectRatio = videoWidth / videoHeight;
        const targetAspectRatio = ID_PHOTO_DIMENSIONS.aspectRatio;
        
        let cropWidth: number;
        let cropHeight: number;
        let cropX: number;
        let cropY: number;
        
        if (videoAspectRatio > targetAspectRatio) {
            // Video is wider than target, crop width
            cropHeight = videoHeight;
            cropWidth = cropHeight * targetAspectRatio;
            cropX = (videoWidth - cropWidth) / 2;
            cropY = 0;
        } else {
            // Video is taller than target, crop height
            cropWidth = videoWidth;
            cropHeight = cropWidth / targetAspectRatio;
            cropX = 0;
            cropY = (videoHeight - cropHeight) / 2;
        }
        
        return { cropX, cropY, cropWidth, cropHeight };
    };

    const capturePhoto = (cropToIdPhoto: boolean = true): boolean => {
        if (!videoRef.value || !canvasRef.value || !isStreamActive.value) {
            error.value = 'Camera not ready';
            return false;
        }

        try {
            const video = videoRef.value;
            const canvas = canvasRef.value;
            const context = canvas.getContext('2d');

            if (!context) {
                error.value = 'Canvas context not available';
                return false;
            }

            if (cropToIdPhoto) {
                // Calculate crop dimensions for 3x4 aspect ratio
                const { cropX, cropY, cropWidth, cropHeight } = calculateCropDimensions(
                    video.videoWidth,
                    video.videoHeight
                );
                
                // Set canvas to ID photo dimensions
                canvas.width = ID_PHOTO_DIMENSIONS.width;
                canvas.height = ID_PHOTO_DIMENSIONS.height;
                
                // Draw cropped and scaled image
                context.drawImage(
                    video,
                    cropX, cropY, cropWidth, cropHeight,
                    0, 0, canvas.width, canvas.height
                );
            } else {
                // Original behavior - capture full video frame
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
            }

            // Convert canvas to blob
            canvas.toBlob((blob) => {
                if (blob) {
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
                    const file = new File([blob], `id-photo-${Date.now()}.jpg`, {
                        type: 'image/jpeg'
                    });

                    capturedPhoto.value = {
                        blob,
                        dataUrl,
                        file,
                        dimensions: {
                            width: canvas.width,
                            height: canvas.height
                        }
                    };
                } else {
                    error.value = 'Failed to capture photo';
                }
            }, 'image/jpeg', 0.9);

            return true;
        } catch (err) {
            console.error('Error capturing photo:', err);
            error.value = 'Failed to capture photo';
            return false;
        }
    };

    const clearCapturedPhoto = () => {
        capturedPhoto.value = null;
    };

    const handleFileUpload = (file: File): Promise<CapturedPhoto> => {
        return new Promise((resolve, reject) => {
            if (!file.type.startsWith('image/')) {
                reject(new Error('Please select an image file'));
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const dataUrl = e.target?.result as string;
                
                // Create blob from file
                const blob = new Blob([file], { type: file.type });
                
                const photo: CapturedPhoto = {
                    blob,
                    dataUrl,
                    file
                };
                
                resolve(photo);
            };
            reader.onerror = () => reject(new Error('Failed to read file'));
            reader.readAsDataURL(file);
        });
    };

    // Cleanup on component unmount
    onUnmounted(() => {
        stopCamera();
    });

    return {
        videoRef,
        canvasRef,
        isStreamActive,
        capturedPhoto,
        error,
        availableCameras,
        selectedCameraId,
        getCameraDevices,
        startCamera,
        stopCamera,
        switchCamera,
        capturePhoto,
        clearCapturedPhoto,
        handleFileUpload
    };
};
