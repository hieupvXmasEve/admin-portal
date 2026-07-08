# PRD: Student Finance Overview

Status: ready-for-agent

> Source: `/to-prd` synthesis from a grill-with-docs session around `AUS121787`, the Student Finance page, DNG payment bridging, voided SUMMER2026 EGC charges, and staff-facing finance language. Use the glossary vocabulary in `CONTEXT.md`: **Tổng tiền đã nộp**, **Đã thu**, **Còn phải thu**, **Còn dư**, and **Cần kiểm tra**. The UI display name is **Học phí sinh viên**; existing route and code names may remain stable to avoid unnecessary refactor.

## Problem Statement

Finance staff need one student finance page that explains the whole tuition situation without forcing them to open Audit Workspace, payment detail, DNG detail, invoice detail, or another report page to understand what happened.

Today the Student Finance page shows technically correct ledger numbers, but the presentation can mislead staff. In the `AUS121787` case, the student paid 45,000,000 total: 15,000,000 was matched to current fee obligations, and 30,000,000 remained as paid DNG money that was no longer matched to an active fee after SUMMER2026 charges were voided. The page displayed this as "Đã thu" 15,000,000 and "Dư chưa khớp" 30,000,000, which made it easy to think the void created an incorrect 30,000,000 balance.

The core problem is not that the backend balance is wrong. The problem is that the page does not explain the difference between total money the student has paid, money already matched to tuition/fees, remaining money to collect, and money still left over. It also hides voided lines from the sổ cái, treats paid DNG as a current DNG card, shows stale installment signals even when the invoice is paid, and requires staff to mentally stitch together a finance story from multiple screens.

## Solution

Turn the Student Finance page into a self-contained **Học phí sinh viên** view that lets staff understand the student's full tuition state in one place.

The first viewport must use school-facing language:

- **Còn phải thu** is the primary number.
- **Tổng tiền đã nộp** shows all money the student has paid.
- **Đã thu** shows paid money already matched to current fee obligations.
- **Còn dư** shows paid money not currently matched to any fee obligation.

When **Còn dư** is greater than zero, show a short, concrete message directly under the KPIs:

`Còn dư 30.000.000 từ DNG #230, đã nộp ngày 02/05/2026`

Add a **Các khoản đã nộp** section immediately under the KPI area. It must show the full payment history, newest first, with enough detail to understand each payment's status without leaving the page: date paid, source, reference, amount paid, amount counted as **Đã thu**, amount still **Còn dư**, and status.

Keep the **Sổ cái** grouped by semester, but show every semester with finance history, including semesters where all lines are voided and there is no longer any **Còn phải thu**. Voided lines must appear in their original semester, muted and marked **Đã hủy**, so staff can see that a fee existed and was later removed.

Change operational cards so they only highlight items that genuinely need action:

- Rename the display page to **Học phí sinh viên**.
- Replace `DNG hiện tại` with `DNG cần xử lý`.
- Do not treat paid DNG as a current DNG action card; paid DNG that leaves money **Còn dư** appears in the KPI and payment-history story instead.
- Show installment cards only when an installment is still relevant to a real **Còn phải thu** amount.
- Hide useless allocation actions when there is **Còn dư** but no fee obligation to match it to.

Add a compact **Cần kiểm tra** box under the KPIs. It must show non-blocking review signals that explain data situations likely to confuse staff, and each item should scroll to the relevant section inside the same page instead of sending staff away.

## User Stories

