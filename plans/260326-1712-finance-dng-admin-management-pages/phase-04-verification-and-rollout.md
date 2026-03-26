---
phase: 4
title: 'Verification, permission wiring, rollout notes'
status: completed
effort: 2h
---

# Phase 4 — Verification and rollout

## Goal

Prove page access/contracts and document rollout friction.

## Test outline

- feature test: payment request index requires auth + permission
- feature test: payment request show renders expected Inertia component/props
- feature test: webhook event index requires auth + permission
- feature test: webhook event show renders expected Inertia component/props
- query behavior tests:
    - campus scoping
    - linked vs orphan webhook filters
    - checksum validity filters
    - payment-bridged filters

## Rollout checklist

- sync new permissions to roles.
- verify menu item visibility by permission.
- verify route names in Ziggy.
- run focused Pest suite.
- if payload rendering is large, trim or lazy-load before broad rollout.

## Non-goals for this phase

- no queue retry action
- no webhook reprocess action
- no manual relink tool
- no export/reporting package work

## Exit criteria

- all 4 pages accessible with correct permissions.
- list filters preserve URL/query state.
- payload-heavy detail pages remain performant enough for admin use.
- unresolved questions recorded before implementation starts.

## Sync-back

- [x] Added focused Pest coverage in `tests/Feature/Finance/DngAdminPagesTest.php` for all 4 DNG admin pages.
- [x] Covered webhook campus scoping for linked and orphan payload paths.
- [x] Verified new permission keys, route names, and Finance sidebar wiring match shipped contracts.
