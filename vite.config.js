import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';
import path from 'path';
import { fileURLToPath } from 'url';

const projectRoot = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, projectRoot, '');
    const devServerPort = Number(env.VITE_DEV_SERVER_PORT || process.env.VITE_DEV_SERVER_PORT || 5173);
    const hmrHost = env.VITE_HMR_HOST || process.env.VITE_HMR_HOST || 'localhost';
    // Docker Compose passa VITE_USE_POLLING via process.env; loadEnv só lê ficheiros .env
    const usePolling = (env.VITE_USE_POLLING ?? process.env.VITE_USE_POLLING ?? 'true') !== 'false';

    return {
        resolve: {
            alias: {
                '@': path.resolve(projectRoot, 'resources/js'),
            },
        },
        server: {
            host: '0.0.0.0',
            port: devServerPort,
            strictPort: true,
            hmr: {
                host: hmrHost,
                port: devServerPort,
            },
            watch: {
                usePolling,
                interval: usePolling ? 500 : undefined,
            },
        },
        build: {
            rollupOptions: {
                output: {
                    manualChunks(id) {
                        if (id.includes('node_modules/apexcharts') || id.includes('node_modules/vue3-apexcharts')) {
                            return 'apexcharts';
                        }
                        if (id.includes('node_modules/@tiptap')) {
                            return 'tiptap';
                        }
                        if (id.includes('node_modules/bootstrap')) {
                            return 'bootstrap';
                        }
                    },
                },
            },
        },
        plugins: [
            laravel({
                input: 'resources/js/app.js',
                refresh: [
                    'resources/views/**',
                    'routes/**',
                    'app/Http/Controllers/**',
                ],
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            VitePWA({
                registerType: 'autoUpdate',
                injectRegister: null,
                includeAssets: ['pwa-icon.svg'],
                manifest: {
                    name: 'Talents — NR-1',
                    short_name: 'Talents',
                    description: 'Pesquisas NR-1, riscos psicossociais e gestão de pessoas',
                    theme_color: '#632a7e',
                    background_color: '#f1f5f9',
                    display: 'standalone',
                    orientation: 'portrait-primary',
                    start_url: '/',
                    lang: 'pt-BR',
                    icons: [
                        {
                            src: '/pwa-icon.svg',
                            sizes: '512x512',
                            type: 'image/svg+xml',
                            purpose: 'any maskable',
                        },
                    ],
                },
                workbox: {
                    // Não precachear .js: após deploy, um SW antigo ainda servia chunks antigos
                    // (ex.: ReferenceError por código já corrigido no repositório).
                    globPatterns: ['**/*.{css,ico,svg,woff2}', 'manifest.webmanifest'],
                    navigateFallback: null,
                    runtimeCaching: [
                        {
                            urlPattern: /^https:\/\/fonts\.bunny\.net\/.*/i,
                            handler: 'CacheFirst',
                            options: {
                                cacheName: 'bunny-fonts',
                                expiration: { maxEntries: 8, maxAgeSeconds: 60 * 60 * 24 * 365 },
                            },
                        },
                    ],
                },
                devOptions: {
                    enabled: false,
                },
            }),
        ],
    };
});