1. As a Finance staff member, I want the student finance page title to read **Học phí sinh viên**, so that I immediately know this page is the complete tuition view for one student.
2. As a Finance staff member, I want **Còn phải thu** to be the primary number, so that I can tell at a glance whether the school still needs to collect money from the student.
3. As a Finance staff member, I want to see **Tổng tiền đã nộp**, so that I know the total amount the student has paid to the school.
4. As a Finance staff member, I want to see **Đã thu**, so that I know how much paid money is already matched to the student's tuition and fee obligations.
5. As a Finance staff member, I want to see **Còn dư**, so that I know whether the student has paid money that still needs follow-up.
6. As a Finance staff member, I want the KPI labels to use education-office language, so that I do not need to interpret accounting or ledger vocabulary.
7. As a Finance staff member, I want **Đã thu** not to pretend it is the same as **Tổng tiền đã nộp**, so that I do not miss paid money that is still **Còn dư**.
8. As a Finance staff member, I want **Còn dư** to be explained directly under the KPI when it exists, so that I do not have to infer why the number is there.
9. As a Finance staff member, I want the **Còn dư** message to name the source, reference, amount, and paid date, so that I can recognize the payment quickly.
10. As a Finance staff member, I want a student like `AUS121787` to show "Còn dư 30.000.000 từ DNG #230, đã nộp ngày 02/05/2026", so that the page explains the 30,000,000 without opening DNG or Audit Workspace.
11. As a Finance staff member, I want the page to distinguish "student still owes money" from "student has paid extra money that needs handling", so that I do not ask a student to pay again incorrectly.
12. As a Finance staff member, I want **Các khoản đã nộp** shown near the top, so that the total paid story is visible before I inspect invoices.
13. As a Finance staff member, I want **Các khoản đã nộp** to show the full payment history, so that I can understand every payment for the student on the same page.
14. As a Finance staff member, I want the payment history sorted newest first, so that recent DNG and manual payments are easiest to inspect.
15. As a Finance staff member, I want each payment row to show date paid, source, reference, amount paid, **Đã thu**, **Còn dư**, and status, so that I can understand the payment without opening its detail page.
16. As a Finance staff member, I want DNG payments in **Các khoản đã nộp** to show their DNG request number, so that I can connect the payment to school payment operations.
17. As a Finance staff member, I want import and manual payments to appear in the same payment-history table, so that the payment story is complete.
18. As a Finance staff member, I want each payment row to show a status such as **Đã thu hết** or **Còn dư**, so that I can scan payment state quickly.
19. As a Finance staff member, I want a payment with **Còn dư** and no matching fee to say there is no tuition to match, so that I do not click a dead-end action.
20. As a Finance staff member, I want a payment with **Còn dư** and real **Còn phải thu** candidates to offer a clear allocation action, so that I can resolve it when appropriate.
21. As a Finance staff member, I want the **Sổ cái** to remain grouped by semester, so that I can review tuition by academic period.
22. As a Finance staff member, I want every semester with finance history to appear, even if there is no current **Còn phải thu**, so that historical billing context is not lost.
23. As a Finance staff member, I want a semester with only voided charges to appear as **Không còn phải thu**, so that I understand the semester is settled or removed rather than missing.
24. As a Finance staff member, I want voided invoice lines to appear muted and marked **Đã hủy**, so that I can see removed fees without confusing them for current fees.
25. As a Finance staff member, I want paid and reversed applications on voided lines to remain understandable, so that I can trace why money became **Còn dư**.
26. As a Finance staff member, I want the sổ cái to show active and voided lines in the same semester context, so that I do not have to reconstruct the story from separate pages.
27. As a Finance staff member, I want paid DNG that no longer maps to active fees to appear in payment history and **Còn dư**, so that I do not mistake it for an active DNG action.
28. As a Finance staff member, I want the DNG card to be called **DNG cần xử lý**, so that it only feels actionable when provider work is actually needed.
29. As a Finance staff member, I want paid DNG not to make **DNG cần xử lý** look active, so that I do not chase a completed DNG request.
30. As a Finance staff member, I want pending or pushed DNG to remain visible when it needs action, so that live provider debt is still controlled from the student page.
31. As a Finance staff member, I want DNG errors to appear in **DNG cần xử lý**, so that I know when payment provider state needs attention.
32. As a Finance staff member, I want installment cards to appear only when there is an actual unpaid obligation behind them, so that stale pending installments do not distract me.
33. As a Finance staff member, I want paid invoices not to produce a "next installment pending" signal, so that I do not push payment requests for already-paid obligations.
34. As a Finance staff member, I want voided or cancelled charge installments not to look actionable, so that I do not act on dead billing state.
35. As a Finance staff member, I want a **Cần kiểm tra** box for review signals, so that confusing data states are surfaced without making the whole student look incorrectly blocked.
36. As a Finance staff member, I want **Cần kiểm tra** to be non-blocking, so that I understand it is a review prompt rather than proof the student owes more money.
37. As a Finance staff member, I want **Cần kiểm tra** to show when the student has **Còn dư**, so that paid money needing follow-up is visible.
38. As a Finance staff member, I want **Cần kiểm tra** to show when DNG was paid but the related fee was later voided, so that I understand the source of **Còn dư**.
39. As a Finance staff member, I want **Cần kiểm tra** to show when installments no longer match invoice state, so that stale payment schedules are not silently trusted.
40. As a Finance staff member, I want **Cần kiểm tra** to show when invoice cached numbers differ from recalculated numbers, so that stale invoice snapshots can be reviewed.
41. As a Finance staff member, I want each **Cần kiểm tra** item to scroll to the relevant section inside the page, so that I stay in context.
42. As a Finance staff member, I want the page to reduce the need to open Audit Workspace for ordinary tuition explanation, so that I can answer student finance questions faster.
43. As a Finance manager, I want staff to see the same student finance story from KPIs, payments, sổ cái, and warnings, so that multiple surfaces do not contradict each other.
44. As a Finance manager, I want the page to preserve evidence of voided fees, so that staff can understand old incidents without treating voided fees as collectible.
45. As a Finance manager, I want the page to expose data review signals without changing money semantics, so that UI clarity does not hide ledger truth.
46. As a Finance manager, I want the page to make DNG-paid-but-voided-fee cases understandable, so that finance repair work can be prioritized accurately.
47. As a Finance manager, I want **Tổng tiền đã nộp** and **Đã thu** to be separate, so that payment collection and fee settlement are not confused.
48. As a Finance manager, I want **Còn dư** to remain visible until it is allocated, refunded, or manually resolved, so that paid money is never hidden.
49. As a developer, I want one backend read model for the student finance overview, so that KPI, payments, warnings, and sổ cái cannot drift.
50. As a developer, I want tests around the student finance page's Inertia data, so that `AUS121787`-style cases remain understandable after future changes.
51. As a developer, I want the implementation to reuse existing settlement and invoice snapshot logic, so that this feature does not create a second money formula.
52. As a developer, I want route and code names to remain stable unless needed, so that the UI language improves without broad refactors.
53. As an Academic staff member opening the read-only finance summary from Student Hub, I want the deep link to land on a page that explains the student's tuition clearly, so that finance context does not require another handoff.
54. As a student-facing staff member, I want the page to show the student's current tuition position in plain language, so that I can confidently explain it to the student or guardian.
55. As an operator reviewing old EGC/DNG incidents, I want the page to show both current collectible fees and old voided evidence, so that I can distinguish current tuition from historical repair context.

