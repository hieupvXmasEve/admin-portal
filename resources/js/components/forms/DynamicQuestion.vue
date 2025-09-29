<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import type { Question } from '@/types/forms'

interface Props {
  question: Question
  modelValue: any
  errors?: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [value: any]
}>()

const localValue = ref(props.modelValue)
const selectedFile = ref<File | null>(null)
const freeTextValues = ref<Record<number, string>>({})
const matrixValues = ref<Record<string, any>>({})

// Watch for external changes
watch(() => props.modelValue, (newVal) => {
  localValue.value = newVal
})

// Watch for local changes and emit
watch(localValue, (newVal) => {
  emit('update:modelValue', newVal)
})

// Helper functions for multi-choice questions
const isOptionSelected = (optionId: number): boolean => {
  return Array.isArray(localValue.value) && localValue.value.includes(optionId)
}

const toggleOption = (optionId: number) => {
  if (!Array.isArray(localValue.value)) {
    localValue.value = []
  }
  
  const index = localValue.value.indexOf(optionId)
  if (index > -1) {
    localValue.value.splice(index, 1)
  } else {
    localValue.value.push(optionId)
  }
  
  emit('update:modelValue', {
    options: localValue.value,
    freeText: freeTextValues.value
  })
}

// Helper function for rating scale
const getRatingScale = (): number[] => {
  const min = props.question.validation_rules?.min || 1
  const max = props.question.validation_rules?.max || 5
  const scale = []
  for (let i = min; i <= max; i++) {
    scale.push(i)
  }
  return scale
}

// Helper functions for matrix questions
const getMatrixValue = (row: string): any => {
  if (typeof localValue.value === 'object' && localValue.value !== null) {
    return localValue.value[row]
  }
  return null
}

const setMatrixValue = (row: string, col: any) => {
  if (typeof localValue.value !== 'object' || localValue.value === null) {
    localValue.value = {}
  }
  localValue.value[row] = col
  emit('update:modelValue', localValue.value)
}

// File handling
const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0] || null
  selectedFile.value = file
  emit('update:modelValue', { file })
}

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}
</script>

