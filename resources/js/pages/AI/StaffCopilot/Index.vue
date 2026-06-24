<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Bot, Database, FileText, Send, ShieldCheck, Sparkles, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
import { route } from 'ziggy-js';

interface Conversation {
    id: number;
    title: string | null;
    status: string;
    created_at: string | null;
    last_message_at: string | null;
}

interface SuggestedPrompt {
    key: string;
    question: string;
    metric: string;
    filters: Record<string, unknown>;
    group_by: string[];
    source_report: string;
}

interface AnswerPayload {
    status: string;
    summary: Record<string, unknown> | null;
    groups: Array<{
        key: string;
        value: string;
        label: string;
        metrics: Record<string, unknown>;
    }>;
    source_references: Array<{
        source_report: string;
        source_reference_policy: string;
        catalog_version: string;
        tool_schema_version: string;
        metric?: string;
        entity_type?: string;
    }>;
    normalized_filters: Record<string, unknown>;
    group_by: string[];
    entity_results: Array<{
        entity_type: string;
        entity_ref: string;
        label: string;
        safe_identifiers: Record<string, unknown>;
        match_reason: string;
        source_reference: {
            source_report: string;
            source_reference_policy: string;
            catalog_version: string;
            tool_schema_version: string;
            entity_type: string;
        };
    }>;
    entity_types: string[];
    normalized_query: string | null;
    result_limit: number | null;
    warnings: string[];
    confidence: {
        level: string;
        basis: string;
    };
    safe_error_code: string | null;
    record_count: number;
}

interface CopilotMessage {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    created_at: string | null;
    hidden_sections: string[];
    answer: AnswerPayload | null;
}

const props = defineProps<{
    conversation: Conversation | null;
    messages: CopilotMessage[];
    suggested_prompts: SuggestedPrompt[];
    capabilities: {
        tool_names: string[];
        catalog_version: string;
        tool_schema_version: string;
        sdk_installed: boolean;
        live_provider_enabled: boolean;
    };
}>();

const form = useForm({
    conversation_id: props.conversation?.id ?? null,
    question: '',
});

const latestAssistantMessage = computed(() => [...props.messages].reverse().find((message) => message.role === 'assistant'));

const statusVariant = (status: string): 'success' | 'warning' | 'destructive' | 'outline' => {
    if (status === 'completed') {
        return 'success';
    }

    if (status === 'partial') {
        return 'warning';
    }

    if (status === 'failed' || status === 'denied') {
        return 'destructive';
    }

    return 'outline';
};

const submit = (): void => {
    const question = form.question.trim();

    if (!question) {
        return;
    }

    form.conversation_id = props.conversation?.id ?? null;
    form.question = question;
    form.post(route('ai.copilot.messages.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('question');
        },
    });
};

const usePrompt = (prompt: SuggestedPrompt): void => {
    form.question = prompt.question;
};

const summaryEntries = (summary: Record<string, unknown> | null): Array<[string, unknown]> => Object.entries(summary ?? {});

const metricsEntries = (metrics: Record<string, unknown>): Array<[string, unknown]> => Object.entries(metrics);

const safeIdentifierEntries = (identifiers: Record<string, unknown>): Array<[string, unknown]> => Object.entries(identifiers);

const formatLabel = (value: string): string =>
    value
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');

