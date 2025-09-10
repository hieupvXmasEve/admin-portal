<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useEmailTemplate } from '@/composables/useEmailTemplate';
import { toTypedSchema } from '@vee-validate/zod';
import { EyeIcon } from 'lucide-vue-next';
import { useForm } from 'vee-validate';
import { computed, ref, watch } from 'vue';
import * as z from 'zod';

interface Props {
    open: boolean;
    template?: any;
    templateTypes: Record<string, string>;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'saved'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const { createTemplate, updateTemplate, previewTemplate } = useEmailTemplate();

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const isEditing = computed(() => !!props.template && props.template.id > 0);

// Zod validation schema
const formSchema = toTypedSchema(
    z.object({
        name: z.string().min(1, 'Template name is required'),
        type: z.string().min(1, 'Template type is required'),
        subject: z.string().min(1, 'Subject line is required'),
        html_content: z.string().min(1, 'HTML content is required'),
        text_content: z.string().optional(),
        description: z.string().optional(),
        is_active: z.boolean(),
    }),
);

// Form setup with vee-validate
const { handleSubmit, setValues, values, resetForm } = useForm({
    validationSchema: formSchema,
});
const isSubmitting = ref(false);
const isGeneratingPreview = ref(false);
const activeTab = ref('html');
const previewData = ref<any>(null);
const sampleVariables = ref<Record<string, string>>({});

const commonVariables = [
    { name: 'user_name', description: 'User full name' },
    { name: 'user_email', description: 'User email address' },
    { name: 'student_name', description: 'Student name' },
    { name: 'student_id', description: 'Student ID' },
    { name: 'course_name', description: 'Course name' },
    { name: 'course_code', description: 'Course code' },
    { name: 'semester', description: 'Current semester' },
    { name: 'grade', description: 'Grade/Score' },
    { name: 'deadline', description: 'Deadline date' },
    { name: 'system_name', description: 'System name' },
];

const extractedVariables = computed(() => {
    const content = (values.subject || '') + ' ' + (values.html_content || '') + ' ' + (values.text_content || '');
    const matches = content.match(/\{\{([^}]+)\}\}/g);
    if (!matches) return [];

    return [...new Set(matches.map((match) => match.slice(2, -2).trim()))];
});

// Watch for template changes
watch(
    () => [props.open, props.template],
    ([isOpen, template]) => {
        if (isOpen) {
            previewData.value = null;
            activeTab.value = 'html';

            if (template) {
                setValues({
                    name: template.name || '',
                    type: template.type || '',
                    subject: template.subject || '',
                    html_content: template.html_content || '',
                    text_content: template.text_content || '',
                    description: template.description || '',
                    is_active: template.is_active ?? true,
                });

                // Initialize sample variables
                if (template.variables) {
                    const samples: Record<string, string> = {};
                    template.variables.forEach((variable: string) => {
                        samples[variable] = `[${variable}]`;
                    });
                    sampleVariables.value = samples;
                }
            } else {
                // Reset form for new template
                setValues({
                    name: '',
                    type: '',
                    subject: '',
                    html_content: '',
                    text_content: '',
                    description: '',
                    is_active: true,
                });
                sampleVariables.value = {};
            }
        }
    },
    { immediate: true },
);

// Watch for variable changes to update sample variables
watch(extractedVariables, (newVariables) => {
    const samples: Record<string, string> = {};
    newVariables.forEach((variable) => {
        samples[variable] = sampleVariables.value[variable] || `[${variable}]`;
    });
    sampleVariables.value = samples;
});

const generatePreview = async () => {
    if (!values.html_content) return;

    isGeneratingPreview.value = true;
    try {
        // Create a temporary template object for preview
        const tempTemplate = {
            subject: values.subject || '',
            html_content: values.html_content || '',
            text_content: values.text_content || '',
        };

        // Use sample variables for preview
        const variables = { ...sampleVariables.value };

        // Simple client-side preview generation
        let subject = tempTemplate.subject;
        let html = tempTemplate.html_content;
        let text = tempTemplate.text_content || '';

        Object.entries(variables).forEach(([key, value]) => {
            const placeholder = `{{${key}}}`;
            subject = subject.replace(new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), value);
            html = html.replace(new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), value);
            text = text.replace(new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), value);
        });

        previewData.value = {
            subject,
            html,
            text: text || null,
        };
    } catch (error) {
        console.error('Failed to generate preview:', error);
    } finally {
        isGeneratingPreview.value = false;
    }
};

const onSubmit = handleSubmit(async (formValues) => {
    isSubmitting.value = true;

    try {
        if (isEditing.value) {
            await updateTemplate(props.template.id, formValues);
        } else {
            await createTemplate(formValues);
        }

        emit('saved');
    } catch (error: any) {
        console.error('Failed to save template:', error);
    } finally {
        isSubmitting.value = false;
    }
});

