import '../css/app.css';
import '../css/tiptap.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import GlobalConfirmDialog from './components/GlobalConfirmDialog.vue';
import { initializeTheme } from './composables/useAppearance';
import { vCan, vCanAny } from './directives/permission';
import AppLayout from './layouts/AppLayout.vue';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Swinx';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
        const page = pages[`./pages/${name}.vue`] as any;
        if (page?.default) {
            page.default.layout = name.startsWith('auth/Login') || name.startsWith('SelectCampus') ? undefined : AppLayout;
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () => h('div', [h(App, props), h(GlobalConfirmDialog)]),
        });
        const pinia = createPinia();

        initializeTheme();

        app.use(plugin).use(ZiggyVue).use(pinia).directive('can', vCan).directive('can-any', vCanAny).mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
