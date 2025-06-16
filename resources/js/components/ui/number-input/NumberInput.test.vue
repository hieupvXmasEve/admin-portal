<script setup lang="ts">
import { ref } from 'vue';
import { NumberInput } from './index';

// Test component to verify NumberInput functionality
const testValue = ref<number | null>(0);
const testValue2 = ref<number | null>(12.5);
const testValue3 = ref<number | null>(100);

const handleUpdate = (value: number | null) => {
    console.log('Value updated:', value);
};
</script>

<template>
    <div class="p-8 space-y-6">
        <h1 class="text-2xl font-bold">NumberInput Component Test</h1>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-2">Integer Input (Min: 0, Max: 100)</label>
                <NumberInput
                    v-model="testValue"
                    :min="0"
                    :max="100"
                    :allow-decimal="false"
                    :allow-negative="false"
                    placeholder="Enter integer"
                    @update:model-value="handleUpdate"
                />
                <p class="text-sm text-gray-600 mt-1">Current value: {{ testValue }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Decimal Input (Step: 0.5, Precision: 2)</label>
                <NumberInput
                    v-model="testValue2"
                    :min="0"
                    :max="20"
                    :step="0.5"
                    :precision="2"
                    :allow-decimal="true"
                    :allow-negative="false"
                    placeholder="0.00"
                />
                <p class="text-sm text-gray-600 mt-1">Current value: {{ testValue2 }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium mb-2">Currency Input (Min: 0, Step: 0.01)</label>
                <NumberInput
                    v-model="testValue3"
                    :min="0"
                    :step="0.01"
                    :precision="2"
                    :allow-decimal="true"
                    :allow-negative="false"
                    placeholder="0.00"
                />
                <p class="text-sm text-gray-600 mt-1">Current value: {{ testValue3 }}</p>
            </div>
        </div>
    </div>
</template>
