<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useApi } from '@/composables/useApiRequest';
import type { FinanceSearchResult } from '@/types/finance';
import { financeRoutes } from '@/utils/routes';
import { router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const open = ref(false);
const query = ref('');
const results = ref<FinanceSearchResult[]>([]);
const loading = ref(false);
const { get } = useApi();

let abort: AbortController | null = null;
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const runSearch = async (term: string): Promise<void> => {
    abort?.abort();
    if (term.trim().length < 2) {
        results.value = [];
        return;
    }
    abort = new AbortController();
    loading.value = true;
    try {
        const res = await get<{ status: string; results: FinanceSearchResult[] }>(financeRoutes.search(), { q: term }, { signal: abort.signal });
        results.value = res.success && res.data ? res.data.results : [];
    } catch {
        results.value = [];
    } finally {
        loading.value = false;
    }
};

watch(query, (term) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => void runSearch(term), 300);
});

const select = (result: FinanceSearchResult): void => {
    open.value = false;
    query.value = '';
    results.value = [];
    router.visit(result.url);
};

const onKeydown = (e: KeyboardEvent): void => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        open.value = !open.value;
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <button type="button" class="text-muted-foreground hover:bg-accent inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm" @click="open = true">
        <Search class="size-4" />
        <span class="hidden md:inline">Tìm sinh viên…</span>
        <kbd class="bg-muted ml-2 hidden rounded px-1.5 text-xs md:inline">⌘K</kbd>
    </button>

    <Dialog v-model:open="open">
        <DialogContent class="max-w-xl p-0">
            <DialogTitle class="sr-only">Tìm kiếm tài chính</DialogTitle>
            <DialogDescription class="sr-only">Tìm sinh viên theo mã, tên, số invoice hoặc item_id DNG</DialogDescription>
            <div class="border-b p-2">
                <Input v-model="query" placeholder="Mã SV · tên · số invoice · item_id DNG" autofocus class="border-0 focus-visible:ring-0" />
            </div>
            <ul class="max-h-80 overflow-y-auto p-1">
                <li v-if="loading" class="text-muted-foreground px-3 py-2 text-sm">Đang tìm…</li>
                <li v-else-if="results.length === 0 && query.length >= 2" class="text-muted-foreground px-3 py-2 text-sm">Không có kết quả.</li>
                <li v-for="result in results" :key="`${result.type}-${result.id}`" class="hover:bg-accent flex cursor-pointer items-center justify-between rounded-md px-3 py-2 text-sm" @click="select(result)">
                    <span class="font-medium">{{ result.label }}</span>
                    <span class="text-muted-foreground text-xs tabular-nums">{{ result.sublabel }}</span>
                </li>
            </ul>
        </DialogContent>
    </Dialog>
</template>
