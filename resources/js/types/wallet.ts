export interface StudentWallet {
  id: number
  student_id: number
  balance: string
  balance_raw: number
  updated_at: string
  formatted_updated_at: string
  student?: {
    id: number
    student_id: string
    full_name: string
    email: string
  }
  recent_transactions?: WalletTransaction[]
}

export interface WalletTransaction {
  id: number
  student_id: number
  amount: string
  amount_raw: number
  absolute_amount: string
  type: 'earn' | 'spend' | 'adjust'
  type_label: string
  source_type: 'event' | 'reward' | 'manual'
  source_type_label: string
  source_id: number | null
  notes: string | null
  is_positive: boolean
  is_negative: boolean
  created_at: string
  formatted_created_at: string
  time_ago: string
  student?: {
    id: number
    student_id: string
    full_name: string
    email: string
  }
  display_amount: string
  amount_class: string
  type_color: 'green' | 'red' | 'blue' | 'gray'
}

export interface WalletStats {
  total_earned: number
  total_spent: number
  total_adjustments: number
  transaction_count: number
  current_balance: string
}

export interface WalletSummary {
  wallet: StudentWallet
  stats: WalletStats
  recent_transactions: WalletTransaction[]
}

export interface WalletBalanceCheckResponse {
  has_sufficient_balance: boolean
  current_balance: string
  requested_amount: string
}

export interface WalletAdjustmentRequest {
  amount: number
  notes: string
}

export interface WalletAdjustmentResponse {
  message: string
  transaction: WalletTransaction
  wallet: StudentWallet
}

// Transaction filtering types
export interface TransactionFilters {
  type?: 'earn' | 'spend' | 'adjust'
  per_page?: number
  limit?: number
}

// Pagination for transactions
export interface TransactionPagination {
  data: WalletTransaction[]
  current_page: number
  per_page: number
  total: number
  last_page: number
  from: number
  to: number
  links: Array<{
    url: string | null
    label: string
    active: boolean
  }>
}

// Combined wallet data for academic summary tab
export interface StudentWalletTabData {
  summary: WalletSummary
  recent_transactions: WalletTransaction[]
  stats: WalletStats
}