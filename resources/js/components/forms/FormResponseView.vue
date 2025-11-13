<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type { FormBuilderQuestion, FormBuilderSection } from '@/types/forms';
import { Calendar, FileIcon, Star } from 'lucide-vue-next';
import { computed } from 'vue';

interface Answer {
    question_id: number;
    question_code?: string;
    question_text: string;
    question_type: string;
    answer_text: string | null;
    answer_number: number | null;
    answer_date: string | null;
    selected_options?: Array<{
        id: number;
        text: string;
        value: string;
    }>;
}

interface Props {
    title: string;
    description?: string;
    type: 'feedback' | 'survey' | 'query';
    sections: FormBuilderSection[];
    questions: FormBuilderQuestion[];
    answers: Answer[];
}

const props = defineProps<Props>();

// Map answers by question code or question_id for easy lookup
const answersMap = computed(() => {
    const map = new Map<string | number, Answer>();
    props.answers.forEach((answer) => {
        const key = answer.question_code || answer.question_id;
        map.set(key, answer);
    });
    return map;
});

// Get answer value for a question
const getAnswerValue = (question: FormBuilderQuestion): any => {
    const key = question.code || question.id;
    const answer = answersMap.value.get(key as string | number);
    if (!answer) {
        return null;
    }

    // Return appropriate value based on question type
    if (answer.answer_text !== null) {
        return answer.answer_text;
    }
    if (answer.answer_number !== null) {
        return answer.answer_number;
    }
    if (answer.answer_date) {
        return answer.answer_date;
    }
    if (answer.selected_options && answer.selected_options.length > 0) {
        // For single choice, return the value
        if (question.type === 'single_choice' || question.type === 'yes_no') {
            return answer.selected_options[0].value;
        }
        // For multi choice, return array of values
        return answer.selected_options.map((opt) => opt.value);
    }
    return null;
};

// Format answer for display
const formatAnswer = (question: FormBuilderQuestion, answerValue: any): string => {
    if (answerValue === null || answerValue === undefined) {
        return 'No answer provided';
    }

    switch (question.type) {
        case 'short_text':
        case 'long_text':
        case 'yes_no':
            return String(answerValue);

        case 'number':
        case 'rating':
            return String(answerValue);

        case 'date':
            if (answerValue) {
                return new Date(answerValue).toLocaleDateString('vi-VN', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                });
            }
            return 'N/A';

        case 'single_choice':
            const option = question.options?.find((opt) => opt.value === answerValue);
            return option?.label || String(answerValue);

        case 'multi_choice':
            if (Array.isArray(answerValue)) {
                const labels = answerValue
                    .map((val) => {
                        const opt = question.options?.find((o) => o.value === val);
                        return opt?.label || val;
                    })
                    .filter(Boolean);
                return labels.join(', ');
            }
            return String(answerValue);

        case 'likert':
            const likertOption = question.options?.find((opt) => opt.value === answerValue);
            return likertOption?.label || String(answerValue);

        default:
            return String(answerValue);
    }
};

// Check if question has answer
const hasAnswer = (question: FormBuilderQuestion): boolean => {
    const key = question.code || question.id;
    return answersMap.value.has(key as string | number);
};
</script>

