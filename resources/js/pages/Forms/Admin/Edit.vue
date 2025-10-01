<script setup lang="ts">
import FormBuilder from '@/components/forms/FormBuilder.vue';
import FormPreviewModal from '@/components/forms/FormPreviewModal.vue';
import TargetingSettings from '@/components/forms/TargetingSettings.vue';
import VisibilitySettings from '@/components/forms/VisibilitySettings.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import type { Form } from '@/types/forms';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Eye, Save } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    form: Form;
    roles: Array<{
        id: number;
        code: string;
        name: string;
    }>;
    campuses: Array<{
        id: number;
        name: string;
        code: string;
    }>;
    queryTopics: Array<{
        id: number;
        title: string;
    }>;
    questionTypes: Record<string, string>;
    visibilityLevels: Record<string, string>;
    scopeTypes: Record<string, string>;
}

const props = defineProps<Props>();
console.log('form', props.form);
// Form data
const editForm = useForm({
    title: props.form.title,
    description: props.form.description || '',
    status: props.form.status,
    effective_from: '',
    effective_to: '',
    sections: props.form.versions?.[props.form.versions.length - 1]?.sections || [],
    questions: props.form.versions?.[props.form.versions.length - 1]?.questions?.filter((q) => !q.section_id) || [],
    visibility_roles: props.form.visibility_roles?.map((role) => role.id) || [],
    result_visibility: props.form.result_visibility || [],
    targets: props.form.targets || [],
});

// Local state
const currentTab = ref('basic');
// const useAdvancedBuilder = ref((props.form.versions?.[props.form.versions.length - 1]?.sections?.length || 0) > 0);
const showPreview = ref(false);

// Computed
const formTypeOptions = [
    { value: 'feedback', label: 'Feedback Form' },
    { value: 'survey', label: 'Survey Form' },
    { value: 'query', label: 'Query Form' },
];

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Active' },
    { value: 'archived', label: 'Archived' },
];

// Methods
const submitForm = () => {
    editForm.put(route('forms.admin.update', props.form.id), {
        onSuccess: () => {
            // Success handled by redirect
        },
        onError: (errors) => {
            console.error('Form update errors:', errors);
        },
    });
};

const previewForm = () => {
    showPreview.value = true;
};

// Transform form sections to include questions for advanced builder
if (props.form.current_version?.sections) {
    editForm.sections = props.form.current_version.sections.map((section) => ({
        id: section.id,
        title: section.title,
        description: section.description,
        order_index: section.order_index,
        questions:
            section.questions?.map((question) => ({
                id: question.id,
                code: question.code,
                text: question.text,
                type: question.type,
                is_required: question.is_required,
                help_text: question.help_text,
                order_index: question.order_index,
                validation_json: question.validation_json,
                visibility_condition_json: question.visibility_condition_json,
                options:
                    question.options?.map((option) => ({
                        id: option.id,
                        value: option.value,
                        label: option.label,
                        order_index: option.order_index,
                        allows_free_text: option.allows_free_text,
                    })) || [],
            })) || [],
    }));
}

// Transform standalone questions
if (props.form.current_version?.questions) {
    editForm.questions = props.form.current_version.questions
        .filter((q) => !q.section_id)
        .map((question) => ({
            id: question.id,
            code: question.code,
            text: question.text,
            type: question.type,
            is_required: question.is_required,
            help_text: question.help_text,
            order_index: question.order_index,
            validation_json: question.validation_json,
            visibility_condition_json: question.visibility_condition_json,
            options:
                question.options?.map((option) => ({
                    id: option.id,
                    value: option.value,
                    label: option.label,
                    order_index: option.order_index,
                    allows_free_text: option.allows_free_text,
                })) || [],
        }));
}
</script>

