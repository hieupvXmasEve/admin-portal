<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import type { UploadRecord } from '@/types/imageUpload';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import Icon from '@/components/Icon.vue';
import { toast } from 'vue-sonner';

// Props
interface Props {
    upload: UploadRecord;
    showFullSize?: boolean;
    showMetadata?: boolean;
    showCopyButton?: boolean;
    thumbnailSize?: 'sm' | 'md' | 'lg';
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    showFullSize: true,
    showMetadata: true,
    showCopyButton: true,
    thumbnailSize: 'md',
});

// Emits
const emit = defineEmits<{
    'image-load': [];
    'image-error': [error: string];
    'url-copied': [url: string];
    'full-size-open': [];
    'full-size-close': [];
}>();

// Refs
const imageRef = ref<HTMLImageElement>();
const isLoading = ref(true);
const hasError = ref(false);
const errorMessage = ref('');
const isFullSizeOpen = ref(false);

// Computed
const thumbnailSizeClasses = computed(() => {
    switch (props.thumbnailSize) {
        case 'sm':
            return 'h-16 w-16';
        case 'md':
            return 'h-24 w-24';
        case 'lg':
            return 'h-32 w-32';
        default:
            return 'h-24 w-24';
    }
});

const fileExtension = computed(() => {
    return props.upload.original_name.split('.').pop()?.toLowerCase() || '';
});

const formattedFileSize = computed(() => {
    return formatFileSize(props.upload.size);
});

const formattedDate = computed(() => {
    return new Date(props.upload.created_at).toLocaleDateString();
});

const isImageType = computed(() => {
    return props.upload.mime_type.startsWith('image/');
});

// Methods
const handleImageLoad = () => {
    isLoading.value = false;
    hasError.value = false;
    emit('image-load');
};

const handleImageError = () => {
    isLoading.value = false;
    hasError.value = true;
    errorMessage.value = 'Failed to load image';
    emit('image-error', errorMessage.value);
};

const copyUrlToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(props.upload.url);
        toast.success('URL copied to clipboard');
        emit('url-copied', props.upload.url);
    } catch (error) {
        toast.error('Failed to copy URL');
        console.error('Failed to copy URL:', error);
    }
};

const openFullSize = () => {
    if (props.showFullSize && !hasError.value) {
        isFullSizeOpen.value = true;
        emit('full-size-open');
    }
};

const closeFullSize = () => {
    isFullSizeOpen.value = false;
    emit('full-size-close');
};

const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Handle keyboard events for accessibility
const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && isFullSizeOpen.value) {
        closeFullSize();
    }
};

// Lifecycle
onMounted(() => {
    document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Card :class="props.class" class="overflow-hidden">
        <!-- Thumbnail view -->
        <div class="relative">
            <!-- Loading skeleton -->
            <div v-if="isLoading" class="flex items-center justify-center" :class="thumbnailSizeClasses">
                <Skeleton class="h-full w-full" />
            </div>

            <!-- Error state -->
            <div
                v-else-if="hasError || !isImageType"
                class="flex flex-col items-center justify-center bg-gray-100 text-gray-500"
                :class="thumbnailSizeClasses"
            >
                <Icon name="image-off" class="h-6 w-6 mb-1" />
                <span class="text-xs">{{ hasError ? 'Error' : 'Not an image' }}</span>
            </div>

            <!-- Image thumbnail -->
            <div
                v-else
                class="relative overflow-hidden cursor-pointer group"
                :class="[thumbnailSizeClasses, { 'cursor-default': !showFullSize }]"
                @click="openFullSize"
            >
                <img
                    ref="imageRef"
                    :src="upload.url"
                    :alt="upload.original_name"
                    class="h-full w-full object-cover transition-transform group-hover:scale-105"
                    @load="handleImageLoad"
                    @error="handleImageError"
                />

                <!-- Hover overlay -->
                <div
                    v-if="showFullSize"
                    class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center"
                >
                    <Icon name="zoom-in" class="h-4 w-4 text-white" />
                </div>
            </div>

            <!-- Copy button overlay -->
            <Button
                v-if="showCopyButton && !isLoading && !hasError"
                variant="ghost"
                size="sm"
                class="absolute top-1 right-1 h-6 w-6 p-0 bg-white/80 hover:bg-white shadow-sm"
                @click.stop="copyUrlToClipboard"
            >
                <Icon name="copy" class="h-3 w-3" />
            </Button>
        </div>

        <!-- Metadata -->
        <div v-if="showMetadata" class="p-3 space-y-2">
            <!-- File name -->
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-gray-900 truncate" :title="upload.original_name">
                    {{ upload.original_name }}
                </p>
                <Badge v-if="fileExtension" variant="secondary" class="text-xs">
                    {{ fileExtension.toUpperCase() }}
                </Badge>
            </div>

            <!-- File details -->
            <div class="flex items-center justify-between text-xs text-gray-600">
                <span>{{ formattedFileSize }}</span>
                <span>{{ formattedDate }}</span>
            </div>

            <!-- Context badge -->
            <div class="flex items-center justify-between">
                <Badge variant="outline" class="text-xs">
                    {{ upload.context }}
                </Badge>

                <!-- Copy URL button -->
                <Button
                    v-if="showCopyButton"
                    variant="ghost"
                    size="sm"
                    class="h-6 px-2 text-xs"
                    @click="copyUrlToClipboard"
                >
                    <Icon name="copy" class="h-3 w-3 mr-1" />
                    Copy URL
                </Button>
            </div>
        </div>

        <!-- Full-size dialog -->
        <Dialog v-if="showFullSize" v-model:open="isFullSizeOpen">
            <DialogContent class="max-w-4xl max-h-[90vh] p-0">
                <DialogHeader class="p-4 pb-2">
                    <DialogTitle class="flex items-center justify-between">
                        <span class="truncate">{{ upload.original_name }}</span>
                        <div class="flex items-center space-x-2 ml-4">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="copyUrlToClipboard"
                            >
                                <Icon name="copy" class="h-4 w-4 mr-2" />
                                Copy URL
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="closeFullSize"
                            >
                                <Icon name="x" class="h-4 w-4" />
                            </Button>
                        </div>
                    </DialogTitle>
                </DialogHeader>

                <!-- Full-size image -->
                <div class="flex items-center justify-center p-4 pt-0 max-h-[calc(90vh-120px)] overflow-auto">
                    <img
                        :src="upload.url"
                        :alt="upload.original_name"
                        class="max-w-full max-h-full object-contain"
                        @error="handleImageError"
                    />
                </div>

                <!-- Full-size metadata -->
                <div class="border-t p-4 bg-gray-50">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">File Name:</span>
                            <p class="text-gray-900 truncate">{{ upload.original_name }}</p>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Size:</span>
                            <p class="text-gray-900">{{ formattedFileSize }}</p>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Type:</span>
                            <p class="text-gray-900">{{ upload.mime_type }}</p>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Context:</span>
                            <p class="text-gray-900">{{ upload.context }}</p>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Uploaded:</span>
                            <p class="text-gray-900">{{ formattedDate }}</p>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">URL:</span>
                            <p class="text-gray-900 truncate font-mono text-xs">{{ upload.url }}</p>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </Card>
</template>
