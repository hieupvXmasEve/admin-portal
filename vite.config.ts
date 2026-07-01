import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = env.APP_URL?.replace(/\/$/, '') ?? '';
    const vitePort = Number(env.VITE_PORT ?? 5173);
    const isHttpsApp = appUrl.startsWith('https://');

    const corsOrigins: (string | RegExp)[] = [
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        /^http:\/\/192\.168\.[0-9]+\.[0-9]+:8000$/,
        /^http:\/\/10\.[0-9]+\.[0-9]+\.[0-9]+:8000$/,
    ];

    if (appUrl) {
        corsOrigins.push(appUrl);
    }

    let hmr: { host: string; protocol?: 'ws' | 'wss'; clientPort?: number } = {
        host: 'localhost',
    };

    if (appUrl) {
        const { hostname, port } = new URL(appUrl);
        hmr = {
            host: hostname,
            protocol: isHttpsApp ? 'wss' : 'ws',
            clientPort: isHttpsApp ? 443 : port ? Number(port) : 80,
        };
    }

    return {
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                ssr: 'resources/js/ssr.ts',
                refresh: true,
            }),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
        server: {
            host: '0.0.0.0',
            port: vitePort,
            strictPort: true,
            ...(appUrl ? { origin: appUrl } : {}),
            hmr,
            cors: {
                origin: corsOrigins,
                credentials: true,
            },
        },
        resolve: {
            alias: {
                '@': path.resolve(__dirname, './resources/js'),
                'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
            },
        },
    };
});