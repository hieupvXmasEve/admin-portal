# S-001 Env-Driven Color Theme Config

## Status

implemented

## Lane

normal

## Product Contract

Swinx staff/admin Inertia UI color theme is selected at deploy time via `.env`,
without per-user theme-picker changes. The active theme drives CSS design tokens
(`--primary`, `--accent`, sidebar, chart) so existing semantic Tailwind classes
(`bg-primary`, `text-primary-foreground`, etc.) follow the chosen palette.

- Default theme name: `green` (current production palette — no visual change
  when unset or explicitly set to `green`).
- Additional preset: `orange`.
- Invalid or unknown `APP_THEME` values fall back to `green` and log a config
  warning in non-production environments.

Light/dark appearance (user cookie `appearance`) remains independent of
`APP_THEME`. This story changes **brand color**, not light/dark mode behavior.

## Relevant Product Docs

- `docs/design-guidelines.md` — §4 Theming and Visual Consistency
- `resources/css/app.css` — current token source of truth
- `resources/views/app.blade.php` — HTML shell + inline background styles
- `app/Http/Middleware/HandleAppearance.php` — light/dark only (unchanged scope)

## Portal Impact

none

## Acceptance Criteria

- `.env` supports `APP_THEME=green|orange`; omitted value behaves as `green`.
- `config/theme.php` defines two named presets (`green`, `orange`) with light and
  dark token maps for brand-related CSS variables.
- `green` preset reproduces the current `app.css` values for `--primary`,
  `--primary-foreground`, `--accent`, `--accent-foreground`, sidebar
  primary/accent tokens, and chart tokens tied to brand hue.
- `orange` preset applies a coherent orange brand palette in both light and dark
  modes while keeping neutral surfaces (`--background`, `--foreground`,
  `--muted`, `--border`, etc.) unchanged from `green`.
- Active theme tokens are injected into the HTML shell before first paint (via
  `app.blade.php` inline `<style>` or equivalent) so SSR/initial load matches
  the SPA.
- `app.blade.php` hardcoded `html` background `oklch(...)` values are removed or
  derived from the active theme neutrals.
- `.env.example` documents `APP_THEME=green`.
- No Vue page refactors required for baseline acceptance; components already
  using semantic tokens (`bg-primary`, `border-primary`, etc.) reflect the env
  theme automatically.
- Feature/unit test proves: default `green`, valid `orange`, invalid fallback.

## Design Notes

### Environment

| Variable    | Values           | Default |
| ----------- | ---------------- | ------- |
| `APP_THEME` | `green`, `orange` | `green` |

### New / changed files

| File | Change |
| ---- | ------ |
| `config/theme.php` | New. Preset registry + `active()` helper reading `env('APP_THEME', 'green')`. |
| `app/Providers/ThemeServiceProvider.php` or extend existing provider | Share `$theme` / CSS variable map with `app.blade.php`. |
| `resources/views/app.blade.php` | Inject `:root` / `.dark` CSS variable overrides from active preset. |
| `resources/css/app.css` | Keep current values as documented `green` fallback defaults; optional `data-theme` attribute hook. |
| `.env.example` | Add `APP_THEME=green`. |
| `tests/Feature/Theme/ThemeConfigTest.php` | Config resolution + fallback tests. |

### Preset token scope (brand only)

Override only brand-hue-dependent tokens in presets; leave neutral palette as
defined in `app.css`:

- `--primary`, `--primary-foreground`
- `--accent`, `--accent-foreground`
- `--sidebar-primary`, `--sidebar-primary-foreground`
- `--sidebar-accent`, `--sidebar-accent-foreground`
- `--chart-1` … `--chart-5` (brand scale)
- `--vis-primary-color` (inherits `--primary`)
- `--ring` where it tracks brand focus (optional; keep neutral if unclear)

### `green` preset (current default)

Copy verbatim from `resources/css/app.css`:

**Light (`:root`)**

| Token | Value |
| ----- | ----- |
| `--primary` | `oklch(0.527 0.154 150.069)` |
| `--primary-foreground` | `oklch(0.982 0.018 155.826)` |
| `--accent` | `oklch(0.527 0.154 150.069)` |
| `--accent-foreground` | `oklch(0.982 0.018 155.826)` |
| `--sidebar-primary` | `oklch(0.627 0.194 149.214)` |
| `--sidebar-primary-foreground` | `oklch(0.982 0.018 155.826)` |
| `--sidebar-accent` | `oklch(0.527 0.154 150.069)` |
| `--sidebar-accent-foreground` | `oklch(0.982 0.018 155.826)` |
| `--chart-1` | `oklch(0.871 0.15 154.449)` |
| `--chart-2` | `oklch(0.723 0.219 149.579)` |
| `--chart-3` | `oklch(0.627 0.194 149.214)` |
| `--chart-4` | `oklch(0.527 0.154 150.069)` |
| `--chart-5` | `oklch(0.448 0.119 151.328)` |

**Dark (`.dark`)**

