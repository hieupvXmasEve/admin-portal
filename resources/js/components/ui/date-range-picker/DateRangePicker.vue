<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RangeCalendar } from '@/components/ui/range-calendar';
import { CalendarIcon } from 'lucide-vue-next';
import { DateFormatter, CalendarDate, parseDate } from '@internationalized/date';

interface DateRange {
    start: string | null; // Use ISO date strings (YYYY-MM-DD)
    end: string | null;
}

interface Props {
    modelValue?: DateRange;
    placeholder?: string;
    disabled?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select date range',
    disabled: false
});

const emit = defineEmits<{
    'update:modelValue': [value: DateRange];
}>();

const open = ref(false);

// Convert string dates to CalendarDate objects for reka-ui
const dateRange = computed({
    get() {
        if (!props.modelValue?.start || !props.modelValue?.end) {
            return undefined;
        }

        try {
            return {
                start: parseDate(props.modelValue.start),
                end: parseDate(props.modelValue.end)
            };
        } catch (error) {
            console.warn('Failed to parse dates:', props.modelValue, error);
            return undefined;
        }
    },
    set(value: any) {
        if (!value || !value.start || !value.end) {
            emit('update:modelValue', { start: null, end: null });
            return;
        }

        try {
            // Convert CalendarDate back to ISO string format
            const start = value.start.toString();
            const end = value.end.toString();

            emit('update:modelValue', { start, end });
            open.value = false;
        } catch (error) {
            console.warn('Failed to convert dates:', value, error);
        }
    }
});

const df = new DateFormatter('en-US', {
    dateStyle: 'medium',
    timeZone: 'Asia/Ho_Chi_Minh'
});

const displayValue = computed(() => {
    if (!props.modelValue?.start || !props.modelValue?.end) {
        return props.placeholder;
    }

    try {
        const startDate = parseDate(props.modelValue.start).toDate('UTC');
        const endDate = parseDate(props.modelValue.end).toDate('UTC');
        return `${df.format(startDate)} - ${df.format(endDate)}`;
    } catch {
        return props.placeholder;
    }
});
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                :class="[
                    'w-full justify-start text-left font-normal',
                    !modelValue?.start && 'text-muted-foreground',
                    props.class,
                ]"
                :disabled="disabled"
            >
                <CalendarIcon class="mr-2 h-4 w-4" />
                {{ displayValue }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0" align="start">
            <RangeCalendar
                v-model="dateRange"
                :locale="'en-US'"
                :number-of-months="2"
                class="rounded-md border"
            />
        </PopoverContent>
    </Popover>
</template>
