# Student Portal - Cash Wallet API Documentation

## Overview

APIs for managing cash wallet, viewing invoices, and transaction history in the Student Portal.

**Base URL**: `/api/v1`  
**Authentication**: Bearer Token (Student)  
**Content-Type**: `application/json`

---

## TypeScript Types

```typescript
// Transaction Types
type TransactionType = 'deposit' | 'payment' | 'refund' | 'adjustment';

// Invoice Status
type InvoiceStatus = 'pending' | 'partial' | 'paid' | 'overdue' | 'cancelled';

// Invoice Item Type
type InvoiceItemType = 'tuition' | 'retake_fee' | 'registration_fee' | 'exam_fee' | 'other';

// Discount Type
type DiscountType = 'percentage' | 'fixed' | 'scholarship';

// Cash Wallet
interface StudentCashWallet {
    id: number;
    student_id: number;
    balance: number;
    currency: string;
    formatted_balance: string;
    created_at: string;
    updated_at: string;
}

// Wallet Transaction
interface WalletTransaction {
    id: number;
    transaction_type: TransactionType;
    amount: number;
    formatted_amount: string;
    balance_before: number;
    balance_after: number;
    formatted_balance_after: string;
    description: string;
    reference_type: string | null;
    reference_id: number | null;
    created_by?: {
        id: number;
        name: string;
    };
    created_at: string;
    updated_at: string;
}

// Wallet Stats
interface WalletStats {
    current_balance: number;
    total_deposits: number;
    total_payments: number;
    total_refunds: number;
    transaction_count: number;
    last_transaction_date: string | null;
}

// Invoice Item
interface InvoiceItem {
    id: number;
    item_type: InvoiceItemType;
    description: string;
    quantity: number;
    unit_price: number;
    total_price: number;
    paid_amount: number;
    remaining_amount: number;
    is_fully_paid: boolean;
    is_partially_paid: boolean;
}

// Invoice Discount
interface InvoiceDiscount {
    id: number;
    type: DiscountType;
    description: string;
    amount: number;
}

// Student Invoice
interface StudentInvoice {
    id: number;
    invoice_number: string;
    subtotal: number;
    discount_total: number;
    total_amount: number;
    paid_amount: number;
    outstanding_balance: number;
    status: InvoiceStatus;
    due_date: string; // YYYY-MM-DD
    formatted_due_date: string; // dd/mm/yyyy
    paid_at: string | null;
    is_paid: boolean;
    is_overdue: boolean;
    semester?: {
        id: number;
        code: string;
        name: string;
    };
    billing_cycle?: {
        id: number;
        name: string;
        start_date: string;
        end_date: string;
    };
    items?: InvoiceItem[];
    discounts?: InvoiceDiscount[];
    created_at: string;
    updated_at: string;
}

// Wallet Summary
interface WalletSummary {
    wallet: StudentCashWallet;
    stats: WalletStats;
    tuition_plan: TuitionPlan | null;
}

// API Response
interface ApiResponse<T = any> {
    success: boolean;
    message: string;
    data?: T;
    meta?: {
        page: number;
        per_page: number;
        total: number;
        total_pages: number;
    };
}
```

---

## Endpoints

### 1. Get Wallet Information

**GET** `/cash-wallet`

Get current student's cash wallet information.

**Response:**
```typescript
ApiResponse<StudentCashWallet>
```

**Example:**
```typescript
const { data } = await api.get('/cash-wallet');
// data.wallet contains wallet info with balance
```

---

### 2. Get Wallet Summary

**GET** `/cash-wallet/summary`

Get comprehensive wallet data including balance, statistics, and tuition plan.

**Response:**
```typescript
ApiResponse<WalletSummary>
```

**Example:**
```json
{
    "success": true,
    "data": {
        "wallet": {
            "id": 123,
            "balance": 15000000,
            "currency": "VND",
            "formatted_balance": "15,000,000 VND"
        },
        "stats": {
            "current_balance": 15000000,
            "total_deposits": 20000000,
            "total_payments": 5000000,
            "total_refunds": 0,
            "transaction_count": 15
        },
        "tuition_plan": {
            "id": 5,
            "total_amount": 280000000,
            "currency": "VND"
        }
    }
}
```

