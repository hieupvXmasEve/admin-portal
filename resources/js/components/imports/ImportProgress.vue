<script setup lang="ts">
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { Check, CloudUpload, Info } from 'lucide-vue-next';

// Props
type Props = {
    isProcessing?: boolean;
    progress?: number;
    status?: string;
};

const props = withDefaults(defineProps<Props>(), {
    isProcessing: false,
    progress: 0,
    status: '',
});

// Emits
defineEmits(['cancel']);

// Processing steps
type ProcessingStep = {
    key: string;
    title: string;
    description: string;
};

const processingSteps: ProcessingStep[] = [
    {
        key: 'validate',
        title: 'Validating Data',
        description: 'Checking data format and requirements',
    },
    {
        key: 'process',
        title: 'Processing Records',
        description: 'Creating and updating student applications',
    },
    {
        key: 'finalize',
        title: 'Finalizing Import',
        description: 'Generating summary and cleaning up',
    },
];

// Methods
const getStepStatus = (stepIndex: number): 'completed' | 'current' | 'pending' => {
    const progressPerStep = 100 / processingSteps.length;
    const stepProgress = (stepIndex + 1) * progressPerStep;

    if (props.progress >= stepProgress) {
        return 'completed';
    } else if (props.progress >= stepIndex * progressPerStep) {
        return 'current';
    } else {
        return 'pending';
    }
};
</script>
<template>
    <div class="py-8 text-center">
        <!-- Progress Icon -->
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-100">
            <CloudUpload v-if="!isProcessing" class="h-8 w-8 text-blue-600" />
            <LoadingSpinner v-else size="lg" class="text-blue-600" />
        </div>

        <!-- Status Title -->
        <h3 class="mt-4 text-lg font-medium text-gray-900">
            {{ isProcessing ? 'Processing Import' : 'Ready to Import' }}
        </h3>

        <!-- Status Message -->
        <p class="mt-2 text-sm text-gray-600">
            {{ status || 'Please wait while we process your file...' }}
        </p>

        <!-- Progress Bar -->
        <div class="mt-6 w-full">
            <div class="mb-2 flex items-center justify-between text-sm text-gray-600">
                <span>Progress</span>
                <span>{{ Math.round(progress) }}%</span>
            </div>
            <div class="overflow-hidden rounded-full bg-gray-200">
                <div class="h-2 rounded-full bg-blue-600 transition-all duration-300 ease-out" :style="{ width: `${progress}%` }" />
            </div>
        </div>

        <!-- Processing Steps -->
        <div class="mt-8 space-y-4">
            <div v-for="(step, index) in processingSteps" :key="step.key" class="flex items-center space-x-3">
                <!-- Step Icon -->
                <div :class="['flex h-8 w-8 items-center justify-center rounded-full', getStepStatus(index) === 'completed' ? 'bg-green-100' : getStepStatus(index) === 'current' ? 'bg-blue-100' : 'bg-gray-100']">
                    <Check v-if="getStepStatus(index) === 'completed'" class="h-5 w-5 text-green-600" />
                    <LoadingSpinner v-else-if="getStepStatus(index) === 'current'" size="sm" class="text-blue-600" />
                    <span v-else :class="['text-sm font-medium', getStepStatus(index) === 'pending' ? 'text-gray-400' : 'text-gray-600']">
                        {{ index + 1 }}
                    </span>
                </div>

                <!-- Step Text -->
                <div class="flex-1 text-left">
                    <p :class="['text-sm font-medium', getStepStatus(index) === 'completed' ? 'text-green-900' : getStepStatus(index) === 'current' ? 'text-blue-900' : 'text-gray-500']">
                        {{ step.title }}
                    </p>
                    <p v-if="step.description" class="text-xs text-gray-500">
                        {{ step.description }}
                    </p>
                </div>

                <!-- Step Status -->
                <div class="flex-shrink-0">
                    <span v-if="getStepStatus(index) === 'completed'" class="text-xs font-medium text-green-600"> Completed </span>
                    <span v-else-if="getStepStatus(index) === 'current'" class="text-xs font-medium text-blue-600"> Processing... </span>
                </div>
            </div>
        </div>

        <!-- Processing Details -->
        <div v-if="isProcessing" class="mt-8 rounded-lg bg-blue-50 p-4">
            <div class="flex">
                <Info class="h-5 w-5 text-blue-400" />
                <div class="ml-3 text-left">
                    <h4 class="text-sm font-medium text-blue-800">Processing Details</h4>
                    <div class="mt-2 text-sm text-blue-700">
                        <ul class="space-y-1">
                            <li>• Validating data integrity</li>
                            <li>• Checking for duplicates</li>
                            <li>• Creating/updating records</li>
                            <li>• Generating import summary</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cancel Button (if needed) -->
        <div v-if="isProcessing" class="mt-6">
            <button @click="$emit('cancel')" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50">Cancel Import</button>
        </div>
    </div>
</template>
