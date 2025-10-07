<script setup lang="ts">
import type { GoldStats, StudentWallet } from '@/types/wallet';

interface Props {
  wallet?: StudentWallet | null
  stats?: GoldStats | null
  loading?: boolean
  error?: string | null
}

withDefaults(defineProps<Props>(), {
  wallet: null,
  stats: null,
  loading: false,
  error: null,
})

const formatAmount = (amount: number): string => {
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount)
}
</script>

<template>
  <div class="bg-white rounded-lg border border-gray-200 p-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-sm font-medium text-gray-900">Gold Balance</h3>
        <div class="mt-2 flex items-baseline">
          <p class="text-2xl font-semibold text-gray-900">
            {{ wallet?.balance || '0.00' }}
          </p>
          <span class="ml-1 text-sm text-gray-500">Gold</span>
        </div>
        <p class="text-xs text-gray-500 mt-1">
          Last updated {{ wallet?.formatted_updated_at }}
        </p>
      </div>
      <div class="flex h-12 w-12 items-center justify-center rounded-full bg-yellow-50">
        <svg class="h-6 w-6 text-yellow-600" fill="currentColor" viewBox="0 0 24 24">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
        </svg>
      </div>
    </div>

    <!-- Quick stats -->
    <div v-if="stats" class="mt-6 grid grid-cols-3 gap-4">
      <div class="text-center">
        <p class="text-lg font-semibold text-green-600">
          {{ formatAmount(stats.total_earned) }}
        </p>
        <p class="text-xs text-gray-500">Total Earned</p>
      </div>
      <div class="text-center">
        <p class="text-lg font-semibold text-red-600">
          {{ formatAmount(stats.total_spent) }}
        </p>
        <p class="text-xs text-gray-500">Total Spent</p>
      </div>
      <div class="text-center">
        <p class="text-lg font-semibold text-gray-600">
          {{ stats.transaction_count }}
        </p>
        <p class="text-xs text-gray-500">Transactions</p>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="flex justify-center py-4">
      <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-yellow-600"></div>
    </div>

    <!-- Error state -->
    <div v-if="error" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
      <p class="text-sm text-red-600">{{ error }}</p>
    </div>
  </div>
</template>
