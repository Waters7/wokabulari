import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Plus Jakarta Sans', {
                    weights: [500, 600, 700],
                    variable: '--font-jakarta',
                }),
                bunny('Baloo 2', {
                    weights: [700, 800],
                    variable: '--font-baloo',
                }),
            ],
        }),
        tailwindcss(),
    ]),
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: { host: 'localhost' },
        cors: true,
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/storage/framework/views/**',
                '**/vendor/**',
            ],
        },
    },
});
