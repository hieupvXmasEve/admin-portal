---
title: "Yearly Aggregate Scalability Plan"
description: "Assess and de-risk yearly aggregation from students/action/progression/semester schema"
status: pending
priority: P1
effort: 6h
branch: main
tags: [scalability, aggregation, reporting, database]
created: 2026-02-25
---

# Yearly Aggregate Scalability Plan

## Context Links
- Report: [scalability-yearly-aggregate-assessment-2026-02-25.md](../reports/scalability-yearly-aggregate-assessment-2026-02-25.md)
- Tables: `students`, `student_action_logs`, `academic_progression_events`, `semesters`

## Phases
| # | Phase | Status | Effort | Output |
|---|---|---|---|---|
| 1 | Define aggregation contract | Pending | 1h | Metric dictionary + canonical time keys |
| 2 | Canonicalize source facts | Pending | 2h | SQL/view deriving single semester/time key per action |
| 3 | Add missing indexes | Pending | 1h | Migration with composite indexes |
| 4 | Build incremental rollup job | Pending | 1.5h | Idempotent upsert job + watermark |
| 5 | Reconcile and guardrail | Pending | 0.5h | Validation SQL + drift alert |

## Key Decisions
- Use incremental materialization as primary serving path.
- Keep on-demand query only as reconciliation/reference.
- Use sliding recompute window (90 days) for late corrections.

## Success Criteria
- Yearly dashboard query p95 stable under growth (no full table scans for hot path).
- Yearly counts match reconciliation query within agreed tolerance.
- Rebuild-by-year command can recover aggregate deterministically.

## Risks
- Wrong year attribution if time-key contract not explicit.
- Campus attribution drift if using current student campus only.
- OR-based semester logic in `student_action_logs` causing poor plan stability.

## Unresolved Questions
- Final canonical year key and campus attribution policy.
- Include/exclude soft-deleted dimensions in published metrics.
- Freshness SLA.
