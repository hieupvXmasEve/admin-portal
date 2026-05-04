<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar } from '@/components/ui/calendar';
import { CalendarIcon } from 'lucide-vue-next';
import { DateFormatter, parseDate } from '@internationalized/date';

interface Props {
    modelValue?: string; // Use ISO date strings (YYYY-MM-DD)
    placeholder?: string;
    disabled?: boolean;
    class?: string;
    /** Pass a portal target to avoid focus-trap conflicts (e.g. inside @inertiaui/modal-vue). */
    portalTo?: string | HTMLElement;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select date',
    disabled: false
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const open = ref(false);

// Convert string date to CalendarDate object for reka-ui
const selectedDate = computed({
    get() {
        if (!props.modelValue) {
            return undefined;
        }

        try {
            return parseDate(props.modelValue);
        } catch (error) {
            console.warn('Failed to parse date:', props.modelValue, error);
            return undefined;
        }
    },
    set(value: any) {
        if (!value) {
            emit('update:modelValue', '');
            return;
        }

        try {
            // Convert CalendarDate back to ISO string format
            const dateString = value.toString();
            emit('update:modelValue', dateString);
            open.value = false;
        } catch (error) {
            console.warn('Failed to convert date:', value, error);
        }
    }
});

const df = new DateFormatter('en-US', {
    dateStyle: 'medium',
    timeZone: 'Asia/Ho_Chi_Minh'
});

const displayValue = computed(() => {
    if (!props.modelValue) {
        return props.placeholder;
    }

    try {
        const date = parseDate(props.modelValue).toDate('UTC');
        return df.format(date);
    } catch {
        return props.placeholder;
    }
});

const clearDate = () => {
    emit('update:modelValue', '');
    open.value = false;
};
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                :class="[
                    'w-full justify-start text-left font-normal',
                    !modelValue && 'text-muted-foreground',
                    props.class,
                ]"
                :disabled="disabled"
            >
                <CalendarIcon class="mr-2 h-4 w-4" />
                {{ displayValue }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0" align="start" :to="portalTo">
            <Calendar
                v-model="selectedDate"
                :locale="'en-US'"
                class="rounded-md border"
            />
            <div class="border-t p-2">
                <Button v-if="modelValue" type="button" variant="ghost" class="w-full" @click="clearDate">Clear date</Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
