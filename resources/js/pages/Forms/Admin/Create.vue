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
import { ArrowLeft, Copy, Eye, Plus, Save, Star, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { route } from 'ziggy-js';
import type { QuestionType } from '@/types/forms';

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
    errors: Record<string, string>;
}

defineProps<Props>();
// Form data
const form = useForm({
    code: '',
    type: 'query' as 'feedback' | 'survey' | 'query',
    title: '',
    description: '',
    status: 'draft' as 'draft' | 'active' | 'archived',
    publish_immediately: true,
    effective_from: '',
    effective_to: '',
    sections: [] as Array<{
        title: string;
        description?: string;
        order_index: number;
        questions: Array<{
            code: string;
            text: string;
            type: QuestionType;
            is_required: boolean;
            order_index: number;
            help_text?: string;
            validation_json?: any;
            visibility_condition_json?: any;
            options?: Array<{
                value: string;
                label: string;
                allows_free_text: boolean;
                order_index: number;
            }>;
        }>;
    }>,
    questions: [] as Array<{
        code: string;
        text: string;
        type: QuestionType;
        is_required: boolean;
        order_index: number;
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
const useSections = ref(false);

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
            .replace(/[\u0300-\u036f]/g, '') // xóa dấu
            .replace(/đ/g, 'd') // thay "đ"
            .replace(/Đ/g, 'D'); // thay "Đ"

        const code = normalized
            .toLowerCase()
            .replace(/[^a-z0-9\s]/g, '') // giữ lại chữ & số
            .replace(/\s+/g, '_') // đổi khoảng trắng thành "_"
            .substring(0, 50);

        form.code = code + '_' + Date.now().toString().slice(-6);
    }
};

const addBasicQuestion = () => {
    form.questions.push({
        code: `q_${Date.now()}`,
        text: '',
        type: 'short_text' as QuestionType,
        is_required: false,
        order_index: form.questions.length,
        options: [],
    });
};

const removeQuestion = (index: number) => {
    form.questions.splice(index, 1);
};

const duplicateQuestion = (index: number) => {
    const question = form.questions[index];
    const duplicatedQuestion = {
        ...question,
        code: `q_${Date.now()}`,
        order_index: index + 1,
        options: question.options?.map((opt, optIdx) => ({
            ...opt,
            order_index: optIdx,
        })),
    };
    form.questions.splice(index + 1, 0, duplicatedQuestion);
    // Update order_index for all questions after insertion
    form.questions.forEach((q, idx) => {
        q.order_index = idx;
    });
};

const addQuestionOption = (questionIndex: number) => {
    if (!form.questions[questionIndex].options) {
        form.questions[questionIndex].options = [];
    }
    const options = form.questions[questionIndex].options as Array<{
        value: string;
        label: string;
        allows_free_text: boolean;
        order_index: number;
    }>;
    options.push({
        value: `option_${Date.now()}`,
        label: '',
        allows_free_text: false,
        order_index: options.length,
    });
};

const removeQuestionOption = (questionIndex: number, optionIndex: number) => {
    form.questions[questionIndex].options?.splice(optionIndex, 1);
};

const questionNeedsOptions = (type: string) => {
    return ['single_choice', 'multi_choice', 'likert'].includes(type);
};

// Section management methods
const addSection = () => {
    form.sections.push({
        title: '',
        description: '',
        order_index: form.sections.length,
        questions: [],
    });
};

const removeSection = (index: number) => {
    form.sections.splice(index, 1);
};

const duplicateSection = (index: number) => {
    const section = form.sections[index];
    const duplicatedSection = {
        ...section,
        title: `${section.title} (Copy)`,
        order_index: index + 1,
        questions: section.questions.map((q, qIdx) => ({
            ...q,
            code: `q_${Date.now()}_${qIdx}`,
            order_index: qIdx,
            options: q.options?.map((opt, optIdx) => ({
                ...opt,
                order_index: optIdx,
            })),
        })),
    };
    form.sections.splice(index + 1, 0, duplicatedSection);
    // Update order_index for all sections after insertion
    form.sections.forEach((sec, idx) => {
        sec.order_index = idx;
    });
};

const addQuestionToSection = (sectionIndex: number) => {
    if (!form.sections[sectionIndex].questions) {
        form.sections[sectionIndex].questions = [];
    }
    form.sections[sectionIndex].questions.push({
        code: `q_${Date.now()}`,
        text: '',
        type: 'short_text' as QuestionType,
        is_required: false,
        order_index: form.sections[sectionIndex].questions.length,
        options: [],
    });
};

const removeQuestionFromSection = (sectionIndex: number, questionIndex: number) => {
    form.sections[sectionIndex].questions.splice(questionIndex, 1);
};

const duplicateQuestionInSection = (sectionIndex: number, questionIndex: number) => {
    const question = form.sections[sectionIndex].questions[questionIndex];
    const duplicatedQuestion = {
        ...question,
        code: `q_${Date.now()}`,
        order_index: questionIndex + 1,
        options: question.options?.map((opt, optIdx) => ({
            ...opt,
            order_index: optIdx,
        })),
    };
    form.sections[sectionIndex].questions.splice(questionIndex + 1, 0, duplicatedQuestion);
    // Update order_index for all questions in this section after insertion
    form.sections[sectionIndex].questions.forEach((q, idx) => {
        q.order_index = idx;
    });
};

const addQuestionOptionToSection = (sectionIndex: number, questionIndex: number) => {
    const question = form.sections[sectionIndex].questions[questionIndex];
    if (!question.options) {
        question.options = [];
    }
    question.options.push({
        value: `option_${Date.now()}`,
        label: '',
        allows_free_text: false,
        order_index: question.options.length,
    });
};

const removeQuestionOptionFromSection = (sectionIndex: number, questionIndex: number, optionIndex: number) => {
    form.sections[sectionIndex].questions[questionIndex].options?.splice(optionIndex, 1);
};

const submitForm = () => {
    // Prepare data: only send sections OR questions, not both
    const submitData = { ...form.data() };
    
    if (useSections.value) {
        // When using sections, clear questions array
        submitData.questions = [];
    } else {
        // When not using sections, clear sections array
        submitData.sections = [];
    }
    
    form.transform(() => submitData).post(route('forms.admin.store'), {
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
        if (newTitle) {
            generateCode();
        }
    },
);

// Watch for section mode toggle
watch(useSections, (newValue) => {
    if (newValue) {
        // Switching to sections mode: migrate existing questions to first section if any
        if (form.questions.length > 0 && form.sections.length === 0) {
            form.sections.push({
                title: '',
                description: '',
                order_index: 0,
                questions: form.questions.map((q, idx) => ({
                    ...q,
                    order_index: idx,
                    options: q.options?.map((opt: any, optIdx: number) => ({
                        ...opt,
                        order_index: opt.order_index ?? optIdx,
                    })),
                })),
            });
            form.questions = [];
        }
    } else {
        // Switching to simple mode: clear sections
        form.sections = [];
    }
});
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
                    <TabsList class="grid w-full grid-cols-3">
                        <TabsTrigger value="basic">Basic Info</TabsTrigger>
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
                                    <Checkbox
                                        id="publish_immediately"
                                        :model-value="form.publish_immediately"
                                        @update:model-value="
                                            (value: boolean | 'indeterminate') => {
                                                (form.publish_immediately as any) = value === true;
                                            }
                                        "
                                    />
                                    <Label for="publish_immediately">Publish immediately</Label>
                                </div>
                            </CardContent>
                        </Card>
                        <!-- Questions Tab -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center justify-between">
                                    Questions
                                    <div class="flex items-center space-x-4">
                                        <div class="flex items-center space-x-2">
                                            <Checkbox
                                                id="use_sections"
                                                :model-value="useSections"
                                                @update:model-value="
                                                    (value) => {
                                                        useSections = Boolean(value);
                                                    }
                                                "
                                            />
                                            <Label for="use_sections" class="text-sm font-normal">Use Sections</Label>
                                        </div>
                                        <div class="flex space-x-2">
                                            <Button v-if="useSections" size="sm" @click="addSection">
                                                <Plus class="mr-2 h-4 w-4" />
                                                Add Section
                                            </Button>
                                            <Button v-else size="sm" @click="addBasicQuestion">
                                                <Plus class="mr-2 h-4 w-4" />
                                                Add Question
                                            </Button>
                                        </div>
                                    </div>
                                </CardTitle>
                                <CardDescription> Build your form questions and structure </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <!-- Advanced Form Builder Component -->
                                <FormBuilder v-if="useAdvancedBuilder" v-model:sections="form.sections as any" v-model:questions="form.questions as any" :question-types="questionTypes" />

                                <!-- Section-based Question Builder -->
                                <div v-else-if="useSections" class="space-y-6">
                                    <div v-if="form.sections.length === 0" class="text-center py-8 text-muted-foreground">
                                        <p>No sections yet. Click "Add Section" to get started.</p>
                                    </div>
                                    <div v-for="(section, sectionIndex) in form.sections" :key="sectionIndex" class="space-y-4 rounded-lg border p-4">
                                        <!-- Section Header -->
                                        <div class="flex items-center justify-between border-b pb-3">
                                            <h4 class="font-semibold text-lg">Section {{ sectionIndex + 1 }}</h4>
                                            <div class="flex items-center space-x-2">
                                                <Button variant="ghost" size="sm" @click="duplicateSection(sectionIndex)">
                                                    <Copy class="mr-2 h-4 w-4" />
                                                    Duplicate
                                                </Button>
                                                <Button variant="ghost" size="sm" @click="removeSection(sectionIndex)">
                                                    <Trash2 class="mr-2 h-4 w-4" />
                                                    Remove Section
                                                </Button>
                                            </div>
                                        </div>

                                        <!-- Section Details -->
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div class="space-y-2">
                                                <Label>Section Title *</Label>
                                                <Input v-model="section.title" placeholder="Enter section title" />
                                                <p v-if="(form.errors as Record<string, string>)[`sections.${sectionIndex}.title`]" class="text-destructive text-sm">
                                                    {{ (form.errors as Record<string, string>)[`sections.${sectionIndex}.title`] }}
                                                </p>
                                            </div>
                                            <div class="space-y-2">
                                                <Label>Section Description</Label>
                                                <Input v-model="section.description" placeholder="Optional description" />
                                            </div>
                                        </div>

                                        <!-- Section Questions -->
                                        <div class="space-y-4">
                                            <div class="flex items-center justify-between">
                                                <Label class="text-base font-medium">Questions in this section</Label>
                                                <Button variant="outline" size="sm" @click="addQuestionToSection(sectionIndex)">
                                                    <Plus class="mr-2 h-4 w-4" />
                                                    Add Question
                                                </Button>
                                            </div>

                                            <div v-if="section.questions.length === 0" class="text-center py-4 text-muted-foreground text-sm">
                                                No questions in this section yet.
                                            </div>

                                            <div v-for="(question, questionIndex) in section.questions" :key="questionIndex" class="bg-muted/50 space-y-4 rounded-lg p-4">
                                                <div class="flex items-center justify-between">
                                                    <h5 class="font-medium">Question {{ questionIndex + 1 }}</h5>
                                                    <div class="flex items-center space-x-2">
                                                        <Button variant="ghost" size="sm" @click="duplicateQuestionInSection(sectionIndex, questionIndex)">
                                                            <Copy class="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="sm" @click="removeQuestionFromSection(sectionIndex, questionIndex)">
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <div class="space-y-2">
                                                        <Label>Question Code</Label>
                                                        <Input v-model="question.code" disabled placeholder="Question code" />
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
                                                    <Label>Question Text *</Label>
                                                    <Textarea v-model="question.text" placeholder="Enter your question" rows="2" />
                                                    <p v-if="(form.errors as Record<string, string>)[`sections.${sectionIndex}.questions.${questionIndex}.text`]" class="text-destructive text-sm">
                                                        {{ (form.errors as Record<string, string>)[`sections.${sectionIndex}.questions.${questionIndex}.text`] }}
                                                    </p>
                                                </div>

                                                <div class="space-y-2">
                                                    <Label>Help Text (Optional)</Label>
                                                    <Input v-model="question.help_text" placeholder="Additional help or instructions" />
                                                </div>

                                                <div class="flex items-center space-x-2">
                                                    <Checkbox
                                                        :id="`section_${sectionIndex}_required_${questionIndex}`"
                                                        :model-value="question.is_required"
                                                        @update:model-value="
                                                            (value) => {
                                                                question.is_required = Boolean(value);
                                                            }
                                                        "
                                                    />
                                                    <Label :for="`section_${sectionIndex}_required_${questionIndex}`">Required</Label>
                                                </div>

                                                <!-- Rating preview -->
                                                <div v-if="question.type === 'rating'" class="space-y-2">
                                                    <Label>Preview</Label>
                                                    <div class="flex items-center space-x-1">
                                                        <Star v-for="i in 5" :key="i" class="h-6 w-6 fill-yellow-400 text-yellow-400" />
                                                    </div>
                                                    <p class="text-muted-foreground text-sm">5-star rating scale</p>
                                                </div>

                                                <!-- Options for choice questions -->
                                                <div v-if="questionNeedsOptions(question.type)" class="space-y-2">
                                                    <div class="flex items-center justify-between">
                                                        <Label>Options</Label>
                                                        <Button variant="outline" size="sm" @click="addQuestionOptionToSection(sectionIndex, questionIndex)">
                                                            <Plus class="mr-2 h-4 w-4" />
                                                            Add Option
                                                        </Button>
                                                    </div>

                                                    <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex items-center space-x-2">
                                                        <Input v-model="option.value" placeholder="Option value" class="flex-1" />
                                                        <Input v-model="option.label" placeholder="Option label" class="flex-1" />
                                                        <Checkbox
                                                            :id="`section_${sectionIndex}_option_${optionIndex}_free_text`"
                                                            :model-value="option.allows_free_text"
                                                            @update:model-value="
                                                                (value) => {
                                                                    option.allows_free_text = Boolean(value);
                                                                }
                                                            "
                                                        />
                                                        <Label :for="`section_${sectionIndex}_option_${optionIndex}_free_text`" class="text-sm"> Allow "Other" </Label>
                                                        <Button variant="ghost" size="sm" @click="removeQuestionOptionFromSection(sectionIndex, questionIndex, optionIndex)">
                                                            <Trash2 class="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Simple Question Builder -->
                                <div v-else class="space-y-4">
                                    <div v-for="(question, questionIndex) in form.questions" :key="questionIndex" class="space-y-4 rounded-lg border p-4">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium">Question {{ questionIndex + 1 }}</h4>
                                            <div class="flex items-center space-x-2">
                                                <Button variant="ghost" size="sm" @click="duplicateQuestion(questionIndex)">
                                                    <Copy class="h-4 w-4" />
                                                </Button>
                                                <Button variant="ghost" size="sm" @click="removeQuestion(questionIndex)">
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div class="space-y-2">
                                                <Label>Question Code</Label>
                                                <Input v-model="question.code" disabled placeholder="Question code" />
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
                                            <Label>Question Text *</Label>
                                            <Textarea v-model="question.text" placeholder="Enter your question" rows="2" />
                                        </div>

                                        <div class="space-y-2">
                                            <Label>Help Text (Optional)</Label>
                                            <Input v-model="question.help_text" placeholder="Additional help or instructions" />
                                        </div>

                                        <div class="flex items-center space-x-2">
                                            <Checkbox
                                                :id="`required_${questionIndex}`"
                                                :model-value="question.is_required"
                                                @update:model-value="
                                                    (value) => {
                                                        question.is_required = Boolean(value);
                                                    }
                                                "
                                            />
                                            <Label :for="`required_${questionIndex}`">Required</Label>
                                        </div>

                                        <!-- Rating preview -->
                                        <div v-if="question.type === 'rating'" class="space-y-2">
                                            <Label>Preview</Label>
                                            <div class="flex items-center space-x-1">
                                                <Star v-for="i in 5" :key="i" class="h-6 w-6 fill-yellow-400 text-yellow-400" />
                                            </div>
                                            <p class="text-muted-foreground text-sm">5-star rating scale</p>
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
                                                <Checkbox
                                                    :id="`simple_option_${optionIndex}_free_text`"
                                                    :model-value="option.allows_free_text"
                                                    @update:model-value="
                                                        (value) => {
                                                            option.allows_free_text = Boolean(value);
                                                        }
                                                    "
                                                />
                                                <Label :for="`simple_option_${optionIndex}_free_text`" class="text-sm"> Allow "Other" </Label>
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
        <FormPreviewModal
            v-model:open="showPreview"
            :title="form.title || 'Untitled Form'"
            :description="form.description"
            :type="form.type"
            :sections="form.sections as any"
            :questions="form.questions as any"
        />
    </div>
</template>
