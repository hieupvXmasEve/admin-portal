<script setup lang="ts">
import FormBuilder from '@/components/forms/FormBuilder.vue';
import FormPreviewModal from '@/components/forms/FormPreviewModal.vue';
import TargetingSettings from '@/components/forms/TargetingSettings.vue';
import VisibilitySettings from '@/components/forms/VisibilitySettings.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Eye, Plus, Save, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
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

// Form data
const form = useForm({
    code: '',
    type: 'query' as 'feedback' | 'survey' | 'query',
    title: '',
    description: '',
    status: 'draft' as 'draft' | 'active' | 'archived',
    publish_immediately: false,
    effective_from: '',
    effective_to: '',
    sections: [] as Array<{
        title: string;
        description?: string;
        questions: Array<{
            code: string;
            text: string;
            type: string;
            is_required: boolean;
            help_text?: string;
            validation_json?: any;
            visibility_condition_json?: any;
            options?: Array<{
                value: string;
                label: string;
                allows_free_text: boolean;
            }>;
        }>;
    }>,
    questions: [] as Array<{
        code: string;
        text: string;
        type: string;
        is_required: boolean;
        help_text?: string;
        validation_json?: any;
        visibility_condition_json?: any;
        options?: Array<{
            value: string;
            label: string;
            allows_free_text: boolean;
        }>;
    }>,
    visibility_roles: [] as number[],
    result_visibility: [] as Array<{
        role_id: number;
        visibility_level: string;
        min_aggregation_threshold?: number;
    }>,
    targets: [] as Array<{
        campus_id?: number;
        scope_type: string;
        scope_id?: number;
        start_at: string;
        end_at?: string;
        submission_limit_per_user: number;
    }>,
});

// Local state
const currentTab = ref('basic');
const useAdvancedBuilder = ref(false);
const showPreview = ref(false);

// Computed
const formTypeOptions = [
    { value: 'query', label: 'Query Form' },
    { value: 'survey', label: 'Survey Form' },
    { value: 'feedback', label: 'Feedback Form' },
];

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Active' },
];

// Methods
const generateCode = () => {
    if (form.title) {
        // chuẩn hóa và loại bỏ dấu
        const normalized = form.title
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')   // xóa dấu
            .replace(/đ/g, 'd')                // thay "đ"
            .replace(/Đ/g, 'D');               // thay "Đ"

        const code = normalized
            .toLowerCase()
            .replace(/[^a-z0-9\s]/g, '')       // giữ lại chữ & số
            .replace(/\s+/g, '_')              // đổi khoảng trắng thành "_"
            .substring(0, 50);

        form.code = code + '_' + Date.now().toString().slice(-6);
    }
};



const addBasicQuestion = () => {
    form.questions.push({
        code: `q_${Date.now()}`,
        text: '',
        type: 'short_text',
        is_required: false,
        options: [],
    });
};

const removeQuestion = (index: number) => {
    form.questions.splice(index, 1);
};

const addQuestionOption = (questionIndex: number) => {
    if (!form.questions[questionIndex].options) {
        form.questions[questionIndex].options = [];
    }
    form.questions[questionIndex].options?.push({
        value: `option_${Date.now()}`,
        label: '',
        allows_free_text: false,
    });
};

const removeQuestionOption = (questionIndex: number, optionIndex: number) => {
    form.questions[questionIndex].options?.splice(optionIndex, 1);
};

const questionNeedsOptions = (type: string) => {
    return ['single_choice', 'multi_choice', 'likert', 'rating'].includes(type);
};

const submitForm = () => {
    form.post(route('forms.admin.store'), {
        onSuccess: () => {
            // Success handled by redirect
        },
        onError: (errors) => {
            console.error('Form submission errors:', errors);
        },
    });
};

const previewForm = () => {
    showPreview.value = true;
};

watch(
    () => form.title,
    (newTitle) => {
        if (newTitle ) {
            generateCode();
        }
    },
);
</script>

