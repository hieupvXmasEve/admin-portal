<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    modelValue?: number | null;
    placeholder?: string;
    disabled?: boolean;
    readonly?: boolean;
    min?: number;
    max?: number;
    step?: number;
    precision?: number; // Number of decimal places
    allowNegative?: boolean;
    allowDecimal?: boolean;
    class?: string;
    id?: string;
    name?: string;
    required?: boolean;
}

interface Emits {
    'update:modelValue': [value: number | null];
    blur: [event: FocusEvent];
    focus: [event: FocusEvent];
    input: [event: Event];
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    placeholder: '',
    disabled: false,
    readonly: false,
    step: 1,
    precision: 2,
    allowNegative: true,
    allowDecimal: true,
    class: '',
    required: false,
});

const emit = defineEmits<Emits>();

const inputRef = ref<HTMLInputElement>();
const internalValue = ref<string>('');
const isFocused = ref(false);

// Format number for display
const formatNumber = (value: number | null): string => {
    if (value === null || value === undefined || isNaN(value)) {
        return '';
    }

    if (props.allowDecimal && props.precision > 0) {
        return value.toFixed(props.precision).replace(/\.?0+$/, '');
    }

    return Math.round(value).toString();
};

// Parse string to number
const parseNumber = (value: string): number | null => {
    if (!value || value.trim() === '') {
        return null;
    }

    // Remove any non-numeric characters except decimal point and minus sign
    let cleanValue = value.replace(/[^\d.-]/g, '');

    // Handle negative numbers
    if (!props.allowNegative) {
        cleanValue = cleanValue.replace(/-/g, '');
    }

    // Handle decimal numbers
    if (!props.allowDecimal) {
        cleanValue = cleanValue.split('.')[0];
    }

    const parsed = parseFloat(cleanValue);

    if (isNaN(parsed)) {
        return null;
    }

    // Apply min/max constraints
    let result = parsed;
    if (props.min !== undefined && result < props.min) {
        result = props.min;
    }
    if (props.max !== undefined && result > props.max) {
        result = props.max;
    }

    // Apply precision
    if (props.allowDecimal && props.precision > 0) {
        result = parseFloat(result.toFixed(props.precision));
    } else if (!props.allowDecimal) {
        result = Math.round(result);
    }

    return result;
};

// Watch for external model value changes
watch(() => props.modelValue, (newValue) => {
    if (!isFocused.value) {
        internalValue.value = '';
    }
}, { immediate: true });

// Computed display value
const displayValue = computed({
    get: () => {
        if (isFocused.value && internalValue.value !== '') {
            return internalValue.value;
        }
        return formatNumber(props.modelValue);
    },
    set: (value: string) => {
        internalValue.value = value;
    }
});

// Handle input events
const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    internalValue.value = target.value;
    emit('input', event);
};

const handleBlur = (event: FocusEvent) => {
    isFocused.value = false;
    const target = event.target as HTMLInputElement;
    const parsedValue = parseNumber(target.value);

    // Update the model value
    emit('update:modelValue', parsedValue);

    // Clear internal value to show formatted value
    internalValue.value = '';

    emit('blur', event);
};

const handleFocus = (event: FocusEvent) => {
    isFocused.value = true;
    // When focusing, show the raw value for editing
    if (props.modelValue !== null && props.modelValue !== undefined) {
        internalValue.value = props.modelValue.toString();
    }
    emit('focus', event);
};

// Validate input in real-time
const validateInput = (event: KeyboardEvent) => {
    const char = event.key;
    const currentValue = (event.target as HTMLInputElement).value;
    
    // Allow control keys
    if (['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(char)) {
        return;
    }
    
    // Allow copy/paste shortcuts
    if (event.ctrlKey || event.metaKey) {
        return;
    }
    
    // Check for valid numeric characters
    if (!/[\d]/.test(char)) {
        // Allow decimal point if decimals are allowed and not already present
        if (char === '.' && props.allowDecimal && !currentValue.includes('.')) {
            return;
        }
        
        // Allow minus sign if negative numbers are allowed and it's at the beginning
        if (char === '-' && props.allowNegative && currentValue.length === 0) {
            return;
        }
        
        // Block invalid characters
        event.preventDefault();
    }
};

// Computed classes
const inputClasses = computed(() => {
    return cn(
        'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
        props.class
    );
});

// Expose input ref for parent components
defineExpose({
    inputRef,
    focus: () => inputRef.value?.focus(),
    blur: () => inputRef.value?.blur(),
});
</script>

<template>
    <div class="relative">
        <input
            ref="inputRef"
            :id="id"
            :name="name"
            :value="displayValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :readonly="readonly"
            :required="required"
            :min="min"
            :max="max"
            :step="step"
            :class="inputClasses"
            type="text"
            inputmode="decimal"
            @input="handleInput"
            @blur="handleBlur"
            @focus="handleFocus"
            @keydown="validateInput"
        />
    </div>
</template>
