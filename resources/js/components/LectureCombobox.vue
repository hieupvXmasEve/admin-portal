<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxList, ComboboxTrigger, ComboboxViewport } from '@/components/ui/combobox';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface LectureOption {
    id: number;
    employee_id?: string | null;
    first_name?: string | null;
    last_name?: string | null;
    full_name?: string | null;
    display_name?: string | null;
    email?: string | null;
    academic_rank?: string | null;
}

interface Props {
    modelValue?: string | number | null;
    lectures: LectureOption[];
    placeholder?: string;
    disabled?: boolean;
    errorMessage?: string;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Search lecturer by name or email...',
    disabled: false,
    errorMessage: '',
    class: '',
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const open = ref(false);
const searchQuery = ref('');

const normalizedModelValue = computed(() => (props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue)));

const selectedLecture = computed(() => props.lectures.find((lecture) => String(lecture.id) === normalizedModelValue.value) ?? null);

const formatLectureName = (lecture: LectureOption): string => {
    const name = lecture.display_name || lecture.full_name || [lecture.first_name, lecture.last_name].filter(Boolean).join(' ');

    return name.trim() || lecture.email || `Lecturer #${lecture.id}`;
};

const formatAcademicRank = (rank?: string | null): string => {
    if (!rank) return '';

    return rank
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const displayValue = computed(() => {
    if (!selectedLecture.value) {
        return props.placeholder;
    }

    const name = formatLectureName(selectedLecture.value);
    return selectedLecture.value.email ? `${name} (${selectedLecture.value.email})` : name;
});

const normalizedSearchQuery = computed(() => searchQuery.value.trim().toLowerCase());
const hasMinimumSearch = computed(() => normalizedSearchQuery.value.length >= 2);

const matchingLectures = computed(() => {
    if (!hasMinimumSearch.value) return [];

    const terms = normalizedSearchQuery.value.split(/\s+/).filter(Boolean);

    return props.lectures.filter((lecture) => {
        const name = formatLectureName(lecture);
        const emailAccount = lecture.email?.split('@')[0] ?? '';
        const searchableText = [name, `${lecture.first_name ?? ''} ${lecture.last_name ?? ''}`, `${lecture.last_name ?? ''} ${lecture.first_name ?? ''}`, lecture.email, emailAccount, lecture.employee_id, formatAcademicRank(lecture.academic_rank)]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return terms.every((term) => searchableText.includes(term));
    });
});

const visibleLectures = computed(() => matchingLectures.value.slice(0, 20));

const showMinimumCharactersMessage = computed(() => searchQuery.value.length > 0 && searchQuery.value.trim().length < 2);
const showInitialMessage = computed(() => searchQuery.value.length === 0);

const handleSelect = (value: unknown) => {
    const lecture = typeof value === 'object' && value !== null && 'id' in value ? (value as LectureOption) : null;

    if (!lecture) return;

    emit('update:modelValue', lecture.id.toString());
    searchQuery.value = '';
    open.value = false;
};

const handleClear = (event: Event) => {
    event.preventDefault();
    event.stopPropagation();
    emit('update:modelValue', '');
    searchQuery.value = '';
    open.value = false;
};

watch(open, (isOpen) => {
    if (!isOpen) {
        searchQuery.value = '';
    }
});
</script>

<template>
    <div class="w-full space-y-1">
        <Combobox :model-value="selectedLecture" by="id" v-model:open="open" v-model:search-term="searchQuery" :ignore-filter="true" :disabled="disabled" @update:model-value="handleSelect">
            <ComboboxAnchor as-child>
                <ComboboxTrigger as-child>
                    <Button variant="outline" class="w-full justify-between" :class="cn(errorMessage && 'border-destructive', props.class)" :disabled="disabled">
                        <span class="truncate" :class="{ 'text-muted-foreground': !selectedLecture }">{{ displayValue }}</span>

                        <span class="ml-2 flex shrink-0 items-center gap-1">
                            <span
                                v-if="selectedLecture && !disabled"
                                role="button"
                                tabindex="0"
                                class="hover:bg-muted-foreground/20 flex h-5 w-5 items-center justify-center rounded-sm"
                                @click="handleClear"
                                @keydown.enter="handleClear"
                                @keydown.space="handleClear"
                            >
                                <X class="h-3 w-3" />
                                <span class="sr-only">Clear lecturer</span>
                            </span>
                            <ChevronsUpDown class="text-muted-foreground h-4 w-4 opacity-50" />
                        </span>
                    </Button>
                </ComboboxTrigger>
            </ComboboxAnchor>

            <ComboboxList class="w-[var(--reka-combobox-trigger-width)]">
                <div class="relative w-full items-center">
                    <ComboboxInput class="h-10 rounded-none border-0 border-b pr-4 pl-10 focus-visible:ring-0" placeholder="Type name or email, e.g. trangnk16..." @update:model-value="(value) => (searchQuery = value)" />
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center justify-center px-3">
                        <Search class="text-muted-foreground size-4" />
                    </span>
                </div>

                <ComboboxViewport class="max-h-[300px] overflow-y-auto">
                    <div v-if="showMinimumCharactersMessage" class="flex items-center justify-center py-6">
                        <span class="text-muted-foreground text-sm">Type at least 2 characters to search lecturers</span>
                    </div>

                    <div v-else-if="showInitialMessage" class="flex items-center justify-center py-6">
                        <span class="text-muted-foreground text-sm">Start typing to search by name or email</span>
                    </div>

                    <ComboboxEmpty v-else-if="visibleLectures.length === 0"> No lecturers found for "{{ searchQuery }}". </ComboboxEmpty>

                    <ComboboxGroup v-else>
                        <ComboboxItem
                            v-for="lecture in visibleLectures"
                            :key="lecture.id"
                            :value="lecture"
                            class="data-[highlighted]:bg-primary data-[highlighted]:text-primary-foreground [&[data-highlighted]_.lecture-option-primary]:text-primary-foreground [&[data-highlighted]_.lecture-option-secondary]:text-primary-foreground/85 px-3 py-2"
                        >
                            <div class="flex min-w-0 flex-1 flex-col">
                                <span class="lecture-option-primary truncate text-sm font-medium">{{ formatLectureName(lecture) }}</span>
                                <span class="lecture-option-secondary text-muted-foreground truncate text-xs">
                                    <span v-if="lecture.email">{{ lecture.email }}</span>
                                    <span v-if="lecture.employee_id"> • {{ lecture.employee_id }}</span>
                                    <span v-if="lecture.academic_rank"> • {{ formatAcademicRank(lecture.academic_rank) }}</span>
                                </span>
                            </div>
                            <ComboboxItemIndicator>
                                <Check class="ml-2 h-4 w-4" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>

                        <div v-if="matchingLectures.length > visibleLectures.length" class="border-t p-2 text-center">
                            <span class="text-muted-foreground text-xs">Showing first 20 results. Refine your search for more specific results.</span>
                        </div>
                    </ComboboxGroup>
                </ComboboxViewport>
            </ComboboxList>
        </Combobox>

        <p v-if="errorMessage" class="text-destructive text-sm">{{ errorMessage }}</p>
    </div>
</template>
