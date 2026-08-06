import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            // preline/helpers/apexcharts imports the bare specifier "ApexCharts" instead of
            // the real "apexcharts" package name — alias it until upstream fixes the bundle.
            ApexCharts: 'apexcharts',
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        watch: {
            usePolling: true,
            ignored: ['**/storage/framework/views/**', '**/.idea/**', '**/.git/**'],
        },
        hmr: {
            host: 'localhost',
        },
    },
});
