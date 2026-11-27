import path from 'path'
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scripts/main.js'],
            publicDirectory: 'public',
            buildDirectory: 'build',
        }),
        vue(),
        VitePWA({
            registerType: 'prompt',
            outDir: 'public',
            buildBase: '/',
            scope: '/',
            manifest: false,
            workbox: {
                cleanupOutdatedCaches: true,
                globDirectory: 'public',
                globPatterns: [
                    'build/**/*.{js,css,woff2}',
                    'favicons/*.{png,svg,ico,webmanifest}',
                ],
                navigateFallback: null,
                navigateFallbackDenylist: [
                    /^\/api\//,
                    /^\/customer\//,
                    /^\/invoices\/pdf\//,
                    /^\/estimates\/pdf\//,
                    /^\/payments\/pdf\//,
                ],
                runtimeCaching: [
                    {
                        urlPattern: ({ url }) => url.pathname.startsWith('/api/'),
                        handler: 'NetworkOnly',
                    },
                    {
                        urlPattern: ({ url }) => url.pathname.includes('/pdf/'),
                        handler: 'NetworkOnly',
                    },
                    {
                        urlPattern: ({ url }) => url.pathname.includes('/receipt'),
                        handler: 'NetworkOnly',
                    },
                    {
                        urlPattern: ({ request }) => request.destination === 'image',
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'images-cache',
                            expiration: {
                                maxEntries: 60,
                                maxAgeSeconds: 30 * 24 * 60 * 60,
                            },
                        },
                    },
                ],
            },
        }),
        {
            name: 'crater-static-assets',
            enforce: 'post',
            config: () => ({
                // The official Laravel plugin disables Vite's public directory.
                publicDir: 'resources/static',
            }),
        },
    ],
    server: {
        watch: {
            ignored: [
                '**/.env/**',
                '**/vendor/**',
                '**/storage/**',
                '**/database/**/*.sqlite*',
            ],
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                silenceDeprecations: ['import', 'global-builtin', 'color-functions'],
            },
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources'),
            'vue-i18n': 'vue-i18n/dist/vue-i18n.cjs.js',
        }
    }
})