<template>
    <div class="space-y-6">
        <!-- Form Header -->
        <Card>
            <CardHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle>{{ title }}</CardTitle>
                        <CardDescription v-if="description">{{ description }}</CardDescription>
                    </div>
                    <div class="flex items-center space-x-2">
                        <Badge variant="outline">{{ type }}</Badge>
                        <Badge variant="secondary">Response View</Badge>
                    </div>
                </div>
            </CardHeader>
        </Card>

        <!-- Form Content -->
        <div class="space-y-6">
            <!-- Sections -->
            <div v-if="sections.length > 0" class="space-y-6">
                <Card v-for="(section, sectionIndex) in sections" :key="sectionIndex">
                    <CardHeader>
                        <CardTitle class="text-lg">{{ section.title }}</CardTitle>
                        <CardDescription v-if="section.description">{{ section.description }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div v-for="(question, questionIndex) in section.questions" :key="questionIndex" class="space-y-2">
                            <!-- Question Label -->
                            <Label>
                                {{ question.text }}
                                <span v-if="question.is_required" class="ml-1 text-red-500">*</span>
                            </Label>
                            <p v-if="question.help_text" class="text-muted-foreground text-sm">{{ question.help_text }}</p>

                            <!-- Answer Display -->
                            <div v-if="hasAnswer(question)" class="mt-2 rounded-md border bg-muted/50 p-4">
                                <!-- Short Text / Long Text -->
                                <p v-if="question.type === 'short_text' || question.type === 'long_text'" class="text-sm">
                                    {{ formatAnswer(question, getAnswerValue(question)) }}
                                </p>

                                <!-- Single Choice / Yes/No -->
                                <div v-else-if="question.type === 'single_choice' || question.type === 'yes_no'" class="flex items-center space-x-2">
                                    <div class="h-4 w-4 rounded-full border-2 border-primary bg-primary/10"></div>
                                    <span class="text-sm">{{ formatAnswer(question, getAnswerValue(question)) }}</span>
                                </div>

                                <!-- Multiple Choice -->
                                <div v-else-if="question.type === 'multi_choice'" class="space-y-2">
                                    <div
                                        v-for="(option, optIndex) in question.options"
                                        :key="optIndex"
                                        class="flex items-center space-x-2"
                                    >
                                        <div
                                            :class="[
                                                'h-4 w-4 rounded border-2',
                                                getAnswerValue(question)?.includes(option.value)
                                                    ? 'border-primary bg-primary/10'
                                                    : 'border-muted-foreground/30',
                                            ]"
                                        ></div>
                                        <span
                                            :class="[
                                                'text-sm',
                                                getAnswerValue(question)?.includes(option.value) ? 'font-medium' : 'text-muted-foreground',
                                            ]"
                                        >
                                            {{ option.label }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Likert Scale -->
                                <div v-else-if="question.type === 'likert'" class="flex space-x-4">
                                    <div
                                        v-for="(option, optIndex) in question.options"
                                        :key="optIndex"
                                        class="flex flex-col items-center space-y-2"
                                    >
                                        <div
                                            :class="[
                                                'h-4 w-4 rounded-full border-2',
                                                getAnswerValue(question) === option.value
                                                    ? 'border-primary bg-primary/10'
                                                    : 'border-muted-foreground/30',
                                            ]"
                                        ></div>
                                        <span
                                            :class="[
                                                'text-center text-xs',
                                                getAnswerValue(question) === option.value ? 'font-medium' : 'text-muted-foreground',
                                            ]"
                                        >
                                            {{ option.label }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Rating -->
                                <div v-else-if="question.type === 'rating'" class="flex space-x-1">
                                    <Star
                                        v-for="rating in [1, 2, 3, 4, 5]"
                                        :key="rating"
                                        :class="[
                                            'h-6 w-6',
                                            getAnswerValue(question) >= rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300',
                                        ]"
                                    />
                                    <span class="ml-2 text-sm text-muted-foreground">({{ getAnswerValue(question) }}/5)</span>
                                </div>

                                <!-- Date -->
                                <div v-else-if="question.type === 'date'" class="flex items-center space-x-2">
                                    <Calendar class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm">{{ formatAnswer(question, getAnswerValue(question)) }}</span>
                                </div>

                                <!-- Number -->
                                <p v-else-if="question.type === 'number'" class="text-sm">
                                    {{ formatAnswer(question, getAnswerValue(question)) }}
                                </p>

                                <!-- File -->
                                <div v-else-if="question.type === 'file'" class="flex items-center space-x-2">
                                    <FileIcon class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm text-muted-foreground">File attachment (if any)</span>
                                </div>
                            </div>

                            <!-- No Answer -->
                            <div v-else class="mt-2 rounded-md border border-dashed border-muted-foreground/30 bg-muted/20 p-4">
                                <p class="text-muted-foreground text-sm italic">No answer provided</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Standalone Questions -->
            <Card v-if="questions.length > 0">
                <CardHeader>
                    <CardTitle class="text-lg">Questions</CardTitle>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div v-for="(question, questionIndex) in questions" :key="questionIndex" class="space-y-2">
                        <!-- Question Label -->
                        <Label>
                            <span v-html="question.text.replace(/\n/g, '<br>')"></span>
                            <span v-if="question.is_required" class="text-red-500">*</span>
                        </Label>
                        <p v-if="question.help_text" class="text-muted-foreground text-sm">{{ question.help_text }}</p>

                        <!-- Answer Display (same as sections) -->
                        <div v-if="hasAnswer(question)" class="mt-2 rounded-md border bg-muted/50 p-4">
                            <p v-if="question.type === 'short_text' || question.type === 'long_text'" class="text-sm">
                                {{ formatAnswer(question, getAnswerValue(question)) }}
                            </p>

                            <div v-else-if="question.type === 'single_choice' || question.type === 'yes_no'" class="flex items-center space-x-2">
                                <div class="h-4 w-4 rounded-full border-2 border-primary bg-primary/10"></div>
                                <span class="text-sm">{{ formatAnswer(question, getAnswerValue(question)) }}</span>
                            </div>

                            <div v-else-if="question.type === 'multi_choice'" class="space-y-2">
                                <div
                                    v-for="(option, optIndex) in question.options"
                                    :key="optIndex"
                                    class="flex items-center space-x-2"
                                >
                                    <div
                                        :class="[
                                            'h-4 w-4 rounded border-2',
                                            getAnswerValue(question)?.includes(option.value)
                                                ? 'border-primary bg-primary/10'
                                                : 'border-muted-foreground/30',
                                        ]"
                                    ></div>
                                    <span
                                        :class="[
                                            'text-sm',
                                            getAnswerValue(question)?.includes(option.value) ? 'font-medium' : 'text-muted-foreground',
                                        ]"
                                    >
                                        {{ option.label }}
                                    </span>
                                </div>
                            </div>

                            <div v-else-if="question.type === 'likert'" class="flex space-x-4">
                                <div
                                    v-for="(option, optIndex) in question.options"
                                    :key="optIndex"
                                    class="flex flex-col items-center space-y-2"
                                >
                                    <div
                                        :class="[
                                            'h-4 w-4 rounded-full border-2',
                                            getAnswerValue(question) === option.value
                                                ? 'border-primary bg-primary/10'
                                                : 'border-muted-foreground/30',
                                        ]"
                                    ></div>
                                    <span
                                        :class="[
                                            'text-center text-xs',
                                            getAnswerValue(question) === option.value ? 'font-medium' : 'text-muted-foreground',
                                        ]"
                                    >
                                        {{ option.label }}
                                    </span>
                                </div>
                            </div>

                            <div v-else-if="question.type === 'rating'" class="flex space-x-1">
                                <Star
                                    v-for="rating in [1, 2, 3, 4, 5]"
                                    :key="rating"
                                    :class="[
                                        'h-6 w-6',
                                        getAnswerValue(question) >= rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300',
                                    ]"
                                />
                                <span class="ml-2 text-sm text-muted-foreground">({{ getAnswerValue(question) }}/5)</span>
                            </div>

                            <div v-else-if="question.type === 'date'" class="flex items-center space-x-2">
                                <Calendar class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm">{{ formatAnswer(question, getAnswerValue(question)) }}</span>
                            </div>

                            <p v-else-if="question.type === 'number'" class="text-sm">
                                {{ formatAnswer(question, getAnswerValue(question)) }}
                            </p>

                            <div v-else-if="question.type === 'file'" class="flex items-center space-x-2">
                                <FileIcon class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm text-muted-foreground">File attachment (if any)</span>
                            </div>
                        </div>

                        <!-- No Answer -->
                        <div v-else class="mt-2 rounded-md border border-dashed border-muted-foreground/30 bg-muted/20 p-4">
                            <p class="text-muted-foreground text-sm italic">No answer provided</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty State -->
            <Card v-if="sections.length === 0 && questions.length === 0">
                <CardContent class="py-12">
                    <div class="text-muted-foreground text-center">
                        <p class="text-lg font-medium">No questions found</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

