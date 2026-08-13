<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxList, ComboboxTrigger, ComboboxViewport } from '@/components/ui/combobox';
import { useApi } from '@/composables';
import { cn } from '@/lib/utils';
import { financeRoutes } from '@/utils/routes';
import { useDebounceFn } from '@vueuse/core';
import { Check, ChevronsUpDown, Loader2, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface PricingUnit {
    id: number;
    code: string;
    name: string;
    credit_points: number | null;
}

interface Props {
    modelValue?: number | null;
    placeholder?: string;
    disabled?: boolean;
    errorMessage?: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    placeholder: 'Áp dụng cho môn học cụ thể (để trống = mọi môn)',
    disabled: false,
    class: '',
});

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const api = useApi();

const open = ref(false);
const searchQuery = ref('');
const units = ref<PricingUnit[]>([]);
const selectedUnit = ref<PricingUnit | null>(null);
const loading = ref(false);
const apiError = ref<string | null>(null);

const displayValue = computed(() => {
    if (selectedUnit.value) {
        return `${selectedUnit.value.code} - ${selectedUnit.value.name}`;
    }
    return props.placeholder;
});

const showMinimumCharactersMessage = computed(() => searchQuery.value.length > 0 && searchQuery.value.length < 2);
const showInitialMessage = computed(() => searchQuery.value.length === 0 && units.value.length === 0);

const fetchUnits = async (query: string) => {
    if (query.length < 2) {
        units.value = [];
        apiError.value = null;
        return;
    }

    units.value = [];
    apiError.value = null;
    loading.value = true;

    try {
        const response = await api.get<PricingUnit[]>(financeRoutes.pricingOperations.unitsSearch(), { q: query.trim(), limit: 20 });
        const responseData = response.data.value;

        if (responseData?.success) {
            units.value = responseData.data ?? [];
        } else {
            throw new Error(responseData?.message || 'Failed to fetch units');
        }
    } catch (err) {
        apiError.value = err instanceof Error ? err.message : 'Failed to fetch units';
    } finally {
        loading.value = false;
    }
};

const debouncedSearch = useDebounceFn((query: string) => {
    fetchUnits(query);
}, 300);

const handleSelect = (value: unknown) => {
    const unit = value as PricingUnit | null;
    if (unit) {
        selectedUnit.value = unit;
        searchQuery.value = '';
        open.value = false;
        emit('update:modelValue', unit.id);
    }
};

const handleClear = (event: Event) => {
    event.stopPropagation();
    selectedUnit.value = null;
    searchQuery.value = '';
    units.value = [];
    emit('update:modelValue', null);
};

watch(searchQuery, (newQuery) => {
    debouncedSearch(newQuery);
});

watch(
    () => props.modelValue,
    (newValue) => {
        if (!newValue) {
            selectedUnit.value = null;
        }
    },
);
</script>

<template>
    <div class="w-full space-y-1">
        <Combobox v-model="selectedUnit" by="id" v-model:open="open" v-model:search-term="searchQuery" @update:model-value="handleSelect" :disabled="props.disabled">
            <ComboboxAnchor as-child>
                <ComboboxTrigger as-child>
                    <Button variant="outline" class="w-full justify-between" :class="cn(props.errorMessage && 'border-destructive', props.class)" :disabled="props.disabled">
                        <span class="truncate">{{ displayValue }}</span>

                        <div class="flex items-center gap-1">
                            <Button v-if="selectedUnit && !props.disabled" type="button" variant="ghost" size="icon" class="hover:bg-muted-foreground/20 h-4 w-4" @click="handleClear">
                                <X class="h-3 w-3" />
                                <span class="sr-only">Clear selection</span>
                            </Button>
                            <ChevronsUpDown class="h-4 w-4 shrink-0 opacity-50" />
                        </div>
                    </Button>
                </ComboboxTrigger>
            </ComboboxAnchor>

            <ComboboxList class="w-[var(--reka-combobox-trigger-width)]">
                <div class="relative w-full items-center">
                    <ComboboxInput class="h-10 rounded-none border-0 border-b pr-4 pl-10 focus-visible:ring-0" placeholder="Search units..." @update:model-value="(value) => (searchQuery = value)" />
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center justify-center px-3">
                        <Search class="text-muted-foreground size-4" />
                    </span>
                </div>

                <ComboboxViewport class="max-h-[300px] overflow-y-auto">
                    <div v-if="loading" class="flex items-center justify-center py-6">
                        <Loader2 class="size-4 animate-spin" />
                        <span class="text-muted-foreground ml-2 text-sm">Loading units...</span>
                    </div>

                    <div v-else-if="apiError" class="flex items-center justify-center py-6">
                        <span class="text-destructive ml-2 text-sm">{{ apiError }}</span>
                    </div>

                    <div v-else-if="showMinimumCharactersMessage" class="flex items-center justify-center py-6">
                        <span class="text-muted-foreground text-sm">Type at least 2 characters to search units</span>
                    </div>

                    <div v-else-if="showInitialMessage" class="flex items-center justify-center py-6">
                        <span class="text-muted-foreground text-sm">Start typing to search for a unit...</span>
                    </div>

                    <ComboboxEmpty v-else-if="units.length === 0 && searchQuery.length >= 2"> No units found for "{{ searchQuery }}". </ComboboxEmpty>

                    <ComboboxGroup v-else-if="units.length > 0">
                        <ComboboxItem v-for="unit in units" :key="unit.id" :value="unit">
                            <div class="flex w-full items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="truncate text-sm font-medium">{{ unit.name }}</p>
                                        <Badge variant="outline" class="shrink-0 text-xs">{{ unit.code }}</Badge>
                                    </div>
                                </div>
                            </div>

                            <ComboboxItemIndicator>
                                <Check class="ml-auto size-4" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>
                    </ComboboxGroup>
                </ComboboxViewport>
            </ComboboxList>
        </Combobox>

        <p v-if="props.errorMessage" class="text-destructive text-sm">
            {{ props.errorMessage }}
        </p>
    </div>
</template>
