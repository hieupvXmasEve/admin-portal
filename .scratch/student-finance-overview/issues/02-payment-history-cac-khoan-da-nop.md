# `Các khoản đã nộp` Payment History

Status: ready-for-agent

## Parent

.scratch/student-finance-overview/PRD.md

## What to build

Add a **Các khoản đã nộp** section immediately below the KPI/surplus area on the **Học phí sinh viên** page. It should show the full payment history for the student, newest first, so staff can understand total paid money, matched money, and remaining **Còn dư** without opening payment or DNG detail pages.

Each row should show enough context for staff to recognize the payment: paid date, source, reference, total amount paid, amount counted as **Đã thu**, amount still **Còn dư**, and a human-readable status such as **Đã thu hết** or **Còn dư**. Allocation actions should only appear when the payment has **Còn dư** and there are real current fee obligations that can be matched.

## Acceptance criteria

- [ ] **Các khoản đã nộp** appears below the KPI/surplus area and above **Sổ cái**.
- [ ] The section includes every completed payment for the student, newest first.
- [ ] Each payment row shows paid date, source, reference, amount paid, **Đã thu**, **Còn dư**, and status.
- [ ] DNG payment rows show the related DNG request reference when available.
- [ ] Import and manual payments appear in the same payment-history table.
- [ ] Payment rows with no remaining **Còn dư** are marked **Đã thu hết** or an equivalent staff-facing state.
- [ ] Payment rows with remaining **Còn dư** are marked **Còn dư**.
- [ ] A payment with **Còn dư** only shows a **Phân bổ** action when there is a current fee obligation to match.
- [ ] A payment with **Còn dư** and no matching fee does not show a useless allocation action and instead explains that no tuition/fee is available to match.
- [ ] Feature tests cover newest-first ordering, DNG reference display, **Đã thu** amount, **Còn dư** amount, and row status.
- [ ] Frontend type/lint checks pass for changed Vue/TypeScript files.

## Blocked by

- .scratch/student-finance-overview/issues/01-first-viewport-student-finance-kpis.md
