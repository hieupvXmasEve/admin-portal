<script setup lang="ts">
import FormPreview from '@/components/forms/FormPreview.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { FormBuilderQuestion, FormBuilderSection } from '@/types/forms';
import { X } from 'lucide-vue-next';

interface Props {
    open: boolean;
    title: string;
    description?: string;
    type: 'feedback' | 'survey' | 'query';
    sections: FormBuilderSection[];
    questions: FormBuilderQuestion[];
}

interface Emits {
    (e: 'update:open', open: boolean): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const closeModal = () => {
    emit('update:open', false);
};
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] max-w-4xl">
            <DialogHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <DialogTitle>Form Preview</DialogTitle>
                        <DialogDescription>Preview how your form will appear to users</DialogDescription>
                    </div>
                    <Button variant="ghost" size="sm" @click="closeModal">
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </DialogHeader>

            <ScrollArea class="max-h-[70vh] pr-4">
                <FormPreview :title="title" :description="description" :type="type" :sections="sections" :questions="questions" />
            </ScrollArea>
        </DialogContent>
    </Dialog>
</template>
