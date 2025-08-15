<script setup lang="ts">
import type { FieldProps } from './interface'
import { FormControl, FormDescription, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import AutoFormLabel from './AutoFormLabel.vue'
import { beautifyObjectName, maybeBooleanishToBoolean } from './utils'
import { computed } from 'vue'

type LabeledOption = { value: string; label: string }

const props = defineProps<FieldProps & {
  options?: string[]
}>()

// Support labeled options via config.inputProps.options when provided.
// Fallback to enum string options from schema.
const normalizedOptions = computed<LabeledOption[]>(() => {
  const cfg: any = props.config?.inputProps as any
  const cfgOptions = cfg?.options as Array<string | LabeledOption> | undefined

  const mapToLabeled = (arr: Array<string | LabeledOption>) => arr.map((opt) => {
    if (typeof opt === 'string') {
      return { value: opt, label: beautifyObjectName(opt) }
    }
    return { value: String(opt.value), label: opt.label ?? beautifyObjectName(String(opt.value)) }
  })

  if (Array.isArray(cfgOptions) && cfgOptions.length) {
    return mapToLabeled(cfgOptions)
  }

  const enumOptions = props.options ?? []
  return enumOptions.map((opt) => ({ value: opt, label: beautifyObjectName(opt) }))
})
</script>

<template>
  <FormField v-slot="slotProps" :name="fieldName">
    <FormItem>
      <AutoFormLabel v-if="!config?.hideLabel" :required="required">
        {{ config?.label || beautifyObjectName(label ?? fieldName) }}
      </AutoFormLabel>
      <FormControl>
        <slot v-bind="slotProps">
          <RadioGroup v-if="config?.component === 'radio'" :disabled="maybeBooleanishToBoolean(config?.inputProps?.disabled) ?? disabled" :orientation="'vertical'" v-bind="{ ...slotProps.componentField }">
            <div v-for="(option, index) in normalizedOptions" :key="option.value" class="mb-2 flex items-center gap-3 space-y-0">
              <RadioGroupItem :id="`${option.value}-${index}`" :value="option.value" />
              <Label :for="`${option.value}-${index}`">{{ option.label }}</Label>
            </div>
          </RadioGroup>

          <Select v-else :disabled="maybeBooleanishToBoolean(config?.inputProps?.disabled) ?? disabled" v-bind="{ ...slotProps.componentField }">
            <SelectTrigger class="w-full">
              <SelectValue :placeholder="config?.inputProps?.placeholder" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="option in normalizedOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </slot>
      </FormControl>

      <FormDescription v-if="config?.description">
        {{ config.description }}
      </FormDescription>
      <FormMessage />
    </FormItem>
  </FormField>
</template>
