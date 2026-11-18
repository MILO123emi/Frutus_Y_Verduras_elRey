import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';

export default defineConfig({
    // Forzar IPv4: en macOS Vite a veces escribe [::1] en public/hot y el
    // navegador no alcanza los scripts (pantalla en blanco).
    server: {
        host: '127.0.0.1',
        hmr: { host: '127.0.0.1' },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        vuetify({ autoImport: true }),
    ],
});
