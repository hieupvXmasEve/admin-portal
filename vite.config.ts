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

    // Local http://localhost:8000 — page via Caddy, HMR websocket direct on VITE_PORT.
    // HTTPS tunnel (APP_URL=https://…) — single origin through Caddy on 443 (wss + proxied assets).
    let hmr: { host: string; port?: number; protocol?: 'ws' | 'wss'; clientPort?: number } = {
        host: 'localhost',
        port: vitePort,
        clientPort: vitePort,
    };

    if (appUrl && isHttpsApp) {
        const { hostname } = new URL(appUrl);
        hmr = {
            host: hostname,
            protocol: 'wss',
            clientPort: 443,
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
            // origin only for HTTPS tunnel — avoids /vendor/* 404 on localhost:8000
            ...(appUrl && isHttpsApp ? { origin: appUrl } : {}),
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