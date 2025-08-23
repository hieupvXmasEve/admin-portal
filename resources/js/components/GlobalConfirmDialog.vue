<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { useConfirmDialogStore } from '@/stores/confirmDialog';
import { ref } from 'vue';

const confirmDialogStore = useConfirmDialogStore();

// Track if we're currently processing a confirm action
const isProcessingConfirm = ref(false);

const handleConfirmClick = async () => {
    isProcessingConfirm.value = true;

    try {
        await confirmDialogStore.handleConfirm();
    } finally {
        isProcessingConfirm.value = false;
    }
};

const handleCancelClick = () => {
    confirmDialogStore.handleCancel();
};
</script>

<template>
    <AlertDialog :open="confirmDialogStore.isOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {{ confirmDialogStore.options?.title || 'Confirm Action' }}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    {{ confirmDialogStore.options?.message }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="handleCancelClick" :disabled="confirmDialogStore.isLoading">
                    {{ confirmDialogStore.options?.cancelText || 'Cancel' }}
                </AlertDialogCancel>
                <Button
                    @click="handleConfirmClick"
                    :disabled="confirmDialogStore.isLoading"
                    class="bg-red-600 hover:bg-red-700 focus:ring-red-600"
                    size="sm"
                >
                    {{ confirmDialogStore.isLoading ? 'Processing...' : confirmDialogStore.options?.confirmText || 'Confirm' }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