<template>
  <div class="space-y-2">
    <!-- Question Label -->
    <Label :for="`question-${question.id}`" class="text-sm font-medium">
      {{ question.text }}
      <span v-if="question.is_required" class="text-destructive ml-1">*</span>
    </Label>
    
    <!-- Help Text -->
    <p v-if="question.help_text" class="text-xs text-muted-foreground">
      {{ question.help_text }}
    </p>

    <!-- Question Input based on type -->
    <div class="mt-2">
      <!-- Short Text -->
      <Input
        v-if="question.type === 'short_text'"
        :id="`question-${question.id}`"
        v-model="localValue"
        type="text"
        :placeholder="question.help_text"
        :class="{ 'border-destructive': errors }"
      />

      <!-- Long Text -->
      <Textarea
        v-else-if="question.type === 'long_text'"
        :id="`question-${question.id}`"
        v-model="localValue"
        :placeholder="question.help_text"
        :rows="4"
        :class="{ 'border-destructive': errors }"
      />

      <!-- Number -->
      <Input
        v-else-if="question.type === 'number'"
        :id="`question-${question.id}`"
        v-model.number="localValue"
        type="number"
        :min="question.validation_rules?.min"
        :max="question.validation_rules?.max"
        :step="question.validation_rules?.step || 'any'"
        :class="{ 'border-destructive': errors }"
      />

      <!-- Date -->
      <Input
        v-else-if="question.type === 'date'"
        :id="`question-${question.id}`"
        v-model="localValue"
        type="date"
        :class="{ 'border-destructive': errors }"
      />

      <!-- Single Choice (Radio) -->
      <RadioGroup
        v-else-if="question.type === 'single_choice'"
        v-model="localValue"
        :class="{ 'border-destructive': errors }"
      >
        <div v-for="option in question.options" :key="option.id" class="flex items-center space-x-2 mb-2">
          <RadioGroupItem :value="option.id" :id="`option-${option.id}`" />
          <Label :for="`option-${option.id}`" class="text-sm font-normal cursor-pointer">
            {{ option.label }}
          </Label>
          <Input
            v-if="option.allows_free_text && localValue === option.id"
            v-model="freeTextValues[option.id]"
            type="text"
            placeholder="Please specify..."
            class="ml-4 flex-1"
          />
        </div>
      </RadioGroup>

      <!-- Multiple Choice (Checkbox) -->
      <div v-else-if="question.type === 'multi_choice'" class="space-y-2">
        <div v-for="option in question.options" :key="option.id" class="flex items-center space-x-2">
          <Checkbox
            :id="`option-${option.id}`"
            :checked="isOptionSelected(option.id)"
            @update:checked="toggleOption(option.id)"
          />
          <Label :for="`option-${option.id}`" class="text-sm font-normal cursor-pointer">
            {{ option.label }}
          </Label>
          <Input
            v-if="option.allows_free_text && isOptionSelected(option.id)"
            v-model="freeTextValues[option.id]"
            type="text"
            placeholder="Please specify..."
            class="ml-4 flex-1"
          />
        </div>
      </div>

      <!-- Yes/No -->
      <RadioGroup
        v-else-if="question.type === 'yes_no'"
        v-model="localValue"
        :class="{ 'border-destructive': errors }"
      >
        <div class="flex items-center space-x-4">
          <div class="flex items-center space-x-2">
            <RadioGroupItem value="1" id="yes" />
            <Label for="yes" class="text-sm font-normal cursor-pointer">Yes</Label>
          </div>
          <div class="flex items-center space-x-2">
            <RadioGroupItem value="0" id="no" />
            <Label for="no" class="text-sm font-normal cursor-pointer">No</Label>
          </div>
        </div>
      </RadioGroup>

      <!-- Rating -->
      <div v-else-if="question.type === 'rating'" class="flex items-center space-x-2">
        <Button
          v-for="rating in getRatingScale()"
          :key="rating"
          type="button"
          :variant="localValue === rating ? 'default' : 'outline'"
          size="sm"
          @click="localValue = rating"
          class="w-10 h-10"
        >
          {{ rating }}
        </Button>
      </div>

      <!-- Likert Scale -->
      <RadioGroup
        v-else-if="question.type === 'likert'"
        v-model="localValue"
        :class="{ 'border-destructive': errors }"
      >
        <div class="space-y-2">
          <div v-for="option in question.options" :key="option.id" class="flex items-center space-x-2">
            <RadioGroupItem :value="option.id" :id="`likert-${option.id}`" />
            <Label :for="`likert-${option.id}`" class="text-sm font-normal cursor-pointer">
              {{ option.label }}
            </Label>
          </div>
        </div>
      </RadioGroup>

      <!-- File Upload -->
      <div v-else-if="question.type === 'file'" class="space-y-2">
        <Input
          :id="`question-${question.id}`"
          type="file"
          @change="handleFileChange"
          :accept="question.validation_rules?.mimes?.join(',')"
          :class="{ 'border-destructive': errors }"
        />
        <p v-if="selectedFile" class="text-sm text-muted-foreground">
          Selected: {{ selectedFile.name }} ({{ formatFileSize(selectedFile.size) }})
        </p>
      </div>

      <!-- Matrix -->
      <div v-else-if="question.type === 'matrix'" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead>
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500"></th>
              <th
                v-for="col in question.validation_rules?.cols || []"
                :key="col"
                class="px-4 py-2 text-center text-xs font-medium text-gray-500"
              >
                {{ col }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="row in question.validation_rules?.rows || []" :key="row">
              <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ row }}</td>
              <td
                v-for="col in question.validation_rules?.cols || []"
                :key="col"
                class="px-4 py-2 text-center"
              >
                <RadioGroupItem
                  :name="`matrix-${question.id}-${row}`"
                  :value="col"
                  :checked="getMatrixValue(row) === col"
                  @change="setMatrixValue(row, col)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Error Message -->
    <p v-if="errors" class="text-sm text-destructive mt-1">
      {{ errors }}
    </p>
  </div>
</template>