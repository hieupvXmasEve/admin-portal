<script setup lang="ts">
import EmailContentRenderer from '@/components/EmailContentRenderer.vue';
import { Dialog, DialogClose, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import DialogDescription from '@/components/ui/dialog/DialogDescription.vue';
import { EyeIcon, XIcon } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Props {
    open: boolean;
    template?: any;
    previewData?: any;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const activeTab = ref('rendered');
const sampleVariables = ref<Record<string, string>>({});
const renderedPreview = ref<any>(null);

const defaultVariableValues: Record<string, string> = {
    user_name: 'John Doe',
    user_email: 'john.doe@example.com',
    student_name: 'Jane Smith',
    student_id: 'STU001',
    course_name: 'Introduction to Computer Science',
    course_code: 'CS101',
    semester: 'Fall 2024',
    grade: 'A',
    deadline: '2024-12-15',
    system_name: 'Academic Management System',
};

// Watch for template changes
watch(
    () => props.template,
    (template) => {
        if (template && props.previewData && props.previewData.variables) {
            const samples: Record<string, string> = {};
            props.previewData.variables.forEach((variable: string) => {
                samples[variable] = defaultVariableValues[variable] || `[${variable}]`;
            });
            sampleVariables.value = samples;
            updatePreview();
        }
    },
    { immediate: true },
);

// Watch for preview data changes
watch(
    () => props.previewData,
    (data) => {
        if (data) {
            updatePreview();
        }
    },
    { immediate: true },
);

const updatePreview = () => {
    if (!props.template || !props.previewData) return;

    // Use preview data for rendering
    let subject = props.previewData.subject || '';
    let html = props.previewData.html_content || '';
    let text = props.previewData.text_content || '';

    Object.entries(sampleVariables.value).forEach(([key, value]) => {
        const placeholder = `{{${key}}}`;
        const regex = new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
        subject = subject.replace(regex, value);
        html = html.replace(regex, value);
        text = text.replace(regex, value);
    });

    renderedPreview.value = {
        subject,
        html,
        text: text || null,
    };
};

const resetToDefaults = () => {
    if (props.template && props.previewData && props.previewData.variables) {
        const samples: Record<string, string> = {};
        props.previewData.variables.forEach((variable: string) => {
            samples[variable] = defaultVariableValues[variable] || `[${variable}]`;
        });
        sampleVariables.value = samples;
        updatePreview();
    }
};

const formatHtml = (html: string): string => {
    if (!html) return '';

    // Simple HTML formatting
    return html
        .replace(/></g, '>\n<') // Add newlines between tags
        .replace(/^\s+|\s+$/g, '') // Trim whitespace
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line.length > 0)
        .join('\n');
};

const closeModal = () => {
    isOpen.value = false;
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="flex max-h-[90vh] flex-col sm:max-w-4xl">
            <!-- Fixed Header -->
            <DialogHeader class="flex-shrink-0">
                <DialogTitle class="text-lg leading-6 font-semibold text-gray-900"> Template Preview: {{ template?.name }} </DialogTitle>
                <DialogDescription class="text-sm text-gray-500"> {{ template?.description }} </DialogDescription>
                <DialogClose
                    class="ring-offset-background focus:ring-ring data-[state=open]:bg-accent data-[state=open]:text-muted-foreground absolute top-4 right-4 rounded-sm opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-none disabled:pointer-events-none"
                >
                    <XIcon class="h-4 w-4" />
                    <span class="sr-only">Close</span>
                </DialogClose>
            </DialogHeader>

            <!-- Scrollable Content -->
            <div v-if="template" class="mt-2 flex-1 overflow-y-auto">
                <!-- Template Info -->
                <div class="mb-6 rounded-lg bg-gray-50 p-4">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">Type:</span>
                            <span class="ml-2 capitalize">{{ template.type.replace('_', ' ') }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Version:</span>
                            <span class="ml-2">{{ template.version }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Status:</span>
                            <span :class="['ml-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', template.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800']">
                                {{ template.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">Variables:</span>
                            <span class="ml-2">{{ template.variables?.length || 0 }}</span>
                        </div>
                    </div>
                    <div v-if="template.description" class="mt-3">
                        <span class="font-medium text-gray-700">Description:</span>
                        <p class="mt-1 text-sm text-gray-600">{{ template.description }}</p>
                    </div>
                </div>

                <!-- Variable Input -->
                <div v-if="template.variables && template.variables.length > 0" class="mb-6">
                    <h4 class="mb-3 text-sm font-medium text-gray-900">Sample Variables</h4>
                    <div class="rounded-lg bg-blue-50 p-4">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div v-for="variable in template.variables" :key="variable" class="flex items-center space-x-2">
                                <label :for="`var-${variable}`" class="w-24 flex-shrink-0 text-sm font-medium text-gray-700"> {{ variable }}: </label>
                                <input
                                    :id="`var-${variable}`"
                                    v-model="sampleVariables[variable]"
                                    type="text"
                                    :placeholder="`Sample ${variable}`"
                                    class="flex-1 rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    @input="updatePreview"
                                />
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end">
                            <button @click="resetToDefaults" class="text-xs text-indigo-600 hover:text-indigo-500">Reset to defaults</button>
                        </div>
                    </div>
                </div>

                <!-- Preview Tabs -->
                <div>
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <button
                                type="button"
                                @click="activeTab = 'rendered'"
                                :class="['border-b-2 px-1 py-2 text-sm font-medium', activeTab === 'rendered' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700']"
                            >
                                Rendered Preview
                            </button>
                            <button
                                type="button"
                                @click="activeTab = 'html'"
                                :class="['border-b-2 px-1 py-2 text-sm font-medium', activeTab === 'html' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700']"
                            >
                                HTML Source
                            </button>
                            <button
                                v-if="renderedPreview?.text"
                                type="button"
                                @click="activeTab = 'text'"
                                :class="['border-b-2 px-1 py-2 text-sm font-medium', activeTab === 'text' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700']"
                            >
                                Plain Text
                            </button>
                        </nav>
                    </div>

                    <div class="mt-4">
                        <!-- Rendered Preview -->
                        <div v-show="activeTab === 'rendered'" class="space-y-4">
                            <div v-if="renderedPreview">
                                <!-- Subject -->
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <h4 class="mb-2 text-sm font-medium text-gray-900">Subject Line</h4>
                                    <div class="rounded border bg-white p-3 text-sm font-medium">
                                        {{ renderedPreview.subject }}
                                    </div>
                                </div>

                                <!-- HTML Content -->
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <h4 class="mb-2 text-sm font-medium text-gray-900">Email Content</h4>
                                    <div class="max-h-96 overflow-y-auto rounded border bg-white p-4">
                                        <!-- <div v-html="renderedPreview.html"></div> -->
                                        <EmailContentRenderer :content="renderedPreview.html" />
                                    </div>
                                </div>
                            </div>
                            <div v-else class="py-8 text-center text-gray-500">
                                <EyeIcon class="mx-auto mb-2 h-8 w-8" />
                                <p class="text-sm">Loading preview...</p>
                            </div>
                        </div>

                        <!-- HTML Source -->
                        <div v-show="activeTab === 'html'" class="space-y-4">
                            <div v-if="renderedPreview">
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <h4 class="mb-2 text-sm font-medium text-gray-900">HTML Source</h4>
                                    <div class="max-h-96 overflow-auto rounded border bg-white">
                                        <pre class="p-4 text-xs break-words whitespace-pre-wrap"><code>{{ formatHtml(renderedPreview.html) }}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Plain Text -->
                        <div v-show="activeTab === 'text'" class="space-y-4">
                            <div v-if="renderedPreview?.text">
                                <div class="rounded-lg bg-gray-50 p-4">
                                    <h4 class="mb-2 text-sm font-medium text-gray-900">Plain Text Version</h4>
                                    <pre class="rounded border bg-white p-4 text-sm whitespace-pre-wrap">{{ renderedPreview.text }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fixed Footer -->
            <div class="mt-2 flex flex-shrink-0 justify-end space-x-3 border-gray-200 pt-4">
                <button
                    @click="closeModal"
                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                >
                    Close
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
