import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/console.css',
                'resources/js/app.js',
                'resources/css/filament/theme.css',
            ],
            refresh: true,
        }),
        // Without this the panel theme is shipped as written: `@import` of
        // Filament's own theme never expands, and `@theme` / `@apply` reach the
        // browser as raw directives it does not understand. The file is 200 OK
        // and 376 KB, and it styles nothing — the panels render unstyled.
        tailwindcss(),
        vue(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
