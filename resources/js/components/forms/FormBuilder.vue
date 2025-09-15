<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { FormBuilderOption, FormBuilderQuestion, FormBuilderSection } from '@/types/forms';
import { ChevronDown, ChevronUp, GripVertical, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    sections: FormBuilderSection[];
    questions: FormBuilderQuestion[];
    questionTypes: Record<string, string>;
}

interface Emits {
    (e: 'update:sections', sections: FormBuilderSection[]): void;
    (e: 'update:questions', questions: FormBuilderQuestion[]): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// Local state
const showSections = ref(false);

// Computed
const localSections = computed({
    get: () => props.sections,
    set: (value) => emit('update:sections', value),
});

const localQuestions = computed({
    get: () => props.questions,
    set: (value) => emit('update:questions', value),
});

// Methods
const addSection = () => {
    const newSection: FormBuilderSection = {
        title: '',
        description: '',
        order_index: localSections.value.length,
        questions: [],
    };
    localSections.value.push(newSection);
};

const removeSection = (sectionIndex: number) => {
    localSections.value.splice(sectionIndex, 1);
    // Update order indices
    localSections.value.forEach((section, index) => {
        section.order_index = index;
    });
};

const addQuestionToSection = (sectionIndex: number) => {
    const newQuestion: FormBuilderQuestion = {
        code: `q_${Date.now()}`,
        text: '',
        type: 'short_text',
        is_required: false,
        order_index: localSections.value[sectionIndex].questions.length,
        options: [],
    };
    localSections.value[sectionIndex].questions.push(newQuestion);
};

const addStandaloneQuestion = () => {
    const newQuestion: FormBuilderQuestion = {
        code: `q_${Date.now()}`,
        text: '',
        type: 'short_text',
        is_required: false,
        order_index: localQuestions.value.length,
        options: [],
    };
    localQuestions.value.push(newQuestion);
};

const removeQuestion = (questionIndex: number, sectionIndex?: number) => {
    if (sectionIndex !== undefined) {
        localSections.value[sectionIndex].questions.splice(questionIndex, 1);
        // Update order indices
        localSections.value[sectionIndex].questions.forEach((question, index) => {
            question.order_index = index;
        });
    } else {
        localQuestions.value.splice(questionIndex, 1);
        // Update order indices
        localQuestions.value.forEach((question, index) => {
            question.order_index = index;
        });
    }
};

const addQuestionOption = (question: FormBuilderQuestion) => {
    if (!question.options) {
        question.options = [];
    }
    const newOption: FormBuilderOption = {
        value: `option_${Date.now()}`,
        label: '',
        order_index: question.options.length,
        allows_free_text: false,
    };
    question.options.push(newOption);
};

const removeQuestionOption = (question: FormBuilderQuestion, optionIndex: number) => {
    if (question.options) {
        question.options.splice(optionIndex, 1);
        // Update order indices
        question.options.forEach((option, index) => {
            option.order_index = index;
        });
    }
};

const questionNeedsOptions = (type: string) => {
    return ['single_choice', 'multi_choice', 'likert', 'rating'].includes(type);
};

const moveSection = (sectionIndex: number, direction: 'up' | 'down') => {
    const sections = [...localSections.value];
    const targetIndex = direction === 'up' ? sectionIndex - 1 : sectionIndex + 1;

    if (targetIndex >= 0 && targetIndex < sections.length) {
        [sections[sectionIndex], sections[targetIndex]] = [sections[targetIndex], sections[sectionIndex]];

        // Update order indices
        sections.forEach((section, index) => {
            section.order_index = index;
        });

        localSections.value = sections;
    }
};

const moveQuestion = (questionIndex: number, direction: 'up' | 'down', sectionIndex?: number) => {
    let questions: FormBuilderQuestion[];

    if (sectionIndex !== undefined) {
        questions = [...localSections.value[sectionIndex].questions];
    } else {
        questions = [...localQuestions.value];
    }

    const targetIndex = direction === 'up' ? questionIndex - 1 : questionIndex + 1;

    if (targetIndex >= 0 && targetIndex < questions.length) {
        [questions[questionIndex], questions[targetIndex]] = [questions[targetIndex], questions[questionIndex]];

        // Update order indices
        questions.forEach((question, index) => {
            question.order_index = index;
        });

        if (sectionIndex !== undefined) {
            localSections.value[sectionIndex].questions = questions;
        } else {
            localQuestions.value = questions;
        }
    }
};
</script>

<template>
    <div class="space-y-6">
        <!-- Section Toggle -->
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium">Form Structure</h3>
                <p class="text-muted-foreground text-sm">Build your form with sections and questions</p>
            </div>
            <div class="flex items-center space-x-2">
<!--                <Button variant="outline" size="sm" @click="showSections = !showSections">-->
<!--                    {{ showSections ? 'Use Simple Structure' : 'Use Sections' }}-->
<!--                </Button>-->
                <Button v-if="showSections" size="sm" @click="addSection">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Section
                </Button>
                <Button v-else size="sm" @click="addStandaloneQuestion">
                    <Plus class="mr-2 h-4 w-4" />
                    Add Question
                </Button>
            </div>
        </div>

