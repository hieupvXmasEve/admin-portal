<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import type { DeleteConfirmationOptions } from '@/composables/useDeleteConfirmation';

interface Props {
    open: boolean;
    loading?: boolean;
    options: DeleteConfirmationOptions | null;
}

interface Emits {
    confirm: [];
    cancel: [];
    'update:open': [value: boolean];
}

defineProps<Props>();
defineEmits<Emits>();
</script>

<template>
    <AlertDialog :open="open" @update:open="$emit('update:open', $event)">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {{ options?.title || 'Confirm Delete' }}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    {{ options?.message }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="$emit('cancel')" :disabled="loading">
                    {{ options?.cancelText || 'Cancel' }}
                </AlertDialogCancel>
                <AlertDialogAction @click="$emit('confirm')" :disabled="loading" class="bg-red-600 hover:bg-red-700 focus:ring-red-600">
                    {{ loading ? 'Deleting...' : options?.confirmText || 'Delete' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
