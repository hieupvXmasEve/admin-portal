---
id: ADR-0044
title: "Reserve Canvas syllabus templates when mapping begins"
status: accepted
date: 2026-05-31
owner: Platform Team
last_verified: 2026-07-25
scope: architecture-decision
---

# Reserve Canvas syllabus templates when mapping begins

## Context

Canvas assignment sync clones the syllabus assigned to a course offering and
replaces its assessment components with Canvas-specific groups. A Canvas course
may already be mapped before that sync runs, so reusing the template during the
gap can make later grade-sync ownership ambiguous.

## Decision

A syllabus template is reserved from manual assignment as soon as any offering
using it has a Canvas mapping with `sync_status = mapped`. Templates whose title
contains `Canvas` or whose offering has `is_canvas_synced = true` are also
reserved.

Course-offering create, update, and duplicate flows cannot assign a reserved
template to another offering. An offering may retain its own currently assigned
template while unrelated fields are edited. The backend enforces the rule; UI
filtering alone is insufficient.

## Consequences

Reservation begins at the provider-link boundary, before first assignment sync.
Existing shared assignments are not rewritten automatically; add a cleanup
workflow only if production data proves one is needed.
