<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Form } from '@/types/forms';
import { router } from '@inertiajs/vue3';
import { Copy, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Props {
    open: boolean;
    form: Form;
}

interface Emits {
    (e: 'update:open', open: boolean): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// Local state
const newCode = ref('');
const newTitle = ref('');
const newDescription = ref('');
const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});

// Computed
const isValid = computed(() => {
    return newCode.value.trim() && newTitle.value.trim();
});

const suggestedCode = computed(() => {
    if (!newTitle.value) return '';
    return (
        newTitle.value
            .toLowerCase()
            .replace(/[^a-z0-9\s]/g, '')
            .replace(/\s+/g, '_')
            .substring(0, 50) + '_copy'
    );
});

// Methods
const closeModal = () => {
    emit('update:open', false);
    resetForm();
};

const resetForm = () => {
    newCode.value = '';
    newTitle.value = '';
    newDescription.value = '';
    errors.value = {};
    isSubmitting.value = false;
};

const generateCode = () => {
    newCode.value = suggestedCode.value;
};

const cloneForm = () => {
    if (!isValid.value) return;

    isSubmitting.value = true;
    errors.value = {};

    router.post(
        route('forms.admin.clone', props.form.id),
        {
            new_code: newCode.value.trim(),
            new_title: newTitle.value.trim(),
            new_description: newDescription.value.trim() || null,
        },
        {
            onSuccess: () => {
                closeModal();
            },
            onError: (responseErrors) => {
                errors.value = responseErrors;
                isSubmitting.value = false;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

// Watch for modal open/close to reset form
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
            // Pre-populate with suggested values
            newTitle.value = `${props.form.title} (Copy)`;
            newDescription.value = props.form.description || '';
        }
    },
);

// Auto-generate code when title changes
watch(newTitle, () => {
    if (!newCode.value) {
        generateCode();
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <DialogTitle class="flex items-center space-x-2">
                            <Copy class="h-5 w-5" />
                            <span>Clone Form</span>
                        </DialogTitle>
                        <DialogDescription>Create a copy of "{{ form.title }}"</DialogDescription>
                    </div>
                    <Button variant="ghost" size="sm" @click="closeModal">
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- New Title -->
                <div class="space-y-2">
                    <Label for="clone-title">New Form Title</Label>
                    <Input id="clone-title" v-model="newTitle" placeholder="Enter new form title" :class="{ 'border-red-500': errors.new_title }" />
                    <p v-if="errors.new_title" class="text-sm text-red-500">
                        {{ errors.new_title }}
                    </p>
                </div>

                <!-- New Code -->
                <div class="space-y-2">
                    <Label for="clone-code">New Form Code</Label>
                    <div class="flex space-x-2">
                        <Input id="clone-code" v-model="newCode" placeholder="Enter unique form code" :class="{ 'border-red-500': errors.new_code }" class="flex-1" />
                        <Button type="button" variant="outline" size="sm" @click="generateCode" :disabled="!newTitle"> Auto </Button>
                    </div>
                    <p v-if="errors.new_code" class="text-sm text-red-500">
                        {{ errors.new_code }}
                    </p>
                    <p v-else class="text-muted-foreground text-xs">Must be unique across all forms</p>
                </div>

                <!-- New Description -->
                <div class="space-y-2">
                    <Label for="clone-description">Description (Optional)</Label>
                    <Textarea id="clone-description" v-model="newDescription" placeholder="Enter form description" rows="3" />
                </div>

                <!-- Original Form Info -->
                <div class="bg-muted rounded-lg p-3">
                    <h4 class="mb-2 text-sm font-medium">What will be copied:</h4>
                    <ul class="text-muted-foreground space-y-1 text-sm">
                        <li>• Form structure and questions</li>
                        <li>• Visibility settings</li>
                        <li>• Result visibility rules</li>
                        <li>• Latest published version</li>
                    </ul>
                    <p class="text-muted-foreground mt-2 text-xs">Note: Responses and targets will not be copied</p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="closeModal" :disabled="isSubmitting"> Cancel </Button>
                <Button @click="cloneForm" :disabled="!isValid || isSubmitting" class="flex items-center space-x-2">
                    <Copy v-if="!isSubmitting" class="h-4 w-4" />
                    <div v-else class="border-background h-4 w-4 animate-spin rounded-full border-2 border-t-transparent"></div>
                    <span>{{ isSubmitting ? 'Cloning...' : 'Clone Form' }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
