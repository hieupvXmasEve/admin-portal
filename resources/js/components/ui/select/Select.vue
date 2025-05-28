<script setup lang="ts">
import { computed, provide, ref } from 'vue'

interface SelectProps {
  modelValue?: string
  placeholder?: string
  disabled?: boolean
}

interface SelectEmits {
  (e: 'update:modelValue', value: string): void
}

const props = withDefaults(defineProps<SelectProps>(), {
  placeholder: 'Select an option...',
  disabled: false,
})

const emit = defineEmits<SelectEmits>()

const isOpen = ref(false)
const selectedValue = computed({
  get: () => props.modelValue,
  set: (value: string) => emit('update:modelValue', value)
})

provide('selectContext', {
  isOpen,
  selectedValue,
  selectValue: (value: string) => {
    selectedValue.value = value
    isOpen.value = false
  },
  disabled: computed(() => props.disabled)
})
</script>

<template>
  <div class="relative">
    <slot />
  </div>
</template>

<script lang="ts">
export const SelectContent = {
  name: 'SelectContent',
  setup() {
    return {}
  },
  template: `
    <div v-show="$parent.isOpen" class="absolute z-50 min-w-[8rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
      <slot />
    </div>
  `
}

export const SelectItem = {
  name: 'SelectItem',
  props: {
    value: {
      type: String,
      required: true
    }
  },
  setup(props: { value: string }) {
    return {
      handleClick: () => {
        // Will be handled by parent context
      }
    }
  },
  template: `
    <div @click="handleClick" class="relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none hover:bg-accent hover:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50">
      <slot />
    </div>
  `
}

export const SelectTrigger = {
  name: 'SelectTrigger',
  setup() {
    return {}
  },
  template: `
    <button type="button" class="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
      <slot />
    </button>
  `
}

export const SelectValue = {
  name: 'SelectValue',
  props: {
    placeholder: {
      type: String,
      default: 'Select an option...'
    }
  },
  template: `
    <span class="pointer-events-none">{{ placeholder }}</span>
  `
}
</script>
