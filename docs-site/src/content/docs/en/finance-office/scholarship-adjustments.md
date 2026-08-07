---
title: Scholarship Adjustments
description: Step-by-step guide to reviewing, interviewing, deciding, and applying scholarship adjustments for students who failed a course.
source:
  - resources/js/pages/ScholarshipAdjustments/Index.vue
  - resources/js/pages/ScholarshipAdjustments/CandidatesPreview.vue
  - resources/js/pages/ScholarshipAdjustments/Show.vue
  - resources/js/pages/ScholarshipAdjustments/dossier-labels.ts
---

Use this feature to review students who failed a course, log an interview, and decide whether their scholarship gets reduced next term. The original scholarship is never edited or deleted — a separate **adjustment dossier** is created for the affected term only.

**Who can access.** `view_scholarship_adjustment` to view, `approve_scholarship_adjustment` to approve decisions.

**Where.** **Discounts & Funding → Scholarship Adjustments**.

> 🎬 _Screenshot/video: (to be added)_

## Process overview

```text
1. Find candidates  →  2. Schedule & record the interview  →
3. Student confirms  →  4. Decide & approve  →
5. Apply to tuition  →  6. Next term: restore or re-review
```

## 1. Find candidates

**Screen.** Click **Tìm sinh viên cần xét** ("Find candidates") at the top right of the list page — opens the candidate search screen.

**Steps**

1. Pick the **target term** (the term the scholarship change will apply to).
2. Pick the **source term** (only terms starting **before** the chosen target are shown).
3. The system lists candidates automatically, with each student's failed courses.
4. Check the students you want to open a dossier for (selecting all is not required).
5. Click **Mở đợt xét cho N sinh viên** ("Open review for N students").

**Read the excluded count.** Right under the list title, a line shows how many students are hidden: already under review, no finalized grade yet, or already charged tuition for this term (once charged, a student drops out of candidacy).

**Watch for the red flag.** A **Cần kiểm tra điểm thủ công** ("Needs manual grade review") badge means the grade was finalized before the pass/fail calculation fix (2026-07-04) — double-check the original grade before deciding.

> 🎬 _Screenshot/video: (to be added)_

## 2. Open the dossier & schedule the interview

Once a review batch is opened, each student gets their own dossier — click **Mở** ("Open") in the last column of the list.

**"Why this student was flagged" panel** shows: source (system-detected or manually added), the current scholarship, and the failed courses with attempt number and grade-finalized date.

**Schedule the interview**

1. In the **Interview** panel, pick a **date** and **time**.
2. Click **Đặt lịch phỏng vấn** ("Schedule interview").

**Record the minutes after the interview**

1. Once the status is **Đã hẹn lịch** ("Scheduled"), the Interview panel shows a **minutes** textarea.
2. Enter what was agreed during the interview.
3. Click **Lưu biên bản và gửi sinh viên xác nhận** ("Save minutes and request confirmation") — the system automatically notifies the student, no extra step needed.

**Note.** Minutes are versioned (**bản chỉnh sửa thứ N**, "edit #N"). Editing minutes after the student confirmed creates a new version and resets confirmation.

> 🎬 _Screenshot/video: (to be added)_

## 3. Student confirmation

Students confirm on the Student Portal. The status shows right in the **Student confirmation** panel: *Pending*, *Confirmed*, *Disputed*, *Overdue*, or *Dispute overruled*.

**If the student hasn't answered yet (Pending / Overdue):** staff may confirm on their behalf.

1. Fill in **how you contacted the student and what they agreed to**.
2. Click **Xác nhận thay sinh viên** ("Confirm on behalf").

**If the student disagreed (already responded with a dispute):** staff **must not** confirm on their behalf. Two honest paths:

- **Revise the minutes** — enter the corrected text and click **Lưu biên bản đã sửa và hỏi lại** ("Save revised minutes and re-ask"). The student confirms again on the new version.
- **Overrule the dispute** — approvers only, requires a written reason (at least 10 characters), click **Bác bỏ phản đối** ("Overrule dispute"). The student's disagreement stays on record — it is never rewritten as "confirmed".

> 🎬 _Screenshot/video: (to be added)_

## 4. Decide & approve

Only available while the dossier is **Interviewed** or **Ready for decision**.

**Proposal step**

1. Choose an **outcome**: *Keep unchanged*, *Reduce*, *Suspend in full*, *Defer*, or *Cancel*.
2. If **Reduce**: enter the **remaining scholarship rate** — unit follows the original type (% of tuition or a fixed amount), and the value entered is the **remaining amount after the cut**, not the amount cut.
3. The **tuition impact** panel updates live as you type: tuition for the term, the discount before/after, the amount payable before/after. If the target term has no invoice yet, it notes the adjustment will apply automatically once one is issued.
4. Enter a **reason** for the outcome (required).
5. Click **Gửi đề xuất quyết định** ("Submit proposal").

**Blocked without confirmation.** If the outcome increases what the student owes (*Reduce* or *Suspend in full*) and the student hasn't confirmed the minutes yet, submission is blocked — go back to step 3.

**Approval step.** Once submitted, the dossier moves to **Ready for decision** and the form locks to read-only:

- Someone with approval rights clicks **Duyệt quyết định này** ("Approve this decision") to finalize it as proposed, or
- Clicks **Sửa lại quyết định** ("Revise decision") to reopen the form, edit, and resubmit.

**After approval.** The form is replaced with a summary: outcome, scholarship rate before/after, target term, reason, proposer/approver and timestamps.

> 🎬 _Screenshot/video: (to be added)_

## 5. Apply to tuition

After approval, the dossier moves into one of these statuses, shown right under the decision summary:

| Status | Meaning |
| --- | --- |
| **Applied** | Tuition for the target term has been updated per the decision. |
| **No adjustment** | Scholarship stays the same; no change to tuition. |
| **Cancelled** | Scholarship was cancelled. |
| **Finance review required** | Couldn't apply automatically (e.g. invoice already issued or paid) — Finance handles it manually. |
| **Not applicable** | The target term doesn't charge tuition on this billing timeline. |
| **Approved** (invoice not yet issued) | Will auto-apply when the target term's invoice is issued. |

**Note.** Issued or paid invoices are never auto-corrected retroactively.

> 🎬 _Screenshot/video: (to be added)_

## 6. Next term: restore or re-review

Once the affected term's results are finalized, the system re-evaluates automatically:

- No more failed courses in scope → auto-proposes **restoring** the original scholarship for the next term.
- Still failing → opens a **new review dossier**; it never silently extends the old decision.

Restoring never deletes the prior adjustment dossier and never auto-corrects an invoice already issued for a previous term.

## Dossier status (quick reference)

`Identified → Interview scheduled → Interviewed → Awaiting student confirmation → Ready for decision → Approved → Applied → Closed`

Side branches you may see: **Student disputed**, **Student no-show**, **Confirmation overdue**, **No adjustment**, **Cancelled**, **Finance review required**, **Not applicable**.

## See also

Full business rules (candidacy conditions, detailed permissions, acceptance criteria, deviations from the original proposal): [docs/features/academic/scholarship-adjustment-deduction.md](../../../../../../docs/features/academic/scholarship-adjustment-deduction.md).
