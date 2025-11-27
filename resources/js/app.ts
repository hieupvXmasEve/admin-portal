import '../css/app.css';
import '../css/tiptap.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import GlobalConfirmDialog from './components/GlobalConfirmDialog.vue';
import { initializeTheme } from './composables/useAppearance';
import { vCan, vCanAny } from './directives/permission';
import AppLayout from './layouts/AppLayout.vue';
import { toast } from 'vue-sonner';

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

// Auto-handle flash messages globally using Inertia router events
// This automatically displays toast notifications when flash messages are present
// No need for watch or onMounted in components - handled here at app level
router.on('navigate', (event) => {
    // Access flash messages from the page props after navigation
    const flash = (event.detail.page.props as any)?.flash;
    
    if (flash) {
        if (flash.success) {
            toast.success(flash.success);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
        if (flash.warning) {
            toast.warning(flash.warning);
        }
        if (flash.info) {
            toast.info(flash.info);
        }
    }
});

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