const closeModal = () => {
    isOpen.value = false;
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Email Template' : 'Create Email Template' }}
                </DialogTitle>
                <DialogDescription></DialogDescription>
            </DialogHeader>

            <form @submit.prevent="onSubmit" class="space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <FormField name="name" v-slot="{ componentField }">
                        <FormItem>
                            <FormLabel>Template Name *</FormLabel>
                            <FormControl>
                                <Input v-bind="componentField" placeholder="e.g., Welcome Email, Grade Notification" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField name="type" v-slot="{ componentField }">
                        <FormItem>
                            <FormLabel>Template Type *</FormLabel>
                            <Select v-bind="componentField">
                                <FormControl>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                </FormControl>
                                <SelectContent>
                                    <SelectItem v-for="(label, value) in templateTypes" :key="value" :value="value">
                                        {{ label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <FormMessage />
                        </FormItem>
                    </FormField>

                    <FormField name="description" v-slot="{ componentField }" class="sm:col-span-2">
                        <FormItem>
                            <FormLabel>Description</FormLabel>
                            <FormControl>
                                <Textarea v-bind="componentField" rows="2" placeholder="Brief description of this template's purpose" />
                            </FormControl>
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </div>

                <!-- Subject Line -->
                <FormField name="subject" v-slot="{ componentField }">
                    <FormItem>
                        <FormLabel>Subject Line *</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" placeholder="Email subject line (use {{variable}} for dynamic content)" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <!-- Content Tabs -->
                <Tabs v-model="activeTab" class="w-full">
                    <TabsList class="grid w-full grid-cols-4">
                        <TabsTrigger value="html">HTML Content</TabsTrigger>
                        <TabsTrigger value="text">Plain Text</TabsTrigger>
                        <TabsTrigger value="preview">Preview</TabsTrigger>
                        <TabsTrigger value="variables">Variables ({{ extractedVariables.length }})</TabsTrigger>
                    </TabsList>

                    <TabsContent value="html" class="space-y-4">
                        <FormField name="html_content" v-slot="{ componentField }">
                            <FormItem>
                                <FormLabel>HTML Content *</FormLabel>
                                <FormControl>
                                    <Textarea v-bind="componentField" rows="12" class="font-mono" placeholder="Enter HTML content here. Use {{variable_name}} for dynamic content." />
                                </FormControl>
                                <FormDescription> Use {{ `variable_name` }} syntax for dynamic content. HTML tags are supported. </FormDescription>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </TabsContent>

                    <TabsContent value="text" class="space-y-4">
                        <FormField name="text_content" v-slot="{ componentField }">
                            <FormItem>
                                <FormLabel>Plain Text Content</FormLabel>
                                <FormControl>
                                    <Textarea v-bind="componentField" rows="12" class="font-mono" placeholder="Enter plain text version (optional). Use {{variable_name}} for dynamic content." />
                                </FormControl>
                                <FormDescription> Optional plain text version for email clients that don't support HTML. </FormDescription>
                                <FormMessage />
                            </FormItem>
                        </FormField>
                    </TabsContent>

                    <TabsContent value="preview" class="space-y-4">
                        <div class="bg-muted/50 rounded-lg p-4">
                            <div class="mb-4 flex items-center justify-between">
                                <h4 class="text-sm font-medium">Template Preview</h4>
                                <Button type="button" variant="outline" size="sm" @click="generatePreview" :disabled="isGeneratingPreview">
                                    <EyeIcon :class="['mr-1 h-3 w-3', { 'animate-spin': isGeneratingPreview }]" />
                                    {{ isGeneratingPreview ? 'Generating...' : 'Generate Preview' }}
                                </Button>
                            </div>

                            <div v-if="previewData" class="space-y-4">
                                <div>
                                    <label class="mb-1 block text-xs font-medium">Subject:</label>
                                    <div class="bg-background rounded border p-2 text-sm">{{ previewData.subject }}</div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium">HTML Content:</label>
                                    <div class="bg-background max-h-64 overflow-y-auto rounded border p-4" v-html="previewData.html"></div>
                                </div>
                                <div v-if="previewData.text">
                                    <label class="mb-1 block text-xs font-medium">Plain Text:</label>
                                    <div class="bg-background rounded border p-2 font-mono text-sm whitespace-pre-wrap">{{ previewData.text }}</div>
                                </div>
                            </div>

                            <div v-else class="text-muted-foreground py-8 text-center">
                                <EyeIcon class="mx-auto mb-2 h-8 w-8" />
                                <p class="text-sm">Click "Generate Preview" to see how your template will look</p>
                            </div>
                        </div>
                    </TabsContent>

                    <TabsContent value="variables" class="space-y-4">
                        <div class="bg-primary/5 rounded-lg p-4">
                            <h4 class="mb-2 text-sm font-medium">Detected Variables</h4>
                            <div v-if="extractedVariables.length > 0" class="space-y-2">
                                <div v-for="variable in extractedVariables" :key="variable" class="bg-background flex items-center justify-between rounded border p-2">
                                    <code class="text-primary text-sm">{{ variable }}</code>
                                    <Input v-model="sampleVariables[variable]" :placeholder="`Sample value for ${variable}`" class="ml-2 flex-1 text-sm" />
                                </div>
                            </div>
                            <div v-else class="text-muted-foreground text-sm">No variables detected. Use {{ variable_name }} syntax in your content to create dynamic placeholders.</div>
                        </div>

                        <div class="bg-muted/50 rounded-lg p-4">
                            <h4 class="mb-2 text-sm font-medium">Common Variables</h4>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div v-for="commonVar in commonVariables" :key="commonVar.name" class="flex items-center justify-between">
                                    <code class="text-muted-foreground">{{ commonVar.name }}</code>
                                    <span class="text-muted-foreground">{{ commonVar.description }}</span>
                                </div>
                            </div>
                        </div>
                    </TabsContent>
                </Tabs>

                <!-- Active Status -->
                <FormField name="is_active" v-slot="{ componentField }">
                    <FormItem class="flex flex-row items-start space-y-0 space-x-3">
                        <FormControl>
                            <Checkbox v-bind="componentField" />
                        </FormControl>
                        <div class="space-y-1 leading-none">
                            <FormLabel>Set as active template</FormLabel>
                        </div>
                    </FormItem>
                </FormField>
            </form>

            <DialogFooter>
                <Button type="button" variant="outline" @click="closeModal"> Cancel </Button>
                <Button type="submit" :disabled="isSubmitting" @click="onSubmit">
                    {{ isSubmitting ? 'Saving...' : isEditing ? 'Update Template' : 'Create Template' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
