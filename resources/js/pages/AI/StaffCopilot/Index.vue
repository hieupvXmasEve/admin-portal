<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Bot, CircleStop, Database, FileText, LoaderCircle, RefreshCw, Send, ShieldCheck, Sparkles, UserRound, Wifi } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
        section?: string;
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
    profile_sections: Record<string, unknown>;
    requested_sections: string[];
    returned_sections: string[];
    profile_catalog_version: string | null;
    profile_entity_type: string | null;
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
    run_id: number | null;
    run_status: string | null;
    answer: AnswerPayload | null;
}

interface ActiveRun {
    id: number;
    status: string;
    assistant_message_id: number;
    last_event_id: number | null;
    stream_url: string;
    can_cancel: boolean;
    provider: string | null;
    model: string | null;
    runtime_mode: 'deterministic' | 'live_provider' | 'fallback';
    stream_transport: 'sse';
    stream_mode: string;
    streaming_enabled: boolean;
    websocket_required: boolean;
    prompt_version: string | null;
    catalog_version: string | null;
    tool_schema_version: string | null;
}

const props = defineProps<{
    conversation: Conversation | null;
    messages: CopilotMessage[];
    active_run: ActiveRun | null;
    suggested_prompts: SuggestedPrompt[];
    capabilities: {
        tool_names: string[];
        catalog_version: string;
        entity_catalog_version: string;
        profile_catalog_version: string;
        tool_schema_version: string;
        tool_schema_versions: Record<string, string>;
        sdk_installed: boolean;
        live_provider_enabled: boolean;
        runtime_mode: 'deterministic' | 'live_provider' | 'fallback';
        stream_transport: 'sse';
        streaming_enabled: boolean;
        websocket_required: boolean;
        supported_provider_stream_modes: Record<string, string>;
    };
}>();

type RuntimePayload = Record<string, unknown>;

const form = useForm({
    conversation_id: props.conversation?.id ?? null,
    question: '',
});

const localMessages = ref<CopilotMessage[]>(props.messages.map((message) => ({ ...message })));
const activeRunStatus = ref<string | null>(props.active_run?.status ?? null);
const lastEventId = ref<number>(props.active_run?.last_event_id ?? 0);
const isStreaming = ref(false);
const eventSource = ref<EventSource | null>(null);

const terminalStatuses = ['completed', 'failed', 'cancelled'];

const displayMessages = computed(() => localMessages.value);

const latestAssistantMessage = computed(() => [...localMessages.value].reverse().find((message) => message.role === 'assistant'));

const canCancelActiveRun = computed(() => props.active_run?.can_cancel === true && activeRunStatus.value !== null && !terminalStatuses.includes(activeRunStatus.value));

const statusVariant = (status: string): 'success' | 'warning' | 'destructive' | 'outline' => {
    if (status === 'completed') {
        return 'success';
    }

    if (status === 'partial' || ['queued', 'running', 'planning', 'tool_running', 'streaming'].includes(status)) {
        return 'warning';
    }

    if (status === 'failed' || status === 'denied' || status === 'cancelled') {
        return 'destructive';
    }

    return 'outline';
};

