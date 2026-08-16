---
phase: 5
title: "Docs-site label and content sync"
status: done
priority: P1
effort: "1d"
dependencies: [4]
---

# Phase 5: Docs-site label and content sync

## Overview

Bring the `docs-site` user guide back in line with the shipped menu. This surface
was missing from the first version of this plan; it is larger than the
`menu-sidebar.ts` edit itself and it has a CI gate that reds the PR when it is
out of date.

## Requirements

Functional:
- `astro.config.mjs` navigation labels, slugs, and `ko`/`zh` translations match
  the shipped group tree.
- Content directories renamed to match new slugs, with the 64 pages that declare
  `menu-sidebar.ts` as a source reviewed and refreshed.
- `docs-site`'s placement of `Scholarship Adjustments` reconciled with Phase 3.

Non-functional:
- `scripts/check-docs-freshness.sh` exits 0 on the PR diff.
- No broken internal links; the site builds.

## Architecture

`docs-site` is an Astro Starlight user guide that **mirrors the sidebar group
labels as its own navigation**, with translations and directory slugs:

| Group label in `astro.config.mjs` | Slug | Phase 3 renames it to |
|---|---|---|
| `Attendance & Completion` (:59) | `academic-operations/attendance-completion` | `Attendance` |
| `Finance Office` (:64) | `finance-office` | `Finance` |
| `Student Services` (:83) | `student-services` | `Students` |
| `Faculty & Teaching` (:85) | `faculty-teaching` | folded into Academic Operations |
| `Forms & Quality` (:86) | `forms-quality` | `Forms & Surveys` |
| `Campus Operations` (:87) | `campus-operations` | split into `Campus` + `Store & Clubs` |

Labels at `:83-89` carry `ko`/`zh` translations that must be re-translated, not
just re-typed in English.

**Contradiction, already decided.** `astro.config.mjs:70-74` places
`Scholarship Adjustments` under `Finance Office`
(`slug: 'finance-office/scholarship-adjustments'`), following the *old* menu
placement. Validation decision 3: the app placement wins. The docs page moves to
the academic tree with a redirect from the old slug.

**The freshness gate:** `scripts/check-docs-freshness.sh` reads each page's
`source:` frontmatter, diffs `$base_ref...HEAD`, and exits 1 when a declared
source changed without its page changing in the same range. 64 pages declare
`resources/js/constants/menu-sidebar.ts`. It runs in CI at
`.github/workflows/admin-portal-user-guide.yml:26` against `origin/$base_ref`.
Because it compares the *whole PR range*, all five phases land in one PR and the
gate must pass for the range as a whole.

## Related Code Files

- Modify: `docs-site/astro.config.mjs`
- Modify/rename: `docs-site/src/content/docs/{faculty-teaching,forms-quality,campus-operations,student-services}/`
- Modify: `docs-site/src/content/docs/academic-operations/attendance-completion*`
- Modify: `docs-site/src/content/docs/finance-office/scholarship-adjustments*`
- Modify: the 64 pages declaring `menu-sidebar.ts` in `source:`
- Read: `scripts/check-docs-freshness.sh`, `.github/workflows/admin-portal-user-guide.yml`
- Read: `docs-site/src/content/docs/{en,ko,zh}/` locale trees

## Implementation Steps

1. **Enumerate the true blast radius** before editing:
   - `grep -rl "menu-sidebar.ts" docs-site/src/content/docs | wc -l` (expected 64)
   - list the affected slugs and their `en`/`ko`/`zh` counterparts
   - confirm whether locale trees duplicate the slugs or reference them

2. **Move the `Scholarship Adjustments` docs page** from `finance-office/` to the
   academic tree, update its slug and `ko`/`zh` labels, and add a redirect from
   `finance-office/scholarship-adjustments`. Decided in validation session 1; no
   further deliberation needed.

   Also relocate the `Course Statistics` documentation to match its move from
   Attendance to Course Delivery (validation decision 4), if a page covers it.

3. **Update `astro.config.mjs`** labels, slugs, and `ko`/`zh` translations for
   every renamed group. Add entries for the new `Store & Clubs` and `Campus`
   groups; remove `Faculty & Teaching` as a top-level entry and place it under
   Academic Operations to mirror the app.

