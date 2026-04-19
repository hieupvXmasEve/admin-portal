---
title: EGC Finance Module — Retake Fee + Dashboard + Menu Separation
status: pending
priority: high
created: 2026-04-16
blockedBy: []
blocks: []
---

# EGC Finance Module — Retake Fee + Dashboard + Menu Separation

## Overview

3 mục tiêu trong cùng 1 plan:

1. **EGC Retake Fee**: Giảm 50% học phí cho EGC students học lại Block 2 sau khi trượt Block 1 (đủ điều kiện chuyên cần ≥ 80%)
2. **Tách Generate Charges**: EGC và Major dùng 2 trang + action riêng biệt để tránh rule change lẫn nhau
3. **EGC Dashboard riêng**: KPIs và danh sách sinh viên EGC tách biệt hoàn toàn khỏi Major

**Business rules:**
- EGC students trả 2 level đầu kỳ: Level X + Level X+1 = 30M
- Fail Block 1 + meets_attendance_requirement = true → Block 2 học lại Level X với 50% = 7.5M
- Void Level X+1 charge → create Level X egc_retake_fee (7.5M) → settlement tự tính 7.5M unapplied credit → carry-forward sang kỳ sau qua AutoAllocatePayments (không cần build thêm)
- Team EGC và Major tách biệt → cần dashboard + operations riêng, không lẫn lộn số liệu

## Phases

| # | Phase | Status | File |
|---|-------|--------|------|
| 1 | Database: Migration + Model | pending | [phase-01](phase-01-database.md) |
| 2 | Backend: Retake Query + Action + Route | pending | [phase-02](phase-02-backend.md) |
| 3 | Frontend: EGC Retake Page | pending | [phase-03](phase-03-frontend.md) |
| 4 | Tách Generate Charges: EGC vs Major | pending | [phase-04](phase-04-split-generate-charges.md) |
| 5 | EGC Dashboard riêng | pending | [phase-05](phase-05-egc-dashboard.md) |
| 6 | Menu Restructure | pending | [phase-06](phase-06-menu-restructure.md) |

> **Thứ tự triển khai:** Phase 1→2→3 (retake feature) → Phase 4 (split action) → Phase 5 (dashboard) → Phase 6 (menu)

## Key Files

**New:**
- `database/migrations/*_add_egc_retake_fee_to_finance_charges_charge_type.php`
- `database/migrations/*_create_egc_block_retakes_table.php`
- `app/Models/EgcBlockRetake.php`
- `app/Modules/Finance/Queries/Operations/PreviewEgcRetakeChargesQuery.php`
- `app/Modules/Finance/Actions/Operations/ProcessEgcBlockRetakeAction.php`
- `app/Modules/Finance/Actions/Operations/GenerateEgcChargesAction.php` ← tách từ GenerateBatchChargesAction
- `app/Modules/Finance/Queries/Operations/GetEgcDashboardStatsQuery.php`
- `app/Modules/Finance/Queries/Operations/GetEgcDashboardStudentsQuery.php`
- `resources/js/Pages/Finance/Operations/EgcRetake.vue`
- `resources/js/Pages/Finance/Operations/EgcDashboard.vue`

**Modified:**
- `app/Models/FinanceCharge.php` — add TYPE_EGC_RETAKE_FEE constant
- `app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php` — extract EGC logic → GenerateEgcChargesAction
- `app/Modules/Finance/Http/Web/Admin/BillingOperationsController.php` — add egcRetake, processEgcRetake, egcDashboard methods
- `app/Modules/Finance/routes/web.php` — add routes
- `resources/js/constants/menu-sidebar.ts` — restructure Fee section

## Architecture Decision

```
Fee Menu (sau khi refactor)
├── EGC (intake_pre_uni_gc)
│   ├── EGC Dashboard          ← phase 5
│   ├── Generate EGC Charges   ← phase 4
│   └── EGC Retake Processing  ← phase 3
│
├── Major (intake_course)
│   ├── Major Dashboard        ← existing Billing Dashboard (renamed)
│   └── Generate Major Charges ← phase 4 (GenerateBatchChargesAction stripped of EGC)
│
└── Finance Operations (shared)
    ├── Invoices, Payments, Settlement
    ├── Exceptions Queue, Due Calendar
    └── Batch DNG
```

## Dependencies

- `VoidFinanceChargeAction` — exists ✓
- `academic_records.meets_attendance_requirement` — exists ✓
- `units.unit_type = 'egc'` + `units.level` — exists ✓
- `GetBillingDashboardStatsQuery` — exists, sẽ clone + filter cho EGC

## Non-Goals

- Không sửa AutoAllocatePayments, SettlementService
- Không tạo charge âm
- Không tách Invoices/Payments/Settlement (shared infrastructure, đúng thiết kế)
- Không xử lý case sinh viên không đủ điều kiện retake