## Implementation Decisions

- **Display language.** The staff-facing page title is **Học phí sinh viên**. The canonical labels are **Tổng tiền đã nộp**, **Đã thu**, **Còn phải thu**, **Còn dư**, and **Cần kiểm tra**. Avoid `unapplied credit`, `balance`, and `outstanding` in the primary UI copy.
- **Primary KPI.** **Còn phải thu** is the primary number. It represents current collectible obligation, not cash received.
- **Cash-vs-fee split.** **Tổng tiền đã nộp** is all completed money paid by the student. **Đã thu** is the portion matched to current fee obligations. **Còn dư** is paid money not currently matched to a fee obligation.
- **Short surplus explanation.** When **Còn dư** exists, show one compact sentence under the KPI: amount, source, reference, and paid date only. Do not put long incident explanations in the first viewport.
- **Payment history first.** Add **Các khoản đã nộp** immediately under the KPI/warning area, before the sổ cái. It lists every completed payment for the student, newest first.
- **Payment row contract.** Each payment row includes paid date, source, reference, total paid amount, amount counted as **Đã thu**, amount still **Còn dư**, and a human-readable status.
- **Smart payment actions.** A payment with **Còn dư** only offers an allocation action when current fee obligations exist. If no matching tuition/fee exists, the row says there is nothing to match instead of showing a useless button.
- **Sổ cái completeness.** The sổ cái remains grouped by semester and includes all semesters with finance history, including semesters whose net current obligation is zero.
- **Voided line visibility.** Voided invoice lines are shown in the relevant semester, muted and marked **Đã hủy**. They do not add to **Còn phải thu**, but they remain visible as historical context.
- **Semester state.** A semester with no current collectible amount is marked **Không còn phải thu**.
- **DNG action semantics.** The DNG card is **DNG cần xử lý**, not "DNG hiện tại". It should focus on live, failed, or review-needed DNG states. Paid DNG appears in the payment-history and **Còn dư** story rather than as an active DNG action.
- **Installment action semantics.** Installment cards should only surface schedules tied to real current **Còn phải thu**. Pending installments on already-paid or voided obligations should become a **Cần kiểm tra** signal, not an action prompt.
- **Review signals.** The first version of **Cần kiểm tra** includes four signal families: **Còn dư**, DNG paid but related fees voided, installment state no longer matching invoice state, and invoice cached numbers differing from recalculated numbers.
- **In-page navigation.** **Cần kiểm tra** items scroll to relevant sections within the same page. They should not send staff to another page for basic understanding.
- **No money semantic change.** This PRD changes the read model and presentation of the student finance page. It does not change DNG webhook settlement, invoice settlement, payment application, void release, refund, or allocation semantics.
- **No route refactor required.** Existing route and component names may stay stable. The user-visible label changes to **Học phí sinh viên**.
- **No portal impact.** This PRD targets the admin/staff Finance page. It does not change student or lecturer API contracts.

