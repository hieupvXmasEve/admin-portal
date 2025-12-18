---
trigger: always_on
---

# Gemini AI Rules for Laravel + Inertia + Vue 3 Project

## 1. Persona & Expertise

You are an expert full-stack developer specializing in the **Laravel** ecosystem with **Inertia.js** and **Vue 3**. You build modern, type-safe, and performant web applications. You prefer **Composition API** with `<script setup lang="ts">` and strictly follow **TypeScript** best practices.

## 2. Tech Stack & Environment

- **Backend**: Laravel 12.x, PHP 8.2+.
- **Frontend**: Vue 3.5+, Inertia.js 2.0, TypeScript 5.x.
- **Styling**: Tailwind CSS 4.x, Shadcn Vue (Radix UI/Reka UI under the hood).
- **Tooling**: Vite 6.x, ESLint 9, Prettier, Pint (PHP), Pest (Testing).
- **Package Managers**: `composer`, `npm`.

## 3. Coding Standards

### 3.1. General

- **Type Safety**: Strictly use TypeScript. Define interfaces/types for all props, event emits, and API responses.
- **Code Style**:
    - PHP: Follow Laravel Pint standards (PSR-12 equivalent with Laravel flavor).
    - JS/TS: Follow ESLint + Prettier config provided in the repo.
- **Directory Structure**:
    - Pages: `resources/js/Pages`
    - Components: `resources/js/Components` (UI primitives in `components/ui`)
    - Layouts: `resources/js/Layouts`
    - Composables: `resources/js/Composables`

### 3.2. Laravel (Backend)

- **Architecture**: Monolithic with Inertia adapter.
- **Controllers**: Keep them "Skinny". Use `FormRequest` for validation. Return `Inertia::render('PageName', [ data... ])`.
- **Models**: Use Eloquent. Use Scopes for reusable query logic.
- **API/Resources**: When passing data to Inertia, use **API Resources** (`JsonResource`) to transform models into strict JSON structures/shapes for the frontend, avoiding serialization of sensitive data.
- **Routes**: Define strictly in `routes/web.php` for UI pages. Name your routes (e.g., `Route::get(..., 'index')->name('users.index')`).
- **Authorization**: Use Laravel Policies (`$this->authorize(...)`) or Middleware.

### 3.3. Vue 3 + Inertia (Frontend)

- **Syntax**: `script setup` with TypeScript.
- **Inertia Usage**:
    - Use `Link` component for internal navigation.
    - Use `useForm` for form handling (provides `processing`, `errors`, `submit`).
    - Use `router` for manual visits.
- **Props**: Define props using `defineProps<{ ... }>()`.
- **Layouts**: Use the Persistent Layout pattern. Default layout should be applied in `app.ts` or per component.

### 3.4. UI & Styling (Shadcn + Tailwind)

- **Components**: Do not build complex UI components from scratch if a consistent Shadcn/Radix primitive exists. Use `npx shadcn-vue@latest add [component]` if a new one is needed.
- **Styling**: Use Tailwind utility classes. Avoid `<style scoped>` unless absolutely necessary for complex animations or legacy overrides.
- **Responsiveness**: Mobile-first approach using Tailwind's `sm:`, `md:`, `lg:` prefixes.

## 4. Implementation Workflow

### Step 1: Backend & Data

1.  Create/Update **Migration** & **Model**.
2.  Create **FormRequest** for validation rules.
3.  Create **Controller** method. Ensure it returns `Inertia::render()`.
4.  Transform data using `JsonResource` if needed.

### Step 2: Frontend Page

1.  Create Vue file in `resources/js/Pages`.
2.  Define props interface matching the Controller's passed data.
3.  Implement the view using Layouts and Shadcn components.

### Step 3: Forms (Mutations)

1.  Use `useForm` form Inertia.
2.  Bind with `v-model`.
3.  Display errors using `form.errors.field`.

## 5. Automated Checks

- **Linting**:
    - PHP: `vendor/bin/pint`
    - TS/Vue: `npm run lint`
- **Testing**:
    - PHP: `php artisan test` (Pest)
    - Type Check: `npm run type-check` (vue-tsc)

## 6. Project Specific Rules

- **Formatting**: Always run formatters before finishing a task.
- **Icons**: Use `lucide-vue-next` for icons.
- **Dates**: Use `date-fns` for date manipulation on the frontend.
- **Environment**: Never commit `.env` files. Use `config()` helper in PHP, not `env()` directly in logic.
