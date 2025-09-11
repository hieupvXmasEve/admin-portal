<script setup lang="ts">
import EmailEditor from '@/components/EmailEditor.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useEmailTemplate } from '@/composables/useEmailTemplate';
import type { EmailVariable } from '@/types/email';
import { DEFAULT_EMAIL_VARIABLES } from '@/types/email';
import { EmailTemplate } from '@/types/models';
import { router } from '@inertiajs/vue3';
import { toTypedSchema } from '@vee-validate/zod';
import { useForm } from 'vee-validate';
import { computed, ref, watch } from 'vue';
import * as z from 'zod';

interface Props {
    templateTypes: Record<string, string>;
    originalTemplate?: EmailTemplate | null;
    isCreatingVersion?: boolean;
}

const props = defineProps<Props>();
const { createTemplate, createNewVersion } = useEmailTemplate();

// Validation schema
const formSchema = toTypedSchema(
    z.object({
        name: z.string().min(1, 'Template name is required'),
        type: z.string().min(1, 'Template type is required'),
        subject: z.string().min(1, 'Subject is required'),
        html_content: z.string().min(1, 'Content is required'),
        text_content: z.string().optional(),
        description: z.string().optional(),
        is_active: z.boolean().default(true),
    }),
);

const { handleSubmit, values } = useForm({
    validationSchema: formSchema,
    // Preserve field values even when their components unmount (e.g., when switching tabs)
    keepValuesOnUnmount: true,
    initialValues: {
        name: props.originalTemplate?.name || '',
        type: props.originalTemplate?.type || '',
        subject: props.originalTemplate?.subject || '',
        html_content: props.originalTemplate?.html_content || '',
        text_content: props.originalTemplate?.text_content || '',
        description: props.originalTemplate?.description || '',
        is_active: true,
    },
});

const activeTab = ref('content');
const isSubmitting = ref(false);
const sampleVariables = ref<Record<string, string>>({});

// Extract variables from subject + html + text
const extractedVariables = computed(() => {
    const content = (values.subject || '') + ' ' + (values.html_content || '') + ' ' + (values.text_content || '');
    const matches = content.match(/\{\{([^}]+)\}\}/g);
    if (!matches) return [];
    return [...new Set(matches.map((m) => m.slice(2, -2).trim()))];
});

watch(extractedVariables, (vars) => {
    const samples: Record<string, string> = {};
    vars.forEach((v) => {
        samples[v] = sampleVariables.value[v] || `[${v}]`;
    });
    sampleVariables.value = samples;
});

