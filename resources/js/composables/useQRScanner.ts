import { BrowserQRCodeReader, ChecksumException, FormatException, NotFoundException } from '@zxing/library';
import { ref } from 'vue';

interface CameraDevice {
    deviceId: string;
    label: string;
    kind: string;
}

export function useQRScanner() {
    const stream = ref<MediaStream | null>(null);
    const availableCameras = ref<CameraDevice[]>([]);
    const selectedCameraId = ref<string | null>(null);
    const isStreamActive = ref(false);
    const activeVideoElement = ref<HTMLVideoElement | null>(null);
    const isDecoding = ref(false);

    let qrCodeReader: BrowserQRCodeReader | null = null;
    let lastDecodedText: string | null = null;
    let lastDecodedAt = 0;
    const duplicateCooldownMs = 1500;

    const getOrCreateReader = () => {
        if (!qrCodeReader) {
            qrCodeReader = new BrowserQRCodeReader(250);
        }

        return qrCodeReader;
    };

    const resetLastDecoded = () => {
        lastDecodedAt = 0;
        lastDecodedText = null;
    };

    const handleDecodingError = (err: unknown, onError?: (error: Error) => void) => {
        if (!err) {
            return;
        }

        if (err instanceof NotFoundException || err instanceof ChecksumException || err instanceof FormatException) {
            return;
        }

        const normalizedError = err instanceof Error ? err : new Error(String(err));
        console.error('QR decoding error:', normalizedError);
        onError?.(normalizedError);
    };

    const stopDecoding = (): void => {
        if (qrCodeReader) {
            qrCodeReader.reset();
            console.debug('[useQRScanner] reader reset');
        }

        resetLastDecoded();
        isDecoding.value = false;
        console.debug('[useQRScanner] stopDecoding');
    };

    const stopCamera = (): void => {
        stopDecoding();

        if (stream.value) {
            stream.value.getTracks().forEach((track) => track.stop());
            stream.value = null;
        }
        if (activeVideoElement.value) {
            activeVideoElement.value.srcObject = null;
        }
        isStreamActive.value = false;
        console.debug('[useQRScanner] camera stopped');
    };

    const startDecoding = (videoElement: HTMLVideoElement, onResult: (qrText: string) => void, onError?: (error: Error) => void): void => {
        if (isDecoding.value) {
            return;
        }

        if (!stream.value) {
            console.warn('[useQRScanner] startDecoding called without active stream');
            return;
        }

        const reader = getOrCreateReader();
        resetLastDecoded();
        isDecoding.value = true;
        console.debug('[useQRScanner] startDecoding on element', {
            hasStream: !!videoElement.srcObject,
            readyState: videoElement.readyState,
            videoWidth: videoElement.videoWidth,
            videoHeight: videoElement.videoHeight,
            streamTracks: stream.value.getTracks().map((track) => ({
                kind: track.kind,
                readyState: track.readyState,
            })),
        });

        reader
            .decodeFromStream(stream.value, videoElement, (result, err) => {
                console.debug('[useQRScanner] decode callback', {
                    hasResult: !!result,
                    hasError: !!err,
                });
                if (result) {
                    const qrText = result.getText();
                    if (qrText) {
                        const now = Date.now();
                        const isDuplicate = qrText === lastDecodedText && now - lastDecodedAt < duplicateCooldownMs;

                        if (!isDuplicate) {
                            lastDecodedText = qrText;
                            lastDecodedAt = now;

                            try {
                                console.debug('[useQRScanner] decoded QR text', qrText);
                                onResult(qrText);
                            } catch (callbackError) {
                                console.error('QR result handler error:', callbackError);
                            } finally {
                                stopCamera();
                            }
                        }
                    }
                }

                if (err) {
                    handleDecodingError(err, onError);
                }
            })
            .catch((decodeError) => {
                handleDecodingError(decodeError, onError);
            })
            .finally(() => {
                isDecoding.value = false;
                console.debug('[useQRScanner] decoding stopped');
            });
    };

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
            console.debug('[useQRScanner] video input devices', videoDevices);

            if (videoDevices.length > 0 && (!selectedCameraId.value || !videoDevices.some((device) => device.deviceId === selectedCameraId.value))) {
                selectedCameraId.value = selectPreferredCamera(videoDevices);
                console.debug('[useQRScanner] preferred camera selected', selectedCameraId.value);
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

    const startCamera = async (videoElement: HTMLVideoElement, deviceId?: string): Promise<void> => {
        try {
            activeVideoElement.value = videoElement;

            if (stream.value) {
                stopCamera();
            }

            const targetDeviceId = deviceId || selectedCameraId.value;
            console.debug('[useQRScanner] starting camera with device', targetDeviceId);

            const mediaStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    ...(targetDeviceId ? { deviceId: { exact: targetDeviceId } } : { facingMode: 'environment' }),
                },
            });

            stream.value = mediaStream;
            videoElement.srcObject = mediaStream;

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

            console.debug('[useQRScanner] active camera track', {
                activeDeviceId,
                facingMode: activeTrack?.getSettings().facingMode,
                label: activeTrack?.label,
            });

            await updateAvailableCameras();
        } catch (err) {
            console.error('Failed to start camera:', err);
            stopCamera();
            throw err;
        }
    };

    const switchCamera = async (deviceId: string): Promise<void> => {
        selectedCameraId.value = deviceId;

        if (!isStreamActive.value || !activeVideoElement.value) {
            return;
        }

        console.debug('[useQRScanner] switchCamera', deviceId);
        await startCamera(activeVideoElement.value, deviceId);
    };

    const isCameraSupported = (): boolean => {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    };

    const requestCameraPermission = async (): Promise<boolean> => {
        try {
            const permissionStream = await navigator.mediaDevices.getUserMedia({ video: true });
            permissionStream.getTracks().forEach((track) => track.stop());
            return true;
        } catch (err) {
            console.error('Camera permission denied:', err);
            return false;
        }
    };

    return {
        stream,
        isStreamActive,
        availableCameras,
        selectedCameraId,
        isDecoding,
        startCamera,
        stopCamera,
        getCameraDevices,
        switchCamera,
        startDecoding,
        stopDecoding,
        isCameraSupported,
        requestCameraPermission,
    };
}