## Testing Decisions

- **Good test shape.** Tests should assert externally visible behavior: the student finance page receives the right KPI values, payment-history rows, review signals, DNG card state, installment card state, and sổ cái lines for realistic finance records. Tests should not assert private helper method internals.
- **Preferred seam.** The highest preferred seam is a Finance Student Overview feature test that exercises the page request and asserts the Inertia props for a constructed student finance scenario.
- **Canonical regression scenario.** Build a test shaped like `AUS121787`: an active paid Spring obligation, a later SUMMER DNG payment, voided SUMMER fee lines, reversed payment applications, and resulting **Còn dư**. The page should show **Còn phải thu** 0, **Tổng tiền đã nộp** 45,000,000, **Đã thu** 15,000,000, **Còn dư** 30,000,000, a short DNG surplus message, a payment-history row for DNG, and SUMMER voided lines in the sổ cái.
- **Payment-history tests.** Assert newest-first ordering, source/reference rendering inputs, **Đã thu** amount, **Còn dư** amount, and row status.
- **Cần kiểm tra tests.** Assert the four first-version signals appear only when their data conditions exist, and that they include in-page targets rather than external navigation requirements.
- **DNG card tests.** Assert paid DNG alone does not make the card look actionable, while pending, pushed, failed, or review-needed DNG still appears as **DNG cần xử lý**.
- **Installment card tests.** Assert pending installments on already-paid or voided obligations do not appear as next actionable installment; instead, inconsistent installment state appears under **Cần kiểm tra**.
- **Sổ cái tests.** Assert active and voided lines both appear in the relevant semester, voided lines are marked as void/hidden-from-collectible totals, and semesters with only voided history are still present as **Không còn phải thu**.
- **Cache drift tests.** Assert invoice cache drift creates a **Cần kiểm tra** signal without changing the displayed current collectible obligation.
- **Prior art.** Reuse existing Finance feature test patterns around Student 360, payments, DNG, SettlementService-derived invoice snapshots, void/release behavior, and invoice snapshot rebuild behavior.
- **Frontend checks.** Because this is a Vue/Inertia page, run TypeScript and lint checks for changed frontend files. Verify manually in browser for a student with **Còn dư**, a student with no **Còn dư**, and a student with active **Còn phải thu**.

## Out of Scope

- Repairing historical data for `AUS121787` or any other student.
- Automatically refunding **Còn dư**.
- Automatically reallocating **Còn dư** without an explicit staff action.
- Changing DNG webhook settlement behavior.
- Changing payment application, reversal, void release, discount allocation, or invoice snapshot semantics.
- Redesigning Audit Workspace.
- Redesigning the global Finance Office navigation outside the student page label.
- Changing student portal, lecturer portal, or public API contracts.
- Introducing a new accounting ledger or parallel source of truth.
- Hiding voided evidence to make historical data look clean.
- Implementing the follow-up actions for refund workflows beyond displaying the need for staff review.

## Further Notes

- `AUS121787` is the canonical example for this PRD: total paid 45,000,000; **Đã thu** 15,000,000; **Còn phải thu** 0; **Còn dư** 30,000,000 from paid DNG after related SUMMER2026 charges were voided.
- The old page was not inventing debt. It lacked plain-language separation between money paid, money matched to fees, money still to collect, and money left over.
- The page should make ordinary student finance explanation possible without opening Audit Workspace. Audit Workspace can remain the deep diagnostic surface.
- Use **Còn dư** instead of "unapplied credit" in UI copy. Use **Cần kiểm tra** instead of "integrity warning" in staff-facing text.
- The glossary was updated during the grill-with-docs session to capture the agreed staff-facing language.
