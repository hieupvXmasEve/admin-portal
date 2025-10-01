<script setup lang="ts">
import DynamicForm from '@/components/forms/DynamicForm.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { Form, FormResponse } from '@/types/forms';
import { router, useForm } from '@inertiajs/vue3';
import { AlertCircle, ArrowLeft, Building, FileText } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import QueryTopicSelector from '../components/QueryTopicSelector.vue';

interface Props {
    form?: Form;
    campus?: { id?: number; name?: string };
    response?: FormResponse;
}

const props = defineProps<Props>();
const hasStudentRoutes = route().has('student.forms.index');

const formRef = ref();
const selectedTopic = ref<number | null>(null);
const customTopicText = ref('');

// Sample query topics - in real app these would come from backend
const queryTopics = ref([
    { id: 1, title: 'Academic', description: 'Course enrollment, grades, transcripts' },
    { id: 2, title: 'Financial', description: 'Tuition, fees, scholarships' },
    { id: 3, title: 'Technical', description: 'IT support, system access' },
    { id: 4, title: 'Administrative', description: 'Registration, documents' },
    { id: 0, title: 'Other', description: 'Other queries' },
]);

const currentForm = computed<Form | null>(() => {
    if (props.form) {
        return props.form;
    }

    return (props.response?.form as Form | undefined) ?? null;
});

const campusInfo = computed(() => {
    if (props.campus) {
        return props.campus;
    }

    return props.response?.campus;
});

const canSubmit = computed(() => Boolean(currentForm.value) && hasStudentRoutes && Boolean(props.form));

const questions = computed(() => {
    return currentForm.value?.current_version?.questions ?? [];
});

const questionCount = computed(() => questions.value.length);

const requiredCount = computed(() => questions.value.filter((q) => q.is_required).length);

const getTypeBadgeVariant = (type: string) => {
    switch (type) {
        case 'feedback':
            return 'default';
        case 'survey':
            return 'secondary';
        case 'query':
            return 'outline';
        default:
            return 'default';
    }
};

const goBack = () => {
    if (hasStudentRoutes) {
        router.visit(route('student.forms.index'));
        return;
    }

    router.visit(route('forms.review.index'));
};

const handleSubmit = async (data: any) => {
    if (!canSubmit.value || !currentForm.value) {
        return;
    }

    // Add query-specific data if it's a query form
    if (currentForm.value.type === 'query') {
        if (selectedTopic.value === 0) {
            data.custom_topic_text = customTopicText.value;
        } else {
            data.topic_id = selectedTopic.value;
        }
    }

    const form = useForm(data);

    form.post(route('student.forms.submit', currentForm.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Form submitted successfully!');
        },
        onError: (errors) => {
            // Pass errors back to form component
            if (formRef.value) {
                formRef.value.setSubmitting(false);
                formRef.value.setErrors(errors);
            }
            toast.error('Please fix the errors and try again');
        },
        onFinish: () => {
            if (formRef.value) {
                formRef.value.setSubmitting(false);
            }
        },
    });
};
</script>
<template>
    <!-- Page Header -->
    <div class="mb-6">
        <Button variant="ghost" size="sm" @click="goBack" class="mb-4">
            <ArrowLeft class="mr-2 h-4 w-4" />
            Back to Forms
        </Button>

        <div class="bg-card rounded-lg border p-6">
            <div class="mb-4 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ currentForm?.title ?? 'Untitled form' }}</h1>
                    <p v-if="currentForm?.description" class="text-muted-foreground mt-2">
                        {{ currentForm.description }}
                    </p>
                </div>
                <Badge v-if="currentForm" :variant="getTypeBadgeVariant(currentForm.type)">
                    {{ currentForm.type }}
                </Badge>
            </div>

            <!-- Form Info -->
            <div class="text-muted-foreground flex flex-wrap gap-4 text-sm">
                <div v-if="campusInfo?.name" class="flex items-center gap-2">
                    <Building class="h-4 w-4" />
                    <span>{{ campusInfo.name }}</span>
                </div>
                <div v-if="questionCount > 0" class="flex items-center gap-2">
                    <FileText class="h-4 w-4" />
                    <span>{{ questionCount }} questions</span>
                </div>
                <div v-if="requiredCount > 0" class="flex items-center gap-2">
                    <AlertCircle class="h-4 w-4" />
                    <span>{{ requiredCount }} required</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Query Topic Selection (for query forms) -->
    <div v-if="currentForm?.type === 'query'" class="mb-6">
        <Card>
            <CardHeader>
                <CardTitle>Query Topic</CardTitle>
                <CardDescription> Select the topic that best describes your query </CardDescription>
            </CardHeader>
            <CardContent>
                <QueryTopicSelector v-model="selectedTopic" :topics="queryTopics" @update:custom-topic="customTopicText = $event" />
            </CardContent>
        </Card>
    </div>

    <!-- Dynamic Form -->
    <div v-if="currentForm" class="bg-card rounded-lg border p-6">
        <DynamicForm v-if="canSubmit" ref="formRef" :form="currentForm" :sections="currentForm.current_version?.sections || []" :questions="questions" :allow-anonymous="currentForm.type === 'feedback'" @submit="handleSubmit" @cancel="goBack" />
        <div v-else class="text-muted-foreground text-sm">This form is not available for submission in the current context.</div>
    </div>
</template>
