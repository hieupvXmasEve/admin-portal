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
    content_markdown: string;
    terminal_state: {
        kind: 'completed' | 'partial' | 'unsupported' | 'cancelled' | 'denied' | 'failed';
        title: string;
        message: string;
        safe_error_code: string | null;
        is_retryable: boolean;
    };
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
    campus_scope_snapshot: Record<string, unknown>;
    freshness: Record<string, unknown>;
    warnings: string[];
    hidden_sections: string[];
    hidden_section_notice: string | null;
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
    run_progress_label?: string | null;
    run_progress_detail?: string | null;
    answer: AnswerPayload | null;
}

interface ActiveRun {
    id: number;
    status: string;
    assistant_message_id: number;
    last_event_id: number | null;
    replay_cursor: number;
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
type MarkdownInline = { text: string; strong: boolean };
type MarkdownBlock = { type: 'heading'; text: string } | { type: 'paragraph'; text: string } | { type: 'unordered-list'; items: string[] } | { type: 'ordered-list'; items: string[] } | { type: 'table'; rows: string[][] };

const form = useForm({
    conversation_id: props.conversation?.id ?? null,
    question: '',
});

const localMessages = ref<CopilotMessage[]>(props.messages.map((message) => ({ ...message })));
const activeRunStatus = ref<string | null>(props.active_run?.status ?? null);
const activeRunProgressLabel = ref<string | null>(null);
const activeRunProgressDetail = ref<string | null>(null);
const lastEventId = ref<number>(props.active_run?.replay_cursor ?? props.active_run?.last_event_id ?? 0);
const isStreaming = ref(false);
const eventSource = ref<EventSource | null>(null);
const reconnectTimer = ref<ReturnType<typeof setTimeout> | null>(null);
const appliedEventIds = ref<Set<number>>(new Set());

const terminalStatuses = ['completed', 'failed', 'cancelled'];
const statusLabels: Record<string, string> = {
    queued: 'Waiting to start',
    running: 'Starting',
    planning: 'Reviewing question',
    tool_running: 'Checking approved data',
    streaming: 'Writing answer',
    completed: 'Answer ready',
    failed: 'Could not complete',
    cancelled: 'Stopped',
    partial: 'Partial answer',
    denied: 'Access blocked',
};

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

const displayStatusLabel = (status: string | null): string | null => (status ? (statusLabels[status] ?? status) : null);

const consumeEventPayload = (payload: RuntimePayload): boolean => {
    const eventId = payloadNumber(payload, 'event_id');

    if (eventId === null) {
        return true;
    }

    if (appliedEventIds.value.has(eventId)) {
        return false;
    }

    appliedEventIds.value.add(eventId);
    lastEventId.value = Math.max(lastEventId.value, eventId);

    return true;
};

const assistantMessageFor = (messageId: number): CopilotMessage | undefined => localMessages.value.find((message) => message.id === messageId && message.role === 'assistant');

const applyStatusPayload = (payload: RuntimePayload): void => {
    if (!consumeEventPayload(payload)) {
        return;
    }

    const status = payloadString(payload, 'status');
    const progressLabel = payloadString(payload, 'progress_label');
    const progressDetail = payloadString(payload, 'progress_detail');

    if (status) {
        activeRunStatus.value = status;
    }

    if (progressLabel) {
        activeRunProgressLabel.value = progressLabel;
    } else if (status) {
        activeRunProgressLabel.value = displayStatusLabel(status);
    }

    if (progressDetail) {
        activeRunProgressDetail.value = progressDetail;
    }

    const assistantMessageId = props.active_run?.assistant_message_id ?? null;

    if (assistantMessageId !== null) {
        const message = assistantMessageFor(assistantMessageId);

        if (message) {
            message.run_status = status ?? message.run_status;
            message.run_progress_label = activeRunProgressLabel.value;
            message.run_progress_detail = activeRunProgressDetail.value;
        }
    }
};

const applyMessageCreated = (event: MessageEvent<string>): void => {
    const payload = parsePayload(event);

    if (!consumeEventPayload(payload)) {
        return;
    }

    const messageId = payloadNumber(payload, 'message_id');
    const role = payloadString(payload, 'role');

    if (messageId === null || role !== 'assistant' || assistantMessageFor(messageId)) {
        return;
    }

    localMessages.value = [
        ...localMessages.value,
        {
            id: messageId,
            role: 'assistant',
            content: '',
            created_at: null,
            hidden_sections: [],
            run_id: payloadNumber(payload, 'run_id'),
            run_status: payloadString(payload, 'status') ?? activeRunStatus.value,
            run_progress_label: activeRunProgressLabel.value,
            run_progress_detail: activeRunProgressDetail.value,
            answer: null,
        },
    ];
};

const applyMessageDelta = (event: MessageEvent<string>): void => {
    const payload = parsePayload(event);

    if (!consumeEventPayload(payload)) {
        return;
    }

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
    message.run_progress_label = displayStatusLabel('streaming');
    message.run_progress_detail = 'The answer is being prepared.';
    activeRunStatus.value = 'streaming';
    activeRunProgressLabel.value = displayStatusLabel('streaming');
};

const applyMessageCompleted = (event: MessageEvent<string>): void => {
    const payload = parsePayload(event);

    if (!consumeEventPayload(payload)) {
        return;
    }

    const messageId = payloadNumber(payload, 'message_id');
    const status = payloadString(payload, 'status');
    const progressLabel = payloadString(payload, 'progress_label');

    if (messageId === null || status === null) {
        return;
    }

    const message = assistantMessageFor(messageId);

    if (message) {
        message.run_status = status;
        message.run_progress_label = progressLabel ?? displayStatusLabel(status);
    }
};

const clearReconnectTimer = (): void => {
    if (reconnectTimer.value !== null) {
        clearTimeout(reconnectTimer.value);
        reconnectTimer.value = null;
    }
};

const closeEventSource = (): void => {
    eventSource.value?.close();
    eventSource.value = null;
};

const disconnectStream = (): void => {
    clearReconnectTimer();
    closeEventSource();
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

const scheduleReconnect = (): void => {
    if (!props.active_run || reconnectTimer.value !== null || activeRunStatus.value === null || terminalStatuses.includes(activeRunStatus.value)) {
        return;
    }

    closeEventSource();
    isStreaming.value = false;
    reconnectTimer.value = setTimeout(() => {
        reconnectTimer.value = null;
        connectToActiveRun(lastEventId.value, false);
    }, 750);
};

const connectToActiveRun = (cursor?: number, resetSeenEvents = true): void => {
    if (!props.active_run || terminalStatuses.includes(props.active_run.status) || typeof EventSource === 'undefined') {
        return;
    }

    clearReconnectTimer();
    closeEventSource();

    activeRunStatus.value = props.active_run.status;
    activeRunProgressLabel.value = displayStatusLabel(props.active_run.status);
    activeRunProgressDetail.value = null;

    if (resetSeenEvents) {
        appliedEventIds.value = new Set();
    }

    lastEventId.value = cursor ?? props.active_run.replay_cursor ?? props.active_run.last_event_id ?? 0;

    const separator = props.active_run.stream_url.includes('?') ? '&' : '?';
    const source = new EventSource(`${props.active_run.stream_url}${separator}cursor=${lastEventId.value}`);
    eventSource.value = source;
    isStreaming.value = true;

    ['run.started', 'run.status', 'tool.started', 'tool.completed', 'tool.denied', 'tool.failed', 'provider.failed'].forEach((eventName) => {
        source.addEventListener(eventName, (event) => applyStatusPayload(parsePayload(event as MessageEvent<string>)));
    });

    source.addEventListener('message.created', (event) => applyMessageCreated(event as MessageEvent<string>));
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
        scheduleReconnect();
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

const sourceDisplayLabel = (sourceReport: string): string => formatLabel(sourceReport.replace(/[.-]/g, '_'));

const freshnessLabel = (freshness: Record<string, unknown>): string | null => {
    const rule = typeof freshness.rule === 'string' ? freshness.rule : null;

    if (rule === 'computed_at_request_time') {
        return 'Fresh at request time';
    }

    return rule ? formatLabel(rule) : null;
};

const parseInlineMarkdown = (text: string): MarkdownInline[] => {
    const segments: MarkdownInline[] = [];
    const pattern = /\*\*([^*]+)\*\*/g;
    let cursor = 0;
    let match: RegExpExecArray | null;

    while ((match = pattern.exec(text)) !== null) {
        if (match.index > cursor) {
            segments.push({ text: text.slice(cursor, match.index), strong: false });
        }

        segments.push({ text: match[1], strong: true });
        cursor = match.index + match[0].length;
    }

    if (cursor < text.length) {
        segments.push({ text: text.slice(cursor), strong: false });
    }

    return segments.length > 0 ? segments : [{ text, strong: false }];
};

const isTableSeparator = (line: string): boolean => /^[\s|:-]+$/.test(line) && line.includes('-');

const parseTableRow = (line: string): string[] =>
    line
        .split('|')
        .map((cell) => cell.trim())
        .filter((cell, index, cells) => cell !== '' || (index > 0 && index < cells.length - 1));

const isMarkdownBlockStart = (line: string): boolean => /^(#{1,3})\s+/.test(line) || /^[-*]\s+/.test(line) || /^\d+\.\s+/.test(line) || line.includes('|');

const safeMarkdownBlocks = (content: string): MarkdownBlock[] => {
    const lines = content.split('\n');
    const blocks: MarkdownBlock[] = [];
    let index = 0;

    while (index < lines.length) {
        const line = lines[index].trim();

        if (line === '') {
            index += 1;
            continue;
        }

        const heading = /^(#{1,3})\s+(.*)$/.exec(line);

        if (heading) {
            blocks.push({ type: 'heading', text: heading[2].trim() });
            index += 1;
            continue;
        }

        if (/^[-*]\s+/.test(line)) {
            const items: string[] = [];

            while (index < lines.length && /^[-*]\s+/.test(lines[index].trim())) {
                items.push(lines[index].trim().replace(/^[-*]\s+/, ''));
                index += 1;
            }

            blocks.push({ type: 'unordered-list', items });
            continue;
        }

        if (/^\d+\.\s+/.test(line)) {
            const items: string[] = [];

            while (index < lines.length && /^\d+\.\s+/.test(lines[index].trim())) {
                items.push(lines[index].trim().replace(/^\d+\.\s+/, ''));
                index += 1;
            }

            blocks.push({ type: 'ordered-list', items });
            continue;
        }

        if (line.includes('|')) {
            const rows: string[][] = [];

            while (index < lines.length && lines[index].trim().includes('|')) {
                const row = lines[index].trim();

                if (!isTableSeparator(row)) {
                    rows.push(parseTableRow(row));
                }

                index += 1;
            }

            if (rows.length > 0) {
                blocks.push({ type: 'table', rows });
            }

            continue;
        }

        const paragraphLines = [line];
        index += 1;

        while (index < lines.length) {
            const nextLine = lines[index].trim();

            if (nextLine === '' || isMarkdownBlockStart(nextLine)) {
                break;
            }

            paragraphLines.push(nextLine);
            index += 1;
        }

        blocks.push({ type: 'paragraph', text: paragraphLines.join(' ') });
    }

    return blocks;
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
        activeRunProgressLabel.value = run ? displayStatusLabel(run.status) : null;
        activeRunProgressDetail.value = null;

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
                <p class="text-muted-foreground max-w-3xl text-sm">Safe answers from approved Academic and Finance data.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline">
                    <Database class="h-3.5 w-3.5" />
                    Approved data
                </Badge>
                <Badge :variant="capabilities.sdk_installed ? 'success' : 'warning'">
                    <Sparkles class="h-3.5 w-3.5" />
                    Laravel AI {{ capabilities.sdk_installed ? 'ready' : 'missing' }}
                </Badge>
                <Badge :variant="capabilities.live_provider_enabled ? 'success' : 'outline'">
                    <ShieldCheck class="h-3.5 w-3.5" />
                    {{ capabilities.live_provider_enabled ? 'Connected' : 'Fallback' }}
                </Badge>
                <Badge :variant="isStreaming ? 'warning' : 'outline'">
                    <Wifi class="h-3.5 w-3.5" />
                    {{ activeRunProgressLabel ?? displayStatusLabel(activeRunStatus) ?? (capabilities.streaming_enabled ? 'Live' : 'Paused') }}
                </Badge>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <Card class="min-h-[640px]">
                <CardHeader class="border-b">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <CardTitle>{{ conversation?.title ?? 'New staff chat' }}</CardTitle>
                            <CardDescription>{{ latestAssistantMessage?.answer?.terminal_state.title ?? 'Conversation history and current run state' }}</CardDescription>
                        </div>
                        <Badge v-if="latestAssistantMessage?.answer" :variant="statusVariant(latestAssistantMessage.answer.status)">
                            {{ displayStatusLabel(latestAssistantMessage.answer.status) ?? latestAssistantMessage.answer.status }}
                        </Badge>
                        <Badge v-else-if="activeRunStatus" :variant="statusVariant(activeRunStatus)">
                            <LoaderCircle v-if="isStreaming" class="h-3.5 w-3.5 animate-spin" />
                            {{ activeRunProgressLabel ?? displayStatusLabel(activeRunStatus) }}
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
                                    <p v-if="message.role === 'user' || !message.answer" class="text-sm leading-6 whitespace-pre-wrap">{{ message.content }}</p>
                                </div>

                                <div v-if="message.role === 'assistant' && message.run_status && !message.answer" class="flex flex-wrap items-center gap-2 border-t pt-3">
                                    <Badge :variant="statusVariant(message.run_status)">
                                        <LoaderCircle v-if="!terminalStatuses.includes(message.run_status)" class="h-3.5 w-3.5 animate-spin" />
                                        {{ message.run_progress_label ?? displayStatusLabel(message.run_status) }}
                                    </Badge>
                                    <span class="text-muted-foreground text-xs">{{ message.run_progress_detail ?? `Run #${message.run_id}` }}</span>
                                </div>

                                <div v-if="message.answer" class="space-y-3 border-t pt-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge :variant="statusVariant(message.answer.status)">{{ message.answer.terminal_state.title }}</Badge>
                                        <Badge variant="outline">{{ message.answer.confidence.level }}</Badge>
                                        <Badge v-if="freshnessLabel(message.answer.freshness)" variant="outline">{{ freshnessLabel(message.answer.freshness) }}</Badge>
                                        <Button v-if="message.run_id && message.answer.terminal_state.is_retryable" type="button" size="sm" variant="outline" @click="retryRun(message.run_id)">
                                            <RefreshCw class="h-3.5 w-3.5" />
                                            Retry
                                        </Button>
                                    </div>

                                    <div class="bg-muted/25 space-y-3 rounded-md border p-3">
                                        <template v-for="(block, blockIndex) in safeMarkdownBlocks(message.answer.content_markdown)" :key="`${message.id}-md-${blockIndex}`">
                                            <h3 v-if="block.type === 'heading'" class="text-base font-semibold">
                                                <template v-for="(segment, segmentIndex) in parseInlineMarkdown(block.text)" :key="segmentIndex">
                                                    <strong v-if="segment.strong">{{ segment.text }}</strong>
                                                    <span v-else>{{ segment.text }}</span>
                                                </template>
                                            </h3>

                                            <p v-else-if="block.type === 'paragraph'" class="text-sm leading-6">
                                                <template v-for="(segment, segmentIndex) in parseInlineMarkdown(block.text)" :key="segmentIndex">
                                                    <strong v-if="segment.strong">{{ segment.text }}</strong>
                                                    <span v-else>{{ segment.text }}</span>
                                                </template>
                                            </p>

                                            <ul v-else-if="block.type === 'unordered-list'" class="ml-5 list-disc space-y-1 text-sm leading-6">
                                                <li v-for="(item, itemIndex) in block.items" :key="itemIndex">
                                                    <template v-for="(segment, segmentIndex) in parseInlineMarkdown(item)" :key="segmentIndex">
                                                        <strong v-if="segment.strong">{{ segment.text }}</strong>
                                                        <span v-else>{{ segment.text }}</span>
                                                    </template>
                                                </li>
                                            </ul>

                                            <ol v-else-if="block.type === 'ordered-list'" class="ml-5 list-decimal space-y-1 text-sm leading-6">
                                                <li v-for="(item, itemIndex) in block.items" :key="itemIndex">
                                                    <template v-for="(segment, segmentIndex) in parseInlineMarkdown(item)" :key="segmentIndex">
                                                        <strong v-if="segment.strong">{{ segment.text }}</strong>
                                                        <span v-else>{{ segment.text }}</span>
                                                    </template>
                                                </li>
                                            </ol>

                                            <div v-else class="overflow-x-auto">
                                                <table class="w-full min-w-[360px] border-collapse text-sm">
                                                    <tbody>
                                                        <tr v-for="(row, rowIndex) in block.rows" :key="rowIndex" class="border-b last:border-b-0">
                                                            <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="px-2 py-1.5 align-top" :class="rowIndex === 0 ? 'font-medium' : ''">
                                                                <template v-for="(segment, segmentIndex) in parseInlineMarkdown(cell)" :key="segmentIndex">
                                                                    <strong v-if="segment.strong">{{ segment.text }}</strong>
                                                                    <span v-else>{{ segment.text }}</span>
                                                                </template>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </template>
                                    </div>

                                    <div v-if="message.answer.hidden_section_notice" class="bg-muted/30 flex gap-2 rounded-md border p-3 text-sm">
                                        <AlertTriangle class="text-muted-foreground mt-0.5 h-4 w-4 shrink-0" />
                                        <p>{{ message.answer.hidden_section_notice }}</p>
                                    </div>

                                    <div v-if="message.answer.warnings.length > 0" class="bg-muted/30 space-y-1 rounded-md border p-3 text-sm">
                                        <p class="font-medium">Notes</p>
                                        <ul class="ml-4 list-disc">
                                            <li v-for="warning in message.answer.warnings" :key="warning">{{ formatLabel(warning) }}</li>
                                        </ul>
                                    </div>

                                    <div v-if="message.answer.summary" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                        <div v-for="[key, value] in summaryEntries(message.answer.summary)" :key="key" class="rounded-md border p-3">
                                            <p class="text-muted-foreground text-xs">{{ formatLabel(key) }}</p>
                                            <p class="text-sm font-semibold">{{ formatValue(value) }}</p>
                                        </div>
                                    </div>

                                    <div v-if="message.answer.groups.length > 0" class="space-y-2">
                                        <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Breakdown</p>
                                        <div class="grid gap-2 lg:grid-cols-2">
                                            <div v-for="group in message.answer.groups" :key="`${group.key}-${group.value}`" class="rounded-md border p-3">
                                                <div class="mb-2 flex items-center justify-between gap-2">
                                                    <p class="text-sm font-medium">{{ group.label }}</p>
                                                    <Badge variant="outline">{{ formatLabel(group.key) }}</Badge>
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
                                                    <Badge variant="outline">{{ formatLabel(candidate.entity_type) }}</Badge>
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
                                                    <Badge variant="outline">{{ formatLabel(message.answer.profile_entity_type ?? 'student') }}</Badge>
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
                                        <p class="text-muted-foreground text-xs font-semibold tracking-widest uppercase">Evidence</p>
                                        <div
                                            v-for="source in message.answer.source_references"
                                            :key="`${source.source_report}-${source.metric ?? source.section ?? source.entity_type ?? 'source'}`"
                                            class="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 text-xs"
                                        >
                                            <FileText class="text-muted-foreground h-3.5 w-3.5" />
                                            <span class="font-medium">{{ sourceDisplayLabel(source.source_report) }}</span>
                                            <span class="text-muted-foreground">summary evidence</span>
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
                        <CardDescription>{{ suggested_prompts.length }} examples</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <button v-for="prompt in suggested_prompts" :key="prompt.key" type="button" class="hover:border-primary/50 hover:bg-muted/50 w-full rounded-md border p-3 text-left transition" @click="usePrompt(prompt)">
                            <span class="block text-sm font-medium">{{ prompt.question }}</span>
                            <span class="text-muted-foreground mt-2 flex flex-wrap items-center gap-2 text-xs">
                                <span>{{ sourceDisplayLabel(prompt.source_report) }}</span>
                                <span v-if="prompt.group_by.length">by {{ prompt.group_by.map(formatLabel).join(', ') }}</span>
                            </span>
                        </button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Run Setup</CardTitle>
                        <CardDescription>Query-only staff answers</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Data</span>
                            <Badge variant="outline">Academic and Finance</Badge>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Provider</span>
                            <Badge :variant="capabilities.live_provider_enabled ? 'success' : 'outline'">{{ capabilities.live_provider_enabled ? 'Connected' : 'Fallback' }}</Badge>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Updates</span>
                            <Badge :variant="capabilities.streaming_enabled ? 'success' : 'outline'">{{ capabilities.streaming_enabled ? 'Live' : 'Paused' }}</Badge>
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