const commonVariables: EmailVariable[] = DEFAULT_EMAIL_VARIABLES;
const onSubmit = handleSubmit(async (formValues) => {
    isSubmitting.value = true;
    try {
        if (props.isCreatingVersion && props.originalTemplate) {
            await createNewVersion(props.originalTemplate.id, formValues);
        } else {
            await createTemplate(formValues);
        }
        router.visit('/systems/email-templates');
    } catch (error) {
        // Error handling is done in the composable via toast
        // Just prevent navigation if there's an error
        console.error('Template creation failed:', error);
    } finally {
        isSubmitting.value = false;
    }
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">{{ props.isCreatingVersion ? 'Create New Template Version' : 'Create Email Template' }}</h1>
            <p class="text-muted-foreground">{{ props.isCreatingVersion ? `Creating a new version of "${props.originalTemplate?.name}"` : 'Use the editor to compose your email content.' }}</p>
        </div>

        <form @submit.prevent="onSubmit" class="space-y-6">
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <FormField name="name" v-slot="{ componentField }">
                    <FormItem>
                        <FormLabel>Template Name *</FormLabel>
                        <FormControl>
                            <Input v-bind="componentField" :readonly="props.isCreatingVersion" :class="{ 'bg-muted': props.isCreatingVersion }" placeholder="e.g., Welcome Email" />
                        </FormControl>
                        <FormDescription v-if="props.isCreatingVersion"> Template name cannot be changed when creating a new version. </FormDescription>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField name="type" v-slot="{ componentField }">
                    <FormItem>
                        <FormLabel>Template Type *</FormLabel>
                        <Select v-bind="componentField" :disabled="props.isCreatingVersion">
                            <FormControl>
                                <SelectTrigger :class="{ 'bg-muted': props.isCreatingVersion }">
                                    <SelectValue placeholder="Select type" />
                                </SelectTrigger>
                            </FormControl>
                            <SelectContent>
                                <SelectItem v-for="(label, value) in templateTypes" :key="value" :value="value">{{ label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <FormDescription v-if="props.isCreatingVersion"> Template type cannot be changed when creating a new version. </FormDescription>
                        <FormMessage />
                    </FormItem>
                </FormField>

                <FormField name="description" v-slot="{ componentField }" class="sm:col-span-2">
                    <FormItem>
                        <FormLabel>Description</FormLabel>
                        <FormControl>
                            <Textarea v-bind="componentField" rows="2" placeholder="Brief description" />
                        </FormControl>
                        <FormMessage />
                    </FormItem>
                </FormField>
            </div>

            <FormField name="subject" v-slot="{ componentField }">
                <FormItem>
                    <FormLabel>Subject *</FormLabel>
                    <FormControl>
                        <Input v-bind="componentField" placeholder="Subject (use {{variable}} for dynamic content)" />
                    </FormControl>
                    <FormMessage />
                </FormItem>
            </FormField>

            <Tabs v-model="activeTab">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="content">Content & Preview</TabsTrigger>
                    <TabsTrigger value="variables">Variables ({{ extractedVariables.length }})</TabsTrigger>
                </TabsList>

                <TabsContent value="content" class="space-y-2">
                    <FormField name="html_content" v-slot="{ componentField }">
                        <FormItem>
                            <EmailEditor v-bind="componentField" :common-variables="commonVariables" min-height="300px" :show-preview="true" placeholder="Start typing your email content..." />
                            <FormMessage />
                        </FormItem>
                    </FormField>
                </TabsContent>

                <TabsContent value="variables" class="space-y-4">
                    <div class="bg-primary/5 rounded-lg p-4">
                        <h4 class="mb-2 text-sm font-medium">Detected Variables</h4>
                        <div v-if="extractedVariables.length" class="space-y-2">
                            <div v-for="v in extractedVariables" :key="v" class="flex items-center gap-2">
                                <code class="text-primary text-sm">{{ v }}</code>
                                <Input v-model="sampleVariables[v]" class="flex-1" :placeholder="`Sample for ${v}`" />
                            </div>
                        </div>
                        <div v-else class="text-muted-foreground text-sm">No variables detected. Use {{ `variable_name` }} syntax in subject or content.</div>
                    </div>

                    <div class="bg-muted/50 rounded-lg p-4">
                        <h4 class="mb-2 text-sm font-medium">Common Variables</h4>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div v-for="cv in commonVariables" :key="cv.name" class="flex items-center justify-between">
                                <code class="text-muted-foreground">{{ cv.name }}</code>
                                <span class="text-muted-foreground">{{ cv.description }}</span>
                            </div>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>

            <FormField name="text_content" v-slot="{ componentField }">
                <FormItem>
                    <FormLabel>Plain Text (optional)</FormLabel>
                    <FormControl>
                        <Textarea v-bind="componentField" rows="5" placeholder="Text-only version" />
                    </FormControl>
                    <FormDescription>Plain text is recommended for maximum email client compatibility.</FormDescription>
                    <FormMessage />
                </FormItem>
            </FormField>

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

            <div class="flex justify-end gap-2">
                <Button type="button" variant="outline" @click="router.visit('/systems/email-templates')">Cancel</Button>
                <Button type="submit" :disabled="isSubmitting">
                    {{ isSubmitting ? 'Saving...' : props.isCreatingVersion ? 'Create New Version' : 'Create Template' }}
                </Button>
            </div>
        </form>
    </div>
</template>
