<script setup lang="ts">
import EditorContent from '@/components/EditorContent.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';
import PreviewPane from './components/PreviewPane.vue';
import TestSendButton from './components/TestSendButton.vue';

interface Campus {
    id: number;
    name: string;
    code: string;
}

interface VariableMeta {
    label: string;
    sample: string;
}

interface NotificationTemplate {
    id: number;
    campus_id: number;
    type_key: string;
    subject: string;
    body_html: string;
    campus: Campus | null;
}

interface Props {
    template: NotificationTemplate;
    /** Record<variableName, {label, sample}> from NotificationTemplateTypeKey::availableVariables() */
    variables: Record<string, VariableMeta>;
}

const props = defineProps<Props>();

const page = usePage<{ auth: { user: { email: string } } }>();

const form = useForm({
    subject: props.template.subject,
    body_html: props.template.body_html,
});

/**
 * Adapt variables record to EditorContent commonVariables shape:
 * Array<{ name: string; description: string }>
 */
const commonVariables = Object.entries(props.variables).map(([name, meta]) => ({
    name,
    description: meta.label,
}));

/** Subject input ref — enables inserting variable tokens at cursor position. */
const subjectInputRef = ref<HTMLInputElement | null>(null);

const insertVariableIntoSubject = (variableName: string) => {
    const el = subjectInputRef.value;
    if (!el) {
        form.subject += `{{${variableName}}}`;
        return;
    }
    const start = el.selectionStart ?? form.subject.length;
    const end = el.selectionEnd ?? form.subject.length;
    form.subject = form.subject.slice(0, start) + `{{${variableName}}}` + form.subject.slice(end);
    const newPos = start + variableName.length + 4;
    el.focus();
    setTimeout(() => {
        el.setSelectionRange(newPos, newPos);
    }, 0);
};

const save = () => {
    // Inertia web PUT (returns back() with Inertia::flash('success', ...) per
    // CLAUDE.md Inertia v3 rules — NOT the deprecated ->with('success', ...)).
    // The form helper resets dirty state automatically on 2xx; field errors
    // wire to form.errors via the standard Inertia error bag.
    form.put(route('admin.notification-templates.update', { template: props.template.id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Template saved successfully.');
        },
        onError: () => {
            toast.error('Failed to save template. Please check the errors below.');
        },
    });
};
</script>

<template>
    <Head :title="`Edit Template — ${props.template.campus?.name ?? ''} / ${props.template.type_key}`" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Edit Notification Template</h1>
                <p class="mt-1 text-sm text-gray-500">
                    <span class="font-medium">{{ props.template.campus?.name ?? 'Unknown Campus' }}</span>
                    &mdash; {{ props.template.type_key }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <TestSendButton
                    :template="props.template"
                    :draft="form"
                    :user-email="page.props.auth.user.email"
                />
                <Button @click="save" :disabled="form.processing" class="min-w-24">
                    {{ form.processing ? 'Saving…' : 'Save' }}
                </Button>
            </div>
        </div>

        <!-- Two-column layout: editor on left, preview on right (stacks on narrow screens) -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Left: editor column -->
            <div class="space-y-6">
                <!-- Subject field -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Subject</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="space-y-1">
                            <Label for="subject">Email Subject</Label>
                            <Input
                                id="subject"
                                ref="subjectInputRef"
                                v-model="form.subject"
                                type="text"
                                placeholder="e.g. Payment reminder for {{student_name}}"
                                :class="form.errors.subject ? 'border-red-500' : ''"
                            />
                            <p v-if="form.errors.subject" class="text-sm text-red-600">
                                {{ form.errors.subject }}
                            </p>
                        </div>

                        <!-- Variable chips for subject -->
                        <div v-if="commonVariables.length > 0" class="flex flex-wrap items-center gap-1.5">
                            <span class="text-xs text-gray-500 shrink-0">Insert variable:</span>
                            <button
                                v-for="variable in commonVariables"
                                :key="variable.name"
                                type="button"
                                class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 hover:bg-gray-200 transition-colors"
                                @click="insertVariableIntoSubject(variable.name)"
                            >
                                {{ variable.description }}
                            </button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Body editor -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Email Body</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p v-if="form.errors.body_html" class="mb-2 text-sm text-red-600">
                            {{ form.errors.body_html }}
                        </p>
                        <EditorContent
                            v-model="form.body_html"
                            email-mode
                            :common-variables="commonVariables"
                            min-height="320px"
                        />
                    </CardContent>
                </Card>
            </div>

            <!-- Right: preview column -->
            <div>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">Live Preview</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <PreviewPane :template="props.template" :draft="form" />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
