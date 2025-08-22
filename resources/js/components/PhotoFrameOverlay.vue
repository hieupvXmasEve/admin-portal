<script setup lang="ts">
import { ID_PHOTO_DIMENSIONS } from '@/composables/usePhotoCapture';
import { computed } from 'vue';

interface Props {
    containerWidth?: number;
    containerHeight?: number;
    showGrid?: boolean;
    showInstructions?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    containerWidth: 640,
    containerHeight: 480,
    showGrid: false,
    showInstructions: true,
});

// Calculate the overlay dimensions to fit within container while maintaining 3:4 aspect ratio
const overlayDimensions = computed(() => {
    const containerAspectRatio = props.containerWidth / props.containerHeight;
    const photoAspectRatio = ID_PHOTO_DIMENSIONS.aspectRatio;

    let overlayWidth: number;
    let overlayHeight: number;

    if (containerAspectRatio > photoAspectRatio) {
        // Container is wider than photo aspect ratio
        overlayHeight = props.containerHeight * 0.85; // 85% of container height
        overlayWidth = overlayHeight * photoAspectRatio;
    } else {
        // Container is taller than photo aspect ratio
        overlayWidth = props.containerWidth * 0.7; // 70% of container width
        overlayHeight = overlayWidth / photoAspectRatio;
    }

    const overlayLeft = (props.containerWidth - overlayWidth) / 2;
    const overlayTop = (props.containerHeight - overlayHeight) / 2;

    return {
        width: overlayWidth,
        height: overlayHeight,
        left: overlayLeft,
        top: overlayTop,
    };
});
</script>

<template>
    <div class="pointer-events-none absolute inset-0">
        <!-- Semi-transparent overlay with cutout -->
        <div class="absolute inset-0">
            <!-- Cutout for the photo frame -->
            <div
                class="absolute border-2 border-dashed border-white bg-transparent"
                :style="{
                    width: `${overlayDimensions.width}px`,
                    height: `${overlayDimensions.height}px`,
                    left: `${overlayDimensions.left}px`,
                    top: `${overlayDimensions.top}px`,
                    boxShadow: `0 0 0 9999px rgba(0, 0, 0, 0.3)`,
                }"
            >
                <!-- Corner markers -->
                <div class="absolute -top-1 -left-1 h-4 w-4 border-t-2 border-l-2 border-white"></div>
                <div class="absolute -top-1 -right-1 h-4 w-4 border-t-2 border-r-2 border-white"></div>
                <div class="absolute -bottom-1 -left-1 h-4 w-4 border-b-2 border-l-2 border-white"></div>
                <div class="absolute -right-1 -bottom-1 h-4 w-4 border-r-2 border-b-2 border-white"></div>

                <!-- Center guidelines -->
                <div v-if="showGrid" class="absolute inset-0">
                    <!-- Vertical center line -->
                    <div class="absolute top-0 bottom-0 left-1/2 w-px -translate-x-px transform bg-white opacity-50"></div>
                    <!-- Horizontal center line -->
                    <div class="absolute top-1/2 right-0 left-0 h-px -translate-y-px transform bg-white opacity-50"></div>
                    <!-- Rule of thirds lines -->
                    <div class="absolute top-0 bottom-0 left-1/3 w-px -translate-x-px transform bg-white opacity-30"></div>
                    <div class="absolute top-0 bottom-0 left-2/3 w-px -translate-x-px transform bg-white opacity-30"></div>
                    <div class="absolute top-1/3 right-0 left-0 h-px -translate-y-px transform bg-white opacity-30"></div>
                    <div class="absolute top-2/3 right-0 left-0 h-px -translate-y-px transform bg-white opacity-30"></div>
                </div>

                <!-- Face positioning guide
                <div class="absolute top-6 bottom-1/2 left-1/4 right-1/4">
                    <div class="w-full h-full border border-white border-opacity-30 rounded-full"></div>
                </div> -->
            </div>
        </div>

        <!-- Instructions -->
        <div v-if="showInstructions" class="absolute bottom-4 left-1/2 -translate-x-1/2 transform">
            <div class="bg-opacity-30 rounded-lg bg-black px-4 py-2 text-center text-sm text-white">
                <p class="font-medium">3×4 ID Photo Frame</p>
                <!-- <p class="text-xs opacity-80">Position your face within the guidelines</p> -->
            </div>
        </div>

        <!-- Photo dimensions indicator -->
        <div class="bg-opacity-60 absolute top-4 right-4 rounded bg-black px-3 py-1 text-xs text-white">3×4" ({{ ID_PHOTO_DIMENSIONS.width }}×{{ ID_PHOTO_DIMENSIONS.height }}px)</div>
    </div>
</template>
