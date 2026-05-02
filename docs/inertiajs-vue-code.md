### Install Client-Side Adapters

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Install the required client-side adapter for Vue to upgrade to v3.0.

```bash
npm install @inertiajs/vue3@^3.0
```

--------------------------------

### Build and Start Production SSR

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Build the production bundles and start the SSR server.

```bash
npm run build
php artisan inertia:start-ssr
```

--------------------------------

### Start Progress Bar on Visit Start

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

Listen for the `start` event from the Inertia router to initiate NProgress when a new visit begins.

```javascript
router.on("start", () => NProgress.start());
```

--------------------------------

### Install Inertia Client-Side Adapter

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

Install the Inertia client-side adapter and the Inertia Vite plugin.

```bash
npm install @inertiajs/vue3 @inertiajs/vite
```

--------------------------------

### Start SSR Server via Artisan

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Execute the command to start the SSR server in production environments.

```bash
php artisan inertia:start-ssr
```

--------------------------------

### Install Vite Plugin

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Install the optional Vite plugin for simplified SSR and component resolution.

```bash
npm install @inertiajs/vite@^3.0
```

--------------------------------

### Install NProgress Library

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

Install the NProgress library using npm to manage page loading progress.

```bash
npm install nprogress
```

--------------------------------

### Install `qs` Dependency

Source: https://inertiajs.com/docs/v3/getting-started/upgrade-guide

Install the `qs` package directly if your application imports it, as it is no longer a dependency of `@inertiajs/core`.

```bash
npm install qs
```

--------------------------------

### Run Development Server

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Start the Vite development server to enable automatic SSR handling.

```bash
npm run dev
```

--------------------------------

### Install Vue and Vite Plugin

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

Install the Vue and Vite plugin dependencies required for a Vue + Vite setup.

```bash
npm install vue @vitejs/plugin-vue
```

--------------------------------

### Install Vite Plugin

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Install the required Inertia Vite plugin via npm.

```bash
npm install @inertiajs/vite
```

--------------------------------

### Controller Redirect Example

Source: https://inertiajs.com/docs/v3/the-basics/redirects

Use this pattern to redirect to a GET endpoint after a successful POST request. Ensure your controller returns a redirect response.

```php
class UsersController extends Controller
{
    public function index()
    {
        return Inertia::render('Users/Index', [
            'users' => User::all(),
        ]);
    }

    public function store(Request $request)
    {
        User::create(
            $request->validate([
                'name' => ['required', 'max:50'],
                'email' => ['required', 'max:50', 'email'],
            ])
        );

        return to_route('users.index');
    }
}
```

--------------------------------

### Complete File Upload Example with Inertia.js Form Helper (Vue)

Source: https://inertiajs.com/docs/v3/the-basics/file-uploads

This Vue example demonstrates a form with text and file inputs using the Inertia form helper. It includes handling form submission and displaying upload progress.

```vue
<script setup>
import { useForm } from "@inertiajs/vue3";

const form = useForm({
  name: null,
  avatar: null,
});

function submit() {
  form.post("/users");
}
</script>

<template>
  <form @submit.prevent="submit">
    <input type="text" v-model="form.name" />
    <input type="file" @input="form.avatar = $event.target.files[0]" />
    <progress v-if="form.progress" :value="form.progress.percentage" max="100">
      {{ form.progress.percentage }}%
    </progress>
    <button type="submit">Submit</button>
  </form>
</template>
```

--------------------------------

### Install `@inertiajs/core` via pnpm

Source: https://inertiajs.com/docs/v3/advanced/typescript

Alternatively, add the package as a direct dependency to resolve module accessibility issues.

```bash
pnpm add @inertiajs/core
```

--------------------------------

### Install Inertia Server-Side Adapter

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

Use Composer to install the Inertia server-side adapter for Laravel. This is the first step in integrating Inertia into your project.

```bash
composer require inertiajs/inertia-laravel
```

--------------------------------

### Start Progress Bar with Delay

Source: https://inertiajs.com/docs/v3/advanced/progress-indicators

Update the `start` event listener to use `setTimeout` to delay the NProgress bar's initiation by 250 milliseconds.

```javascript
router.on("start", () => {
  timeout = setTimeout(() => NProgress.start(), 250);
});
```

--------------------------------

### Verify SSR Runtime Existence

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Enable the check to ensure the configured runtime binary exists before starting the server.

```php
'ssr' => [
    'ensure_runtime_exists' => (bool) env('INERTIA_SSR_ENSURE_RUNTIME_EXISTS', false),
],
```

--------------------------------

### Create Inertia App for Vue

Source: https://inertiajs.com/docs/v3/installation/client-side-setup

Use `createInertiaApp()` with Vue and `import.meta.glob()` to resolve page components.

```javascript
import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'

createInertiaApp({
  resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    return pages[`./Pages/${name}.vue`]
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .mount(el)
  },
})
```

--------------------------------

### Root Blade Template for Vue + Vite

Source: https://inertiajs.com/docs/v3/installation/server-side-setup

Configure the `resources/views/app.blade.php` file as the root template for a Vue + Vite app. Use Inertia's Blade directives for rendering head and body content, and include `@vite` for asset management.

```blade
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/js/app.js'])
    @inertiaHead
  </head>
  <body>
    @inertia
  </body>
</html>
```

--------------------------------

### Basic Form with `useForm` in Vue

Source: https://inertiajs.com/docs/v3/forms

Manage form state in Vue using the `useForm` helper.

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

function submit() {
  form.post('/login')
}
</script>

<template>
  <form @submit.prevent="submit">
    <input v-model="form.email" type="email" />
    <input v-model="form.password" type="password" />
    <input v-model="form.remember" type="checkbox" />
    <button :disabled="form.processing" type="submit">Login</button>
  </form>
</template>
```

--------------------------------

### SSR Entry Point for Vue

Source: https://inertiajs.com/docs/v3/advanced/server-side-rendering

Configure the SSR entry point for a Vue application using Inertia.js.

```javascript
import { createSSRApp, h } from 'vue'
import { renderToString } from '@vue/server-renderer'
import createServer from '@inertiajs/vue3/server'
import { createInertiaApp } from '@inertiajs/vue3'

createServer(page =>
  createInertiaApp({
    page,
    render: renderToString,
    resolve: name => {
      const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
      return pages[`./Pages/${name}.vue`]
    },
    setup({ App, props, plugin }) {
      return createSSRApp({
        render: () => h(App, props),
      }).use(plugin)
    },
  }),
)
```

--------------------------------

### Manual Visits in Vue

Source: https://inertiajs.com/docs/v3/manual-visits

Use the Inertia router for programmatic navigation and submissions in Vue.

```javascript
import { router } from '@inertiajs/vue3'

router.visit('/users')
router.get('/users', { search: 'John' })
router.post('/users', { name: 'John Doe' })
```

--------------------------------

### Vue Link Example

Source: https://inertiajs.com/docs/v3/links

Use the `Link` component for client-side navigation in Vue.

```vue
<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <Link href="/dashboard">Dashboard</Link>
</template>
```

--------------------------------

### Show Validation Errors in Vue

Source: https://inertiajs.com/docs/v3/forms

Display field-level validation errors from the form helper.

```vue
<template>
  <input v-model="form.email" type="email" />
  <div v-if="form.errors.email">{{ form.errors.email }}</div>
</template>
```

--------------------------------

### Transform Form Data Before Submission in Vue

Source: https://inertiajs.com/docs/v3/forms

Use `transform()` to modify payload values before submitting the form.

```javascript
form.transform((data) => ({
  ...data,
  remember: data.remember ? 'on' : '',
})).post('/login')
```
