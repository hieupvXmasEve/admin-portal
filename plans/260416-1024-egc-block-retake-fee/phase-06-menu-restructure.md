---
phase: 6
title: Menu Restructure — Fee Section
status: pending
---

# Phase 6: Menu Restructure

## Overview

Tái cấu trúc menu Fee trong `menu-sidebar.ts` để phản ánh sự tách biệt EGC vs Major.
**Thực hiện sau cùng** khi tất cả pages và routes đã tồn tại.

---

## 6.1 Cấu trúc menu mới

**File:** `resources/js/constants/menu-sidebar.ts`

Thay thế block `Fee` hiện tại (lines 494–618):

```ts
{
    title: 'Fee',
    href: '#',
    icon: DollarSign,
    children: [
        // ─── EGC Students ────────────────────────────────────
        {
            title: 'EGC Students',
            href: '#',
            icon: GraduationCap,
            children: [
                {
                    title: 'EGC Dashboard',
                    href: '/finance/operations/egc-dashboard',
                    icon: LayoutDashboard,
                    requiredPermissions: ['view_finance_operations_dashboard'],
                },
                {
                    title: 'Generate EGC Charges',
                    href: '/finance/operations/generate-egc-charges',
                    icon: Play,
                    requiredPermissions: ['view_finance_operations_generate_charges'],
                },
                {
                    title: 'EGC Retake Processing',
                    href: '/finance/operations/egc-retake',
                    icon: RefreshCw,
                    requiredPermissions: ['view_finance_operations_generate_charges'],
                },
            ],
        },

        // ─── Major Students ──────────────────────────────────
        {
            title: 'Major Students',
            href: '#',
            icon: BookOpen,
            children: [
                {
                    title: 'Billing Dashboard',
                    href: '/finance/operations/dashboard',
                    icon: LayoutDashboard,
                    requiredPermissions: ['view_finance_operations_dashboard'],
                },
                {
                    title: 'Generate Major Charges',
                    href: '/finance/operations/generate-charges',
                    icon: Play,
                    requiredPermissions: ['view_finance_operations_generate_charges'],
                },
            ],
        },

        // ─── Finance Operations (Shared) ─────────────────────
        {
            title: 'Finance Operations',
            href: '#',
            icon: BarChart3,
            children: [
                {
                    title: 'Exceptions Queue',
                    href: '/finance/operations/exceptions',
                    icon: AlertCircle,
                    requiredPermissions: ['view_finance_operations_exceptions'],
                },
                {
                    title: 'Due Calendar',
                    href: '/finance/operations/due-calendar',
                    icon: CalendarIcon,
                    requiredPermissions: ['view_finance_operations_due_calendar'],
                },
                {
                    title: 'Settlement Worklist',
                    href: '/finance/operations/settlement',
                    icon: Sparkles,
                    requiredPermissions: ['allocate_finance_payment'],
                },
                {
                    title: 'Batch DNG',
                    href: '/finance/operations/batch-dng',
                    icon: Send,
                    requiredPermissions: ['create_finance_payments'],
                },
            ],
        },

        // ─── Billing Records (Shared) ─────────────────────────
        {
            title: 'Billing Records',
            href: '#',
            icon: Receipt,
            children: [
                {
                    title: 'Charge Ledger',
                    href: '/finance/charges',
                    icon: BarChart3,
                    requiredPermissions: ['view_finance_charges'],
                },
                {
                    title: 'Invoices',
                    href: '/finance/invoices',
                    icon: Receipt,
                    requiredPermissions: ['view_finance_invoices'],
                },
                {
                    title: 'Payments',
                    href: '/finance/payments',
                    icon: BarChart3,
                    requiredPermissions: ['view_finance_payments'],
                },
                {
                    title: 'DNG Payment Requests',
                    href: '/finance/dng/payment-requests',
                    icon: Receipt,
                    requiredPermissions: ['view_finance_dng_payment_requests'],
                },
                {
                    title: 'DNG Webhook Events',
                    href: '/finance/dng/webhook-events',
                    icon: Receipt,
                    requiredPermissions: ['view_finance_dng_webhook_events'],
                },
            ],
        },

        // ─── Configuration ────────────────────────────────────
        {
            title: 'Tuition Plans',
            href: '/tuition-plans',
            icon: Calculator,
            requiredPermissions: ['view_tuition_plan'],
        },
        {
            title: 'Scholarships',
            href: '/scholarships',
            icon: Award,
            requiredPermissions: ['view_scholarship'],
        },
        {
            title: 'Student Scholarships',
            href: '/student-scholarships',
            icon: Users,
            requiredPermissions: ['assign_scholarship'],
        },
        {
            title: 'Vouchers',
            href: '/vouchers',
            icon: Ticket,
            requiredPermissions: ['view_voucher'],
        },
    ],
},
```

**Import cần thêm:**
```ts
import { RefreshCw } from 'lucide-vue-next';
```

---

## 6.2 So sánh trước/sau

**Trước:**
```
Fee
├── Operations (6 items mixed EGC+Major)
├── Billing Operations (5 items)
├── Tuition Plans
├── Scholarships
├── Student Scholarships
└── Vouchers
```

**Sau:**
```
Fee
├── EGC Students
│   ├── EGC Dashboard
│   ├── Generate EGC Charges
│   └── EGC Retake Processing
├── Major Students
│   ├── Billing Dashboard
│   └── Generate Major Charges
├── Finance Operations (shared)
│   ├── Exceptions Queue
│   ├── Due Calendar
│   ├── Settlement Worklist
│   └── Batch DNG
├── Billing Records (shared)
│   ├── Charge Ledger, Invoices, Payments
│   └── DNG Payment Requests, DNG Webhook Events
├── Tuition Plans
├── Scholarships
├── Student Scholarships
└── Vouchers
```

---

## Todo

- [ ] Verify tất cả routes ở Phase 3, 4, 5 đã tồn tại trước khi sửa menu
- [ ] Update `menu-sidebar.ts` với cấu trúc mới
- [ ] Thêm `RefreshCw` vào imports nếu chưa có
- [ ] Chạy `./scripts/dev.sh npm run type-check`
- [ ] Chạy `./scripts/dev.sh npm run lint`
- [ ] Kiểm tra thủ công: mở sidebar, navigate qua từng menu item

## Success Criteria

- EGC team thấy 3 items dưới "EGC Students"
- Major team thấy 2 items dưới "Major Students"
- Shared ops vẫn accessible cho cả 2 team
- Không có broken link nào trong menu
- Menu depth không vượt quá 3 levels
