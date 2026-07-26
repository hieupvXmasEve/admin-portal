import '../css/app.css';
import '../css/tiptap.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { putConfig, withInertiaModal } from '@inertiaui/modal-vue';
import { createPinia } from 'pinia';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import GlobalConfirmDialog from './components/GlobalConfirmDialog.vue';
import { initializeTheme } from './composables/useAppearance';
import { vCan, vCanAny } from './directives/permission';
import AppLayout from './layouts/AppLayout.vue';
import { setupEcho } from './lib/echo';

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

createInertiaApp({
    title: (title) => {
        const appName = document.documentElement.dataset.appName;

        return appName && title ? `${title} - ${appName}` : title || appName || '';
    },
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true });
        const page = pages[`./pages/${name}.vue`] as any;
        if (page?.default) {
            page.default.layout = name.startsWith('Auth/Login') || name.startsWith('SelectCampus') ? undefined : AppLayout;
        }
        return page;
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () => h('div', [h(App, props), h(GlobalConfirmDialog)]),
        });
        const pinia = createPinia();

        initializeTheme();
        setupEcho();
        // Disable native <dialog> so portaled dropdowns (Select, Combobox, etc.)
        // are not clipped by the browser top-layer.
        // Require explicit close (X button) instead of clicking outside.
        putConfig({ useNativeDialog: false, modal: { closeOnClickOutside: false }, slideover: { closeOnClickOutside: false } });
        withInertiaModal(app);

        app.use(plugin).use(ZiggyVue).use(pinia).directive('can', vCan).directive('can-any', vCanAny).mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
