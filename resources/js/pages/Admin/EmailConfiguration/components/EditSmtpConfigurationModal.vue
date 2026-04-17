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

interface EmailConfiguration {
    id: number
    campus_id: number | null
    name: string
    host: string
    port: number
    username?: string
    encryption: string
    from_address: string
    from_name: string
    is_active: boolean
    daily_limit: number
    rate_limit: number
    last_tested_at?: string
    test_result?: string
}

interface Props {
    open: boolean
    configuration?: EmailConfiguration | null
    campuses?: Campus[]
}

interface Emits {
  (e: 'update:open', value: boolean): void
  (e: 'saved'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { updateConfiguration } = useSmtpConfiguration()

const isOpen = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value)
})

const isSubmitting = ref(false)

const handleFormSubmit = async (formValues: any) => {
    if (!props.configuration?.id) {
        console.error('No configuration ID provided for update');
        return;
    }

    isSubmitting.value = true;

    try {
        await updateConfiguration(props.configuration.id, formValues);
        emit('saved');
        toast.success('Update configuration successfully!');
    } catch (error: any) {
        console.error('Failed to update configuration:', error);
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
        <DialogTitle>Edit SMTP Configuration</DialogTitle>
        <DialogDescription>
          Update the details for "{{ configuration?.name }}" SMTP configuration.
        </DialogDescription>
      </DialogHeader>

      <SmtpConfigurationForm
        :configuration="configuration"
        :is-open="isOpen"
        :campuses="campuses"
        @submit="handleFormSubmit"
        @cancel="handleFormCancel"
      >
        <template #actions="{ submit, cancel }">
          <DialogFooter>
            <Button type="button" variant="outline" @click="cancel">Cancel</Button>
            <Button type="submit" :disabled="isSubmitting" @click="submit">
              {{ isSubmitting ? 'Updating...' : 'Update Configuration' }}
            </Button>
          </DialogFooter>
        </template>
      </SmtpConfigurationForm>
    </DialogContent>
  </Dialog>
</template>
