import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/theme.css',
            ],
            refresh: true,
        }),
        vue(),
        // The panel theme is the only stylesheet that speaks Tailwind. Without
        // this plugin `@import 'tailwindcss' source(none)` falls through to
        // postcss-import, which reads `source(none)` as a media query and emits
        // `@media source(none){...}` — a parse error that makes the browser drop
        // the entire stylesheet, leaving both panels unstyled.
        //
        // app.css is hand-written CSS with no Tailwind directives, so it is
        // unaffected by this plugin.
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
