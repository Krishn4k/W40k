import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny, fontsource } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                fontsource('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                bunny('Oswald', {
                    weights: [500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
