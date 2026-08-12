import { resolve } from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'node',
        include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
    },
    resolve: {
        alias: {
            '@': resolve(import.meta.dirname, 'resources/js'),
        },
    },
});
