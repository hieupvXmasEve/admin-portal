### 1. **Tuition plan**
- `tuition_plans` → định nghĩa học phí cố định cho curriculum_version + intake.
- `tuition_plan_terms` → số tiền từng kỳ (có kỳ thu, có kỳ =0).

### 2. **Scholarship**
- `student_scholarship_awards` → lưu học bổng được xét từ đầu (base value, percent/amount).

### 3. **Billing & Invoice**

- `billing_cycles` → quản lý chu kỳ thu phí theo semester.
- `student_invoices` → hóa đơn theo student + semester.
- `invoice_items` → chi tiết phí (tuition, egc, retake, misc…).
- `invoice_discounts` → giảm trừ (scholarship, voucher).

### 4. **Payments & Wallet**

- `student_cash_wallets` → ví tiền VND.
- `wallet_transactions` → giao dịch ví.

### 5. **Voucher**
- `voucher_definitions` → định nghĩa voucher (percent/amount, điều kiện).
- `voucher_redemptions` → log student sử dụng voucher.