const submit = (): void => {
    const question = form.question.trim();

    if (!question) {
        return;
    }

    const optimisticId = Date.now() * -1;

    localMessages.value = [
        ...localMessages.value,
        {
            id: optimisticId,
            role: 'user',
            content: question,
            created_at: null,
            hidden_sections: [],
            run_id: null,
            run_status: null,
            answer: null,
        },
    ];

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

const profileSectionEntries = (sections: Record<string, unknown>): Array<[string, unknown]> => Object.entries(sections);

const isRecord = (value: unknown): value is Record<string, unknown> => value !== null && typeof value === 'object' && !Array.isArray(value);

const profileValueEntries = (value: unknown): Array<[string, unknown]> => (isRecord(value) ? Object.entries(value) : [['value', value]]);

const parsePayload = (event: MessageEvent<string>): RuntimePayload => {
    try {
        const decoded: unknown = JSON.parse(event.data);

        return decoded && typeof decoded === 'object' ? (decoded as RuntimePayload) : {};
    } catch {
        return {};
    }
};

const payloadNumber = (payload: RuntimePayload, key: string): number | null => (typeof payload[key] === 'number' ? payload[key] : null);

const payloadString = (payload: RuntimePayload, key: string): string | null => (typeof payload[key] === 'string' ? payload[key] : null);

const updateLastEventId = (payload: RuntimePayload): void => {
    const eventId = payloadNumber(payload, 'event_id');

    if (eventId !== null) {
        lastEventId.value = eventId;
    }
};

const assistantMessageFor = (messageId: number): CopilotMessage | undefined => localMessages.value.find((message) => message.id === messageId && message.role === 'assistant');

const applyStatusPayload = (payload: RuntimePayload): void => {
    updateLastEventId(payload);

    const status = payloadString(payload, 'status');

    if (status) {
        activeRunStatus.value = status;
    }
};

const applyMessageDelta = (event: MessageEvent<string>): void => {
    const payload = parsePayload(event);
    updateLastEventId(payload);

    const messageId = payloadNumber(payload, 'message_id');
    const delta = payloadString(payload, 'delta');

    if (messageId === null || delta === null) {
        return;
    }

    const message = assistantMessageFor(messageId);

    if (!message) {
        return;
    }

    message.content += delta;
    message.run_status = 'streaming';
    activeRunStatus.value = 'streaming';
};

const applyMessageCompleted = (event: MessageEvent<string>): void => {
    const payload = parsePayload(event);
    updateLastEventId(payload);

    const messageId = payloadNumber(payload, 'message_id');
    const status = payloadString(payload, 'status');

    if (messageId === null || status === null) {
        return;
    }

    const message = assistantMessageFor(messageId);

    if (message) {
        message.run_status = status;
    }
};

const disconnectStream = (): void => {
    eventSource.value?.close();
    eventSource.value = null;
    isStreaming.value = false;
};

const finishRun = (status: string): void => {
    activeRunStatus.value = status;
    disconnectStream();
    router.reload({
        only: ['conversation', 'messages', 'active_run', 'capabilities'],
        preserveScroll: true,
    });
};

const handleTerminalEvent =
    (status: string) =>
    (event: Event): void => {
        applyStatusPayload(parsePayload(event as MessageEvent<string>));
        finishRun(status);
    };

const connectToActiveRun = (): void => {
    if (!props.active_run || terminalStatuses.includes(props.active_run.status) || typeof EventSource === 'undefined') {
        return;
    }

    disconnectStream();

    activeRunStatus.value = props.active_run.status;
    lastEventId.value = props.active_run.last_event_id ?? 0;

    const separator = props.active_run.stream_url.includes('?') ? '&' : '?';
    const source = new EventSource(`${props.active_run.stream_url}${separator}cursor=${lastEventId.value}`);
    eventSource.value = source;
    isStreaming.value = true;

    ['run.started', 'run.status', 'tool.started', 'tool.completed', 'tool.denied', 'tool.failed', 'provider.failed'].forEach((eventName) => {
        source.addEventListener(eventName, (event) => applyStatusPayload(parsePayload(event as MessageEvent<string>)));
    });

    source.addEventListener('message.delta', (event) => applyMessageDelta(event as MessageEvent<string>));
    source.addEventListener('message.completed', (event) => applyMessageCompleted(event as MessageEvent<string>));
    source.addEventListener('run.completed', handleTerminalEvent('completed'));
    source.addEventListener('run.failed', handleTerminalEvent('failed'));
    source.addEventListener('run.cancelled', handleTerminalEvent('cancelled'));
    source.addEventListener('update', (event) => {
        if ((event as MessageEvent<string>).data === '</stream>') {
            disconnectStream();
        }
    });
    source.onerror = () => {
        isStreaming.value = false;
    };
};

const cancelActiveRun = (): void => {
    if (!props.active_run || !canCancelActiveRun.value) {
        return;
    }

    router.post(
        route('ai.copilot.runs.cancel', props.active_run.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

const retryRun = (runId: number | null): void => {
    if (runId === null) {
        return;
    }

    router.post(
        route('ai.copilot.runs.retry', runId),
        {},
        {
            preserveScroll: true,
        },
    );
};

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

const formatProfileValue = (value: unknown): string => {
    if (Array.isArray(value)) {
        return `${value.length} item${value.length === 1 ? '' : 's'}`;
    }

    if (isRecord(value)) {
        const entries = Object.entries(value)
            .slice(0, 3)
            .map(([key, entryValue]) => `${formatLabel(key)}: ${Array.isArray(entryValue) || isRecord(entryValue) ? 'details' : formatValue(entryValue)}`);

        return entries.length > 0 ? entries.join(' · ') : '-';
    }

    return formatValue(value);
};

watch(
    () => props.messages,
    (messages) => {
        localMessages.value = messages.map((message) => ({ ...message }));
    },
    { deep: true },
);

watch(
    () => props.active_run,
    (run) => {
        activeRunStatus.value = run?.status ?? null;

        if (run) {
            connectToActiveRun();
        } else {
            disconnectStream();
        }
    },
);

onMounted(() => {
    connectToActiveRun();
});

onBeforeUnmount(() => {
    disconnectStream();
});

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
                    {{ capabilities.runtime_mode === 'live_provider' ? 'Live provider' : 'Deterministic' }}
                </Badge>
                <Badge :variant="isStreaming ? 'warning' : 'outline'">
                    <Wifi class="h-3.5 w-3.5" />
                    {{ activeRunStatus ?? capabilities.stream_transport }}
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
                        <Badge v-else-if="activeRunStatus" :variant="statusVariant(activeRunStatus)">
                            <LoaderCircle v-if="isStreaming" class="h-3.5 w-3.5 animate-spin" />
                            {{ activeRunStatus }}
                        </Badge>
                    </div>
                </CardHeader>

                <CardContent class="flex min-h-[560px] flex-col gap-4 p-4">
                    <div class="flex-1 space-y-4">
                        <div v-if="displayMessages.length === 0" class="border-border bg-muted/30 flex min-h-[280px] items-center justify-center rounded-md border border-dashed p-6 text-center">
                            <div class="max-w-sm space-y-3">
                                <Bot class="text-muted-foreground mx-auto h-9 w-9" />
                                <p class="text-sm font-medium">Choose a prompt or ask for an allowlisted metric.</p>
                            </div>
                        </div>

                        <div v-for="message in displayMessages" :key="message.id" class="flex gap-3" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div v-if="message.role === 'assistant'" class="bg-primary/10 text-primary mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full">
                                <Bot class="h-4 w-4" />
                            </div>

                            <div class="max-w-[88%] space-y-3 rounded-md border p-4" :class="message.role === 'user' ? 'border-primary/30 bg-primary/5' : 'bg-background'">
                                <div class="flex items-start gap-2">
                                    <UserRound v-if="message.role === 'user'" class="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                    <p class="text-sm leading-6 whitespace-pre-wrap">{{ message.content }}</p>
                                </div>

                                <div v-if="message.role === 'assistant' && message.run_status && !message.answer" class="flex flex-wrap items-center gap-2 border-t pt-3">
                                    <Badge :variant="statusVariant(message.run_status)">
                                        <LoaderCircle v-if="!terminalStatuses.includes(message.run_status)" class="h-3.5 w-3.5 animate-spin" />
                                        {{ message.run_status }}
                                    </Badge>
                                    <span class="text-muted-foreground text-xs">Run #{{ message.run_id }}</span>
                                </div>

                                <div v-if="message.answer" class="space-y-3 border-t pt-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge :variant="statusVariant(message.answer.status)">{{ message.answer.status }}</Badge>
                                        <Badge variant="outline">{{ message.answer.confidence.level }}</Badge>
                                        <Badge v-if="message.answer.safe_error_code" variant="destructive">{{ message.answer.safe_error_code }}</Badge>
                                        <Button v-if="message.run_id && message.answer.status === 'failed'" type="button" size="sm" variant="outline" @click="retryRun(message.run_id)">
                                            <RefreshCw class="h-3.5 w-3.5" />
                                            Retry
                                        </Button>
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

                                    <div v-if="profileSectionEntries(message.answer.profile_sections).length > 0" class="space-y-2">
                                        <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Profile</p>
                                        <div class="grid gap-2 lg:grid-cols-2">
                                            <div v-for="[section, value] in profileSectionEntries(message.answer.profile_sections)" :key="section" class="rounded-md border p-3">
                                                <div class="mb-2 flex items-center justify-between gap-2">
                                                    <p class="text-sm font-medium">{{ formatLabel(section) }}</p>
                                                    <Badge variant="outline">{{ message.answer.profile_entity_type ?? 'student' }}</Badge>
                                                </div>
                                                <div class="grid gap-1 text-xs">
                                                    <div v-for="[key, entryValue] in profileValueEntries(value)" :key="key" class="flex items-center justify-between gap-3">
                                                        <span class="text-muted-foreground">{{ formatLabel(key) }}</span>
                                                        <span class="max-w-[60%] truncate text-right font-medium">{{ formatProfileValue(entryValue) }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="message.answer.source_references.length > 0" class="space-y-2">
                                        <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Sources</p>
                                        <div
                                            v-for="source in message.answer.source_references"
                                            :key="`${source.source_report}-${source.metric ?? source.section ?? source.entity_type ?? 'source'}`"
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

                                <div class="flex items-center gap-2">
                                    <Button v-if="canCancelActiveRun" type="button" variant="outline" @click="cancelActiveRun">
                                        <CircleStop class="h-4 w-4" />
                                        Stop
                                    </Button>
                                    <Button type="submit" :disabled="form.processing || !form.question.trim()">
                                        <Send class="h-4 w-4" />
                                        {{ form.processing ? 'Queueing' : 'Send' }}
                                    </Button>
                                </div>
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
                            <Badge :variant="capabilities.live_provider_enabled ? 'success' : 'outline'">{{ capabilities.runtime_mode }}</Badge>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Stream</span>
                            <Badge :variant="capabilities.streaming_enabled ? 'success' : 'outline'">{{ capabilities.stream_transport }}</Badge>
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
