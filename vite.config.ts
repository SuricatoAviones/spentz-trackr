import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fontsource } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    server: {
        // IPv4 explícito. Con el `localhost` por defecto, Node (>=17) resuelve
        // primero ::1 y Vite publica sus assets en `http://[::1]:5173`. La
        // gramática de CSP no admite literales IPv6 (host-char solo acepta
        // letras, dígitos y guiones), así que ese origen NO se puede permitir
        // en `script-src`/`font-src` por mucho que se escriba: el navegador lo
        // bloquea igual y la app arranca en blanco. Ver SecurityHeaders.
        host: '127.0.0.1',
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                fontsource('Manrope', {
                    weights: [400, 500, 600, 700, 800],
                }),
                fontsource('Inter', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            command: process.env.SKIP_WAYFINDER === '1' ? 'node -e 0 --' : 'php artisan wayfinder:generate',
        }),
    ],
});
