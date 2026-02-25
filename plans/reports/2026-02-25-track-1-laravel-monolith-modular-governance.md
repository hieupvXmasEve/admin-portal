# Track 1 Research: Laravel Monolith + Modular Governance (University Scale)

Date: 2026-02-25
Scope: maintainability, layering rules (`module` vs `shared service`), CI minimum gates, risk mitigation under YAGNI/KISS/DRY.
Context used: current codebase is Laravel 12 monolith with `app/Modules/*` + `app/Services/*`.

## Executive Take
Use **modular monolith governance**, not service split now. Keep domain behavior inside modules; keep shared services tiny + cross-cutting only. Enforce with minimal CI gates at PR merge point. Grow strictness gradually (avoid big-bang quality mandates).

Why now:
- Monolith-first is safer until boundaries stabilize and org has service-operational maturity [S2].
- Laravel-modules pattern already maps to module-local routes/providers/tests, supports this architecture directly [S1].

## Governance Model (Lean)
1. Architecture stance
- Default: one deployable Laravel app, domain modules as first-class boundaries [S1].
- Microservice decomposition is deferred until clear scaling/org pain exists (YAGNI) [S2].

2. Module boundary contract
- Each module owns: routes, controllers/actions, policies, migrations, tests, internal services.
- External interaction preference: explicit module API (application service/facade), not cross-module model reach-in.
- Rule: module A cannot import module B internals; only B public contract.

3. Shared service admission rule (critical)
- Put code in `app/Services/*` only if all true:
  - Reused by >= 3 modules.
  - Language is non-domain/cross-cutting (e.g., mail adapter, id generation, file checksum).
  - No dependency on any single module domain model.
  - Stable API expected for >= 2 release cycles.
- If not all true: keep in module.
- This is an **inference** from monolith-first boundary discipline + modular package structure [S1][S2].

4. Layering rules
- Allowed direction: `HTTP/UI -> Application -> Domain -> Infrastructure`.
- Disallow: controllers calling infra directly for business decisions; disallow domain depending on framework facades where avoidable.
- Shared services can be called by Application layer only.
- Boundary linting can start as simple static import checks (inference).

## CI Minimum Gates (Day-1 baseline)
Enforce these as required status checks on protected `main` branch [S3]:
1. `build-and-test`
- Composer install, bootstrap sanity, PHPUnit suite (fast subset first).
2. `static-analysis`
- PHPStan with explicit level in config; start level 5-6, raise quarterly.
- Use baseline for incremental adoption; avoid blocking all legacy debt day-1 [S4].
3. `style`
- Laravel Pint/format check (fast fail).
4. `security-pipeline-lite`
- Minimal SPVS-aligned controls: dependency audit, secret scan, artifact integrity metadata, CI identity hardening (inference from SPVS control framing) [S5].

Branch protection settings (must-have) [S3]:
- Require status checks before merge.
- Use **Strict** mode for “up to date before merging” on high-churn branches.
- Require conversation resolution.
- Prefer linear history.
- Optionally require signed commits for sensitive repos.

## Maintainability Playbook (Pragmatic)
1. Change placement policy
- New feature starts in one module. No pre-emptive shared abstractions (YAGNI).
2. Duplication policy
- Allow short-term duplication across 2 modules.
- Extract to shared only after 3rd confirmed use (DRY without premature abstraction).
3. Simplicity guard (KISS)
- One module = one business capability slice (admissions, curriculum, finance aid, etc.).
- Keep module public contract small; avoid “god shared service”.
4. Review checklist
- “Does this PR create cross-module internal dependency?” -> reject.
- “Is shared service truly cross-cutting?” -> prove with usages.

## Risk Register + Mitigation
1. Risk: shared layer turns into dumping ground
- Mitigation: shared admission checklist + architecture owner approval.

2. Risk: module boundaries erode via cross-imports
- Mitigation: boundary checks in CI + PR template question.

3. Risk: CI too strict too early slows delivery
- Mitigation: staged quality ratchet (PHPStan level increments with baseline burn-down) [S4].

4. Risk: pipeline security gaps in university PII workflows
- Mitigation: adopt SPVS baseline controls first, mature by stage [S5].

5. Risk: premature microservice split
- Mitigation: monolith-first trigger policy; split only when measurable pain threshold met [S2].

## Trigger Policy for Service Extraction (When to break monolith)
Proceed only if >=2 for 2 consecutive quarters (inference guided by [S2]):
- Deployment coupling causes repeated release blocking across unrelated domains.
- Module-specific scale profile cannot be solved in-app (queue/cache/db partitioning insufficient).
- Team topology requires independent release cadence with clear bounded context ownership.

## 90-Day Rollout (Minimal)
1. Weeks 1-2
- Ratify module/shared rules, add PR checklist.
2. Weeks 3-4
- Enable protected branch required checks (strict mode for main) [S3].
3. Weeks 5-8
- Add PHPStan level + baseline; track top 20 violations [S4].
4. Weeks 9-12
- Add SPVS-lite pipeline controls + quarterly architecture review [S5].

## Sources (max 5)
- [S1] Laravel Modules v12 intro: https://laravelmodules.com/docs/12/getting-started/introduction
- [S2] Martin Fowler, Monolith First (2015-06-03): https://martinfowler.com/bliki/MonolithFirst.html
- [S3] GitHub protected branches + required status checks: https://docs.github.com/en/enterprise-server@3.19/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches
- [S4] PHPStan rule levels + baseline: https://phpstan.org/user-guide/rule-levels
- [S5] OWASP SPVS (Release 1.0 Oct 2025): https://owasp.org/www-project-spvs/

## Unresolved Questions
1. Which modules are “core record of truth” vs “supporting workflows” in current university domain map?
2. What is acceptable PR CI latency target (e.g., <=10 min) for your team?
3. Do you require signed commits and merge queue immediately, or phased adoption?
4. What compliance baseline applies (FERPA-local policy mapping, retention, audit evidence)?
