<script setup lang="ts">
import EditorContent from '@/components/EditorContent.vue';
import EmailContentRenderer from '@/components/EmailContentRenderer.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Edit3, Eye } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { EmailVariable, BulkEmailStudent } from '@/types/email';
import { DEFAULT_EMAIL_VARIABLES, getStudentSampleData } from '@/types/email';

interface Props {
    modelValue?: string;
    placeholder?: string;
    minHeight?: string;
    commonVariables?: EmailVariable[];
    showPreview?: boolean;
    students?: BulkEmailStudent[]; // Optional student data for preview
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: 'Start typing your email content...',
    minHeight: '300px',
    showPreview: true,
    commonVariables: () => DEFAULT_EMAIL_VARIABLES,
    students: () => [],
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const currentTab = ref('edit');
const content = ref(props.modelValue);

const updateContent = (value: string) => {
    content.value = value;
    emit('update:modelValue', value);
};

// Process content for preview (replace variables with sample data)
const processedContent = computed(() => {
    let processed = content.value;

    // Get sample data based on provided students or use defaults
    const sampleData = getStudentSampleData(props.students || []);

    // Replace variables in the format {{variable_name}}
    Object.entries(sampleData).forEach(([key, value]) => {
        const regex = new RegExp(`{{\\s*${key}\\s*}}`, 'g');
        processed = processed.replace(regex, `<strong>${value}</strong>`);
    });

    return processed;
});
</script>

<template>
    <div class="space-y-4">
        <div v-if="showPreview" class="w-full">
            <Tabs v-model="currentTab" class="w-full">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="edit" class="flex items-center gap-2">
                        <Edit3 class="h-4 w-4" />
                        Edit
                    </TabsTrigger>
                    <TabsTrigger value="preview" class="flex items-center gap-2">
                        <Eye class="h-4 w-4" />
                        Preview
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="edit" class="mt-4">
                    <EditorContent :model-value="content" @update:model-value="updateContent" :placeholder="placeholder" :min-height="minHeight" :common-variables="commonVariables" :email-mode="true" />
                </TabsContent>

                <TabsContent value="preview" class="mt-4">
                    <div class="rounded-md border" :style="{ minHeight }">
                        <div class="p-4">
                            <div class="text-muted-foreground mb-4 text-sm">Preview with sample data:</div>
                            <EmailContentRenderer :content="processedContent" />
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>

        <div v-else>
            <EditorContent :model-value="content" @update:model-value="updateContent" :placeholder="placeholder" :min-height="minHeight" :common-variables="commonVariables" :email-mode="true" />
        </div>
    </div>
</template>
