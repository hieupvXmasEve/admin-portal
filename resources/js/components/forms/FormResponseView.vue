<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type { FormBuilderQuestion, FormBuilderSection } from '@/types/forms';
import { Calendar, FileIcon, Star } from 'lucide-vue-next';
import { computed } from 'vue';
import { QuestionCard } from '@/components/form-engine';

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
    sections: FormBuilderSection[] | [];
    questions: FormBuilderQuestion[] | [];
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

// Get answer object for a question to pass to QuestionCard
const getAnswerObject = (question: FormBuilderQuestion): any => {
    const key = question.code || question.id;
    const answer = answersMap.value.get(key as string | number);
    if (!answer) {
        return undefined;
    }

    return {
        question_id: answer.question_id,
        answer_text: answer.answer_text,
        answer_number: answer.answer_number,
        answer_date: answer.answer_date,
        selected_options: answer.selected_options?.map(opt => ({
            option_id: opt.id,
            text: opt.text,
            value: opt.value
        }))
    };
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
                        <div v-for="(question, questionIndex) in section.questions" :key="questionIndex">
                            <QuestionCard
                                :question="(question as any)"
                                :model-value="getAnswerObject(question)"
                                :show-border="false"
                                readonly
                                class="!p-0 !hover:bg-transparent"
                            />
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
                    <div v-for="(question, questionIndex) in questions" :key="questionIndex">
                        <QuestionCard
                            :question="(question as any)"
                            :model-value="getAnswerObject(question)"
                            :show-border="false"
                            readonly
                            class="!p-0 !hover:bg-transparent"
                        />
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