<template>
    <Head :title="`Edit Form: ${form.title}`" />

    <div>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <Button variant="ghost" @click="router.visit(route('forms.admin.show', form.id))">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Edit Form</h1>
                    <p class="text-muted-foreground">Modify {{ form.title }}</p>
                </div>
            </div>
            <div class="flex space-x-2">
                <Button variant="outline" @click="previewForm">
                    <Eye class="mr-2 h-4 w-4" />
                    Preview
                </Button>
                <Button @click="submitForm" :disabled="editForm.processing">
                    <Save class="mr-2 h-4 w-4" />
                    {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </div>

        <!-- Form Warning -->
        <div v-if="form.has_responses" class="mb-6">
            <Card class="border-orange-200 bg-orange-50">
                <CardContent class="pt-6">
                    <div class="flex items-start space-x-3">
                        <div class="rounded-full bg-orange-100 p-1">
                            <svg class="h-4 w-4 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-medium text-orange-800">Form Has Responses</h3>
                            <p class="mt-1 text-sm text-orange-700">This form has existing responses. Structural changes will create a new version to preserve data integrity.</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Form Builder -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Main Content -->
            <div class="lg:col-span-3">
                <Tabs v-model:default-value="currentTab" class="w-full">
                    <TabsList class="grid w-full grid-cols-4">
                        <TabsTrigger value="basic">Basic Info</TabsTrigger>
                        <TabsTrigger value="questions">Questions</TabsTrigger>
                        <TabsTrigger value="visibility">Visibility</TabsTrigger>
                        <TabsTrigger value="targeting">Targeting</TabsTrigger>
                    </TabsList>

                    <!-- Basic Information Tab -->
                    <TabsContent value="basic" class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Basic Information</CardTitle>
                                <CardDescription>Configure the basic properties of your form</CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="title">Form Title</Label>
                                        <Input id="title" v-model="editForm.title" placeholder="Enter form title" :error="editForm.errors.title" />
                                        <p v-if="editForm.errors.title" class="text-destructive text-sm">
                                            {{ editForm.errors.title }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="code">Form Code</Label>
                                        <Input id="code" :model-value="form.code" placeholder="Form code" disabled />
                                        <p class="text-muted-foreground text-sm">Code cannot be changed after creation</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="type">Form Type</Label>
                                        <Select :model-value="form.type" disabled>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select form type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="option in formTypeOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p class="text-muted-foreground text-sm">Type cannot be changed after creation</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="status">Status</Label>
                                        <Select v-model:model-value="editForm.status">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="option in statusOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description</Label>
                                    <Textarea id="description" v-model="editForm.description" placeholder="Enter form description" rows="3" />
                                    <p v-if="editForm.errors.description" class="text-destructive text-sm">
                                        {{ editForm.errors.description }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Questions Tab -->
                    <TabsContent value="questions" class="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center justify-between">
                                    Questions
                                    <!--                                    <div class="flex space-x-2">-->
                                    <!--                                        <Button variant="outline" size="sm" @click="useAdvancedBuilder = !useAdvancedBuilder"> {{ useAdvancedBuilder ? 'Simple' : 'Advanced' }} Builder </Button>-->
                                    <!--                                    </div>-->
                                </CardTitle>
                                <CardDescription>Modify your form questions and structure</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <!-- Advanced Form Builder Component -->
                                <FormBuilder v-model:sections="editForm.sections" v-model:questions="editForm.questions" :question-types="questionTypes" />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Visibility Tab -->
                    <TabsContent value="visibility">
                        <VisibilitySettings v-model:visibility-roles="editForm.visibility_roles" v-model:result-visibility="editForm.result_visibility" :roles="roles" :visibility-levels="visibilityLevels" />
                    </TabsContent>

                    <!-- Targeting Tab -->
                    <TabsContent value="targeting">
                        <TargetingSettings v-model:targets="editForm.targets" :campuses="campuses" :scope-types="scopeTypes" />
                    </TabsContent>
                </Tabs>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <Card>
                    <CardHeader>
                        <CardTitle>Form Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Current Version</span>
                                <span class="text-muted-foreground text-sm">v{{ form.latest_version || 1 }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Created</span>
                                <span class="text-muted-foreground text-sm">{{ new Date(form.created_at).toLocaleDateString() }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Last Modified</span>
                                <span class="text-muted-foreground text-sm">{{ new Date(form.updated_at).toLocaleDateString() }}</span>
                            </div>
                            <div v-if="form.statistics" class="flex items-center justify-between">
                                <span class="text-sm">Responses</span>
                                <span class="text-muted-foreground text-sm">{{ form.statistics.total_responses || 0 }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="mt-4">
                    <CardHeader>
                        <CardTitle>Form Progress</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Basic Info</span>
                                <span class="text-muted-foreground text-sm">
                                    {{ editForm.title ? '✓' : '○' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Questions</span>
                                <span class="text-muted-foreground text-sm">
                                    {{ editForm.questions.length > 0 || editForm.sections.length > 0 ? '✓' : '○' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Visibility</span>
                                <span class="text-muted-foreground text-sm">
                                    {{ editForm.visibility_roles.length > 0 ? '✓' : '○' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Targeting</span>
                                <span class="text-muted-foreground text-sm">
                                    {{ editForm.targets.length > 0 ? '✓' : '○' }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Preview Modal -->
        <FormPreviewModal v-model:open="showPreview" :title="editForm.title || form.title" :description="editForm.description" :type="form.type" :sections="editForm.sections" :questions="editForm.questions" />
    </div>
</template>
