import { useApi, type ApiResponse } from '@/composables/useApiRequest';
import type { BatchPreviewLineClient, BatchPreviewResponse, BatchPreviewSummary, BatchResult } from '@/types/finance';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface BatchStudioConfig<TSetup extends Record<string, unknown>> {
    previewUrl: string;
    commitUrl: string;
    defaultSetup: TSetup;
    commitExtras?: () => Record<string, unknown>;
    onCommitError?: (errors: Record<string, string>) => void;
    defaultInclude?: (line: BatchPreviewLineClient) => boolean;
}

export function useBatchStudio<TSetup extends Record<string, unknown>>(config: BatchStudioConfig<TSetup>) {
    const step = ref<1 | 2 | 3 | 4>(1);
    const setup = reactive({ ...config.defaultSetup });
    const lines = ref<BatchPreviewLineClient[]>([]);
    const summary = ref<BatchPreviewSummary>({});
    const previewToken = ref<string | null>(null);
    const selected = ref<Set<string>>(new Set());
    const driftMessage = ref<string | null>(null);
    const previewing = ref(false);

    const include = config.defaultInclude ?? ((l: BatchPreviewLineClient) => l.display.diff !== 'skip');
    const api = useApi();

    async function runPreview(): Promise<void> {
        driftMessage.value = null;
        previewing.value = true;
        try {
            const response = await api.post<BatchPreviewResponse>(config.previewUrl, { ...setup });
            const envelope = response.data?.value as ApiResponse<BatchPreviewResponse> | undefined;
            if (!envelope?.success || !envelope.data) {
                driftMessage.value = envelope?.message ?? 'Không thể tải xem trước.';
                return;
            }
            const data = envelope.data;
            lines.value = data.lines;
            summary.value = data.summary;
            previewToken.value = data.preview_token;
            selected.value = new Set(data.lines.filter(include).map((l) => l.key));
            step.value = 2;
        } finally {
            previewing.value = false;
        }
    }

    function toggle(key: string): void {
        const next = new Set(selected.value);
        if (next.has(key)) {
            next.delete(key);
        } else {
            next.add(key);
        }
        selected.value = next;
    }

    function setSelection(keys: string[], value: boolean): void {
        const next = new Set(selected.value);
        for (const key of keys) {
            if (value) {
                next.add(key);
            } else {
                next.delete(key);
            }
        }
        selected.value = next;
    }

    function excludeWarnings(): void {
        selected.value = new Set(
            [...selected.value].filter((k) => lines.value.find((l) => l.key === k)?.display.diff !== 'warning'),
        );
    }

    const form = useForm<{ preview_token: string; selected_keys: string[] } & Record<string, unknown>>({
        preview_token: '',
        selected_keys: [],
    });

    function commit(): void {
        const extras = config.commitExtras?.() ?? {};

        form.preview_token = previewToken.value ?? '';
        form.selected_keys = [...selected.value];

        form
            .transform((data) => ({
                ...data,
                ...extras,
            }))
            .post(config.commitUrl, {
                preserveScroll: true,
                onSuccess: () => {
                    step.value = 4;
                },
                onError: (errors) => {
                    if (errors.preview_token) {
                        driftMessage.value = errors.preview_token;
                        step.value = 2;
                    }
                    config.onCommitError?.(errors);
                },
            });
    }

    const counts = computed(() => {
        const by: Record<string, number> = { create: 0, update: 0, skip: 0, warning: 0 };
        for (const l of lines.value) {
            by[l.display.diff]++;
        }
        return by;
    });

    const page = usePage();
    const result = computed<BatchResult | null>(() => {
        const flash = page.flash as Record<string, unknown> | undefined;
        return (flash?.batch_result as BatchResult) ?? null;
    });

    return {
        step,
        setup,
        lines,
        summary,
        previewToken,
        selected,
        driftMessage,
        counts,
        result,
        form,
        previewing,
        runPreview,
        toggle,
        setSelection,
        excludeWarnings,
        commit,
    };
}