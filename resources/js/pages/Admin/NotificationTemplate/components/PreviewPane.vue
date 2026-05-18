<script setup lang="ts">
import { ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { route } from 'ziggy-js';

interface NotificationTemplate {
    id: number;
    campus_id: number;
    type_key: string;
    subject: string;
    body_html: string;
}

interface Draft {
    subject: string;
    body_html: string;
}

const props = defineProps<{
    template: NotificationTemplate;
    draft: Draft;
}>();

const renderedSubject = ref<string>('');
const renderedHtml = ref<string>('');
const isLoading = ref<boolean>(false);
const error = ref<string | null>(null);

const fetchPreview = async () => {
    isLoading.value = true;
    error.value = null;

    try {
        const response = await fetch(
            route('api.admin.notification-templates.preview', { template: props.template.id }),
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    subject: props.draft.subject,
                    body_html: props.draft.body_html,
                }),
            },
        );

        if (!response.ok) {
            throw new Error(`Preview request failed: ${response.status}`);
        }

        const json = await response.json();
        renderedSubject.value = json.data?.rendered_subject ?? '';
        renderedHtml.value = json.data?.rendered_html ?? '';
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Preview failed';
    } finally {
        isLoading.value = false;
    }
};

const debouncedFetch = useDebounceFn(fetchPreview, 300);

// Trigger preview on mount with initial values
fetchPreview();

watch(
    () => [props.draft.subject, props.draft.body_html],
    () => debouncedFetch(),
);
</script>

<template>
    <div class="flex flex-col gap-3">
        <!-- Loading indicator -->
        <div v-if="isLoading" class="flex items-center gap-2 text-sm text-gray-500">
            <span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600"></span>
            Rendering preview…
        </div>

        <!-- Error state -->
        <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>

        <!-- Rendered subject -->
        <div v-if="renderedSubject" class="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-800">
            <span class="mr-1.5 text-xs font-normal text-gray-400 uppercase tracking-wide">Subject:</span>
            {{ renderedSubject }}
        </div>

        <!-- Rendered HTML body in fully-sandboxed iframe.
             Sandbox is intentionally empty: blocks script execution AND prevents
             same-origin navigation (e.g. <meta http-equiv="refresh">) from leaking
             admin Referer to an attacker URL embedded in the draft body. -->
        <iframe
            v-if="renderedHtml"
            :srcdoc="renderedHtml"
            sandbox=""
            class="h-96 w-full rounded border border-gray-200 bg-white"
            title="Email preview"
        />

        <!-- Empty state -->
        <div v-else-if="!isLoading && !error" class="flex h-40 items-center justify-center rounded border border-dashed border-gray-200 text-sm text-gray-400">
            Preview will appear here
        </div>
    </div>
</template>