const formatValue = (value: unknown): string => {
    if (typeof value === 'number') {
        return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(value);
    }

    if (value === null || value === undefined || value === '') {
        return '-';
    }

    return String(value);
};

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <Head title="Staff Copilot" />

    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-1">
                <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">AI Operations</p>
                <h1 class="text-2xl font-semibold tracking-tight">Staff Copilot</h1>
                <p class="text-muted-foreground max-w-3xl text-sm">Metric answers from allowlisted Academic and Finance reports.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline">
                    <Database class="h-3.5 w-3.5" />
                    {{ capabilities.catalog_version }}
                </Badge>
                <Badge :variant="capabilities.sdk_installed ? 'success' : 'warning'">
                    <Sparkles class="h-3.5 w-3.5" />
                    Laravel AI {{ capabilities.sdk_installed ? 'ready' : 'missing' }}
                </Badge>
                <Badge :variant="capabilities.live_provider_enabled ? 'success' : 'outline'">
                    <ShieldCheck class="h-3.5 w-3.5" />
                    {{ capabilities.live_provider_enabled ? 'Live provider' : 'Deterministic' }}
                </Badge>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <Card class="min-h-[640px]">
                <CardHeader class="border-b">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>{{ conversation?.title ?? 'New staff chat' }}</CardTitle>
                            <CardDescription>{{ latestAssistantMessage?.answer?.source_references?.[0]?.source_report ?? capabilities.tool_names.join(', ') }}</CardDescription>
                        </div>
                        <Badge v-if="latestAssistantMessage?.answer" :variant="statusVariant(latestAssistantMessage.answer.status)">
                            {{ latestAssistantMessage.answer.status }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="flex min-h-[560px] flex-col gap-4 p-4">
                    <div class="flex-1 space-y-4">
                        <div v-if="messages.length === 0" class="border-border bg-muted/30 flex min-h-[280px] items-center justify-center rounded-md border border-dashed p-6 text-center">
                            <div class="max-w-sm space-y-3">
                                <Bot class="text-muted-foreground mx-auto h-9 w-9" />
                                <p class="text-sm font-medium">Choose a prompt or ask for an allowlisted metric.</p>
                            </div>
                        </div>

                        <div v-for="message in messages" :key="message.id" class="flex gap-3" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div v-if="message.role === 'assistant'" class="bg-primary/10 text-primary mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full">
                                <Bot class="h-4 w-4" />
                            </div>

                            <div class="max-w-[88%] space-y-3 rounded-md border p-4" :class="message.role === 'user' ? 'border-primary/30 bg-primary/5' : 'bg-background'">
                                <div class="flex items-start gap-2">
                                    <UserRound v-if="message.role === 'user'" class="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                    <p class="text-sm leading-6 whitespace-pre-wrap">{{ message.content }}</p>
                                </div>

                                <div v-if="message.answer" class="space-y-3 border-t pt-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge :variant="statusVariant(message.answer.status)">{{ message.answer.status }}</Badge>
                                        <Badge variant="outline">{{ message.answer.confidence.level }}</Badge>
                                        <Badge v-if="message.answer.safe_error_code" variant="destructive">{{ message.answer.safe_error_code }}</Badge>
                                    </div>

                                    <div v-if="message.answer.summary" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                        <div v-for="[key, value] in summaryEntries(message.answer.summary)" :key="key" class="rounded-md border p-3">
                                            <p class="text-muted-foreground text-xs">{{ formatLabel(key) }}</p>
                                            <p class="text-sm font-semibold">{{ formatValue(value) }}</p>
                                        </div>
                                    </div>

                                    <div v-if="message.answer.groups.length > 0" class="space-y-2">
                                        <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Groups</p>
                                        <div class="grid gap-2 lg:grid-cols-2">
                                            <div v-for="group in message.answer.groups" :key="`${group.key}-${group.value}`" class="rounded-md border p-3">
                                                <div class="mb-2 flex items-center justify-between gap-2">
                                                    <p class="text-sm font-medium">{{ group.label }}</p>
                                                    <Badge variant="outline">{{ group.key }}</Badge>
                                                </div>
                                                <div class="grid gap-1 text-xs">
                                                    <div v-for="[metric, value] in metricsEntries(group.metrics)" :key="metric" class="flex items-center justify-between gap-3">
                                                        <span class="text-muted-foreground">{{ formatLabel(metric) }}</span>
                                                        <span class="font-medium">{{ formatValue(value) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="message.answer.entity_results.length > 0" class="space-y-2">
                                        <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Candidates</p>
                                        <div class="grid gap-2 lg:grid-cols-2">
                                            <div v-for="candidate in message.answer.entity_results" :key="candidate.entity_ref" class="rounded-md border p-3">
                                                <div class="mb-2 flex items-start justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-medium">{{ candidate.label }}</p>
                                                        <p class="text-muted-foreground text-xs">{{ candidate.match_reason }}</p>
                                                    </div>
                                                    <Badge variant="outline">{{ candidate.entity_type }}</Badge>
                                                </div>
                                                <div class="grid gap-1 text-xs">
                                                    <div v-for="[key, value] in safeIdentifierEntries(candidate.safe_identifiers)" :key="key" class="flex items-center justify-between gap-3">
                                                        <span class="text-muted-foreground">{{ formatLabel(key) }}</span>
                                                        <span class="font-medium">{{ formatValue(value) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="message.answer.source_references.length > 0" class="space-y-2">
                                        <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Sources</p>
                                        <div
                                            v-for="source in message.answer.source_references"
                                            :key="`${source.source_report}-${source.metric ?? source.entity_type ?? 'source'}`"
                                            class="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 text-xs"
                                        >
                                            <FileText class="text-muted-foreground h-3.5 w-3.5" />
                                            <span class="font-medium">{{ source.source_report }}</span>
                                            <span class="text-muted-foreground">{{ source.source_reference_policy }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form class="border-t pt-4" @submit.prevent="submit">
                        <div class="flex flex-col gap-3">
                            <textarea
                                v-model="form.question"
                                class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring min-h-24 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                maxlength="1000"
                                placeholder="Ask about current semester outstanding tuition, defer counts, fee monitor, or DNG lifecycle"
                                :disabled="form.processing"
                            />
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <p v-if="form.errors.question" class="text-destructive flex items-center gap-2 text-sm">
                                    <AlertTriangle class="h-4 w-4" />
                                    {{ form.errors.question }}
                                </p>
                                <span v-else class="text-muted-foreground text-xs">{{ form.question.length }}/1000</span>

                                <Button type="submit" :disabled="form.processing || !form.question.trim()">
                                    <Send class="h-4 w-4" />
                                    {{ form.processing ? 'Sending' : 'Send' }}
                                </Button>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div class="space-y-5">
                <Card>
                    <CardHeader>
                        <CardTitle>Prompts</CardTitle>
                        <CardDescription>{{ suggested_prompts.length }} catalog cases</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <button v-for="prompt in suggested_prompts" :key="prompt.key" type="button" class="hover:border-primary/50 hover:bg-muted/50 w-full rounded-md border p-3 text-left transition" @click="usePrompt(prompt)">
                            <span class="block text-sm font-medium">{{ prompt.question }}</span>
                            <span class="text-muted-foreground mt-2 flex flex-wrap items-center gap-2 text-xs">
                                <span>{{ prompt.metric }}</span>
                                <span v-if="prompt.group_by.length">by {{ prompt.group_by.join(', ') }}</span>
                            </span>
                        </button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Capability</CardTitle>
                        <CardDescription>{{ capabilities.tool_schema_version }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Tool</span>
                            <Badge variant="outline">{{ capabilities.tool_names.join(', ') }}</Badge>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Provider</span>
                            <Badge variant="outline">deterministic</Badge>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Evidence</span>
                            <Badge variant="success">audited</Badge>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