        <!-- Sections View -->
        <div v-if="showSections" class="space-y-4">
            <div v-for="(section, sectionIndex) in localSections" :key="sectionIndex" class="space-y-4 rounded-lg border p-4">
                <!-- Section Header -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <GripVertical class="text-muted-foreground h-4 w-4" />
                        <h4 class="font-medium">Section {{ sectionIndex + 1 }}</h4>
                    </div>
                    <div class="flex items-center space-x-2">
                        <Button variant="ghost" size="sm" @click="moveSection(sectionIndex, 'up')" :disabled="sectionIndex === 0">
                            <ChevronUp class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" @click="moveSection(sectionIndex, 'down')" :disabled="sectionIndex === localSections.length - 1">
                            <ChevronDown class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" @click="removeSection(sectionIndex)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <!-- Section Details -->
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Section Title</Label>
                        <Input v-model="section.title" placeholder="Enter section title" />
                    </div>
                    <div class="space-y-2">
                        <Label>Section Description</Label>
                        <Input v-model="section.description" placeholder="Optional description" />
                    </div>
                </div>

                <!-- Section Questions -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <Label>Questions in this section</Label>
                        <Button variant="outline" size="sm" @click="addQuestionToSection(sectionIndex)">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Question
                        </Button>
                    </div>

                    <div v-for="(question, questionIndex) in section.questions" :key="questionIndex" class="bg-muted/50 space-y-4 rounded-lg p-4">
                        <!-- Question Header -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <GripVertical class="text-muted-foreground h-4 w-4" />
                                <span class="text-sm font-medium"> Question {{ questionIndex + 1 }} </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <Button variant="ghost" size="sm" @click="moveQuestion(questionIndex, 'up', sectionIndex)" :disabled="questionIndex === 0">
                                    <ChevronUp class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="sm" @click="moveQuestion(questionIndex, 'down', sectionIndex)" :disabled="questionIndex === section.questions.length - 1">
                                    <ChevronDown class="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="sm" @click="removeQuestion(questionIndex, sectionIndex)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        <!-- Question Fields -->
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
                            <Checkbox :id="`section_${sectionIndex}_required_${questionIndex}`" :model-value="question.is_required" @update:model-value="question.is_required = $event" />
                            <Label :for="`section_${sectionIndex}_required_${questionIndex}`"> Required </Label>
                        </div>

                        <!-- Options for choice questions -->
                        <div v-if="questionNeedsOptions(question.type)" class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label>Options</Label>
                                <Button variant="outline" size="sm" @click="addQuestionOption(question)">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add Option
                                </Button>
                            </div>

                            <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex items-center space-x-2">
                                <Input v-model="option.value" placeholder="Option value" class="flex-1" />
                                <Input v-model="option.label" placeholder="Option label" class="flex-1" />
                                <Checkbox :id="`option_${optionIndex}_free_text`" :model-value="option.allows_free_text" @update:model-value="option.allows_free_text = $event" />
                                <Label :for="`option_${optionIndex}_free_text`" class="text-sm"> Allow "Other" </Label>
                                <Button variant="ghost" size="sm" @click="removeQuestionOption(question, optionIndex)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Simple Questions View -->
        <div v-else class="space-y-4">
            <div v-for="(question, questionIndex) in localQuestions" :key="questionIndex" class="space-y-4 rounded-lg border p-4">
                <!-- Question Header -->
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <GripVertical class="text-muted-foreground h-4 w-4" />
                        <h4 class="font-medium">Question {{ questionIndex + 1 }}</h4>
                    </div>
                    <div class="flex items-center space-x-2">
                        <Button variant="ghost" size="sm" @click="moveQuestion(questionIndex, 'up')" :disabled="questionIndex === 0">
                            <ChevronUp class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" @click="moveQuestion(questionIndex, 'down')" :disabled="questionIndex === localQuestions.length - 1">
                            <ChevronDown class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" @click="removeQuestion(questionIndex)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <!-- Question Fields (same as section questions) -->
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
                        <Button variant="outline" size="sm" @click="addQuestionOption(question)">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Option
                        </Button>
                    </div>

                    <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex items-center space-x-2">
                        <Input v-model="option.value" placeholder="Option value" class="flex-1" />
                        <Input v-model="option.label" placeholder="Option label" class="flex-1" />
                        <Checkbox :id="`simple_option_${optionIndex}_free_text`" :model-value="option.allows_free_text" @update:model-value="option.allows_free_text = $event" />
                        <Label :for="`simple_option_${optionIndex}_free_text`" class="text-sm"> Allow "Other" </Label>
                        <Button variant="ghost" size="sm" @click="removeQuestionOption(question, optionIndex)">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
