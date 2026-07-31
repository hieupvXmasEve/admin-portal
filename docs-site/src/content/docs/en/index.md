---
title: Portal User Guide
description: How to use the Portal admin application, written for the staff who run academic operations.
source:
  - resources/js/constants/menu-sidebar.ts
---

<!-- docs-freshness: reviewed against menu-sidebar.ts change (new "Doanh thu" revenue nav item under Finance Office) -- no update needed here. -->

This guide is for **university staff** who use the Portal admin application in a browser. No technical knowledge required.

Students and lecturers use separate portals, which this guide does not cover.

## Where to start

If you are new, read [First steps](/en/bat-dau/) first: signing in, choosing a campus, and reading the screen. Once is enough for every chapter that follows.

Then open the area you work in — ten areas matching the ten menu groups, listed below.

## Coverage

| Area | Status |
| --- | --- |
| [First steps](/en/bat-dau/) | Written |
| [Academic Operations](/en/academic-operations/) | Written |
| [Student Services](/en/student-services/) | Written |
| [Reports & Audits](/en/reports-audits/) | Written |
| [Faculty & Teaching](/en/faculty-teaching/) | Written |
| [Finance Office](/en/finance-office/) | Written |
| [Forms & Quality](/en/forms-quality/) | Written |
| [Campus Operations](/en/campus-operations/) | Written |
| [Communications](/en/communications/) | Written |
| [Administration](/en/administration/) | Written |

## Conventions

- Menu paths are written as **Menu group → Item → Sub-item**.
- Screen labels are quoted exactly as they appear in the application.
- Every screen follows the same four parts: *what it is for, who can open it, steps, notes*.
- If an item described here is missing from your menu, your account has not been granted access to it. Ask an administrator.

## For documentation maintainers

Every page lists the source files it documents in its frontmatter `source:` field. When one of those files changes, `scripts/check-docs-freshness.sh` asks for the matching page to be updated in the same pull request.
