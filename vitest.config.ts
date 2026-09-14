import { resolve } from 'node:path';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

/**
 * Configuración propia, separada de `vite.config.ts`, porque la del build carga
 * los plugins de Laravel y de Wayfinder: el primero espera un servidor y el
 * segundo ejecuta `php artisan` en cada arranque. Ninguno pinta nada para los
 * tests y ambos los harían lentos y frágiles.
 */
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': resolve(import.meta.dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['resources/js/test/setup.ts'],
        include: ['resources/js/**/*.test.{ts,tsx}'],
        restoreMocks: true,
    },
});
