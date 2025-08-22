<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { usePhotoCapture } from '@/composables/usePhotoCapture';
import { router } from '@inertiajs/vue3';
import { Camera, Upload, User } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface Props {
    studentId: number;
    currentAvatar?: string | null;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    editable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    size: 'lg',
    editable: true
});

const emit = defineEmits<{
    photoSelected: [file: File, dataUrl: string];
}>();

const { handleFileUpload } = usePhotoCapture();
const fileInputRef = ref<HTMLInputElement>();
const currentPhotoUrl = ref<string | null>(props.currentAvatar || null);

const sizeClasses = {
    sm: 'h-8 w-8',
    md: 'h-12 w-12', 
    lg: 'h-16 w-16',
    xl: 'h-24 w-24'
};

const iconSizeClasses = {
    sm: 'h-3 w-3',
    md: 'h-4 w-4',
    lg: 'h-8 w-8',
    xl: 'h-10 w-10'
};

onMounted(() => {
    // Check if there's a captured photo in session storage
    const capturedPhoto = sessionStorage.getItem('captured_photo');
    if (capturedPhoto) {
        try {
            const photoData = JSON.parse(capturedPhoto);
            currentPhotoUrl.value = photoData.dataUrl;
            
            // Convert dataUrl back to file for emission
            fetch(photoData.dataUrl)
                .then(res => res.blob())
                .then(blob => {
                    const file = new File([blob], photoData.fileName, { type: 'image/jpeg' });
                    emit('photoSelected', file, photoData.dataUrl);
                });
            
            // Clear session storage
            sessionStorage.removeItem('captured_photo');
        } catch (error) {
            console.error('Error processing captured photo:', error);
        }
    }
});

const handleUploadClick = () => {
    fileInputRef.value?.click();
};

const handleTakePhotoClick = () => {
    router.visit(route('students.photo-capture', props.studentId));
};

const handleFileChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (file) {
        try {
            const photo = await handleFileUpload(file);
            currentPhotoUrl.value = photo.dataUrl;
            emit('photoSelected', photo.file, photo.dataUrl);
        } catch (error) {
            console.error('Error processing uploaded file:', error);
        }
    }
};
</script>

<template>
    <div class="flex items-center gap-4">
        <div class="relative">
            <!-- Avatar Display -->
            <div 
                :class="[
                    'bg-primary/10 flex items-center justify-center rounded-full overflow-hidden border-2 border-border',
                    sizeClasses[size],
                    editable && 'cursor-pointer hover:opacity-80 transition-opacity'
                ]"
                @click="editable && handleUploadClick()"
            >
                <img
                    v-if="currentPhotoUrl"
                    :src="currentPhotoUrl"
                    alt="Student avatar"
                    class="h-full w-full object-cover"
                />
                <User 
                    v-else
                    :class="['text-primary', iconSizeClasses[size]]"
                />
            </div>

            <!-- Dropdown Menu for Options (only when editable) -->
            <DropdownMenu v-if="editable">
                <DropdownMenuTrigger as-child>
                    <Button
                        size="sm"
                        variant="outline"
                        class="absolute -bottom-1 -right-1 h-6 w-6 rounded-full p-0"
                    >
                        <Camera class="h-3 w-3" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-48">
                    <DropdownMenuItem @click="handleUploadClick" class="cursor-pointer">
                        <Upload class="mr-2 h-4 w-4" />
                        Upload Photo
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="handleTakePhotoClick" class="cursor-pointer">
                        <Camera class="mr-2 h-4 w-4" />
                        Take Photo
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <!-- Hidden file input -->
        <input
            ref="fileInputRef"
            type="file"
            accept="image/*"
            class="hidden"
            @change="handleFileChange"
        />
        
        <div v-if="editable" class="text-sm text-muted-foreground">
            <p class="font-medium">Student Photo</p>
            <p class="text-xs">Click the camera icon to upload or take a photo</p>
        </div>
    </div>
</template>
