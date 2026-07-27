# Swinx User Guide Docs Site

Astro Starlight site for the Vietnamese end-user guide. This is the only home
for user-facing instructions; do not duplicate them under `docs/`.

## Run locally

```bash
cd docs-site
pnpm install
pnpm dev
```

Served at `http://localhost:4321/`.

## Build

```bash
cd docs-site
pnpm build
pnpm preview
```

Output goes to `docs-site/dist/`.

## Publishing

Published at `https://docs.knu.edu.vn` on Cloudflare Pages by
`.github/workflows/admin-portal-user-guide.yml`, on every push to `main`.
The public origin is set by `SITE_URL` in `astro.config.mjs`; change that
constant if the domain changes.

See `docs/deployment-guide.md` for the Cloudflare Pages and DNS setup.

## Keeping pages in sync with the app

Each page declares the source files it documents in its frontmatter:

```yaml
source:
  - resources/js/pages/Programs/Index.vue
```

`../scripts/check-docs-freshness.sh` fails when a listed file changed in a pull
request but the page did not. Update the page, or drop the path from `source:`
when the page no longer documents that file.

## Languages

| Locale | URL | Content |
| --- | --- | --- |
| Vietnamese | `/` | Authoritative. Write here first. |
| English | `/en/` | Translated from Vietnamese in the same change. |
| Korean | `/ko/` | Translated from Vietnamese in the same change. |

Vietnamese lives at the site root so its published URLs stay stable. Starlight
renders the Vietnamese page with a notice when a locale has no translation, so
partial translation is safe to ship.

To translate a page, copy it to `src/content/docs/<locale>/<same path>` and keep
the `source:` list identical, so the freshness check covers every language.

## Writing rules

Pages target staff who do not read code:

- Use the labels shown on screen, with the Vietnamese meaning in parentheses.
- One action per step.
- Never mention routes, permission codes, table names, or framework internals.
- Structure each screen as: mục đích, ai vào được, các bước, lưu ý.

Keep Astro source out of `resources/js`. The admin app is Vue 3 + Inertia; this
guide is a separate static surface with its own dependency lifecycle.
