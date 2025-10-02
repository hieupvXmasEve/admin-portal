<script setup lang="ts">
import type { GoldTransaction } from '@/types/wallet';
import { ref } from 'vue';

interface Props {
    transactions?: GoldTransaction[];
    loading?: boolean;
    error?: string | null;
    showShowMore?: boolean;
}

withDefaults(defineProps<Props>(), {
    transactions: () => [],
    loading: false,
    error: null,
    showShowMore: false,
});

const emit = defineEmits<{
    'filter-change': [filter: string];
    'show-more': [];
}>();

const selectedFilter = ref('');

const onFilterChange = () => {
    emit('filter-change', selectedFilter.value);
};
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white">
        <div class="border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900">Transaction History</h3>

                <!-- Filter dropdown -->
                <div class="flex items-center space-x-2">
                    <label for="transaction-filter" class="text-sm font-medium text-gray-700">Filter:</label>
                    <select id="transaction-filter" v-model="selectedFilter" @change="onFilterChange" class="rounded-md border border-gray-300 px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        <option value="">All Transactions</option>
                        <option value="earn">Earned</option>
                        <option value="spend">Spent</option>
                        <option value="adjust">Adjustments</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="divide-y divide-gray-200">
            <!-- Loading state -->
            <div v-if="loading" class="flex justify-center py-8">
                <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
            </div>

            <!-- Error state -->
            <div v-else-if="error" class="p-6 text-center">
                <p class="text-red-600">{{ error }}</p>
            </div>

            <!-- Empty state -->
            <div v-else-if="!transactions?.length" class="p-6 text-center">
                <div class="mb-2 text-gray-400">
                    <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                        />
                    </svg>
                </div>
                <p class="text-gray-500">No transactions found</p>
            </div>

            <!-- Transaction list -->
            <div v-else class="max-h-96 overflow-y-auto">
                <div v-for="transaction in transactions" :key="transaction.id" class="px-6 py-4 transition-colors duration-200 hover:bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <!-- Transaction type icon -->
                            <div class="flex-shrink-0">
                                <div :class="['flex h-8 w-8 items-center justify-center rounded-full', transaction.type === 'earn' ? 'bg-green-100' : transaction.type === 'spend' ? 'bg-red-100' : 'bg-blue-100']">
                                    <!-- Earn icon -->
                                    <svg v-if="transaction.type === 'earn'" class="h-4 w-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z" clip-rule="evenodd" />
                                    </svg>
                                    <!-- Spend icon -->
                                    <svg v-else-if="transaction.type === 'spend'" class="h-4 w-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z" clip-rule="evenodd" />
                                    </svg>
                                    <!-- Adjust icon -->
                                    <svg v-else class="h-4 w-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            fill-rule="evenodd"
                                            d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </div>
                            </div>

                            <!-- Transaction details -->
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-900">{{ transaction.type_label }} - {{ transaction.source_type_label }}</p>
                                <p v-if="transaction.notes" class="truncate text-xs text-gray-500">
                                    {{ transaction.notes }}
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ transaction.time_ago }}
                                </p>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="text-right">
                            <p :class="['text-sm font-semibold', transaction.is_positive ? 'text-green-600' : 'text-red-600']">
                                {{ transaction.display_amount }}
                            </p>
                            <p class="text-xs text-gray-500">
                                {{ transaction.formatted_created_at }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Show more button -->
            <div v-if="showShowMore" class="border-t border-gray-200 bg-gray-50 px-6 py-3">
                <button @click="$emit('show-more')" class="w-full text-center text-sm font-medium text-blue-600 transition-colors hover:text-blue-500">Show More Transactions</button>
            </div>
        </div>
    </div>
</template>
