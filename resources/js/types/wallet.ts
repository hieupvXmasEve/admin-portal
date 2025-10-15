export interface StudentWallet {
    id: number;
    student_id: number;
    balance: string;
    balance_raw: number;
    updated_at: string;
    formatted_updated_at: string;
    student?: {
        id: number;
        student_id: string;
        full_name: string;
        email: string;
    };
    recent_transactions?: GoldTransaction[];
}

export interface GoldTransaction {
    id: number;
    student_id: number;
    amount: string;
    amount_raw: number;
    absolute_amount: string;
    type: 'earn' | 'spend' | 'adjust';
    type_label: string;
    source_type: 'event' | 'reward' | 'manual';
    source_type_label: string;
    source_id: number | null;
    notes: string | null;
    is_positive: boolean;
    is_negative: boolean;
    created_at: string;
    formatted_created_at: string;
    time_ago: string;
    student?: {
        id: number;
        student_id: string;
        full_name: string;
        email: string;
    };
    display_amount: string;
    amount_class: string;
    type_color: 'green' | 'red' | 'blue' | 'gray';
}

export interface GoldStats {
    total_earned: number;
    total_spent: number;
    total_adjustments: number;
    transaction_count: number;
    current_balance: string;
}

export interface WalletSummary {
    wallet: StudentWallet;
    stats: WalletStats;
    recent_transactions: GoldTransaction[];
}

export interface WalletBalanceCheckResponse {
    has_sufficient_balance: boolean;
    current_balance: string;
    requested_amount: string;
}

export interface WalletAdjustmentRequest {
    amount: number;
    notes: string;
}

export interface WalletAdjustmentResponse {
    message: string;
    transaction: GoldTransaction;
    wallet: StudentWallet;
}

// Transaction filtering types
export interface TransactionFilters {
    type?: 'earn' | 'spend' | 'adjust';
    per_page?: number;
    limit?: number;
}

// Pagination for transactions
export interface TransactionPagination {
    data: GoldTransaction[];
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number;
    to: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

// Combined wallet data for academic summary tab
export interface StudentGoldTabData {
    summary: WalletSummary;
    recent_transactions: GoldTransaction[];
    stats: WalletStats;
}


export interface WalletTransaction {
  id: number
  transaction_type: 'deposit' | 'payment' | 'refund' | 'adjustment'
  amount: number
  formatted_amount: string
  balance_before: number
  balance_after: number
  formatted_balance_after: string
  description: string
  created_at: string
  updated_at: string
  created_by?: {
    id: number
    name: string
  }
  reference_type?: string
  reference_id?: number
}

export interface StudentCashWallet {
  id: number
  student_id: number
  balance: number
  currency: string
  formatted_balance: string
  created_at: string
  updated_at: string
}

export interface WalletStats {
  current_balance: number
  total_deposits: number
  total_payments: number
  total_refunds: number
  transaction_count: number
  last_transaction_date?: string
}

export interface PaginatedTransactions {
  data: WalletTransaction[]
  from: number
  to: number
  total: number
  current_page: number
  last_page: number
  per_page: number
  prev_page_url: string | null
  next_page_url: string | null
  links: any[]
}

export interface PaymentValidationResult {
  valid: boolean
  errors: string[]
  wallet?: StudentCashWallet
}

export interface PaymentProcessingResult {
  success: boolean
  errors: string[]
  transaction?: WalletTransaction
}

export interface TuitionPlanTerm {
  id: number
  term_number: number
  amount: number
  due_date?: string
  formatted_due_date?: string
  semester?: {
    id: number
    code: string
    name: string
    start_date?: string
    end_date?: string
  }
}

export interface TuitionPlan {
  id: number
  total_amount: number
  currency: string
  is_active: boolean
  curriculum_version?: {
    id: number
    version_code: string
    program?: {
      id: number
      name: string
      code: string
    }
    specialization?: {
      id: number
      name: string
      code: string
    }
  }
  intake_semester?: {
    id: number
    code: string
    name: string
    start_date?: string
    end_date?: string
  }
  terms: TuitionPlanTerm[]
}
