# First Viewport: `Còn phải thu`, `Tổng tiền đã nộp`, `Đã thu`, `Còn dư`

Status: ready-for-agent

## Parent

.scratch/student-finance-overview/PRD.md

## What to build

Update the Student Finance page first viewport into a staff-facing **Học phí sinh viên** summary that clearly separates current money still to collect from total money the student has paid. The page should use the canonical terms **Còn phải thu**, **Tổng tiền đã nộp**, **Đã thu**, and **Còn dư**, with **Còn phải thu** as the primary number.

When **Còn dư** is greater than zero, show a compact explanation under the KPIs using the agreed shape: `Còn dư 30.000.000 từ DNG #230, đã nộp ngày 02/05/2026`. The implementation should reuse existing settlement/payment truth and must not introduce a new money formula or change any write-path behavior.

## Acceptance criteria

- [ ] The page title/user-facing heading is **Học phí sinh viên** while existing route/code names may remain stable.
- [ ] The first viewport shows **Còn phải thu**, **Tổng tiền đã nộp**, **Đã thu**, and **Còn dư** using these exact staff-facing labels.
- [ ] **Còn phải thu** is visually primary and represents current collectible obligation.
- [ ] **Tổng tiền đã nộp** includes all completed student payments, including paid money that has not been matched to any current fee.
- [ ] **Đã thu** shows only paid money matched to current fee obligations.
- [ ] **Còn dư** shows completed paid money not currently matched to any fee obligation.
- [ ] When **Còn dư** exists, the page shows a short source/date message and does not require opening another page to identify the source payment.
- [ ] A regression scenario shaped like `AUS121787` shows **Còn phải thu** 0, **Tổng tiền đã nộp** 45,000,000, **Đã thu** 15,000,000, and **Còn dư** 30,000,000.
- [ ] Feature tests assert the Inertia props/page data for the KPI values and surplus message.
- [ ] Frontend type/lint checks pass for changed Vue/TypeScript files.

## Blocked by

None - can start immediately
