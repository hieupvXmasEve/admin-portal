---
title: Phase 6 tuition notices
date: 2026-09-06
summary: "Staff-issued tuition notices to students and parents via Notification V2, fail-closed on missing templates."
---

# Phase 6 tuition notices

## What happened
Implemented plan 260904-1114 Phase 6. Staff issue a tuition notice from DNG Due Reminders. Amounts come from Settlement Position. Student email uses recipient_type=student; parent email uses recipient_type=email because PARENT users have no campus_user_roles (RecipientResolver campus_mismatch). No new tables. Stable content-hash dedup only for tuition_notice keys; legacy reminders keep UUID.

## Decision
- Did not add `v2` to PaymentService write-mode allowlist: `invoice_paid` has no template and would send English boilerplate.
- Did not filter `email_verified_at` on guardians (accepted risk, same as existing parent reminders).
- Fail closed if V2 off or campus missing tuition templates.

## Next steps
Phase 7 surplus lifecycle. Pre-existing: parent payment reminders still use recipient_type=user and likely never deliver.

> Historical work record — not durable authority. Prefer docs/specs/ADRs for current decisions.
