<script setup lang="ts">
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';

const page = usePage<SharedData>();
const semester = computed(() => page.props.semester ?? null);

const selected = computed<string>({
    get: () => (semester.value?.selected_id != null ? String(semester.value.selected_id) : 'all'),
    set: (value: string) => {
        router.post(route('semester-context.update'), { semester_id: value === 'all' ? null : Number(value) }, { preserveScroll: true, preserveState: false });
    },
});
</script>

<template>
    <Select v-if="semester" v-model="selected">
        <SelectTrigger class="h-8 w-44 text-sm">
            <SelectValue placeholder="Kỳ học" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem value="all">Tất cả kỳ</SelectItem>
            <SelectItem v-for="option in semester.options" :key="option.id" :value="String(option.id)"> {{ option.name }}{{ option.is_active ? ' ·  hiện tại' : '' }} </SelectItem>
        </SelectContent>
    </Select>
</template>
