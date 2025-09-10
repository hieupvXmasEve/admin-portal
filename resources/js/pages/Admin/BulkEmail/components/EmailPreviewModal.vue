
<script setup lang="ts">
import { ref, computed } from 'vue'
import {
    DialogRoot,
    DialogPortal,
    DialogOverlay,
    DialogContent,
    DialogTitle,
    DialogClose
} from 'reka-ui'
import {
    XIcon,
    InfoIcon
} from 'lucide-vue-next'

interface PreviewSample {
    email: string
    name?: string
    subject: string
    content: string
}

interface Props {
    open: boolean
    subject: string
    content: string
    samples?: PreviewSample[]
}

interface Emits {
    (e: 'update:open', value: boolean): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value)
})

const activeTab = ref('rendered')
const currentSampleIndex = ref(0)
const hasSamples = computed(() => Array.isArray(props.samples) && (props.samples?.length || 0) > 0)
const visibleSamples = computed(() => (props.samples || []).slice(0, 3))
const setSample = (idx: number) => { if (idx >=0 && idx < visibleSamples.value.length) currentSampleIndex.value = idx }

const isHtmlContent = computed(() => {
    return props.content.includes('<') && props.content.includes('>')
})

const closeModal = () => {
  isOpen.value = false
}
</script>

<template>
    <DialogRoot v-model:open="isOpen">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" />
            <DialogContent class="fixed inset-0 z-50 overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div
                        class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl sm:p-6">
                        <div>
                            <div class="flex items-center justify-between">
                                <DialogTitle class="text-lg font-semibold leading-6 text-gray-900">
                                    Email Preview
                                </DialogTitle>
                                <DialogClose
                                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    <XIcon class="h-6 w-6" />
                                </DialogClose>
                            </div>

                            <div class="mt-6">
                                <!-- Email Header -->
                                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                    <div class="space-y-2">
                                        <div class="flex items-center text-sm">
                                            <span class="font-medium text-gray-700 w-16">From:</span>
                                            <span class="text-gray-600">Academic Management System
                                                &lt;noreply@example.com&gt;</span>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <span class="font-medium text-gray-700 w-16">To:</span>
                                            <span class="text-gray-600">Recipients (bulk email)</span>
                                        </div>
                                        <div class="flex items-center text-sm">
                                            <span class="font-medium text-gray-700 w-16">Subject:</span>
                                            <span class="text-gray-900 font-medium">{{ subject || 'No subject' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Per-student selector (if provided) -->
                                <div v-if="hasSamples" class="mb-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm text-gray-600">Showing preview for {{ visibleSamples.length }} sample recipient(s)</span>
                                    </div>
                                    <div class="flex gap-2 overflow-x-auto pb-2">
                                        <button
                                            v-for="(s, idx) in visibleSamples"
                                            :key="s.email"
                                            type="button"
                                            @click="setSample(idx)"
                                            :class="['px-3 py-2 rounded-md text-sm border', idx === currentSampleIndex ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50']"
                                            title="Switch sample"
                                        >
                                            <div class="font-medium truncate max-w-[180px]">{{ s.name || s.email }}</div>
                                            <div class="text-xs text-gray-500 truncate max-w-[180px]">{{ s.email }}</div>
                                        </button>
                                    </div>
                                </div>

                                <!-- Content Tabs -->
                                <div>
                                    <div class="border-b border-gray-200">
                                        <nav class="-mb-px flex space-x-8">
                                            <button type="button" @click="activeTab = 'rendered'" :class="[
                        'py-2 px-1 border-b-2 font-medium text-sm',
                        activeTab === 'rendered'
                          ? 'border-indigo-500 text-indigo-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      ]">
                                                Rendered View
                                            </button>
                                            <button type="button" @click="activeTab = 'source'" :class="[
                        'py-2 px-1 border-b-2 font-medium text-sm',
                        activeTab === 'source'
                          ? 'border-indigo-500 text-indigo-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                      ]">
                                                Source Code
                                            </button>
                                        </nav>
                                    </div>

                                    <div class="mt-4">
                                        <!-- Rendered View -->
                                        <div v-show="activeTab === 'rendered'"
                                            class="bg-white border rounded-lg p-6 max-h-96 overflow-y-auto">
                                            <template v-if="hasSamples">
                                                <div class="mb-4 text-sm text-gray-600 flex items-center gap-2">
                                                    <span class="font-medium">To:</span>
                                                    <span>{{ visibleSamples[currentSampleIndex].name || visibleSamples[currentSampleIndex].email }}</span>
                                                    <span class="text-gray-400">&lt;{{ visibleSamples[currentSampleIndex].email }}&gt;</span>
                                                </div>
                                                <div class="mb-3 text-sm">
                                                    <span class="font-medium text-gray-700">Subject:</span>
                                                    <span class="text-gray-900">{{ visibleSamples[currentSampleIndex].subject || subject }}</span>
                                                </div>
                                                <div v-if="(visibleSamples[currentSampleIndex].content || content).includes('<') && (visibleSamples[currentSampleIndex].content || content).includes('>')" v-html="visibleSamples[currentSampleIndex].content || content"></div>
                                                <div v-else class="whitespace-pre-wrap font-mono text-sm">{{ visibleSamples[currentSampleIndex].content || content }}</div>
                                            </template>
                                            <template v-else>
                                                <div v-if="isHtmlContent" v-html="content"></div>
                                                <div v-else class="whitespace-pre-wrap font-mono text-sm">{{ content }}</div>
                                            </template>
                                        </div>

                                        <!-- Source Code -->
                                        <div v-show="activeTab === 'source'"
                                            class="bg-gray-50 rounded-lg p-4 max-h-96 overflow-y-auto">
                                            <pre
                                                class="text-xs text-gray-800 whitespace-pre-wrap"><code>{{ content }}</code></pre>
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview Notes -->
                                <div class="mt-4 bg-blue-50 rounded-lg p-3">
                                    <div class="flex">
                                        <InfoIcon class="h-5 w-5 text-blue-400 flex-shrink-0" />
                                        <div class="ml-3">
                                            <h4 class="text-sm font-medium text-blue-800">Preview Notes</h4>
                                            <div class="mt-1 text-sm text-blue-700">
                                                <ul class="list-disc list-inside space-y-1">
                                                    <li>This is how your email will appear to recipients</li>
                                                    <li>Variable placeholders (if any) will be replaced with actual
                                                        values</li>
                                                    <li>Email client rendering may vary slightly from this preview</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="mt-6 flex justify-end space-x-3">
                                <button @click="closeModal"
                                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
