# Student page is a single student-centric operational console

**Context.** Student academic features were scattered across four unrelated top-level menus, and most per-student tasks existed twice — once as a per-student page (`/students/{id}/actions`, `/students/{id}/placement`) and again as an aggregate report. This confused the single role that actually owns the student lifecycle: Academic Affairs staff (Cán Bộ Đào tạo). Other roles only read a few basic fields.

**Decision.** Consolidate into one **Student Hub** (see CONTEXT.md): a student-centric *operational console* where Academic Affairs staff view **and act on** a student's whole academic lifecycle in one place, instead of hopping between menus. The hub is the existing academic-summary surface, upgraded.

**Boundaries (the non-obvious parts):**

- **Finance is finance-aware, not finance-owning.** Fees, gold wallet, and scholarships appear in the hub only as a read-only summary that links out to the Finance Office. No money operations happen in the hub. This keeps the Finance module's ownership intact (CONTEXT.md: "no Eloquent joins across module boundaries").
- **One detail page only.** The orphan `Show.vue` and the two standalone per-student pages (`/students/{id}/actions`, `/students/{id}/placement`) are retired/redirected into hub tabs. Their useful fields move into the hub.
- **Per-student vs cross-student split.** Aggregate audit/report pages (student-actions audit, academic-progression audit, lifecycle-yearly, performance dashboard, course ranking, academic report) stay as a **separate management area** for Directors/Heads. Linking is **one-way: report → hub**. The hub already holds each student's full per-student timeline, so there is no hub → audit back-link.

**Why this shape.** One role does all per-student work, so a single console removes both the menu-scatter and the per-student/report duplication. Keeping the aggregate reports separate serves a different user (oversight) with a different job (cohort patterns), without bloating the hub.
