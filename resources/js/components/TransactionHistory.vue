<script setup lang="ts">
import { computed } from 'vue'
import { TrendingUp, RotateCcw, FileText, CreditCard, AlertTriangle } from 'lucide-vue-next'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import DataPagination from '@/components/DataPagination.vue'
import type { WalletTransaction, PaginatedTransactions } from '@/types/wallet'

interface Props {
  transactions: PaginatedTransactions
  title?: string
  description?: string
  showPagination?: boolean
  showRunningBalance?: boolean
  compact?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Transaction History',
  description: 'Recent wallet transactions and balance changes.',
  showPagination: true,
  showRunningBalance: true,
  compact: false
})

const emit = defineEmits<{
  navigate: [url: string]
}>()

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('vi-VN').format(amount) + ' VND'
}

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleString('vi-VN')
}

const getTransactionIcon = (type: string) => {
  switch (type) {
    case 'deposit':
      return TrendingUp
    case 'payment':
      return CreditCard
    case 'refund':
      return RotateCcw
    case 'adjustment':
      return AlertTriangle
    default:
      return FileText
  }
}

const getTransactionIconClass = (type: string): string => {
  switch (type) {
    case 'deposit':
      return 'text-green-600'
    case 'payment':
      return 'text-red-600'
    case 'refund':
      return 'text-blue-600'
    case 'adjustment':
      return 'text-yellow-600'
    default:
      return 'text-gray-600'
  }
}

const getTransactionTypeLabel = (type: string): string => {
  switch (type) {
    case 'deposit':
      return 'Deposit'
    case 'payment':
      return 'Payment'
    case 'refund':
      return 'Refund'
    case 'adjustment':
      return 'Adjustment'
    default:
      return type
  }
}

const getAmountClass = (type: string): string => {
  switch (type) {
    case 'deposit':
    case 'refund':
      return 'text-green-600'
    case 'payment':
      return 'text-red-600'
    case 'adjustment':
      return 'text-yellow-600'
    default:
      return 'text-gray-600'
  }
}

const getAmountSign = (type: string): string => {
  switch (type) {
    case 'deposit':
    case 'refund':
      return '+'
    case 'payment':
      return '-'
    case 'adjustment':
      return '±'
    default:
      return ''
  }
}

// Calculate running balance for display
const transactionsWithRunningBalance = computed(() => {
  if (!props.showRunningBalance) {
    return props.transactions.data
  }

  return props.transactions.data.map((transaction) => {
    // The balance_after from the transaction record is the actual running balance
    return {
      ...transaction,
      running_balance: transaction.balance_after,
      formatted_running_balance: formatCurrency(transaction.balance_after)
    }
  })
})

type TransactionWithRunningBalance = WalletTransaction & {
  running_balance: number
  formatted_running_balance: string
}

const handlePaginationNavigate = (url: string) => {
  emit('navigate', url)
}

const getReferenceDisplay = (transaction: WalletTransaction): string => {
  if (transaction.reference_type && transaction.reference_id) {
    switch (transaction.reference_type) {
      case 'invoice':
        return `Invoice #${transaction.reference_id}`
      case 'enrollment':
        return `Enrollment #${transaction.reference_id}`
      default:
        return `${transaction.reference_type} #${transaction.reference_id}`
    }
  }
  return ''
}
</script>

<template>
  <Card>
    <CardHeader v-if="!compact">
      <CardTitle>{{ title }}</CardTitle>
      <CardDescription>{{ description }}</CardDescription>
    </CardHeader>
    <CardContent :class="compact ? 'p-0' : 'p-0'">
      <div v-if="transactions.data.length === 0" class="px-4 py-8 text-center text-gray-500">
        No transactions found.
      </div>
      <div v-else>
        <!-- Desktop Table View -->
        <div class="hidden md:block">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Transaction
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Amount
                </th>
                <th v-if="showRunningBalance" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Running Balance
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Date
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Reference
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="transaction in (transactionsWithRunningBalance as TransactionWithRunningBalance[])" :key="transaction.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0">
                      <component
                        :is="getTransactionIcon(transaction.transaction_type)"
                        :class="getTransactionIconClass(transaction.transaction_type)"
                        class="h-5 w-5"
                      />
                    </div>
                    <div class="ml-3">
                      <div class="text-sm font-medium text-gray-900">
                        {{ getTransactionTypeLabel(transaction.transaction_type) }}
                      </div>
                      <div class="text-sm text-gray-500">
                        {{ transaction.description }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div :class="getAmountClass(transaction.transaction_type)" class="text-sm font-medium">
                    {{ getAmountSign(transaction.transaction_type) }}{{ formatCurrency(transaction.amount) }}
                  </div>
                </td>
                <td v-if="showRunningBalance" class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">
                    {{ transaction.formatted_running_balance }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">
                    {{ formatDate(transaction.created_at) }}
                  </div>
                  <div v-if="transaction.created_by" class="text-xs text-gray-500">
                    by {{ transaction.created_by.name }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-500">
                    {{ getReferenceDisplay(transaction) }}
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile List View -->
        <ul class="md:hidden divide-y divide-gray-200">
          <li v-for="transaction in (transactionsWithRunningBalance as TransactionWithRunningBalance[])" :key="transaction.id" class="px-4 py-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center min-w-0 flex-1">
                <div class="flex-shrink-0">
                  <component
                    :is="getTransactionIcon(transaction.transaction_type)"
                    :class="getTransactionIconClass(transaction.transaction_type)"
                    class="h-6 w-6"
                  />
                </div>
                <div class="ml-4 min-w-0 flex-1">
                  <div class="text-sm font-medium text-gray-900 truncate">
                    {{ getTransactionTypeLabel(transaction.transaction_type) }}
                  </div>
                  <div class="text-sm text-gray-500 truncate">
                    {{ transaction.description }}
                  </div>
                  <div class="text-xs text-gray-400">
                    {{ formatDate(transaction.created_at) }}
                    <span v-if="transaction.created_by">
                      by {{ transaction.created_by.name }}
                    </span>
                  </div>
                  <div v-if="getReferenceDisplay(transaction)" class="text-xs text-gray-400">
                    {{ getReferenceDisplay(transaction) }}
                  </div>
                </div>
              </div>
              <div class="text-right flex-shrink-0">
                <div :class="getAmountClass(transaction.transaction_type)" class="text-sm font-medium">
                  {{ getAmountSign(transaction.transaction_type) }}{{ formatCurrency(transaction.amount) }}
                </div>
                <div v-if="showRunningBalance" class="text-xs text-gray-500">
                  Balance: {{ transaction.formatted_running_balance }}
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>

      <!-- Pagination -->
      <div v-if="showPagination && transactions.last_page > 1" class="px-4 py-3 border-t border-gray-200">
        <DataPagination
          :pagination-data="transactions"
          item-name="transactions"
          :show-page-size-selector="false"
          @navigate="handlePaginationNavigate"
        />
      </div>
    </CardContent>
  </Card>
</template>
