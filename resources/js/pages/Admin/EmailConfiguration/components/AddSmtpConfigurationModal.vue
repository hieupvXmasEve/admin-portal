<script setup lang="ts">
import { ref, computed } from 'vue'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import SmtpConfigurationForm from './SmtpConfigurationForm.vue'
import { useSmtpConfiguration } from '@/composables/useSmtpConfiguration'
import { toast } from 'vue-sonner';

interface Campus {
    id: number
    name: string
    code: string
}

interface Props {
    open: boolean
    campuses?: Campus[]
    currentCampusId?: number | null
}

interface Emits {
  (e: 'update:open', value: boolean): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { createConfiguration } = useSmtpConfiguration()

const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
})

const isSubmitting = ref(false)

const handleFormSubmit = async (formValues: any) => {
    isSubmitting.value = true;

    try {
        await createConfiguration(formValues);
        emit('saved');
        toast.success('Add email configuration successfully!');
    } catch (error: any) {
        console.error('Failed to create configuration:', error);
        // You could emit an error event here if needed
        // emit('error', error);
    } finally {
        isSubmitting.value = false;
    }
}

const handleFormCancel = () => {
    closeModal()
}

const closeModal = () => {
  isOpen.value = false
}
</script>

<template>
  <Dialog :open="isOpen" @update:open="isOpen = $event">
    <DialogContent class="sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>Add SMTP Configuration</DialogTitle>
        <DialogDescription>
          Fill in the details below to create a new SMTP server configuration.
        </DialogDescription>
      </DialogHeader>

      <SmtpConfigurationForm
        :is-open="isOpen"
        :campuses="campuses"
        :current-campus-id="currentCampusId"
        @submit="handleFormSubmit"
        @cancel="handleFormCancel"
      >
        <template #actions="{ submit, cancel }">
          <DialogFooter>
            <Button type="button" variant="outline" @click="cancel">Cancel</Button>
            <Button type="submit" :disabled="isSubmitting" @click="submit">
              {{ isSubmitting ? 'Creating...' : 'Create Configuration' }}
            </Button>
          </DialogFooter>
        </template>
      </SmtpConfigurationForm>
    </DialogContent>
  </Dialog>
</template>