---

### 3. Get Transaction History

**GET** `/cash-wallet/transactions`

Get paginated wallet transaction history.

**Query Parameters:**
- `per_page` (number, optional, default: 20, max: 100) - Items per page

**Response:**
```typescript
ApiResponse<WalletTransaction[]> & {
    meta: {
        page: number;
        per_page: number;
        total: number;
        total_pages: number;
    }
}
```

**Example:**
```typescript
const { data, meta } = await api.get('/cash-wallet/transactions', {
    per_page: 20
});
```

---

### 4. Get Invoices

**GET** `/cash-wallet/invoices`

Get paginated list of student invoices with filters.

**Query Parameters:**
- `status` (string, optional) - Filter by status: `pending`, `partial`, `paid`, `overdue`, `cancelled`
- `semester_id` (number, optional) - Filter by semester
- `per_page` (number, optional, default: 20, max: 100)

**Response:**
```typescript
ApiResponse<StudentInvoice[]> & {
    meta: {
        page: number;
        per_page: number;
        total: number;
        total_pages: number;
    }
}
```

**Example:**
```typescript
const { data } = await api.get('/cash-wallet/invoices', {
    status: 'pending',
    per_page: 20
});
```

---

### 5. Get Invoice Detail

**GET** `/cash-wallet/invoices/{invoice}`

Get detailed information for a specific invoice including items and discounts.

**Authorization:** Student can only view their own invoices

**Response:**
```typescript
ApiResponse<StudentInvoice>
```

**Example:**
```json
{
    "success": true,
    "data": {
        "id": 45,
        "invoice_number": "INV-2024-001",
        "subtotal": 75000000,
        "discount_total": 5000000,
        "total_amount": 70000000,
        "paid_amount": 50000000,
        "outstanding_balance": 20000000,
        "status": "partial",
        "due_date": "2024-12-31",
        "formatted_due_date": "31/12/2024",
        "is_paid": false,
        "is_overdue": false,
        "semester": {
            "id": 10,
            "code": "SEM1-2024",
            "name": "Semester 1 2024"
        },
        "items": [
            {
                "id": 100,
                "item_type": "tuition",
                "description": "Tuition Fee - Term 1",
                "quantity": 1,
                "unit_price": 70000000,
                "total_price": 70000000,
                "paid_amount": 50000000,
                "remaining_amount": 20000000,
                "is_fully_paid": false,
                "is_partially_paid": true
            }
        ],
        "discounts": [
            {
                "id": 20,
                "type": "scholarship",
                "description": "Merit Scholarship 2024",
                "amount": 5000000
            }
        ]
    }
}
```

---

## Error Responses

All endpoints may return error responses:

```typescript
// 400 Bad Request
{
    "success": false,
    "message": "Invalid request parameters",
    "errors": [
        {
            "code": "VALIDATION_ERROR",
            "field": "per_page",
            "detail": "Must be between 1 and 100"
        }
    ]
}

// 401 Unauthorized
{
    "success": false,
    "message": "Unauthenticated"
}

// 403 Forbidden
{
    "success": false,
    "message": "You are not authorized to view this invoice"
}

// 404 Not Found
{
    "success": false,
    "message": "Invoice not found"
}

// 500 Server Error
{
    "success": false,
    "message": "Failed to retrieve wallet information"
}
```


## Notes

- All monetary amounts are in the wallet's currency (typically VND)
- Balance cannot go negative - payments will fail if insufficient balance
- Transactions are immutable once created
- Invoice outstanding balance = total_amount - paid_amount
- Invoices are auto-marked as "overdue" when past due_date and not fully paid
- Students can only access their own wallet and invoices
- All datetime strings are in ISO 8601 format
- Formatted amounts use Vietnamese number format (e.g., "15,000,000 VND")
