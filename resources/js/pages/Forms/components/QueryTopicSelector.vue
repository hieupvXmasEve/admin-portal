<script setup lang="ts">
import { computed, watch } from 'vue'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent } from '@/components/ui/card'

interface QueryTopic {
  id: number
  title: string
  description: string
}

interface Props {
  topics: QueryTopic[]
  modelValue?: number | null
}

interface Emits {
  (e: 'update:modelValue', value: number | null): void
  (e: 'update:customTopic', value: string): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const selectedTopicId = computed({
  get: () => props.modelValue?.toString() || '',
  set: (value: string) => {
    const numValue = value === '' ? null : parseInt(value)
    emit('update:modelValue', numValue)
  }
})

const isCustomSelected = computed(() => props.modelValue === 0)

// Reset custom topic when switching away from "Other"
watch(() => props.modelValue, (newValue) => {
  if (newValue !== 0) {
    emit('update:customTopic', '')
  }
})

const handleCustomTopicChange = (event: Event) => {
  const target = event.target as HTMLTextAreaElement
  emit('update:customTopic', target.value)
}
</script>

<template>
  <div class="space-y-4">
    <RadioGroup v-model="selectedTopicId" class="space-y-3">
      <div
        v-for="topic in topics"
        :key="topic.id"
        class="space-y-2"
      >
        <Card 
          class="cursor-pointer transition-all hover:bg-muted/50"
          :class="{
            'ring-2 ring-primary': selectedTopicId === topic.id.toString(),
            'border-muted': selectedTopicId !== topic.id.toString()
          }"
        >
          <CardContent class="p-4">
            <div class="flex items-start space-x-3">
              <RadioGroupItem
                :value="topic.id.toString()"
                :id="`topic-${topic.id}`"
                class="mt-1"
              />
              <div class="flex-1 min-w-0">
                <Label
                  :for="`topic-${topic.id}`"
                  class="cursor-pointer block"
                >
                  <div class="font-medium">{{ topic.title }}</div>
                  <div class="text-sm text-muted-foreground mt-1">
                    {{ topic.description }}
                  </div>
                </Label>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </RadioGroup>

    <!-- Custom topic input for "Other" selection -->
    <div v-if="isCustomSelected" class="mt-4">
      <Label for="custom-topic" class="text-sm font-medium">
        Please describe your query topic
      </Label>
      <Textarea
        id="custom-topic"
        placeholder="Enter your custom topic description..."
        class="mt-2"
        rows="3"
        @input="handleCustomTopicChange"
      />
    </div>
  </div>
</template>