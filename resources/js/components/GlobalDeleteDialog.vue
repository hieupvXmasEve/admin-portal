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
import { useDeleteDialogStore } from '@/stores/deleteDialog';
import { ref } from 'vue';

const deleteDialogStore = useDeleteDialogStore();

// Track if we're currently processing a confirm action
const isProcessingConfirm = ref(false);

const handleConfirmClick = async () => {
    isProcessingConfirm.value = true;

    try {
        await deleteDialogStore.handleConfirm();
    } finally {
        isProcessingConfirm.value = false;
    }
};

const handleCancelClick = () => {
    deleteDialogStore.handleCancel();
};
</script>

<template>
    <AlertDialog :open="deleteDialogStore.isOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {{ deleteDialogStore.options?.title || 'Confirm Delete' }}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    {{ deleteDialogStore.options?.message }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="handleCancelClick" :disabled="deleteDialogStore.isLoading">
                    {{ deleteDialogStore.options?.cancelText || 'Cancel' }}
                </AlertDialogCancel>
                <Button
                    @click="handleConfirmClick"
                    :disabled="deleteDialogStore.isLoading"
                    class="bg-red-600 hover:bg-red-700 focus:ring-red-600"
                    size="sm"
                >
                    {{ deleteDialogStore.isLoading ? 'Deleting...' : deleteDialogStore.options?.confirmText || 'Delete' }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