| Token | Value |
| ----- | ----- |
| `--primary` | `oklch(0.448 0.119 151.328)` |
| `--primary-foreground` | `oklch(0.982 0.018 155.826)` |
| `--accent` | `oklch(0.448 0.119 151.328)` |
| `--accent-foreground` | `oklch(0.982 0.018 155.826)` |
| `--sidebar-primary` | `oklch(0.723 0.219 149.579)` |
| `--sidebar-primary-foreground` | `oklch(0.982 0.018 155.826)` |
| `--sidebar-accent` | `oklch(0.448 0.119 151.328)` |
| `--sidebar-accent-foreground` | `oklch(0.982 0.018 155.826)` |
| `--chart-1` … `--chart-5` | Same green scale as light block |

### `orange` preset (new)

Orange palette with the same lightness/chroma relationships as `green`, hue
shifted to ~45–55°:

**Light (`:root`)**

| Token | Value |
| ----- | ----- |
| `--primary` | `oklch(0.627 0.19 48)` |
| `--primary-foreground` | `oklch(0.985 0.01 48)` |
| `--accent` | `oklch(0.627 0.19 48)` |
| `--accent-foreground` | `oklch(0.985 0.01 48)` |
| `--sidebar-primary` | `oklch(0.72 0.17 50)` |
| `--sidebar-primary-foreground` | `oklch(0.985 0.01 48)` |
| `--sidebar-accent` | `oklch(0.627 0.19 48)` |
| `--sidebar-accent-foreground` | `oklch(0.985 0.01 48)` |
| `--chart-1` | `oklch(0.87 0.14 52)` |
| `--chart-2` | `oklch(0.75 0.18 50)` |
| `--chart-3` | `oklch(0.65 0.17 49)` |
| `--chart-4` | `oklch(0.627 0.19 48)` |
| `--chart-5` | `oklch(0.52 0.14 46)` |

**Dark (`.dark`)**

| Token | Value |
| ----- | ----- |
| `--primary` | `oklch(0.52 0.14 46)` |
| `--primary-foreground` | `oklch(0.985 0.01 48)` |
| `--accent` | `oklch(0.52 0.14 46)` |
| `--accent-foreground` | `oklch(0.985 0.01 48)` |
| `--sidebar-primary` | `oklch(0.75 0.18 50)` |
| `--sidebar-primary-foreground` | `oklch(0.985 0.01 48)` |
| `--sidebar-accent` | `oklch(0.52 0.14 46)` |
| `--sidebar-accent-foreground` | `oklch(0.985 0.01 48)` |
| `--chart-1` … `--chart-5` | Same orange scale as light block |

> Implementer may fine-tune orange oklch values during implementation for
> contrast/accessibility; acceptance requires visible orange brand on buttons,
> sidebar active states, and focus rings in both light and dark modes.

### Injection pattern (recommended)

```blade
{{-- app.blade.php --}}
<style>
    :root {
        @foreach ($themeVariables['light'] as $name => $value)
        {{ $name }}: {{ $value }};
        @endforeach
    }
    html { background-color: var(--background); }
    html.dark { background-color: var(--background); }
    .dark {
        @foreach ($themeVariables['dark'] as $name => $value)
        {{ $name }}: {{ $value }};
        @endforeach
    }
</style>
```

Set `data-theme="{{ $themeName }}"` on `<html>` for debugging; CSS cascade
still relies on variable overrides.

### Out of scope

- Per-user or per-campus theme selection.
- Student/lecturer Nuxt portal theming.
- Replacing hardcoded `bg-green-*` / `text-red-*` status colors in ~180 Vue
  files (semantic status colors stay as-is).
- Runtime theme switching without redeploy / config cache clear.

### Commands

- None (config-only).

### Queries

- None.

### API

- None.

### Tables

- None.

### Domain rules

- Theme is deployment config, not user preference.
- `APP_THEME` is read at boot; `php artisan config:cache` applies in production.

### UI surfaces

- All Inertia staff/admin pages via global CSS variables.

## Validation

| Layer | Expected proof |
| --- | --- |
| Unit | `ThemeConfigTest`: `green` default, `orange` resolves, unknown → `green`. |
| Integration | Render `app.blade.php` / view composer includes correct CSS variables for each preset. |
| E2E | Manual browser smoke: login page + sidebar with `APP_THEME=green` (unchanged) and `APP_THEME=orange` (orange primary buttons/sidebar). |
| Platform | `./scripts/dev.sh composer exec pint -- --dirty`; `./scripts/dev.sh artisan test --compact --filter=ThemeConfig`. |
| Release | Document `APP_THEME` in `.env.example`; note `config:cache` after deploy. |

## Harness Delta

- Intake #154 recorded.
- Story `THEME-001-env-theme-config` registered in harness matrix.

## Evidence

- `./scripts/dev.sh artisan test --compact --filter=ThemeConfig` passed (5 tests).
- `./scripts/dev.sh composer exec pint -- --dirty --format agent` passed on touched PHP files.
- Implemented: `config/theme.php`, `app/Support/ThemeConfig.php`, `AppServiceProvider` view sharing, `resources/views/app.blade.php` injection, `.env.example` `APP_THEME=green`.
- Fix: removed brand tokens from `resources/css/app.css` (Vite CSS was overriding injected variables) and re-applied theme block after `@vite`.