<template>
    <Head title="Create Form" />

    <div>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <Button variant="ghost" @click="router.visit(route('forms.admin.index'))">
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Create Form</h1>
                    <p class="text-muted-foreground">Build a new feedback form, survey, or query form</p>
                </div>
            </div>
            <div class="flex space-x-2">
                <Button variant="outline" @click="previewForm">
                    <Eye class="mr-2 h-4 w-4" />
                    Preview
                </Button>
                <Button @click="submitForm" :disabled="form.processing">
                    <Save class="mr-2 h-4 w-4" />
                    {{ form.processing ? 'Creating...' : 'Create Form' }}
                </Button>
            </div>
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
                                <CardDescription> Configure the basic properties of your form </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="title">Form Title</Label>
                                        <Input id="title" v-model="form.title" placeholder="Enter form title" :error="form.errors.title" />
                                        <p v-if="form.errors.title" class="text-destructive text-sm">
                                            {{ form.errors.title }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="code">Form Code</Label>
                                        <div class="flex space-x-2">
                                            <Input id="code" v-model="form.code" placeholder="Form code" :error="form.errors.code" />
                                            <Button type="button" variant="outline" @click="generateCode" :disabled="!form.title"> Generate </Button>
                                        </div>
                                        <p v-if="form.errors.code" class="text-destructive text-sm">
                                            {{ form.errors.code }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="type">Form Type</Label>
                                        <Select v-model:model-value="form.type">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select form type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="option in formTypeOptions" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p v-if="form.errors.type" class="text-destructive text-sm">
                                            {{ form.errors.type }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="status">Status</Label>
                                        <Select v-model:model-value="form.status">
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
                                    <Textarea id="description" v-model="form.description" placeholder="Enter form description" rows="3" />
                                    <p v-if="form.errors.description" class="text-destructive text-sm">
                                        {{ form.errors.description }}
                                    </p>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <Checkbox id="publish_immediately" :model-value="form.publish_immediately" @update:model-value="form.publish_immediately = $event" />
                                    <Label for="publish_immediately">Publish immediately</Label>
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
                                    <div class="flex space-x-2">
                                        <!--                                        <Button variant="outline" size="sm" @click="useAdvancedBuilder = !useAdvancedBuilder"> {{ useAdvancedBuilder ? 'Simple' : 'Advanced' }} Builder </Button>-->
                                        <Button size="sm" @click="addBasicQuestion">
                                            <Plus class="mr-2 h-4 w-4" />
                                            Add Question
                                        </Button>
                                    </div>
                                </CardTitle>
                                <CardDescription> Build your form questions and structure </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <!-- Advanced Form Builder Component -->
                                <FormBuilder v-if="useAdvancedBuilder" v-model:sections="form.sections" v-model:questions="form.questions" :question-types="questionTypes" />

                                <!-- Simple Question Builder -->
                                <div v-else class="space-y-4">
                                    <div v-for="(question, questionIndex) in form.questions" :key="questionIndex" class="space-y-4 rounded-lg border p-4">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium">Question {{ questionIndex + 1 }}</h4>
                                            <Button variant="ghost" size="sm" @click="removeQuestion(questionIndex)">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div class="space-y-2">
                                                <Label>Question Code</Label>
                                                <Input v-model="question.code" placeholder="Question code" />
                                            </div>

                                            <div class="space-y-2">
                                                <Label>Question Type</Label>
                                                <Select v-model:model-value="question.type">
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem v-for="(label, value) in questionTypes" :key="value" :value="value">
                                                            {{ label }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <Label>Question Text</Label>
                                            <Textarea v-model="question.text" placeholder="Enter your question" rows="2" />
                                        </div>

                                        <div class="space-y-2">
                                            <Label>Help Text (Optional)</Label>
                                            <Input v-model="question.help_text" placeholder="Additional help or instructions" />
                                        </div>

                                        <div class="flex items-center space-x-2">
                                            <Checkbox :id="`required_${questionIndex}`" :model-value="question.is_required" @update:model-value="question.is_required = $event" />
                                            <Label :for="`required_${questionIndex}`">Required</Label>
                                        </div>

                                        <!-- Options for choice questions -->
                                        <div v-if="questionNeedsOptions(question.type)" class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <Label>Options</Label>
                                                <Button variant="outline" size="sm" @click="addQuestionOption(questionIndex)">
                                                    <Plus class="mr-2 h-4 w-4" />
                                                    Add Option
                                                </Button>
                                            </div>

                                            <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex items-center space-x-2">
                                                <Input v-model="option.value" placeholder="Option value" class="flex-1" />
                                                <Input v-model="option.label" placeholder="Option label" class="flex-1" />
                                                <Button variant="ghost" size="sm" @click="removeQuestionOption(questionIndex, optionIndex)">
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Visibility Tab -->
                    <TabsContent value="visibility">
                        <VisibilitySettings v-model:visibility-roles="form.visibility_roles" v-model:result-visibility="form.result_visibility" :roles="roles" :visibility-levels="visibilityLevels" />
                    </TabsContent>

                    <!-- Targeting Tab -->
                    <TabsContent value="targeting">
                        <TargetingSettings v-model:targets="form.targets" :campuses="campuses" :scope-types="scopeTypes" />
                    </TabsContent>
                </Tabs>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <Card>
                    <CardHeader>
                        <CardTitle>Form Progress</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Basic Info</span>
                                <span class="text-muted-foreground text-sm">
                                    {{ form.title && form.code && form.type ? '✓' : '○' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Questions</span>
                                <span class="text-muted-foreground text-sm">
                                    {{ form.questions.length > 0 || form.sections.length > 0 ? '✓' : '○' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Visibility</span>
                                <span class="text-muted-foreground text-sm">
                                    {{ form.visibility_roles.length > 0 ? '✓' : '○' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm">Targeting</span>
                                <span class="text-muted-foreground text-sm">
                                    {{ form.targets.length > 0 ? '✓' : '○' }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Preview Modal -->
        <FormPreviewModal v-model:open="showPreview" :title="form.title || 'Untitled Form'" :description="form.description" :type="form.type" :sections="form.sections" :questions="form.questions" />
    </div>
</template>
