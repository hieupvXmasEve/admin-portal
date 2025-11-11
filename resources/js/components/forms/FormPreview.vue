<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import type { FormBuilderQuestion, FormBuilderSection } from '@/types/forms';
import { Calendar, FileIcon, Star } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    title: string;
    description?: string;
    type: 'feedback' | 'survey' | 'query';
    sections: FormBuilderSection[];
    questions: FormBuilderQuestion[];
}

defineProps<Props>();

// Mock form responses for preview
const formData = ref<Record<string, any>>({});

// Methods for handling form interactions
const updateCheckboxValue = (questionCode: string, optionValue: string, checked: boolean) => {
    if (!formData.value[questionCode]) {
        formData.value[questionCode] = [];
    }
    if (checked) {
        formData.value[questionCode].push(optionValue);
    } else {
        const index = formData.value[questionCode].indexOf(optionValue);
        if (index > -1) {
            formData.value[questionCode].splice(index, 1);
        }
    }
};

const setRating = (questionCode: string, rating: number) => {
    formData.value[questionCode] = rating;
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
                        <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800">
                            {{ type }}
                        </span>
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800">Preview Mode</span>
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

                            <!-- Short Text Input -->
                            <Input v-if="question.type === 'short_text'" v-model="formData[question.code]" placeholder="Enter your answer..." />

                            <!-- Long Text Input -->
                            <Textarea v-else-if="question.type === 'long_text'" v-model="formData[question.code]" placeholder="Enter your detailed response..." rows="4" />

                            <!-- Single Choice -->
                            <RadioGroup v-else-if="question.type === 'single_choice'" v-model="formData[question.code]">
                                <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex items-center space-x-2">
                                    <RadioGroupItem :value="option.value" :id="`${question.code}_${option.value}`" />
                                    <Label :for="`${question.code}_${option.value}`">{{ option.label }}</Label>
                                </div>
                            </RadioGroup>

                            <!-- Multiple Choice -->
                            <div v-else-if="question.type === 'multi_choice'" class="space-y-2">
                                <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex items-center space-x-2">
                                    <Checkbox :id="`${question.code}_${option.value}`" :checked="formData[question.code]?.includes(option.value)" @update:checked="(checked: boolean) => updateCheckboxValue(question.code, option.value, checked)" />
                                    <Label :for="`${question.code}_${option.value}`">{{ option.label }}</Label>
                                </div>
                            </div>

                            <!-- Likert Scale -->
                            <RadioGroup v-else-if="question.type === 'likert'" v-model="formData[question.code]" class="flex space-x-4">
                                <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex flex-col items-center space-y-2">
                                    <RadioGroupItem :value="option.value" :id="`${question.code}_${option.value}`" />
                                    <Label :for="`${question.code}_${option.value}`" class="text-center text-xs">{{ option.label }}</Label>
                                </div>
                            </RadioGroup>

                            <!-- Rating -->
                            <div v-else-if="question.type === 'rating'" class="flex space-x-1">
                                <Button v-for="rating in [1, 2, 3, 4, 5]" :key="rating" variant="ghost" size="sm" class="p-1" @click="setRating(question.code, rating)">
                                    <Star :class="`h-6 w-6 ${formData[question.code] >= rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`" />
                                </Button>
                            </div>

                            <!-- Date -->
                            <div v-else-if="question.type === 'date'" class="relative">
                                <Calendar class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                                <Input v-model="formData[question.code]" type="date" class="pl-8" />
                            </div>

                            <!-- Number -->
                            <Input v-else-if="question.type === 'number'" v-model="formData[question.code]" type="number" placeholder="Enter a number..." />

                            <!-- File -->
                            <div v-else-if="question.type === 'file'" class="flex items-center space-x-2">
                                <Button variant="outline" size="sm">
                                    <FileIcon class="mr-2 h-4 w-4" />
                                    Choose File
                                </Button>
                                <span class="text-muted-foreground text-sm">No file chosen</span>
                            </div>

                            <!-- Yes/No -->
                            <RadioGroup v-else-if="question.type === 'yes_no'" v-model="formData[question.code]" class="flex space-x-4">
                                <div class="flex items-center space-x-2">
                                    <RadioGroupItem value="yes" :id="`${question.code}_yes`" />
                                    <Label :for="`${question.code}_yes`">Yes</Label>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <RadioGroupItem value="no" :id="`${question.code}_no`" />
                                    <Label :for="`${question.code}_no`">No</Label>
                                </div>
                            </RadioGroup>
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

                        <!-- Same question types as sections -->
                        <!-- Short Text Input -->
                        <Input v-if="question.type === 'short_text'" v-model="formData[question.code]" placeholder="Enter your answer..." />

                        <!-- Long Text Input -->
                        <Textarea v-else-if="question.type === 'long_text'" v-model="formData[question.code]" placeholder="Enter your detailed response..." rows="4" />

                        <!-- Single Choice -->
                        <RadioGroup v-else-if="question.type === 'single_choice'" v-model="formData[question.code]">
                            <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex items-center space-x-2">
                                <RadioGroupItem :value="option.value" :id="`${question.code}_${option.value}`" />
                                <Label :for="`${question.code}_${option.value}`">{{ option.label }}</Label>
                            </div>
                        </RadioGroup>

                        <!-- Multiple Choice -->
                        <div v-else-if="question.type === 'multi_choice'" class="space-y-2">
                            <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex items-center space-x-2">
                                <Checkbox :id="`${question.code}_${option.value}`" :checked="formData[question.code]?.includes(option.value)" @update:checked="(checked: boolean) => updateCheckboxValue(question.code, option.value, checked)" />
                                <Label :for="`${question.code}_${option.value}`">{{ option.label }}</Label>
                            </div>
                        </div>

                        <!-- Likert Scale -->
                        <RadioGroup v-else-if="question.type === 'likert'" v-model="formData[question.code]" class="flex space-x-4">
                            <div v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex flex-col items-center space-y-2">
                                <RadioGroupItem :value="option.value" :id="`${question.code}_${option.value}`" />
                                <Label :for="`${question.code}_${option.value}`" class="text-center text-xs">{{ option.label }}</Label>
                            </div>
                        </RadioGroup>

                        <!-- Rating -->
                        <div v-else-if="question.type === 'rating'" class="flex space-x-1">
                            <Button v-for="rating in [1, 2, 3, 4, 5]" :key="rating" variant="ghost" size="sm" class="p-1" @click="setRating(question.code, rating)">
                                <Star :class="`h-6 w-6 ${formData[question.code] >= rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`" />
                            </Button>
                        </div>

                        <!-- Date -->
                        <div v-else-if="question.type === 'date'" class="relative">
                            <Calendar class="text-muted-foreground absolute top-2.5 left-2 h-4 w-4" />
                            <Input v-model="formData[question.code]" type="date" class="pl-8" />
                        </div>

                        <!-- Number -->
                        <Input v-else-if="question.type === 'number'" v-model="formData[question.code]" type="number" placeholder="Enter a number..." />

                        <!-- File -->
                        <div v-else-if="question.type === 'file'" class="flex items-center space-x-2">
                            <Button variant="outline" size="sm">
                                <FileIcon class="mr-2 h-4 w-4" />
                                Choose File
                            </Button>
                            <span class="text-muted-foreground text-sm">No file chosen</span>
                        </div>

                        <!-- Yes/No -->
                        <RadioGroup v-else-if="question.type === 'yes_no'" v-model="formData[question.code]" class="flex space-x-4">
                            <div class="flex items-center space-x-2">
                                <RadioGroupItem value="yes" :id="`${question.code}_yes`" />
                                <Label :for="`${question.code}_yes`">Yes</Label>
                            </div>
                            <div class="flex items-center space-x-2">
                                <RadioGroupItem value="no" :id="`${question.code}_no`" />
                                <Label :for="`${question.code}_no`">No</Label>
                            </div>
                        </RadioGroup>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty State -->
            <Card v-if="sections.length === 0 && questions.length === 0">
                <CardContent class="py-12">
                    <div class="text-muted-foreground text-center">
                        <p class="text-lg font-medium">No questions added yet</p>
                        <p class="text-sm">Add questions to see how your form will look</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Form Actions -->
        <Card>
            <CardContent class="pt-6">
                <div class="flex justify-end space-x-2">
                    <Button variant="outline">Cancel</Button>
                    <Button>Submit Response</Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