4. **Rename content directories** to match new slugs. Add redirects for changed
   slugs if the site config supports them, so existing links do not 404.

5. **Refresh the 64 pages.** Every page whose described navigation path changed
   needs its body updated, not just its frontmatter timestamp — a page that says
   "open Campus Operations > Merchandise" is wrong once Merchandise lives under
   Store & Clubs. Prioritise pages describing the six moved areas; the rest may
   need only a freshness touch.

6. **Verify the gate locally** before pushing:
   `./scripts/check-docs-freshness.sh origin/main` must exit 0 for the full
   branch range.

7. **Build the site** and check for broken internal links across all four
   locales.

## Success Criteria

- [ ] No `astro.config.mjs` label refers to a group that no longer exists
- [ ] Every new/renamed group has `ko` and `zh` translations
- [ ] Content directories match their slugs; changed slugs have redirects or are
      confirmed unlinked
- [ ] `Scholarship Adjustments` classification identical in app and docs
- [ ] `./scripts/check-docs-freshness.sh origin/main` exits 0 for the branch
- [ ] Docs site builds; no broken internal links in `en`/`ko`/`zh`
- [ ] Pages describing the six moved areas have updated bodies, not just
      refreshed frontmatter

## Risk Assessment

The effort estimate for this phase is the least reliable in the plan: "review 64
pages" ranges from a frontmatter sweep to a substantive rewrite depending on how
many describe navigation paths in prose. Step 1 exists to size that honestly
before committing — if the real number of substantive rewrites is large, raise
it rather than absorbing it.

Translations are the second risk. Re-typing an English label into the `ko`/`zh`
fields is worse than leaving the old translation, because it silently degrades
the localized guide. If no translator is available, flag the untranslated entries
explicitly rather than guessing.

Renaming slugs breaks external links and bookmarks. Prefer redirects; where the
platform cannot redirect, record which URLs die.

**Execution note (implementation session, 2026-08-16):**

- Step 1 sizing: 64 pages declared `menu-sidebar.ts`, resolving to 16 unique
  slugs × 4 locales (minus `scholarship-adjustments`, which doesn't declare that
  source). Real content-shape work landed on 8 unique pages × 4 locales:
  `academic-operations/{attendance-completion,course-delivery,index,grades-performance,faculty,scholarship-adjustments}`,
  `finance-office/index`, plus a genuine content split of
  `campus-operations/index` into new `campus/index` + `store-clubs/index`. The
  other 8 pages (`administration`, `communications`, `reports-audits`, `bat-dau`,
  `index`, `academic-operations/curriculum-setup`, `academic-operations/flow-map`)
  needed smaller fixes — 1-3 line breadcrumb/label updates, not full rewrites (one
  exception: `administration/index.md` documented `Canvas Integrations`, which
  moved out in Phase 4 — that page needed a real content removal, not just a
  freshness touch).
- Slug stability: only `campus-operations` (genuinely split), `faculty-teaching`
  (folded into Academic Operations), and `finance-office/scholarship-adjustments`
  (moved to Academic Operations) got new slugs, each with an `astro.config.mjs`
  `redirects` entry across all 4 locale prefixes. `student-services`,
  `forms-quality`, and `finance-office` kept their existing slugs — only their
  nav `label` changed — to avoid unnecessary link breakage on a purely cosmetic
  rename.
- Translation quality: Vietnamese (canonical/root) and English content were
  authored/reviewed directly. Korean and Chinese content mirrors the same
  structural moves and breadcrumb renames as the source-language versions,
  translated by the same session, not reviewed by a native speaker. Two new
  coined terms — **Store & Clubs** (ko: 상점 및 동아리, zh: 商店与社团) and
  **Forms & Surveys** (ko: 양식 및 설문, zh: 表单与调查) — are best-effort and
  should get a native-speaker pass before this ships to non-Vietnamese/English
  readers.
- Verified: `./scripts/check-docs-freshness.sh` simulated against
  `baseline_commit` on the full working-tree diff (72 anchored pages, 0 stale);
  `npm run build` succeeds (73 pages, including the 12 generated redirect
  stubs); a link-crawl of the built `dist/` found 0 broken internal links.
